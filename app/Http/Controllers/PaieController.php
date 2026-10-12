<?php

namespace App\Http\Controllers;

use App\Models\PaieBulletin;
use App\Models\PaieEmploye;
use App\Models\User;
use App\Services\PaieCalcul;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Module Paie : salariés, bulletins de salaire mensuels, livre de paie et récapitulatif des cotisations
class PaieController extends Controller
{
    const JSON = ['ir_bareme', 'ir_reductions', 'trimf_bareme'];
    const TEXTE = ['raison_sociale', 'etablissement', 'ninea', 'rccm', 'numero_ipres', 'numero_css', 'adresse'];

    private function exigerGerant(Request $request)
    {
        if (!in_array((int) optional($request->user())->role_id, [2, 4], true)) {
            abort(403, "Réservé au gérant et à l'admin.");
        }
    }

    private function params()
    {
        $p = PaieCalcul::defauts();
        foreach (DB::table('paie_parametres')->pluck('valeur', 'cle') as $cle => $valeur) {
            if (in_array($cle, self::JSON, true)) {
                $d = json_decode($valeur, true);
                if (is_array($d)) {
                    $p[$cle] = $d;
                }
            } elseif (in_array($cle, self::TEXTE, true)) {
                $p[$cle] = (string) $valeur;
            } else {
                $p[$cle] = (float) $valeur;
            }
        }
        return $p;
    }

    private function moteur()
    {
        return new PaieCalcul($this->params());
    }

    private function mois($m)
    {
        if (!is_string($m) || !preg_match('/^\d{4}-\d{2}$/', $m)) {
            abort(422, 'Mois invalide (AAAA-MM).');
        }
        return $m;
    }

    // ---------------------------------------------------------------- Paramètres

    public function parametres(Request $request)
    {
        $this->exigerGerant($request);
        return $this->params();
    }

    public function enregistrerParametres(Request $request)
    {
        $this->exigerGerant($request);
        $now = now();
        foreach (PaieCalcul::defauts() as $cle => $defaut) {
            if (!$request->has($cle)) {
                continue;
            }
            $v = $request->input($cle);
            if (in_array($cle, self::JSON, true)) {
                if (!is_array($v)) {
                    abort(422, "Barème invalide : {$cle}.");
                }
                $v = json_encode($v);
            } elseif (in_array($cle, self::TEXTE, true)) {
                $v = (string) $v;
            } else {
                if (!is_numeric($v)) {
                    abort(422, "Valeur numérique attendue : {$cle}.");
                }
                $v = (string) (float) $v;
            }
            DB::table('paie_parametres')->updateOrInsert(['cle' => $cle], ['valeur' => $v, 'updated_at' => $now]);
        }
        return $this->params();
    }

    // ---------------------------------------------------------------- Salariés

    public function employes(Request $request)
    {
        $this->exigerGerant($request);
        $q = PaieEmploye::query()->orderByDesc('actif')->orderBy('prenom')->orderBy('nom');
        if ($request->boolean('actifs')) {
            $q->where('actif', true);
        }
        return $q->get()->map(function ($e) {
            $e->parts_calculees = PaieCalcul::parts($e->toArray());
            return $e;
        });
    }

    private function validerEmploye(Request $request)
    {
        return $request->validate([
            'user_id' => 'nullable|integer',
            'matricule' => 'nullable|string|max:20',
            'prenom' => 'required|string|max:80',
            'nom' => 'required|string|max:80',
            'fonction' => 'nullable|string|max:80',
            'categorie' => 'nullable|string|max:40',
            'date_naissance' => 'nullable|date',
            'date_embauche' => 'nullable|date',
            'date_sortie' => 'nullable|date',
            'type_contrat' => 'nullable|string|max:20',
            'cadre' => 'boolean',
            'situation' => 'required|in:celibataire,marie,divorce,veuf',
            'enfants' => 'nullable|integer|min:0|max:20',
            'conjoint_sans_revenu' => 'boolean',
            'parts' => 'nullable|numeric|min:1|max:5',
            'salaire_base' => 'required|numeric|min:0',
            'sursalaire' => 'nullable|numeric|min:0',
            'indemnite_transport' => 'nullable|numeric|min:0',
            'primes_fixes' => 'nullable|array',
            'primes_fixes.*.libelle' => 'nullable|string|max:60',
            'primes_fixes.*.montant' => 'nullable|numeric',
            'primes_fixes.*.imposable' => 'nullable|boolean',
            'ipm' => 'boolean',
            'numero_ipres' => 'nullable|string|max:30',
            'numero_css' => 'nullable|string|max:30',
            'cni' => 'nullable|string|max:30',
            'telephone' => 'nullable|string|max:30',
            'mode_paiement' => 'nullable|string|max:20',
            'rib' => 'nullable|string|max:60',
            'actif' => 'boolean',
            'commentaire' => 'nullable|string|max:1000',
        ]);
    }

