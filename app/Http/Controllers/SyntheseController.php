<?php

namespace App\Http\Controllers;

use App\Models\Stock;
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
}
