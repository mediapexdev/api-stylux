<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Calcul d'un bulletin de salaire (Sénégal).
 * Une seule source de vérité : l'écran d'aperçu et le bulletin enregistré passent tous deux par ici.
 * Les taux, plafonds et barèmes viennent de paie_parametres (modifiables par le gérant).
 */
class PaieCalcul
{
    // Valeurs de départ (Sénégal, janvier 2026). Toutes modifiables dans Paie > Paramètres.
    public static function defauts()
    {
        return [
            'raison_sociale' => 'STYLUX OIL',
            'etablissement' => "Station Shell Point d'Eau - Thiaroye",
            'ninea' => '006984604',
            'rccm' => 'SN DKR 2024 A 45934',
            'numero_ipres' => '',
            'numero_css' => '',
            'adresse' => 'Km 12 Route de Rufisque, Thiaroye sur Mer, Dakar',
            'smig' => 64223,
            'heures_mois' => 173.33,
            'ipres_rg_sal' => 5.6,
            'ipres_rg_pat' => 8.4,
            'ipres_rg_plafond' => 432000,
            'ipres_rcc_sal' => 2.4,
            'ipres_rcc_pat' => 3.6,
            'ipres_rcc_plafond' => 1296000,
            'css_pf' => 7,
            'css_at' => 3,
            'css_plafond' => 63000,
            'ipm_sal' => 3,
            'ipm_pat' => 3,
            'ipm_plafond' => 250000,
            'cfce' => 3,
            'abattement_taux' => 30,
            'abattement_plafond' => 900000,
            'ipres_deductible_ir' => 0,
            'transport_exonere' => 26000,
            'anciennete_auto' => 1,
            'ir_bareme' => [[630000, 0], [1500000, 20], [4000000, 30], [8000000, 35], [13500000, 37], [50000000, 40], [null, 43]],
            'ir_reductions' => [
                '1' => [0, 0, 0], '1.5' => [10, 100000, 300000], '2' => [15, 200000, 650000],
                '2.5' => [20, 300000, 1100000], '3' => [25, 400000, 1650000], '3.5' => [30, 500000, 2030000],
                '4' => [35, 600000, 2490000], '4.5' => [40, 700000, 2755000], '5' => [45, 800000, 3180000],
            ],
            'trimf_bareme' => [[600000, 900], [1000000, 3600], [2000000, 4800], [7000000, 12000], [12000000, 18000], [null, 36000]],
        ];
    }

    // Majorations des heures supplémentaires
    const HEURES_SUP = [
        'hs15' => ['Heures sup. à 15 %', 15],
        'hs40' => ['Heures sup. à 40 %', 40],
        'hs60' => ['Heures sup. de nuit à 60 %', 60],
        'hs100' => ['Heures sup. dimanche / férié à 100 %', 100],
    ];

    private $p;

    public function __construct(array $parametres)
    {
        $this->p = array_merge(self::defauts(), $parametres);
    }

    private function n($k)
    {
        return (float) ($this->p[$k] ?? 0);
    }

    /** Nombre de parts fiscales */
    public static function parts(array $e)
    {
        if (isset($e['parts']) && $e['parts'] !== null && $e['parts'] !== '' && (float) $e['parts'] > 0) {
            return min(5, (float) $e['parts']);
        }
        $enfants = (int) ($e['enfants'] ?? 0);
        $situation = $e['situation'] ?? 'celibataire';
        if ($situation === 'marie') {
            $p = 1.5 + 0.5 * $enfants + (!empty($e['conjoint_sans_revenu']) ? 0.5 : 0);
        } elseif ($situation === 'veuf') {
            $p = $enfants > 0 ? 2 + 0.5 * ($enfants - 1) : 1;
        } else {
            $p = $enfants > 0 ? 1.5 + 0.5 * ($enfants - 1) : 1;
        }
        return min(5, $p);
    }

    /** Années d'ancienneté révolues à la fin du mois */
    public static function anciennete($dateEmbauche, $mois)
    {
        if (!$dateEmbauche) {
            return 0;
        }
        $fin = Carbon::parse($mois . '-01')->endOfMonth();
        $debut = Carbon::parse($dateEmbauche);
        return $debut->gt($fin) ? 0 : $debut->diffInYears($fin);
    }