    public function enregistrerEmploye(Request $request, $id = null)
    {
        $this->exigerGerant($request);
        $d = $this->validerEmploye($request);
        foreach (['enfants', 'sursalaire', 'indemnite_transport'] as $k) {
            $d[$k] = $d[$k] ?? 0;
        }
        $d['primes_fixes'] = array_values(array_filter((array) ($d['primes_fixes'] ?? []), function ($p) {
            return !empty($p['libelle']) && (float) ($p['montant'] ?? 0) != 0;
        })) ?: null;
        $e = $id ? PaieEmploye::findOrFail($id) : new PaieEmploye();
        $e->fill($d)->save();
        if (!$e->matricule) {
            $e->update(['matricule' => 'S' . str_pad($e->id, 3, '0', STR_PAD_LEFT)]);
        }
        return $e->fresh();
    }

    public function supprimerEmploye(Request $request, $id)
    {
        $this->exigerGerant($request);
        $e = PaieEmploye::findOrFail($id);
        if ($e->bulletins()->exists()) {
            $e->update(['actif' => false]);
            return ['archive' => true];
        }
        $e->delete();
        return ['archive' => false];
    }

    /** Crée une fiche salarié pour chaque pompiste et chef de piste de l'appli qui n'en a pas encore */
    public function importerUtilisateurs(Request $request)
    {
        $this->exigerGerant($request);
        $deja = PaieEmploye::whereNotNull('user_id')->pluck('user_id')->all();
        $crees = 0;
        foreach (User::whereIn('role_id', [1, 3])->whereNotIn('id', $deja)->get() as $u) {
            $parts = preg_split('/\s+/', trim($u->name), 2);
            $e = PaieEmploye::create([
                'user_id' => $u->id,
                'prenom' => $parts[0] ?? $u->name,
                'nom' => $parts[1] ?? '',
                'fonction' => (int) $u->role_id === 3 ? 'Chef de piste' : 'Pompiste',
                'situation' => 'celibataire',
                'salaire_base' => 0,
                'telephone' => preg_match('/^\d{9}$/', (string) $u->email) ? $u->email : null,
            ]);
            $e->update(['matricule' => 'S' . str_pad($e->id, 3, '0', STR_PAD_LEFT)]);
            $crees++;
        }
        return ['crees' => $crees];
    }

    // ---------------------------------------------------------------- Bulletins

    private function variables(Request $request)
    {
        $v = (array) $request->input('variables', []);
        $out = [];
        foreach (['jours_absence', 'hs15', 'hs40', 'hs60', 'hs100'] as $k) {
            if (isset($v[$k]) && is_numeric($v[$k]) && (float) $v[$k] > 0) {
                $out[$k] = (float) $v[$k];
            }
        }
        foreach (['primes', 'retenues'] as $k) {
            $lignes = [];
            foreach ((array) ($v[$k] ?? []) as $l) {
                $m = (float) ($l['montant'] ?? 0);
                if ($m != 0) {
                    $lignes[] = [
                        'libelle' => substr((string) ($l['libelle'] ?? ''), 0, 60),
                        'montant' => $m,
                        'imposable' => !array_key_exists('imposable', $l) || !empty($l['imposable']),
                    ];
                }
            }
            if ($lignes) {
                $out[$k] = $lignes;
            }
        }
        return $out;
    }

    private function calculerPour(PaieEmploye $e, $mois, array $variables)
    {
        $r = $this->moteur()->calculer($e->toArray(), $mois, $variables);
        return [$r, [
            'variables' => $variables ?: null,
            'lignes' => $r,
            'employe' => $e->only(['matricule', 'prenom', 'nom', 'fonction', 'categorie', 'date_embauche', 'type_contrat', 'cadre', 'situation', 'enfants', 'numero_ipres', 'numero_css', 'cni', 'salaire_base', 'mode_paiement']) + ['parts' => $r['fiscal']['parts']],
            'brut' => $r['brut'],
            'brut_imposable' => $r['brut_imposable'],
            'retenues' => $r['retenues_total'],
            'ir' => $r['ir'],
            'trimf' => $r['trimf'],
            'net' => $r['net'],
            'charges_patronales' => $r['charges_patronales'],
            'cout_total' => $r['cout_total'],
        ]];
    }

