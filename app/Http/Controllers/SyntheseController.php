<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Compteur;
use Carbon\Carbon;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Synthese;
use App\Models\Reception;
use App\Models\Reservoir;
use App\Models\RemiseCuve;
use App\Models\Encaissement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SyntheseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return Synthese::firstOrCreate(['date' => $request->date]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Synthese  $synthese
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $synthese = Synthese::find($id);
        $synthese->load(['receptions', 'commande_cars', 'remise_cuves', 'stocks', 'approbateur:id,name']);
        $caisses = Caisse::with(['pompe.pistolets', 'pompe', 'venteTpes', 'depenses', 'bonClients'])->where('date_caisse', $synthese->date)->get();
        //return $caisses;
        $reservoirs = Reservoir::all();

        /* 
        $synthese->encaissements = Encaissement::with('client')->where('synthese_id', $synthese->id)->get()
            ->groupBy(function ($query) {
                return $query->client-> ;
            }); */

        $clients = Client::whereHas('encaissements', function ($q) use ($synthese) {
            $q->where('synthese_id', $synthese->id);
        })->get();

        $data = [];
        foreach ($clients as $client) {

            $montbon = 0;
            if ($client->bonClients) {
                foreach ($client->bonClients as $bon) {
                    if ($bon->encaissement) {
                        if ($bon->encaissement->synthese_id == $synthese->id) {
                            $montbon += $bon->montant;
                        }
                    }
                }
            }
            // $client->bonclients_count = $nbon;
            $client->bonclients_montant = $montbon;
            $data[] = $client;
        }
        $synthese->encaissements = $data;
        //$reservoirs_stock=[];
        foreach ($reservoirs as $key => $reservoir) {
            $reservoirs[$key]['stock'] = Stock::where('synthese_id', $id)
                ->where('reservoir_id', $reservoir->id)->first();

            $reservoirs[$key]['reception'] = Reception::where('synthese_id', $id)
                ->where('reservoir_id', $reservoir->id)->first();

            $reservoirs[$key]['remise_cuve'] = RemiseCuve::where('synthese_id', $id)
                ->where('reservoir_id', $reservoir->id)->first();
        }

        $pompes = [];
        foreach ($caisses as  $caisse) {
            $pompes[] = $caisse['pompe'];
        }
        foreach ($caisses as  $caisse) {
            foreach ($caisse['venteTpes'] as  $ventes) {
                foreach ($pompes as $key => $pompe) {
                    if ($pompe->id == $caisse->pompe_id) {
                        $pompe['mventeTpes'] += $ventes->montant;
                    }
                }
            }
        }
        foreach ($caisses as  $caisse) {
            foreach ($caisse['depenses'] as  $ventes) {
                foreach ($pompes as $key => $pompe) {
                    if ($pompe->id == $caisse->pompe_id) {
                        $pompe['mdepenses'] += $ventes->montant;
                    }
                }
            }
        }
        foreach ($caisses as  $caisse) {
            foreach ($caisse['bonClients'] as  $ventes) {
                foreach ($pompes as $key => $pompe) {
                    if ($pompe->id == $caisse->pompe_id) {
                        $pompe['mbonClients'] += $ventes->montant;
                    }
                }
            }
        }
        return [
            'synthese' => $synthese,
            'pompes' => $pompes,
            'reservoirs' => $reservoirs


        ];
        /*$synthese->load(['receptions', 'commande_cars', 'remise_cuves', 'stocks', 'encaissements']);
        $caisses = Caisse::with(['pompe.pistolets','pompe', 'venteTpes', 'depenses', 'bonClients'])->where('date_caisse', $synthese->date)->get();

        $index = [];
        $clients = [];
        $venteTpes = [];
        $depences = [];
      
        foreach ($caisses as $key => $caisse) {

            foreach ($caisse['pompe']->pistolets as  $piostolet) {
                $index[] = $piostolet;
            }
            foreach ($caisse['bonClients'] as  $value) {
                $clients[] = $value;
            }
            foreach ($caisse['venteTpes'] as $key => $value) {
               
                $venteTpes['pompe'] += $value->montant;
            }
           
           
            foreach ($caisse['depenses'] as  $value) {
                $depences[] = $value;
            }
        }
        return [
            'synthese' => $synthese,
            'index' => $index,
            'venteTpes' => $venteTpes,
            'bonClients' => $clients,
            'depences' => $depences,
        ];*/
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Synthese  $synthese
     * @return \Illuminate\Http\Response
     */
    public function edit(Synthese $synthese)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Synthese  $synthese
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Synthese $synthese)
    {
        //
        if (!Gate::allows('chefpiste')) {
            abort(403);
        }
        if ($synthese->etat) {
            abort(403);
        }
        $synthese->update($request->all());
        return 1;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Synthese  $synthese
     * @return \Illuminate\Http\Response
     */
    public function destroy(Synthese $synthese)
    {
        $synthese->delete();
        return 1;
    }


    public function approuver($id)
    {
        if (!Gate::allows('admin-gerant')) {
            abort(403);
        }
        $synthese = Synthese::find($id);
        $etat = !$synthese->etat;
        // Signature : qui a approuvé la journée et quand
        $synthese->update([
            'etat' => $etat,
            'approuve_par' => $etat ? optional(auth()->user())->id : null,
            'approuve_le' => $etat ? now() : null,
        ]);
        return 1;
    }

    /**
     * Controle des stocks carburant d'une journee, par produit :
     * stock theorique = stock 07h00 + receptions + remises en cuve - sorties aux index (electroniques)
     * stock reel = stock 07h00 saisi le lendemain ; tolerance = 5 pour 1000 des sorties (contrat Vivo)
     */
    public function controleStocks($date)
    {
        $lendemain = Carbon::parse($date)->addDay()->toDateString();
        $synthese = Synthese::where('date', $date)->first();
        $suivante = Synthese::where('date', $lendemain)->first();
        $produit = function ($carburant) {
            return stripos((string) $carburant, 'super') !== false ? 'super' : 'gasoil';
        };

        $lignes = [];
        foreach (['super', 'gasoil'] as $p) {
            $lignes[$p] = [
                'produit' => $p, 'capacite' => 0, 'ouverture' => null, 'receptions' => 0, 'remises' => 0,
                'sorties' => 0, 'theorique' => null, 'reel' => null, 'ecart' => null, 'tolerance' => null, 'cuves' => 0,
            ];
        }
        $reservoirs = Reservoir::all();
        $cuveProduit = [];
        foreach ($reservoirs as $r) {
            $p = $produit($r->carburant);
            $cuveProduit[$r->id] = $p;
            $lignes[$p]['capacite'] += (float) $r->capacite;
            $lignes[$p]['cuves']++;
        }

        $somme = function ($model, $syntheseId) use ($cuveProduit) {
            $res = ['super' => null, 'gasoil' => null];
            if (!$syntheseId) {
                return $res;
            }
            foreach ($model::where('synthese_id', $syntheseId)->get() as $l) {
                $p = $cuveProduit[$l->reservoir_id] ?? null;
                if ($p) {
                    $res[$p] = ($res[$p] ?? 0) + (float) $l->capacite;
                }
            }
            return $res;
        };
        $ouv = $somme(Stock::class, optional($synthese)->id);
        $rec = $somme(Reception::class, optional($synthese)->id);
        $rem = $somme(RemiseCuve::class, optional($synthese)->id);
        $fer = $somme(Stock::class, optional($suivante)->id);

        $caisses = Caisse::where('date_caisse', $date)->pluck('id');
        $nbCaisses = $caisses->count();
        foreach (Compteur::with('pistolet')->whereIn('caisse_id', $caisses)->get() as $c) {
            $sortie = (float) $c->indexFerE - (float) $c->indexOuvE;
            if ($c->indexFerE !== null && $sortie > 0 && $c->pistolet) {
                $lignes[$produit($c->pistolet->carburant)]['sorties'] += $sortie;
            }
        }

        foreach ($lignes as $p => &$l) {
            $l['ouverture'] = $ouv[$p];
            $l['receptions'] = (float) ($rec[$p] ?? 0);
            $l['remises'] = (float) ($rem[$p] ?? 0);
            $l['sorties'] = round($l['sorties'], 2);
            $l['reel'] = $fer[$p];
            if ($l['ouverture'] !== null) {
                $l['theorique'] = round($l['ouverture'] + $l['receptions'] + $l['remises'] - $l['sorties'], 2);
            }
            if ($l['theorique'] !== null && $l['reel'] !== null) {
                $l['ecart'] = round($l['reel'] - $l['theorique'], 2);
            }
            $l['tolerance'] = round($l['sorties'] * 0.005, 2);
        }
        unset($l);

        return [
            'date' => $date,
            'lendemain' => $lendemain,
            'caisses' => $nbCaisses,
            'stock_saisi' => $ouv['super'] !== null || $ouv['gasoil'] !== null,
            'stock_lendemain_saisi' => $fer['super'] !== null || $fer['gasoil'] !== null,
            'produits' => array_values($lignes),
        ];
    }
}