    /** Prime d'ancienneté : 2 % après 2 ans, puis 1 % par année, plafonnée à 25 % */
    public static function tauxAnciennete($annees)
    {
        return $annees < 2 ? 0 : min(25, 2 + ($annees - 2));
    }

    /** Impôt annuel par tranches : [[plafond, taux %], ..., [null, taux %]] */
    public static function bareme($revenu, array $tranches)
    {
        $impot = 0;
        $bas = 0;
        foreach ($tranches as $t) {
            $haut = $t[0];
            $taux = (float) $t[1];
            if ($revenu <= $bas) {
                break;
            }
            $tranche = ($haut === null ? $revenu : min($revenu, (float) $haut)) - $bas;
            $impot += max(0, $tranche) * $taux / 100;
            if ($haut === null) {
                break;
            }
            $bas = (float) $haut;
        }
        return $impot;
    }

    /** TRIMF annuel selon la tranche de revenu brut annuel */
    public static function trimfAnnuel($revenu, array $tranches)
    {
        foreach ($tranches as $t) {
            if ($t[0] === null || $revenu < (float) $t[0]) {
                return (float) $t[1];
            }
        }
        return 0;
    }

    /**
     * Calcule un bulletin.
     * $e : salarié (tableau), $mois : AAAA-MM, $v : variables du mois
     *   jours_absence, hs15/hs40/hs60/hs100 (heures), primes [{libelle, montant, imposable, cotisable}], retenues [{libelle, montant}]
     */
    public function calculer(array $e, $mois, array $v = [])
    {
        $gains = [];
        $retenues = [];
        $patronal = [];
        $alertes = [];
        $r0 = function ($x) {
            return round($x);
        };

        $base = (float) ($e['salaire_base'] ?? 0);
        $heuresMois = $this->n('heures_mois') ?: 173.33;
        $tauxHoraire = $base / $heuresMois;

        // Salaire de base, au prorata des absences non payées (base 30 jours)
        $absences = max(0, min(30, (float) ($v['jours_absence'] ?? 0)));
        $gains[] = ['code' => 'base', 'libelle' => 'Salaire de base', 'base' => 30, 'taux' => $base / 30, 'montant' => $r0($base), 'imposable' => true, 'cotisable' => true];
        if ($absences > 0) {
            $gains[] = ['code' => 'absence', 'libelle' => 'Absences non payées', 'base' => $absences, 'taux' => $base / 30, 'montant' => -$r0($base / 30 * $absences), 'imposable' => true, 'cotisable' => true];
        }
        $sursalaire = (float) ($e['sursalaire'] ?? 0);
        if ($sursalaire > 0) {
            $gains[] = ['code' => 'sursalaire', 'libelle' => 'Sursalaire', 'base' => null, 'taux' => null, 'montant' => $r0($sursalaire), 'imposable' => true, 'cotisable' => true];
        }

        // Prime d'ancienneté
        $annees = self::anciennete($e['date_embauche'] ?? null, $mois);
        if ($this->n('anciennete_auto') && ($taux = self::tauxAnciennete($annees)) > 0) {
            $gains[] = ['code' => 'anciennete', 'libelle' => "Prime d'ancienneté ({$annees} ans)", 'base' => $base, 'taux' => $taux, 'montant' => $r0($base * $taux / 100), 'imposable' => true, 'cotisable' => true];
        }

        // Heures supplémentaires
        foreach (self::HEURES_SUP as $k => list($libelle, $maj)) {
            $h = (float) ($v[$k] ?? 0);
            if ($h > 0) {
                $gains[] = ['code' => $k, 'libelle' => $libelle, 'base' => $h, 'taux' => round($tauxHoraire * (1 + $maj / 100), 2), 'montant' => $r0($h * $tauxHoraire * (1 + $maj / 100)), 'imposable' => true, 'cotisable' => true];
            }
        }

        // Primes fixes du salarié puis primes du mois
        $primes = array_merge((array) ($e['primes_fixes'] ?? []), (array) ($v['primes'] ?? []));
        foreach ($primes as $pr) {
            $m = (float) ($pr['montant'] ?? 0);
            if ($m == 0) {
                continue;
            }
            $imposable = !array_key_exists('imposable', $pr) || !empty($pr['imposable']);
            $cotisable = array_key_exists('cotisable', $pr) ? !empty($pr['cotisable']) : $imposable;
            $gains[] = ['code' => 'prime', 'libelle' => $pr['libelle'] ?: 'Prime', 'base' => null, 'taux' => null, 'montant' => $r0($m), 'imposable' => $imposable, 'cotisable' => $cotisable];
        }

        // Indemnité de transport : exonérée jusqu'au plafond, le surplus est imposable
        $transport = (float) ($e['indemnite_transport'] ?? 0);
        if ($transport > 0) {
            $exo = min($transport, $this->n('transport_exonere'));
            $gains[] = ['code' => 'transport', 'libelle' => 'Indemnité de transport', 'base' => null, 'taux' => null, 'montant' => $r0($exo), 'imposable' => false, 'cotisable' => false];
            if ($transport > $exo) {
                $gains[] = ['code' => 'transport_imp', 'libelle' => 'Indemnité de transport (part imposable)', 'base' => null, 'taux' => null, 'montant' => $r0($transport - $exo), 'imposable' => true, 'cotisable' => true];
            }
        }

        $brut = 0;
        $brutImposable = 0;
        $brutCotisable = 0;
        foreach ($gains as $g) {
            $brut += $g['montant'];
            if ($g['imposable']) {
                $brutImposable += $g['montant'];
            }
            if ($g['cotisable']) {
                $brutCotisable += $g['montant'];
            }
        }
        $brutImposable = max(0, $brutImposable);
        $brutCotisable = max(0, $brutCotisable);

        // Cotisations salariales et patronales
        $assRg = min($brutCotisable, $this->n('ipres_rg_plafond'));
        $retenues[] = ['code' => 'ipres_rg', 'libelle' => 'IPRES régime général', 'base' => $assRg, 'taux' => $this->n('ipres_rg_sal'), 'montant' => $r0($assRg * $this->n('ipres_rg_sal') / 100)];
        $patronal[] = ['code' => 'ipres_rg', 'libelle' => 'IPRES régime général', 'base' => $assRg, 'taux' => $this->n('ipres_rg_pat'), 'montant' => $r0($assRg * $this->n('ipres_rg_pat') / 100)];
        if (!empty($e['cadre'])) {
            $assRcc = min($brutCotisable, $this->n('ipres_rcc_plafond'));
            $retenues[] = ['code' => 'ipres_rcc', 'libelle' => 'IPRES régime cadres', 'base' => $assRcc, 'taux' => $this->n('ipres_rcc_sal'), 'montant' => $r0($assRcc * $this->n('ipres_rcc_sal') / 100)];
            $patronal[] = ['code' => 'ipres_rcc', 'libelle' => 'IPRES régime cadres', 'base' => $assRcc, 'taux' => $this->n('ipres_rcc_pat'), 'montant' => $r0($assRcc * $this->n('ipres_rcc_pat') / 100)];
        }
        if (!empty($e['ipm'])) {
            $assIpm = min($brutCotisable, $this->n('ipm_plafond'));
            $retenues[] = ['code' => 'ipm', 'libelle' => 'IPM (maladie)', 'base' => $assIpm, 'taux' => $this->n('ipm_sal'), 'montant' => $r0($assIpm * $this->n('ipm_sal') / 100)];
            $patronal[] = ['code' => 'ipm', 'libelle' => 'IPM (maladie)', 'base' => $assIpm, 'taux' => $this->n('ipm_pat'), 'montant' => $r0($assIpm * $this->n('ipm_pat') / 100)];
        }
        $assCss = min($brutCotisable, $this->n('css_plafond'));
        $patronal[] = ['code' => 'css_pf', 'libelle' => 'CSS prestations familiales', 'base' => $assCss, 'taux' => $this->n('css_pf'), 'montant' => $r0($assCss * $this->n('css_pf') / 100)];
        $patronal[] = ['code' => 'css_at', 'libelle' => 'CSS accidents du travail', 'base' => $assCss, 'taux' => $this->n('css_at'), 'montant' => $r0($assCss * $this->n('css_at') / 100)];
        $patronal[] = ['code' => 'cfce', 'libelle' => 'CFCE', 'base' => $brutImposable, 'taux' => $this->n('cfce'), 'montant' => $r0($brutImposable * $this->n('cfce') / 100)];

        // Impôt sur le revenu : projection annuelle, abattement, barème, réduction pour charges de famille
        $ipresSal = 0;
        foreach ($retenues as $r) {
            if (strpos($r['code'], 'ipres') === 0) {
                $ipresSal += $r['montant'];
            }
        }
        $annuel = $brutImposable * 12 - ($this->n('ipres_deductible_ir') ? $ipresSal * 12 : 0);
        $abattement = min($annuel * $this->n('abattement_taux') / 100, $this->n('abattement_plafond'));
        $revenuNet = max(0, floor(($annuel - $abattement) / 1000) * 1000);
        $irBrut = self::bareme($revenuNet, (array) $this->p['ir_bareme']);
        $parts = self::parts($e);
        $reduction = 0;
        $red = ((array) $this->p['ir_reductions'])[self::cleParts($parts)] ?? null;
        if ($irBrut > 0 && $red) {
            $reduction = min($irBrut, max((float) $red[1], min((float) $red[2], $irBrut * (float) $red[0] / 100)));
        }
        $ir = $r0(max(0, $irBrut - $reduction) / 12);
        $retenues[] = ['code' => 'ir', 'libelle' => "Impôt sur le revenu ({$parts} part" . ($parts > 1 ? 's' : '') . ')', 'base' => $brutImposable, 'taux' => null, 'montant' => $ir];
        $trimf = $r0(self::trimfAnnuel($brutImposable * 12, (array) $this->p['trimf_bareme']) / 12);
        $retenues[] = ['code' => 'trimf', 'libelle' => 'TRIMF', 'base' => $brutImposable, 'taux' => null, 'montant' => $trimf];

        // Autres retenues du mois (avances, prêts, ...)
        foreach ((array) ($v['retenues'] ?? []) as $rt) {
            $m = (float) ($rt['montant'] ?? 0);
            if ($m != 0) {
                $retenues[] = ['code' => 'autre', 'libelle' => $rt['libelle'] ?: 'Retenue', 'base' => null, 'taux' => null, 'montant' => $r0($m)];
            }
        }

        $totalRetenues = array_sum(array_column($retenues, 'montant'));
        $totalPatronal = array_sum(array_column($patronal, 'montant'));
        $net = $brut - $totalRetenues;

        $smig = $this->n('smig');
        if ($smig > 0 && $base > 0 && $base < $smig) {
            $alertes[] = 'Salaire de base inférieur au SMIG (' . number_format($smig, 0, ',', ' ') . ' F).';
        }
        if ($net < 0) {
            $alertes[] = 'Le net à payer est négatif : vérifiez les retenues.';
        }

        return [
            'mois' => $mois,
            'gains' => $gains,
            'retenues' => $retenues,
            'patronal' => $patronal,
            'brut' => $brut,
            'brut_imposable' => $brutImposable,
            'brut_cotisable' => $brutCotisable,
            'retenues_total' => $totalRetenues,
            'ir' => $ir,
            'trimf' => $trimf,
            'net' => $net,
            'charges_patronales' => $totalPatronal,
            'cout_total' => $brut + $totalPatronal,
            'fiscal' => [
                'revenu_annuel' => $annuel,
                'abattement' => $abattement,
                'revenu_net_imposable' => $revenuNet,
                'ir_brut_annuel' => $irBrut,
                'reduction_famille' => $reduction,
                'parts' => $parts,
            ],
            'anciennete' => $annees,
            'taux_horaire' => round($tauxHoraire, 2),
            'alertes' => $alertes,
        ];
    }

    private static function cleParts($parts)
    {
        $s = rtrim(rtrim(number_format($parts, 1, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
    }
}