    /** Aperçu sans enregistrer */
    public function calculer(Request $request)
    {
        $this->exigerGerant($request);
        $e = PaieEmploye::findOrFail($request->input('employe_id'));
        list($r) = $this->calculerPour($e, $this->mois($request->input('mois')), $this->variables($request));
        return $r;
    }

    /** Bulletins d'un mois, avec les salariés actifs qui n'en ont pas encore */
    public function bulletins(Request $request)
    {
        $this->exigerGerant($request);
        $mois = $this->mois($request->input('mois'));
        $bulletins = PaieBulletin::with('salarie')->where('mois', $mois)->get()->keyBy('employe_id');
        $lignes = [];
        foreach (PaieEmploye::orderBy('prenom')->orderBy('nom')->get() as $e) {
            $b = $bulletins->get($e->id);
            if (!$b && !$e->actif) {
                continue;
            }
            $lignes[] = ['employe' => $e, 'bulletin' => $b ? $b->makeHidden(['lignes', 'salarie']) : null];
        }
        return $lignes;
    }

    public function bulletin(Request $request, $id)
    {
        $this->exigerGerant($request);
        $b = PaieBulletin::with(['salarie', 'validateur:id,name'])->findOrFail($id);
        $annee = substr($b->mois, 0, 4);
        $cumul = PaieBulletin::where('employe_id', $b->employe_id)
            ->where('mois', '>=', $annee . '-01')->where('mois', '<=', $b->mois)
            ->where(function ($q) use ($b) {
                $q->whereIn('statut', ['valide', 'paye'])->orWhere('id', $b->id);
            })
            ->selectRaw('SUM(brut) brut, SUM(brut_imposable) brut_imposable, SUM(ir) ir, SUM(trimf) trimf, SUM(retenues) retenues, SUM(net) net, SUM(charges_patronales) charges_patronales, COUNT(*) mois')
            ->first();
        $p = $this->params();
        return [
            'bulletin' => $b,
            'cumuls' => $cumul,
            'entreprise' => array_intersect_key($p, array_flip(self::TEXTE)),
        ];
    }

    public function enregistrerBulletin(Request $request)
    {
        $this->exigerGerant($request);
        $mois = $this->mois($request->input('mois'));
        $e = PaieEmploye::findOrFail($request->input('employe_id'));
        $b = PaieBulletin::firstOrNew(['employe_id' => $e->id, 'mois' => $mois]);
        if ($b->exists && $b->statut !== 'brouillon') {
            abort(422, 'Ce bulletin est déjà validé : rouvrez-le pour le modifier.');
        }
        list(, $data) = $this->calculerPour($e, $mois, $this->variables($request));
        $b->fill($data + ['statut' => 'brouillon', 'user_id' => optional($request->user())->id])->save();
        return $b->fresh('salarie');
    }

    /** Crée les bulletins brouillons du mois pour tous les salariés actifs (sans variables) */
    public function generer(Request $request)
    {
        $this->exigerGerant($request);
        $mois = $this->mois($request->input('mois'));
        $existants = PaieBulletin::where('mois', $mois)->pluck('employe_id')->all();
        $crees = 0;
        foreach (PaieEmploye::where('actif', true)->whereNotIn('id', $existants)->get() as $e) {
            if (($e->date_embauche && substr($e->date_embauche, 0, 7) > $mois) || ($e->date_sortie && substr($e->date_sortie, 0, 7) < $mois)) {
                continue;
            }
            list(, $data) = $this->calculerPour($e, $mois, []);
            PaieBulletin::create($data + ['employe_id' => $e->id, 'mois' => $mois, 'statut' => 'brouillon', 'user_id' => optional($request->user())->id]);
            $crees++;
        }
        return ['crees' => $crees];
    }

    /** Valide : recalcule avec les paramètres du moment puis fige le bulletin */
    public function valider(Request $request, $id)
    {
        $this->exigerGerant($request);
        $b = PaieBulletin::with('salarie')->findOrFail($id);
        if ($b->statut !== 'brouillon') {
            abort(422, 'Ce bulletin est déjà validé.');
        }
        list(, $data) = $this->calculerPour($b->salarie, $b->mois, (array) $b->variables);
        $b->update($data + ['statut' => 'valide', 'valide_par' => optional($request->user())->id, 'valide_le' => now()]);
        return $b->fresh('salarie');
    }

    public function payer(Request $request, $id)
    {
        $this->exigerGerant($request);
        $b = PaieBulletin::findOrFail($id);
        if ($b->statut !== 'valide') {
            abort(422, "Validez le bulletin avant d'enregistrer le paiement.");
        }
        $d = $request->validate([
            'date_paiement' => 'required|date',
            'mode_paiement' => 'required|string|max:20',
            'reference_paiement' => 'nullable|string|max:60',
        ]);
        $b->update($d + ['statut' => 'paye']);
        return $b->fresh('salarie');
    }

