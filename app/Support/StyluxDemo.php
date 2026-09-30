<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Données fictives de démonstration pour Stylux Oil (un mois d'activité).
 *
 * Tout passe par le query builder (DB::table) pour ne déclencher aucun observer
 * et pouvoir fixer les dates de création.
 *
 * Remise à zéro : voir StyluxDemo::reinitialiser() (commande php artisan stylux:reinitialiser).
 */
class StyluxDemo
{
    /** Fichier de sauvegarde des index pistolets et niveaux de cuves avant la démo */
    const SAUVEGARDE = 'stylux-demo-sauvegarde.json';

    /** Tables d'activité vidées par la remise à zéro (le paramétrage est conservé) */
    const TABLES_ACTIVITE = [
        'caisses', 'compteurs', 'bon_clients', 'depenses', 'vente_tpes',
        'syntheses', 'encaissements', 'commande_cars', 'remise_cuves', 'stocks', 'receptions',
        'lavages', 'recettes', 'lubrifiants', 'accessoires', 'tablubs', 'tabaccs',
        'magasins', 'inventaires', 'tabinventaires', 'entre_m_s', 'sortie_m_s', 'entree_magasins',
        'journees', 'fiche_chef_pistes', 'factures', 'demande_modifications',
        'lub_mouvements', 'lub_inventaires', 'lub_inventaire_lignes',
    ];

    /** Clients crédit Stylux (liste du gérant, septembre 2026) */
    const CLIENTS = [
        'Juge Ndary Diop', 'Mme Diaw BASN', 'Ets Traore & Fils', 'Saliou Badji Dieye Global', 'Gandiol',
        'Oumar Faye', 'Mor Ndiaye & Arla Bodian', 'Bouna Diop', 'Mamadou Diop / RIM', 'Amina Niang',
        'Yoro Diop', 'Agro-Pharm', 'Aïcha Racky Bop', 'Mme Cisse BASN', 'Fama / BASN',
        'Docteur Beye', 'Moussa Tall', 'ABT', 'LSB', 'Djamil Logis-Ique',
        'DAP', 'Khady Ka Diallo', 'Becaye Sene', 'Ndeye Arame Mamie Diop', 'Lamine Seck / Leyti Ndiaye',
        'Ficra-Com', 'Pape Mbaye -Bodian- Madjiguene Mbaye', 'Gendarmerie Thiaroye', 'A2FP Ex Dieye Global', 'Mbaye Gningue & Bamba Gaye',
    ];

    const ACCESSOIRES = [
        ['Balai essuie-glace', 3500], ['Liquide de frein DOT 4', 2500], ['Ampoule H4', 1500],
        ['Désodorisant', 1000], ['Eau déminéralisée 1L', 500], ['Triangle de signalisation', 5000],
        ['Liquide de refroidissement 1L', 3000], ['Chiffon microfibre', 1000],
    ];

    const DEPENSES = [
        ['Eau et glace', 1000, 3000], ['Transport', 2000, 6000], ['Carburant groupe électrogène', 5000, 15000],
        ['Petit matériel', 1500, 7500], ['Crédit téléphone', 1000, 3000], ['Nettoyage', 2000, 5000],
    ];

    // Tarifs lavage : [carrosserie, moteur, graissage, pulvérisation, complet]
    const LAVAGE = [
        'Voiture' => [2500, 2000, 3000, 1000, 5000],
        '4x4' => [3500, 2500, 4000, 1500, 7000],
        'Camion' => [6000, 4000, 6000, 2500, 12000],
    ];

    /** @var callable */
    private $log;
    private $stats = [];

    public function __construct(callable $log = null)
    {
        $this->log = $log ?: function ($m) {};
    }

    private function log($m)
    {
        ($this->log)($m);
    }

    private static function r($min, $max)
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    private static function ri($min, $max)
    {
        return mt_rand($min, $max);
    }

    private static function arrondi5($v)
    {
        return round($v / 5) * 5;
    }

    private static function norm($s)
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $s)));
        return strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ï' => 'i', 'î' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c']);
    }

    /**
     * Génère les données du mois.
     *
     * @param string $mois          AAAA-MM
     * @param float  $litresParJour moyenne journalière visée
     * @param string $jusquau       dernier jour généré (AAAA-MM-JJ), par défaut la veille ou la fin du mois
     * @param string $dossierSauvegarde dossier où écrire la sauvegarde (storage/app)
     */
    public function generer($mois, $litresParJour, $jusquau, $dossierSauvegarde)
    {
        mt_srand(20260901);
        $debut = new \DateTimeImmutable("$mois-01");
        $fin = min(new \DateTimeImmutable($debut->format('Y-m-t')), new \DateTimeImmutable($jusquau));
        if ($fin < $debut) {
            throw new \RuntimeException("Aucun jour à générer (fin avant le début du mois).");
        }

        // --- Paramétrage existant
        $pompes = DB::table('pompes')->whereNull('deleted_at')->orderBy('id')->get();
        $pistolets = DB::table('pistolets')->whereNull('deleted_at')->orderBy('id')->get();
        $reservoirs = DB::table('reservoirs')->whereNull('deleted_at')->orderBy('id')->get();
        if (!count($pompes) || !count($pistolets)) {
            throw new \RuntimeException("Aucune pompe ou aucun pistolet : créez d'abord le paramétrage de la station.");
        }
        $pompistes = DB::table('users')->where('role_id', 1)->whereNull('deleted_at')->pluck('id')->all();
        $chef = DB::table('users')->where('role_id', 3)->whereNull('deleted_at')->value('id');
        if (!$pompistes) {
            throw new \RuntimeException("Aucun pompiste (role_id = 1) : créez au moins un compte pompiste.");
        }
        $chef = $chef ?: $pompistes[0];

        $deja = DB::table('caisses')->whereBetween('date_caisse', [$debut->format('Y-m-d'), $fin->format('Y-m-d')])->count();
        if ($deja) {
            throw new \RuntimeException("$deja caisse(s) existent déjà sur cette période : lancez d'abord la remise à zéro.");
        }
        $avecMode = DB::getSchemaBuilder()->hasColumn('vente_tpes', 'mode');
        $avecSignature = DB::getSchemaBuilder()->hasColumn('caisses', 'approuve_par');
        $gerant = DB::table('users')->whereIn('role_id', [2, 4])->whereNull('deleted_at')->orderBy('role_id')->value('id') ?: $chef;

        // --- Sauvegarde du paramétrage modifié (une seule fois)
        $fichier = rtrim($dossierSauvegarde, '/\\') . DIRECTORY_SEPARATOR . self::SAUVEGARDE;
        if (!file_exists($fichier)) {
            file_put_contents($fichier, json_encode([
                'date' => date('c'),
                'pistolets' => array_map(function ($p) {
                    return ['id' => $p->id, 'indexE' => $p->indexE, 'indexM' => $p->indexM];
                }, is_array($pistolets) ? $pistolets : $pistolets->all()),
                'reservoirs' => array_map(function ($r) {
                    return ['id' => $r->id, 'quantite' => $r->quantite];
                }, is_array($reservoirs) ? $reservoirs : $reservoirs->all()),
            ], JSON_PRETTY_PRINT));
            $this->log("Sauvegarde des index et niveaux de cuves : $fichier");
        }

        $clients = $this->assurerClients();

        // --- Index de départ et poids de chaque pistolet
        $pist = [];
        foreach ($pistolets as $p) {
            $carb = self::norm($p->carburant);
            $e = (float) $p->indexE > 0 ? (float) $p->indexE : self::ri(150000, 1500000);
            $pist[$p->id] = [
                'pompe_id' => $p->pompe_id,
                'carb' => strpos($carb, 'gas') !== false || strpos($carb, 'gaz') !== false ? 'gasoil' : 'super',
                'prix' => (float) $p->prix,
                'e' => $e,
                'm' => (float) $p->indexM > 0 ? (float) $p->indexM : $e - self::ri(10, 60),
                'poids' => self::r(0.75, 1.25),
            ];
        }

        // --- Cuves : niveau de départ
        $cuves = [];
        foreach ($reservoirs as $r) {
            $carb = self::norm($r->carburant);
            $cap = (float) $r->capacite ?: 20000;
            $cuves[$r->id] = [
                'carb' => strpos($carb, 'gas') !== false || strpos($carb, 'gaz') !== false ? 'gasoil' : 'super',
                'cap' => $cap,
                'niveau' => (float) $r->quantite > 0 ? min((float) $r->quantite, $cap) : round($cap * self::r(0.55, 0.75)),
            ];
        }

        // --- Volumes journaliers : moyenne exacte sur la période, plus de ventes en fin de semaine
        $jours = [];
        for ($d = $debut; $d <= $fin; $d = $d->modify('+1 day')) {
            $jours[] = $d;
        }
        $facteurSemaine = [1 => 0.95, 2 => 0.93, 3 => 0.97, 4 => 1.0, 5 => 1.08, 6 => 1.12, 7 => 0.95];
        $bruts = array_map(function ($d) use ($facteurSemaine) {
            return $facteurSemaine[(int) $d->format('N')] * self::r(0.92, 1.08);
        }, $jours);
        $k = $litresParJour * count($jours) / array_sum($bruts);
        $volumes = array_map(function ($b) use ($k) {
            return round($b * $k, 2);
        }, $bruts);

        // --- Stock lubrifiants / accessoires (report d'un jour sur l'autre)
        $lubs = DB::table('lubs')->orderBy('id')->get();
        $stockLub = [];
        foreach ($lubs as $l) {
            $stockLub[$l->id] = self::ri(6, 40);
        }
        $stockAcc = [];
        foreach (self::ACCESSOIRES as $i => $a) {
            $stockAcc[$i] = self::ri(5, 25);
        }

        $bons = [];
        $synthesesParDate = [];
        $nbJours = count($jours);
        $cumul = ['litres' => 0, 'montant' => 0];

        foreach ($jours as $j => $d) {
            $date = $d->format('Y-m-d');
            $ts = $d->format('Y-m-d') . ' 20:30:00';
            $approuve = $j < $nbJours - 2 ? 1 : 0; // les deux derniers jours restent à valider
            $volume = $volumes[$j];
            $partGasoil = self::r(0.55, 0.62);
            $parCarb = ['gasoil' => $volume * $partGasoil, 'super' => $volume * (1 - $partGasoil)];

            // Répartition par pistolet
            $litres = [];
            foreach (['gasoil', 'super'] as $c) {
                $ids = array_keys(array_filter($pist, function ($p) use ($c) {
                    return $p['carb'] === $c;
                }));
                if (!$ids) {
                    continue;
                }
                $w = [];
                foreach ($ids as $id) {
                    $w[$id] = $pist[$id]['poids'] * self::r(0.85, 1.15);
                }
                $sw = array_sum($w);
                foreach ($ids as $id) {
                    $litres[$id] = round($parCarb[$c] * $w[$id] / $sw, 2);
                }
            }

            // --- Synthèse du jour + relevé des cuves à 08h00
            $syntheseId = DB::table('syntheses')->insertGetId([
                'date' => $date, 'user_id' => $chef, 'etat' => $approuve,
                'created_at' => $date . ' 08:10:00', 'updated_at' => $ts,
            ]);
            $synthesesParDate[$date] = $syntheseId;
            if ($avecSignature && $approuve) {
                // Journée approuvée par le gérant le lendemain matin
                DB::table('syntheses')->where('id', $syntheseId)->update([
                    'approuve_par' => $gerant,
                    'approuve_le' => date('Y-m-d', strtotime($date . ' +1 day')) . sprintf(' 09:%02d:00', self::ri(5, 55)),
                ]);
            }
            foreach ($cuves as $rid => $cv) {
                DB::table('stocks')->insert([
                    'capacite' => round($cv['niveau'] + self::r(-12, 12)), // lecture de la jauge
                    'reservoir_id' => $rid, 'synthese_id' => $syntheseId,
                    'created_at' => $date . ' 08:05:00', 'updated_at' => $date . ' 08:05:00',
                ]);
            }

            // --- Caisses par pompe
            $pompisteIdx = $j;
            foreach ($pompes as $pompe) {
                $userId = $pompistes[$pompisteIdx++ % count($pompistes)];
                $montant = 0;
                $caisseId = DB::table('caisses')->insertGetId([
                    'date_caisse' => $date, 'horaire' => 24, 'pompe_id' => $pompe->id, 'user_id' => $userId,
                    'approuve' => $approuve, 'netVer' => 0, 'coffre' => 0, 'ecart' => 0,
                    'created_at' => $date . ' 06:30:00', 'updated_at' => $ts,
                ]);
                if ($avecSignature && $approuve) {
                    // Caisse approuvée par le chef de piste en fin de journée
                    DB::table('caisses')->where('id', $caisseId)->update([
                        'approuve_par' => $chef,
                        'approuve_le' => $date . sprintf(' 21:%02d:00', self::ri(5, 55)),
                    ]);
                }
                foreach ($pist as $pid => &$p) {
                    if ($p['pompe_id'] != $pompe->id || !isset($litres[$pid])) {
                        continue;
                    }
                    $l = $litres[$pid];
                    $ouvE = $p['e'];
                    $ouvM = $p['m'];
                    $p['e'] = round($ouvE + $l, 2);
                    $p['m'] = round($ouvM + $l + self::r(-0.4, 0.4), 2);
                    DB::table('compteurs')->insert([
                        'indexOuvE' => $ouvE, 'indexOuvM' => $ouvM, 'indexFerE' => $p['e'], 'indexFerM' => $p['m'],
                        'prix' => $p['prix'], 'pistolet_id' => $pid, 'caisse_id' => $caisseId,
                        'created_at' => $ts, 'updated_at' => $ts,
                    ]);
                    $montant += $l * $p['prix'];
                    $cumul['litres'] += $l;
                }
                unset($p);
                $montant = round($montant, 2);
                $cumul['montant'] += $montant;

                // Paiements électroniques : cartes TPE, puis Wave et Orange Money (si la colonne "mode" existe)
                $tpe = 0;
                $paiements = [];
                for ($t = 0, $n = self::ri(0, 3); $t < $n; $t++) {
                    $paiements[] = ['carte', (string) self::ri(1000, 9999), self::r(0.01, 0.035)];
                }
                if ($avecMode) {
                    for ($t = 0, $n = self::ri(1, 4); $t < $n; $t++) {
                        $wave = self::r(0, 1) < 0.65;
                        $paiements[] = [$wave ? 'wave' : 'orange_money', ($wave ? 'W' : 'OM') . self::ri(100000, 999999), self::r(0.004, 0.02)];
                    }
                }
                foreach ($paiements as [$mode, $ref, $part]) {
                    $m = self::arrondi5($montant * $part);
                    $tpe += $m;
                    $ligne = ['numero_carte' => $ref, 'montant' => $m, 'caisse_id' => $caisseId, 'created_at' => $ts, 'updated_at' => $ts];
                    if ($avecMode) {
                        $ligne['mode'] = $mode;
                    }
                    DB::table('vente_tpes')->insert($ligne);
                }
                // Bons clients (ventes à crédit)
                $bonsTotal = 0;
                if (self::r(0, 1) < 0.6) {
                    $nb = self::ri(1, 2);
                    for ($b = 0; $b < $nb; $b++) {
                        $m = self::arrondi5(min(self::r(10000, 120000), $montant * 0.08));
                        if ($m < 5000) {
                            continue;
                        }
                        $clientId = $clients[self::ri(0, count($clients) - 1)];
                        $heure = sprintf('%s %02d:%02d:00', $date, self::ri(7, 19), self::ri(0, 59));
                        $bonId = DB::table('bon_clients')->insertGetId([
                            'montant' => $m, 'caisse_id' => $caisseId, 'client_id' => $clientId, 'etat' => 0,
                            'created_at' => $heure, 'updated_at' => $heure,
                        ]);
                        $bons[] = ['id' => $bonId, 'client_id' => $clientId, 'j' => $j];
                        $bonsTotal += $m;
                    }
                }
                // Dépenses
                $dep = 0;
                if (self::r(0, 1) < 0.45) {
                    $x = self::DEPENSES[self::ri(0, count(self::DEPENSES) - 1)];
                    $m = round(self::ri($x[1], $x[2]) / 500) * 500;
                    $dep += $m;
                    DB::table('depenses')->insert([
                        'justificatif' => $x[0], 'montant' => $m, 'caisse_id' => $caisseId,
                        'created_at' => $ts, 'updated_at' => $ts,
                    ]);
                }
                // Coffre, versement et écart (même calcul que l'écran caisse)
                $coffre = self::ri(6, 16) * 5000;
                $aVerser = $montant - $tpe - $bonsTotal - $dep - $coffre;
                $alea = self::r(0, 1);
                $ecart = $alea < 0.25 ? self::ri(1, 6) * 500 : ($alea < 0.3 ? -self::ri(1, 3) * 500 : 0);
                DB::table('caisses')->where('id', $caisseId)->update([
                    'coffre' => $coffre, 'netVer' => $aVerser - $ecart, 'ecart' => $ecart,
                ]);
            }

            // --- Mouvements des cuves : ventes (avec pertes ~0,3 %), remises en cuve, réceptions
            foreach (['gasoil', 'super'] as $c) {
                $venteC = array_sum(array_intersect_key($litres, array_filter($pist, function ($p) use ($c) {
                    return $p['carb'] === $c;
                })));
                $ids = array_keys(array_filter($cuves, function ($cv) use ($c) {
                    return $cv['carb'] === $c;
                }));
                if (!$ids) {
                    continue;
                }
                $capTot = array_sum(array_map(function ($id) use ($cuves) {
                    return $cuves[$id]['cap'];
                }, $ids));
                foreach ($ids as $rid) {
                    $part = $venteC * $cuves[$rid]['cap'] / $capTot;
                    $remise = 0;
                    if (self::r(0, 1) < 0.06) {
                        $remise = 20; // contrôle de jaugeage : 20 L remis en cuve
                        DB::table('remise_cuves')->insert([
                            'capacite' => $remise, 'reservoir_id' => $rid, 'synthese_id' => $syntheseId,
                            'created_at' => $ts, 'updated_at' => $ts,
                        ]);
                    }
                    $cuves[$rid]['niveau'] = $cuves[$rid]['niveau'] - $part * self::r(1.001, 1.005) + $remise;
                    // Livraison quand la cuve passe sous 35 %
                    if ($cuves[$rid]['niveau'] < 0.35 * $cuves[$rid]['cap']) {
                        $qte = floor((0.9 * $cuves[$rid]['cap'] - $cuves[$rid]['niveau']) / 1000) * 1000;
                        if ($qte > 0) {
                            DB::table('receptions')->insert([
                                'capacite' => $qte, 'reservoir_id' => $rid, 'synthese_id' => $syntheseId,
                                'created_at' => $date . ' 11:00:00', 'updated_at' => $date . ' 11:00:00',
                            ]);
                            DB::table('commande_cars')->insert([
                                'numero_cde' => 'CDE-' . $d->format('md') . '-' . $rid,
                                'numero_bl' => 'BL-' . self::ri(100000, 999999),
                                'heure' => 11, 'synthese_id' => $syntheseId,
                                'created_at' => $date . ' 11:00:00', 'updated_at' => $date . ' 11:00:00',
                            ]);
                            $cuves[$rid]['niveau'] += $qte;
                        }
                    }
                    $cuves[$rid]['niveau'] = max(0, $cuves[$rid]['niveau']);
                }
            }

            // --- Lubrifiants, accessoires, lavages, recettes du jour
            $totLub = 0;
            foreach ($lubs as $l) {
                $ouv = $stockLub[$l->id];
                $ent = $ouv < 5 ? 24 : 0;
                $v = min($ouv + $ent, [0, 0, 0, 1, 1, 2, 3][self::ri(0, 6)]);
                $stockLub[$l->id] = $ouv + $ent - $v;
                $totLub += $v * (float) $l->prix;
                DB::table('lubrifiants')->insert([
                    'date_lubrifiant' => $date, 'produit' => $l->nom, 'ouverture' => $ouv, 'entrant' => $ent,
                    'prixunitaire' => $l->prix, 'fermeture' => $stockLub[$l->id], 'created_at' => $ts, 'updated_at' => $ts,
                ]);
            }
            $totAcc = 0;
            foreach (self::ACCESSOIRES as $i => $a) {
                $ouv = $stockAcc[$i];
                $ent = $ouv < 3 ? 12 : 0;
                $v = min($ouv + $ent, [0, 0, 1, 1, 2][self::ri(0, 4)]);
                $stockAcc[$i] = $ouv + $ent - $v;
                $totAcc += $v * $a[1];
                DB::table('accessoires')->insert([
                    'date_accessoire' => $date, 'produit' => $a[0], 'ouverture' => $ouv, 'entrant' => $ent,
                    'prixunitaire' => $a[1], 'fermeture' => $stockAcc[$i], 'created_at' => $ts, 'updated_at' => $ts,
                ]);
            }
            $totLav = 0;
            $nLav = self::ri(3, 9);
            for ($x = 0; $x < $nLav; $x++) {
                $type = ['Voiture', 'Voiture', 'Voiture', '4x4', '4x4', 'Camion'][self::ri(0, 5)];
                $t = self::LAVAGE[$type];
                $complet = self::r(0, 1) < 0.35;
                $row = [
                    'carosserie' => $complet ? 0 : $t[0],
                    'moteur' => !$complet && self::r(0, 1) < 0.3 ? $t[1] : 0,
                    'graissage' => self::r(0, 1) < 0.2 ? $t[2] : 0,
                    'pulv' => self::r(0, 1) < 0.15 ? $t[3] : 0,
                    'complet' => $complet ? $t[4] : 0,
                ];
                $totLav += array_sum($row);
                DB::table('lavages')->insert($row + [
                    'num_vehicule' => sprintf('DK-%04d-%s', self::ri(1000, 9999), chr(65 + self::ri(0, 25)) . chr(65 + self::ri(0, 25))),
                    'type' => $type, 'date_lavage' => $date, 'created_at' => $ts, 'updated_at' => $ts,
                ]);
            }
            DB::table('recettes')->insert([
                'totallub' => $totLub, 'totallav' => $totLav, 'totalacc' => $totAcc,
                'totalfut' => self::r(0, 1) < 0.15 ? 5000 : 0, 'date_recette' => $date,
                'created_at' => $ts, 'updated_at' => $ts,
            ]);
            DB::table('tablubs')->insert(['dateEnreg' => $date, 'user_id' => $chef, 'approuve' => $approuve, 'created_at' => $ts, 'updated_at' => $ts]);
            DB::table('tabaccs')->insert(['dateEnreg' => $date, 'user_id' => $chef, 'approuve' => $approuve, 'created_at' => $ts, 'updated_at' => $ts]);
        }

        // --- Encaissements : environ 70 % des bons de plus d'une semaine sont réglés 5 à 15 jours après
        $nEnc = 0;
        foreach ($bons as $b) {
            if ($nbJours - 1 - $b['j'] < 7 || self::r(0, 1) > 0.7) {
                continue;
            }
            $jEnc = min($nbJours - 1, $b['j'] + self::ri(5, 15));
            $dEnc = $jours[$jEnc]->format('Y-m-d');
            $encId = DB::table('encaissements')->insertGetId([
                'type' => ['espece', 'cheque', 'virement', 'wave'][self::ri(0, 3)],
                'synthese_id' => $synthesesParDate[$dEnc], 'client_id' => $b['client_id'], 'etat' => 1,
                'created_at' => $dEnc . ' 16:00:00', 'updated_at' => $dEnc . ' 16:00:00',
            ]);
            DB::table('bon_clients')->where('id', $b['id'])->update(['encaissement_id' => $encId]);
            $nEnc++;
        }

        // --- Module lubrifiants (si installé) : stock initial, réceptions, réassorts, ventes, inventaire de contrôle
        $nLub = $this->genererLubrifiants($jours, $pompistes, $chef, $gerant);

        // --- Index et niveaux à jour pour la suite (le lendemain de la démo)
        foreach ($pist as $pid => $p) {
            DB::table('pistolets')->where('id', $pid)->update(['indexE' => $p['e'], 'indexM' => $p['m']]);
        }
        foreach ($cuves as $rid => $cv) {
            DB::table('reservoirs')->where('id', $rid)->update(['quantite' => round($cv['niveau'])]);
        }

        return $this->stats = [
            'mouvements_lubrifiants' => $nLub,
            'jours' => $nbJours,
            'du' => $debut->format('Y-m-d'),
            'au' => $fin->format('Y-m-d'),
            'litres' => round($cumul['litres']),
            'moyenne' => round($cumul['litres'] / $nbJours),
            'montant' => $cumul['montant'],
            'caisses' => $nbJours * count($pompes),
            'bons' => count($bons),
            'encaissements' => $nEnc,
        ];
    }

    /** Crée les clients crédit Stylux manquants et renvoie la liste des id clients */
    /** Ventes mensuelles de référence (unités, mars 2026) dans l'ordre du catalogue lub_produits */
    const LUB_VENTES_MOIS = [243, 156, 131, 10, 1, 5, 26, 4, 32, 15, 1, 193, 91, 144, 45, 20, 4, 1, 4, 7, 2, 0];

    private function genererLubrifiants($jours, $pompistes, $chef, $gerant)
    {
        if (!DB::getSchemaBuilder()->hasTable('lub_mouvements')) {
            return 0;
        }
        $produits = DB::table('lub_produits')->orderBy('ordre')->get()->values();
        if (!count($produits)) {
            return 0;
        }
        $n = 0;
        $mvt = function ($lot, $date, $type, $p, $emp, $q, $prix, $extra = []) use (&$n) {
            DB::table('lub_mouvements')->insert($extra + [
                'lot' => $lot, 'date' => $date, 'type' => $type, 'produit_id' => $p->id, 'emplacement' => $emp,
                'quantite' => $q, 'prix_unitaire' => $prix, 'created_at' => $date . ' 12:00:00', 'updated_at' => $date . ' 12:00:00',
            ]);
            $n++;
        };
        $d0 = $jours[0]->format('Y-m-d');
        $nbJours = count($jours);

        // Stock initial : inventaire validé le premier jour
        $invId = DB::table('lub_inventaires')->insertGetId([
            'numero' => substr($d0, 0, 4) === '2026' ? 'INV-2026-001' : 'INV-' . substr($d0, 0, 4) . '-001',
            'date' => $d0, 'emplacement' => 'tous', 'statut' => 'valide', 'commentaire' => 'Stock initial (démo)',
            'user_id' => $chef, 'valide_par' => $gerant, 'valide_le' => $d0 . ' 07:45:00',
            'created_at' => $d0 . ' 07:00:00', 'updated_at' => $d0 . ' 07:45:00',
        ]);
        $st = [];
        foreach ($produits as $i => $p) {
            $mois = self::LUB_VENTES_MOIS[$i] ?? 5;
            $cq = max(1, (int) $p->colis_qte);
            $pres = $cq >= 100 ? $cq : max($cq, (int) ceil($mois / 3));                     // présentoir : ~10 jours de vente
            $mag = $cq >= 100 ? $cq * self::ri(1, 2) : $cq * max(1, (int) ceil($mois / $cq)); // magasin : ~1 mois
            if ($mois === 0) {
                $pres = 0;
                $mag = $cq;
            }
            $st[$p->id] = ['magasin' => $mag, 'presentoir' => $pres];
            foreach (['magasin' => $mag, 'presentoir' => $pres] as $e => $q) {
                DB::table('lub_inventaire_lignes')->insert([
                    'inventaire_id' => $invId, 'produit_id' => $p->id, 'emplacement' => $e, 'theorique' => 0,
                    'compte' => $q, 'ecart' => $q, 'prix_achat' => $p->prix_achat, 'created_at' => $d0 . ' 07:00:00', 'updated_at' => $d0 . ' 07:45:00',
                ]);
                if ($q) {
                    $mvt('INV-' . $invId, $d0, 'initial', $p, $e, $q, $p->prix_achat, ['reference' => 'INV-2026-001', 'inventaire_id' => $invId, 'user_id' => $gerant, 'commentaire' => "Stock initial"]);
                }
            }
        }

        foreach ($jours as $j => $d) {
            $date = $d->format('Y-m-d');
            // Ventes du jour, par poste
            foreach (['matin', 'soir'] as $poste) {
                $lot = 'VEN-' . $date . '-' . $poste;
                $pompiste = $pompistes[($j * 2 + ($poste === 'soir')) % count($pompistes)];
                foreach ($produits as $i => $p) {
                    $moy = (self::LUB_VENTES_MOIS[$i] ?? 0) / 30 / 2;
                    if ($moy <= 0) continue;
                    $q = 0;
                    // tirage simple autour de la moyenne
                    $q = (int) floor($moy + self::r(0, 1));
                    $vrac = in_array($p->unite, ['litre', 'kg'], true);
                    if ($vrac && self::r(0, 1) < 0.5) $q = 0;
                    if ($vrac && $q) $q = self::ri(4, 12); // vente au litre / kg
                    if ($q <= 0) continue;
                    // Réassort si le présentoir ne suffit pas
                    if ($st[$p->id]['presentoir'] < $q) {
                        $colis = max(1, (int) $p->colis_qte);
                        if ($st[$p->id]['magasin'] < $colis) {
                            // Réception fournisseur (Vivo Energy) : un mois de stock
                            $recu = $colis * max(2, (int) ceil((self::LUB_VENTES_MOIS[$i] ?? 12) / $colis));
                            $mvt('REC-' . $date . '-' . $p->id, $date, 'reception', $p, 'magasin', $recu, $p->prix_achat, ['reference' => 'BL-' . self::ri(40000, 49999), 'user_id' => $chef]);
                            $st[$p->id]['magasin'] += $recu;
                        }
                        $mvt('REA-' . $date . '-' . $p->id, $date, 'reassort', $p, 'magasin', -$colis, $p->prix_achat, ['user_id' => $chef]);
                        $mvt('REA-' . $date . '-' . $p->id, $date, 'reassort', $p, 'presentoir', $colis, $p->prix_achat, ['user_id' => $chef]);
                        $st[$p->id]['magasin'] -= $colis;
                        $st[$p->id]['presentoir'] += $colis;
                    }
                    $q = min($q, $st[$p->id]['presentoir']);
                    if ($q <= 0) continue;
                    $mvt($lot, $date, 'vente', $p, 'presentoir', -$q, $p->prix_vente, ['poste' => $poste, 'pompiste_id' => $pompiste, 'user_id' => $chef]);
                    $st[$p->id]['presentoir'] -= $q;
                }
            }

            // Inventaire de contrôle des présentoirs à mi-mois, avec deux petits écarts
            if ($j === (int) floor($nbJours / 2)) {
                $inv2 = DB::table('lub_inventaires')->insertGetId([
                    'numero' => 'INV-2026-002', 'date' => $date, 'emplacement' => 'presentoir', 'statut' => 'valide',
                    'commentaire' => 'Contrôle de mi-mois (démo)', 'user_id' => $chef, 'valide_par' => $gerant, 'valide_le' => $date . ' 19:30:00',
                    'created_at' => $date . ' 18:00:00', 'updated_at' => $date . ' 19:30:00',
                ]);
                foreach ($produits as $i => $p) {
                    $theo = $st[$p->id]['presentoir'];
                    $ecart = ($i === 0 && $theo >= 1) ? -1 : (($i === 13 && $theo >= 1) ? -1 : 0);
                    DB::table('lub_inventaire_lignes')->insert([
                        'inventaire_id' => $inv2, 'produit_id' => $p->id, 'emplacement' => 'presentoir', 'theorique' => $theo,
                        'compte' => $theo + $ecart, 'ecart' => $ecart, 'prix_achat' => $p->prix_achat,
                        'created_at' => $date . ' 18:00:00', 'updated_at' => $date . ' 19:30:00',
                    ]);
                    if ($ecart) {
                        $mvt('INV-' . $inv2, $date, 'inventaire', $p, 'presentoir', $ecart, $p->prix_achat, ['reference' => 'INV-2026-002', 'inventaire_id' => $inv2, 'user_id' => $gerant, 'commentaire' => "Écart d'inventaire"]);
                        $st[$p->id]['presentoir'] += $ecart;
                    }
                }
            }
        }
        return $n;
    }

    private function assurerClients()
    {
        $existants = DB::table('clients')->whereNull('deleted_at')->get();
        $noms = [];
        foreach ($existants as $c) {
            $noms[self::norm($c->nom)] = $c->id;
        }
        foreach (self::CLIENTS as $nom) {
            if (!isset($noms[self::norm($nom)])) {
                $noms[self::norm($nom)] = DB::table('clients')->insertGetId([
                    'nom' => $nom, 'telephone' => '-', 'email' => '-', 'adresse' => '-',
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        return array_values($noms);
    }

    /**
     * Remise à zéro avant le lancement : vide toutes les tables d'activité.
     * Conserve utilisateurs, îlots, pompes, pistolets, cuves, clients et produits.
     * Restaure les index des pistolets et le niveau des cuves sauvegardés avant la démo (si la sauvegarde existe).
     *
     * @return array [tables vidées => nb lignes, restauration => bool]
     */
    public static function reinitialiser($dossierSauvegarde, $garderSauvegarde = false)
    {
        $videes = [];
        foreach (self::TABLES_ACTIVITE as $t) {
            if (!DB::getSchemaBuilder()->hasTable($t)) {
                continue;
            }
            $videes[$t] = DB::table($t)->count();
            DB::table($t)->delete();
        }
        $fichier = rtrim($dossierSauvegarde, '/\\') . DIRECTORY_SEPARATOR . self::SAUVEGARDE;
        $restaure = false;
        if (file_exists($fichier)) {
            $s = json_decode(file_get_contents($fichier), true);
            foreach ($s['pistolets'] ?? [] as $p) {
                DB::table('pistolets')->where('id', $p['id'])->update(['indexE' => $p['indexE'] ?? 0, 'indexM' => $p['indexM'] ?? 0]);
            }
            foreach ($s['reservoirs'] ?? [] as $r) {
                DB::table('reservoirs')->where('id', $r['id'])->update(['quantite' => $r['quantite']]);
            }
            $restaure = true;
            if (!$garderSauvegarde) {
                @unlink($fichier);
            }
        }
        return ['tables' => $videes, 'restauration' => $restaure];
    }
}
