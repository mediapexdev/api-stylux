<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\FinCommande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Module Finance : commandes de carburants et lubrifiants (commande, livraison, facture, paiement),
// plafond bancaire, échéances et solde des avoirs TPE. Remplace le "Tableau de trésorerie" Excel.
class FinanceController extends Controller
{
    const CODES = ['AB', 'ZL', 'DR', 'ZH', 'DG', 'DZ'];

    // Article 19 du contrat 2026 : commissions sur les paiements électroniques de carburant
    const COMMISSIONS = ['wave' => 0.0015, 'orange_money' => 0.00175, 'carte' => 0];
    const CARTE_SHELL_PAR_LITRE = 2;
    const PRIX_POMPE_MOYEN = 700; // pour estimer les litres payés par carte Shell

    private function exigerGerant(Request $request)
    {
        if (!in_array((int) optional($request->user())->role_id, [2, 4], true)) {
            abort(403, "Réservé au gérant et à l'admin.");
        }
    }

    private function params()
    {
        $p = DB::table('fin_parametres')->pluck('valeur', 'cle')->toArray();
        foreach (['plafond', 'prix_super', 'prix_gasoil', 'delai_livraison', 'delai_carburant', 'delai_lubrifiant', 'tpe_solde_initial'] as $k) {
            $p[$k] = isset($p[$k]) ? (float) $p[$k] : 0;
        }
        return $p;
    }

    private function delai(array $p, $type)
    {
        return (int) ($type === 'lubrifiant' ? ($p['delai_lubrifiant'] ?: 30) : ($p['delai_carburant'] ?: 7));
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
        $data = $request->validate([
            'plafond' => 'required|numeric|min:0',
            'prix_super' => 'required|numeric|min:0',
            'prix_gasoil' => 'required|numeric|min:0',
            'delai_livraison' => 'required|integer|min:0|max:30',
            'delai_carburant' => 'required|integer|min:0|max:90',
            'delai_lubrifiant' => 'required|integer|min:0|max:120',
            'tpe_depuis' => 'required|date',
            'tpe_solde_initial' => 'required|numeric',
        ]);
        $now = now();
        foreach ($data as $cle => $valeur) {
            DB::table('fin_parametres')->updateOrInsert(['cle' => $cle], ['valeur' => (string) $valeur, 'updated_at' => $now]);
        }
        return $this->params();
    }

    // ---------------------------------------------------------------- Calculs

    /** Avoirs TPE : paiements électroniques des caisses depuis la date de départ, moins les commissions et les avoirs déjà utilisés (ZH) */
    private function tpe(array $p)
    {
        $depuis = $p['tpe_depuis'] ?? date('Y-m-d');
        $avoirs = 0;
        $commissions = 0;
        if (Schema::hasTable('vente_tpes')) {
            $avecMode = Schema::hasColumn('vente_tpes', 'mode');
            $q = DB::table('vente_tpes')
                ->join('caisses', 'caisses.id', '=', 'vente_tpes.caisse_id')
                ->whereNull('vente_tpes.deleted_at')
                ->whereNull('caisses.deleted_at')
                ->where('caisses.date_caisse', '>=', $depuis)
                ->select($avecMode ? DB::raw("COALESCE(vente_tpes.mode, 'carte') as mode") : DB::raw("'carte' as mode"), DB::raw('SUM(vente_tpes.montant) as total'))
                ->groupBy('mode');
            foreach ($q->get() as $r) {
                $m = (float) $r->total;
                $avoirs += $m;
                if ($r->mode === 'carte_shell') {
                    $commissions += $m / self::PRIX_POMPE_MOYEN * self::CARTE_SHELL_PAR_LITRE;
                } else {
                    $commissions += $m * (self::COMMISSIONS[$r->mode] ?? 0);
                }
            }
        }
        $utilises = 0;
        FinCommande::where('statut', 'payee')->where('date_paiement', '>=', $depuis)->whereNull('source')->get(['ajustements'])
            ->each(function ($c) use (&$utilises) {
                $utilises += -(float) (($c->ajustements ?? [])['ZH'] ?? 0); // ZH est saisi en négatif (déduction)
            });
        $initial = (float) $p['tpe_solde_initial'];
        return [
            'depuis' => $depuis,
            'initial' => $initial,
            'encaisse' => round($avoirs),
            'commissions' => round($commissions),
            'utilises' => round($utilises),
            'disponible' => round($initial + $avoirs - $commissions - $utilises),
        ];
    }