    /** Revient d'une étape : payé -> validé -> brouillon */
    public function rouvrir(Request $request, $id)
    {
        $this->exigerGerant($request);
        $b = PaieBulletin::findOrFail($id);
        if ($b->statut === 'paye') {
            $b->update(['statut' => 'valide', 'date_paiement' => null, 'mode_paiement' => null, 'reference_paiement' => null]);
        } elseif ($b->statut === 'valide') {
            $b->update(['statut' => 'brouillon', 'valide_par' => null, 'valide_le' => null]);
        }
        return $b->fresh('salarie');
    }

    public function supprimerBulletin(Request $request, $id)
    {
        $this->exigerGerant($request);
        $b = PaieBulletin::findOrFail($id);
        if ($b->statut !== 'brouillon') {
            abort(422, 'Seul un bulletin en brouillon peut être supprimé.');
        }
        $b->delete();
        return 1;
    }

    // ---------------------------------------------------------------- Livre de paie et déclarations

    /** Livre de paie du mois et totaux par organisme (IPRES, CSS, IPM, impôts) */
    public function livre(Request $request)
    {
        $this->exigerGerant($request);
        $mois = $this->mois($request->input('mois'));
        $bulletins = PaieBulletin::where('mois', $mois)->orderBy('id')->get();
        $organismes = [];
        $ajouter = function ($code, $libelle, $sal, $pat, $base) use (&$organismes) {
            if (!isset($organismes[$code])) {
                $organismes[$code] = ['code' => $code, 'libelle' => $libelle, 'base' => 0, 'salarial' => 0, 'patronal' => 0];
            }
            $organismes[$code]['base'] += $base;
            $organismes[$code]['salarial'] += $sal;
            $organismes[$code]['patronal'] += $pat;
        };
        $lignes = [];
        foreach ($bulletins as $b) {
            $l = (array) $b->lignes;
            $sal = [];
            foreach ((array) ($l['retenues'] ?? []) as $r) {
                $sal[$r['code']] = ($sal[$r['code']] ?? 0) + $r['montant'];
            }
            $pat = [];
            $bases = [];
            foreach ((array) ($l['patronal'] ?? []) as $r) {
                $pat[$r['code']] = ($pat[$r['code']] ?? 0) + $r['montant'];
                $bases[$r['code']] = $r['base'];
            }
            $ajouter('ipres_rg', 'IPRES régime général', $sal['ipres_rg'] ?? 0, $pat['ipres_rg'] ?? 0, $bases['ipres_rg'] ?? 0);
            if (isset($pat['ipres_rcc'])) {
                $ajouter('ipres_rcc', 'IPRES régime cadres', $sal['ipres_rcc'] ?? 0, $pat['ipres_rcc'], $bases['ipres_rcc'] ?? 0);
            }
            $ajouter('css_pf', 'CSS prestations familiales', 0, $pat['css_pf'] ?? 0, $bases['css_pf'] ?? 0);
            $ajouter('css_at', 'CSS accidents du travail', 0, $pat['css_at'] ?? 0, $bases['css_at'] ?? 0);
            if (isset($pat['ipm'])) {
                $ajouter('ipm', 'IPM', $sal['ipm'] ?? 0, $pat['ipm'], $bases['ipm'] ?? 0);
            }
            $ajouter('ir', 'Impôt sur le revenu', $sal['ir'] ?? 0, 0, $b->brut_imposable);
            $ajouter('trimf', 'TRIMF', $sal['trimf'] ?? 0, 0, $b->brut_imposable);
            $ajouter('cfce', 'CFCE', 0, $pat['cfce'] ?? 0, $bases['cfce'] ?? 0);
            $lignes[] = [
                'id' => $b->id,
                'employe' => $b->employe,
                'statut' => $b->statut,
                'brut' => $b->brut,
                'brut_imposable' => $b->brut_imposable,
                'ipres' => ($sal['ipres_rg'] ?? 0) + ($sal['ipres_rcc'] ?? 0),
                'ipm' => $sal['ipm'] ?? 0,
                'ir' => $b->ir,
                'trimf' => $b->trimf,
                'autres' => $sal['autre'] ?? 0,
                'net' => $b->net,
                'charges_patronales' => $b->charges_patronales,
                'cout_total' => $b->cout_total,
            ];
        }
        return [
            'mois' => $mois,
            'lignes' => $lignes,
            'organismes' => array_values($organismes),
            'non_valides' => $bulletins->where('statut', 'brouillon')->count(),
        ];
    }
}
