<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\LubProduit;
use App\Models\LubMouvement;
use App\Models\LubInventaire;
use App\Models\LubInventaireLigne;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

// Module lubrifiants : stock en temps réel (somme des mouvements), réceptions, réassorts,
// ventes, inventaires de contrôle et rapport mensuel (stock valorisé, marges).
class LubStockController extends Controller
{
    const EMPLACEMENTS = ['magasin', 'presentoir'];

    private function role(Request $request)
    {
        return (int) optional($request->user())->role_id; // 1 pompiste, 2 gérant, 3 chef de piste, 4 admin
    }

    private function exigerGestion(Request $request)
    {
        if (!in_array($this->role($request), [2, 3, 4], true)) {
            abort(403, "Réservé au chef de piste, au gérant et à l'admin.");
        }
    }

    private function exigerGerant(Request $request)
    {
        if (!in_array($this->role($request), [2, 4], true)) {
            abort(403, "Réservé au gérant et à l'admin.");
        }
    }

    /** Stock par produit et emplacement à une date (incluse) : [produit_id => [magasin => x, presentoir => y]] */
    private function stocks($date = null, $avantLe = false)
    {
        $q = LubMouvement::select('produit_id', 'emplacement', DB::raw('SUM(quantite) as q'))->groupBy('produit_id', 'emplacement');
        if ($date) {
            $q->where('date', $avantLe ? '<' : '<=', $date);
        }
        $out = [];
        foreach ($q->get() as $r) {
            $out[$r->produit_id][$r->emplacement] = round((float) $r->q, 3);
        }
        return $out;
    }

    // ---------------------------------------------------------------- Catalogue

    public function produits()
    {
        return LubProduit::orderBy('ordre')->orderBy('designation')->get();
    }

    public function enregistrerProduit(Request $request, $id = null)
    {
        $this->exigerGerant($request);
        $data = $request->validate([
            'code' => 'nullable|string|max:30',
            'designation' => 'required|string|max:255',
            'famille' => 'nullable|string|max:40',
            'unite' => 'required|in:bidon,litre,kg,seau,fut',
            'contenance' => 'required|numeric|min:0',
            'colis_qte' => 'required|integer|min:1',
            'colis' => 'required|string|max:20',
            'prix_vente' => 'required|numeric|min:0',
            'prix_achat' => 'required|numeric|min:0',
            'seuil' => 'nullable|numeric|min:0',
            'actif' => 'boolean',
            'ordre' => 'nullable|integer',
        ]);
        if ($id) {
            $p = LubProduit::findOrFail($id);
            $p->update($data);
            return $p;
        }
        $data['ordre'] = $data['ordre'] ?? ((int) LubProduit::max('ordre') + 1);
        return response()->json(LubProduit::create($data), 201);
    }

    // ---------------------------------------------------------------- Stock en temps réel

    public function stock(Request $request)
    {
        $stocks = $this->stocks();
        $debutMois = Carbon::now()->startOfMonth()->toDateString();
        $ventesMois = LubMouvement::where('type', 'vente')->where('date', '>=', $debutMois)
            ->select('produit_id', DB::raw('-SUM(quantite) as q'), DB::raw('-SUM(quantite * prix_unitaire) as ca'))
            ->groupBy('produit_id')->get()->keyBy('produit_id');
        $dernierInventaire = LubInventaire::where('statut', 'valide')->orderByDesc('date')->orderByDesc('id')->first(['id', 'numero', 'date', 'emplacement']);

        $produits = LubProduit::orderBy('ordre')->get()->map(function ($p) use ($stocks, $ventesMois) {
            $m = $stocks[$p->id]['magasin'] ?? 0;
            $pr = $stocks[$p->id]['presentoir'] ?? 0;
            $v = $ventesMois[$p->id] ?? null;
            return $p->toArray() + [
                'stock_magasin' => $m,
                'stock_presentoir' => $pr,
                'stock_total' => round($m + $pr, 3),
                'ventes_mois' => $v ? round((float) $v->q, 3) : 0,
                'ca_mois' => $v ? round((float) $v->ca, 2) : 0,
            ];
        });
        return ['produits' => $produits, 'dernier_inventaire' => $dernierInventaire, 'date' => Carbon::now()->toDateString()];
    }