    private function exposition()
    {
        $ouvertes = FinCommande::whereIn('statut', ['programmee', 'livree'])->get(['statut', 'montant']);
        return [
            'livree' => round($ouvertes->where('statut', 'livree')->sum('montant')),
            'programmee' => round($ouvertes->where('statut', 'programmee')->sum('montant')),
            'total' => round($ouvertes->sum('montant')),
        ];
    }

    // ---------------------------------------------------------------- Tableau de bord

    public function tableau(Request $request)
    {
        $this->exigerGerant($request);
        $p = $this->params();
        $exp = $this->exposition();
        $auj = Carbon::today();

        $aPayer = FinCommande::where('statut', 'livree')->orderBy('echeance')->orderBy('id')->get();
        $programmees = FinCommande::where('statut', 'programmee')->orderBy('date_livraison')->orderBy('id')->get();

        $debutMois = $auj->copy()->startOfMonth()->toDateString();
        $mois = FinCommande::where('date_commande', '>=', $debutMois)->get();

        return [
            'parametres' => $p,
            'plafond' => $p['plafond'],
            'exposition' => $exp,
            'disponible' => round($p['plafond'] - $exp['total']),
            'a_payer' => $aPayer,
            'programmees' => $programmees,
            'en_retard' => $aPayer->filter(function ($c) use ($auj) {
                return $c->echeance && $c->echeance < $auj->toDateString();
            })->count(),
            'a_payer_7j' => round($aPayer->filter(function ($c) use ($auj) {
                return $c->echeance && $c->echeance <= $auj->copy()->addDays(7)->toDateString();
            })->sum('montant')),
            'tpe' => $this->tpe($p),
            'mois' => [
                'debut' => $debutMois,
                'commandes' => $mois->count(),
                'super_l' => $mois->sum('super_l'),
                'gasoil_l' => $mois->sum('gasoil_l'),
                'montant' => round($mois->sum('montant')),
                'paye' => round(FinCommande::where('statut', 'payee')->where('date_paiement', '>=', $debutMois)->sum('montant_paye')),
            ],
        ];
    }

    // ---------------------------------------------------------------- Commandes

    public function commandes(Request $request)
    {
        $this->exigerGerant($request);
        $q = FinCommande::with('user:id,name')->orderByDesc('date_commande')->orderByDesc('id');
        if ($request->filled('statut')) {
            $q->whereIn('statut', explode(',', $request->query('statut')));
        }
        if ($request->filled('type')) {
            $q->where('type', $request->query('type'));
        }
        if ($request->filled('du')) {
            $q->where('date_commande', '>=', $request->query('du'));
        }
        if ($request->filled('au')) {
            $q->where('date_commande', '<=', $request->query('au'));
        }
        if ($request->filled('q')) {
            $s = '%' . $request->query('q') . '%';
            $q->where(function ($w) use ($s) {
                $w->where('numero', 'like', $s)->orWhere('numero_cheque', 'like', $s)->orWhere('numero_bl', 'like', $s)->orWhere('numero_facture', 'like', $s);
            });
        }
        return $q->limit(1000)->get();
    }

    private function valider(Request $request)
    {
        return $request->validate([
            'type' => 'required|in:carburant,lubrifiant',
            'numero' => 'nullable|string|max:40',
            'date_commande' => 'required|date',
            'super_l' => 'nullable|numeric|min:0',
            'gasoil_l' => 'nullable|numeric|min:0',
            'prix_super' => 'nullable|numeric|min:0',
            'prix_gasoil' => 'nullable|numeric|min:0',
            'montant' => 'nullable|numeric|min:0',
            'date_livraison' => 'nullable|date',
            'echeance' => 'nullable|date',
            'commentaire' => 'nullable|string|max:1000',
        ]);
    }