    // ---------------------------------------------------------------- Mouvements

    public function mouvements(Request $request)
    {
        $q = LubMouvement::with('produit:id,designation,unite', 'user:id,name', 'pompiste:id,name')->orderByDesc('date')->orderByDesc('id');
        if ($request->filled('du')) $q->where('date', '>=', $request->du);
        if ($request->filled('au')) $q->where('date', '<=', $request->au);
        if ($request->filled('type')) $q->where('type', $request->type);
        if ($request->filled('produit_id')) $q->where('produit_id', $request->produit_id);
        return $q->limit(2000)->get();
    }

    /**
     * Enregistre une opération (plusieurs lignes) :
     *  reception  : + magasin (prix d'achat)
     *  reassort   : - magasin, + présentoir
     *  vente      : - présentoir (prix de vente), poste et pompiste facultatifs
     *  ajustement : +/- sur l'emplacement choisi (casse, retour, correction), commentaire obligatoire
     */
    public function enregistrerMouvement(Request $request)
    {
        $this->exigerGestion($request);
        $data = $request->validate([
            'type' => 'required|in:reception,reassort,vente,ajustement',
            'date' => 'required|date',
            'reference' => 'nullable|string|max:60',
            'poste' => 'nullable|in:matin,soir',
            'pompiste_id' => 'nullable|integer',
            'commentaire' => 'nullable|string|max:255',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|integer|exists:lub_produits,id',
            'lignes.*.quantite' => 'required|numeric',
            'lignes.*.emplacement' => 'nullable|in:magasin,presentoir',
            'lignes.*.prix_unitaire' => 'nullable|numeric|min:0',
        ]);
        if ($data['type'] === 'ajustement' && !trim((string) ($data['commentaire'] ?? ''))) {
            return response()->json(['message' => "Indiquez le motif de l'ajustement (casse, retour, erreur...)."], 422);
        }

        $produits = LubProduit::whereIn('id', array_column($data['lignes'], 'produit_id'))->get()->keyBy('id');
        $lot = strtoupper(substr($data['type'], 0, 3)) . '-' . Carbon::now()->format('ymdHis') . '-' . Str::random(4);
        $base = [
            'lot' => $lot, 'date' => $data['date'], 'type' => $data['type'],
            'reference' => $data['reference'] ?? null, 'poste' => $data['poste'] ?? null,
            'pompiste_id' => $data['pompiste_id'] ?? null, 'commentaire' => $data['commentaire'] ?? null,
            'user_id' => optional($request->user())->id,
        ];

        DB::transaction(function () use ($data, $produits, $base) {
            foreach ($data['lignes'] as $l) {
                $q = (float) $l['quantite'];
                if ($q == 0) continue;
                $p = $produits[$l['produit_id']];
                switch ($data['type']) {
                    case 'reception':
                        LubMouvement::create($base + ['produit_id' => $p->id, 'emplacement' => 'magasin', 'quantite' => abs($q), 'prix_unitaire' => $l['prix_unitaire'] ?? $p->prix_achat]);
                        break;
                    case 'reassort':
                        LubMouvement::create($base + ['produit_id' => $p->id, 'emplacement' => 'magasin', 'quantite' => -abs($q), 'prix_unitaire' => $p->prix_achat]);
                        LubMouvement::create($base + ['produit_id' => $p->id, 'emplacement' => 'presentoir', 'quantite' => abs($q), 'prix_unitaire' => $p->prix_achat]);
                        break;
                    case 'vente':
                        LubMouvement::create($base + ['produit_id' => $p->id, 'emplacement' => $l['emplacement'] ?? 'presentoir', 'quantite' => -abs($q), 'prix_unitaire' => $l['prix_unitaire'] ?? $p->prix_vente]);
                        break;
                    case 'ajustement':
                        LubMouvement::create($base + ['produit_id' => $p->id, 'emplacement' => $l['emplacement'] ?? 'magasin', 'quantite' => $q, 'prix_unitaire' => $p->prix_achat]);
                        break;
                }
            }
        });

        return response()->json(['lot' => $lot, 'mouvements' => LubMouvement::where('lot', $lot)->count()], 201);
    }

    /** Annule une opération entière (gérant / admin). Les écarts d'inventaire ne s'annulent pas ici. */
    public function annulerLot(Request $request, $lot)
    {
        $this->exigerGerant($request);
        $n = LubMouvement::where('lot', $lot)->where('type', '!=', 'inventaire')->delete();
        if (!$n) {
            return response()->json(['message' => 'Opération introuvable ou non annulable.'], 422);
        }
        return ['supprimes' => $n];
    }

    // ---------------------------------------------------------------- Inventaires (contrôle)

    public function inventaires()
    {
        return LubInventaire::with('user:id,name', 'validateur:id,name')
            ->withCount('lignes')
            ->orderByDesc('date')->orderByDesc('id')->limit(200)->get();
    }

    public function ouvrirInventaire(Request $request)
    {
        $this->exigerGestion($request);
        $data = $request->validate([
            'date' => 'required|date',
            'emplacement' => 'required|in:magasin,presentoir,tous',
            'commentaire' => 'nullable|string|max:255',
        ]);
        $inv = DB::transaction(function () use ($data, $request) {
            $n = (int) LubInventaire::whereYear('date', substr($data['date'], 0, 4))->lockForUpdate()->count() + 1;
            $inv = LubInventaire::create($data + [
                'numero' => sprintf('INV-%s-%03d', substr($data['date'], 0, 4), $n),
                'statut' => 'brouillon',
                'user_id' => optional($request->user())->id,
            ]);
            $stocks = $this->stocks($data['date']);
            $emps = $data['emplacement'] === 'tous' ? self::EMPLACEMENTS : [$data['emplacement']];
            foreach (LubProduit::where('actif', true)->orderBy('ordre')->get() as $p) {
                foreach ($emps as $e) {
                    LubInventaireLigne::create([
                        'inventaire_id' => $inv->id, 'produit_id' => $p->id, 'emplacement' => $e,
                        'theorique' => $stocks[$p->id][$e] ?? 0, 'compte' => null, 'prix_achat' => $p->prix_achat,
                    ]);
                }
            }
            return $inv;
        });
        return response()->json($this->chargerInventaire($inv->id), 201);
    }

    private function chargerInventaire($id)
    {
        $inv = LubInventaire::with('user:id,name', 'validateur:id,name', 'lignes')->findOrFail($id);
        if ($inv->statut === 'brouillon') {
            // Le théorique suit les mouvements tant que l'inventaire n'est pas validé
            $stocks = $this->stocks($inv->date);
            foreach ($inv->lignes as $l) {
                $l->theorique = $stocks[$l->produit_id][$l->emplacement] ?? 0;
                $l->ecart = $l->compte === null ? null : round($l->compte - $l->theorique, 3);
            }
        }
        return $inv;
    }

    public function inventaire($id)
    {
        return $this->chargerInventaire($id);
    }

    public function enregistrerComptage(Request $request, $id)
    {
        $this->exigerGestion($request);
        $inv = LubInventaire::findOrFail($id);
        if ($inv->statut !== 'brouillon') {
            return response()->json(['message' => 'Cet inventaire est déjà validé.'], 422);
        }
        $data = $request->validate([
            'commentaire' => 'nullable|string|max:255',
            'lignes' => 'required|array',
            'lignes.*.id' => 'required|integer',
            'lignes.*.compte' => 'nullable|numeric|min:0',
        ]);
        foreach ($data['lignes'] as $l) {
            LubInventaireLigne::where('inventaire_id', $inv->id)->where('id', $l['id'])->update(['compte' => $l['compte']]);
        }
        if (array_key_exists('commentaire', $data)) {
            $inv->update(['commentaire' => $data['commentaire']]);
        }
        return $this->chargerInventaire($inv->id);
    }