    /** Valeur brute : litres x prix gérant pour le carburant, montant saisi pour les lubrifiants */
    private function completer(array $d, array $p)
    {
        if ($d['type'] === 'carburant') {
            $d['super_l'] = (float) ($d['super_l'] ?? 0);
            $d['gasoil_l'] = (float) ($d['gasoil_l'] ?? 0);
            $d['prix_super'] = isset($d['prix_super']) ? (float) $d['prix_super'] : $p['prix_super'];
            $d['prix_gasoil'] = isset($d['prix_gasoil']) ? (float) $d['prix_gasoil'] : $p['prix_gasoil'];
            $d['montant'] = round($d['super_l'] * $d['prix_super'] + $d['gasoil_l'] * $d['prix_gasoil'], 2);
            if ($d['montant'] <= 0) {
                abort(422, 'Indiquez les litres de Super et de Gasoil.');
            }
        } else {
            $d['super_l'] = 0;
            $d['gasoil_l'] = 0;
            $d['prix_super'] = null;
            $d['prix_gasoil'] = null;
            if (empty($d['montant'])) {
                abort(422, 'Indiquez le montant de la commande de lubrifiants.');
            }
        }
        if (empty($d['date_livraison'])) {
            $d['date_livraison'] = Carbon::parse($d['date_commande'])->addDays((int) $p['delai_livraison'])->toDateString();
        }
        if (empty($d['echeance'])) {
            $d['echeance'] = Carbon::parse($d['date_livraison'])->addDays($this->delai($p, $d['type']))->toDateString();
        }
        return $d;
    }

    public function enregistrer(Request $request, $id = null)
    {
        $this->exigerGerant($request);
        $p = $this->params();
        $d = $this->completer($this->valider($request), $p);
        if ($id) {
            $c = FinCommande::findOrFail($id);
            if ($c->statut === 'payee') {
                abort(422, 'Commande déjà payée : rouvrez-la avant de la modifier.');
            }
            $c->update($d);
        } else {
            $c = FinCommande::create($d + ['statut' => 'programmee', 'user_id' => optional($request->user())->id]);
        }
        return $c->fresh('user:id,name');
    }

    public function supprimer(Request $request, $id)
    {
        $this->exigerGerant($request);
        $c = FinCommande::findOrFail($id);
        if ($c->statut !== 'programmee') {
            abort(422, 'Seule une commande non livrée peut être supprimée.');
        }
        $c->delete();
        return ['ok' => true];
    }

    public function livrer(Request $request, $id)
    {
        $this->exigerGerant($request);
        $c = FinCommande::findOrFail($id);
        $d = $request->validate([
            'date_livraison' => 'required|date',
            'numero_bl' => 'nullable|string|max:40',
            'numero_facture' => 'nullable|string|max:40',
            'echeance' => 'nullable|date',
            'montant' => 'nullable|numeric|min:0',
        ]);
        if (empty($d['echeance'])) {
            $d['echeance'] = Carbon::parse($d['date_livraison'])->addDays($this->delai($this->params(), $c->type))->toDateString();
        }
        if (!isset($d['montant']) || $d['montant'] === null || $d['montant'] === '') {
            unset($d['montant']);
        }
        $c->update($d + ['statut' => 'livree']);
        return $c->fresh('user:id,name');
    }

    public function payer(Request $request, $id)
    {
        $this->exigerGerant($request);
        $c = FinCommande::findOrFail($id);
        $d = $request->validate([
            'date_paiement' => 'required|date',
            'mode_paiement' => 'required|in:cheque,especes,virement',
            'banque' => 'nullable|string|max:20',
            'numero_cheque' => 'nullable|string|max:40',
            'ajustements' => 'nullable|array',
            'commentaire' => 'nullable|string|max:1000',
        ]);
        $aj = [];
        foreach ((array) ($d['ajustements'] ?? []) as $code => $v) {
            $code = strtoupper(trim($code));
            if (in_array($code, self::CODES, true) && is_numeric($v) && (float) $v != 0) {
                $aj[$code] = round((float) $v, 2);
            }
        }
        $d['ajustements'] = $aj ?: null;
        $d['montant_paye'] = round($c->montant + array_sum($aj), 2);
        if ($c->statut === 'programmee' && !$c->date_livraison) {
            $d['date_livraison'] = $d['date_paiement'];
        }
        $c->update($d + ['statut' => 'payee']);
        return $c->fresh('user:id,name');
    }

    /** Retour à l'étape précédente (erreur de saisie) */
    public function rouvrir(Request $request, $id)
    {
        $this->exigerGerant($request);
        $c = FinCommande::findOrFail($id);
        if ($c->statut === 'payee') {
            $c->update(['statut' => 'livree', 'date_paiement' => null, 'mode_paiement' => null, 'banque' => null, 'numero_cheque' => null, 'ajustements' => null, 'montant_paye' => null]);
        } elseif ($c->statut === 'livree') {
            $c->update(['statut' => 'programmee', 'numero_bl' => null, 'numero_facture' => null]);
        }
        return $c->fresh('user:id,name');
    }
}