    /** Validation : fige le théorique, calcule les écarts et passe les ajustements de stock. */
    public function validerInventaire(Request $request, $id)
    {
        $this->exigerGerant($request);
        $inv = LubInventaire::with('lignes')->findOrFail($id);
        if ($inv->statut !== 'brouillon') {
            return response()->json(['message' => 'Cet inventaire est déjà validé.'], 422);
        }
        if ($inv->lignes->whereNull('compte')->count()) {
            return response()->json(['message' => 'Tous les produits doivent être comptés avant validation (mettez 0 si le produit est absent).'], 422);
        }
        DB::transaction(function () use ($inv, $request) {
            $stocks = $this->stocks($inv->date);
            $lot = 'INV-' . $inv->id;
            foreach ($inv->lignes as $l) {
                $theo = $stocks[$l->produit_id][$l->emplacement] ?? 0;
                $ecart = round($l->compte - $theo, 3);
                $l->update(['theorique' => $theo, 'ecart' => $ecart]);
                if (abs($ecart) > 0.0005) {
                    LubMouvement::create([
                        'lot' => $lot, 'date' => $inv->date, 'type' => 'inventaire', 'produit_id' => $l->produit_id,
                        'emplacement' => $l->emplacement, 'quantite' => $ecart, 'prix_unitaire' => $l->prix_achat,
                        'reference' => $inv->numero, 'inventaire_id' => $inv->id, 'user_id' => optional($request->user())->id,
                        'commentaire' => 'Écart d\'inventaire',
                    ]);
                }
            }
            $inv->update(['statut' => 'valide', 'valide_par' => optional($request->user())->id, 'valide_le' => Carbon::now()]);
        });
        return $this->chargerInventaire($inv->id);
    }

    public function supprimerInventaire(Request $request, $id)
    {
        $this->exigerGestion($request);
        $inv = LubInventaire::findOrFail($id);
        if ($inv->statut !== 'brouillon') {
            return response()->json(['message' => 'Un inventaire validé ne peut pas être supprimé.'], 422);
        }
        LubInventaireLigne::where('inventaire_id', $inv->id)->delete();
        $inv->delete();
        return ['ok' => true];
    }

    // ---------------------------------------------------------------- Rapport mensuel

    public function rapport(Request $request)
    {
        $mois = $request->input('mois', Carbon::now()->format('Y-m'));
        $debut = Carbon::parse($mois . '-01')->startOfMonth();
        $fin = (clone $debut)->endOfMonth();
        $si = $this->stocks($debut->toDateString(), true);
        $mvts = LubMouvement::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->get();

        $lignes = [];
        foreach (LubProduit::orderBy('ordre')->get() as $p) {
            $m = $mvts->where('produit_id', $p->id);
            $siM = $si[$p->id]['magasin'] ?? 0;
            $siP = $si[$p->id]['presentoir'] ?? 0;
            $ventes = $m->where('type', 'vente');
            $qVentes = -$ventes->sum('quantite');
            $ca = -$ventes->sum(function ($x) { return $x->quantite * $x->prix_unitaire; });
            $receptions = $m->where('type', 'reception')->sum('quantite');
            $reassort = $m->where('type', 'reassort')->where('emplacement', 'presentoir')->sum('quantite');
            $ajust = $m->whereIn('type', ['ajustement'])->sum('quantite');
            $ecartsInv = $m->where('type', 'inventaire')->sum('quantite');
            $sf = $siM + $siP + $m->sum('quantite');
            $parJour = [];
            foreach ($ventes as $v) {
                $j = (int) substr($v->date, 8, 2);
                $parJour[$j] = ($parJour[$j] ?? 0) - $v->quantite;
            }
            $lignes[] = [
                'produit' => $p,
                'si' => round($siM + $siP, 3), 'si_magasin' => $siM, 'si_presentoir' => $siP,
                'receptions' => round($receptions, 3), 'reassort' => round($reassort, 3),
                'ventes' => round($qVentes, 3), 'ca' => round($ca, 2),
                'marge' => round($ca - $qVentes * $p->prix_achat, 2),
                'ajustements' => round($ajust, 3), 'ecarts_inventaire' => round($ecartsInv, 3),
                'valeur_ecarts' => round($ecartsInv * $p->prix_achat, 2),
                'sf' => round($sf, 3),
                'sf_magasin' => round($siM + $m->where('emplacement', 'magasin')->sum('quantite'), 3),
                'sf_presentoir' => round($siP + $m->where('emplacement', 'presentoir')->sum('quantite'), 3),
                'valeur_sf_achat' => round($sf * $p->prix_achat, 2),
                'valeur_sf_vente' => round($sf * $p->prix_vente, 2),
                'volume_vendu' => round($qVentes * $p->contenance, 3),
                'ventes_par_jour' => $parJour,
            ];
        }
        return ['mois' => $mois, 'jours' => (int) $fin->format('d'), 'lignes' => $lignes];
    }
}
