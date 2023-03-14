<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Pompe;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Lavage;
use App\Models\Recette;
use App\Models\Compteur;
use App\Models\Synthese;
use Illuminate\Http\Request;
use function PHPSTORM_META\map;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CaisseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Caisse::with('pompe')
            ->where('user_id', '=', auth()->user()->id)
            ->orderBy('created_at', 'DESC') 
            ->paginate(10);
        //->get();
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
        // if (!Gate::allows('pompiste')) {
        //     abort(403);
        // }
        $nombrePompe = Pompe::count();
        $nombreCaisse = Caisse::where('date_caisse', $request->date_caisse)->count();
        if ($nombrePompe == $nombreCaisse || $nombrePompe < $nombreCaisse) {
            return 0;
        }
        return Caisse::create($request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Caisse  $caisse
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $caisse = Caisse::find($id);
        //$pompi
        $pistolets = $caisse->pompe->pistolets()->get();
        $data = null;
        $compteurs = $caisse->compteurs()->get();
        foreach ($pistolets as $key => $pistolet) {
            $data[] = $pistolet;
            // $compteur=$pistolet->compteur()->get();
            if ($compteurs) {
                foreach ($compteurs as $compteur) {
                    if ($compteur->pistolet_id == $pistolet->id) {
                        $data[$key]['compteur'] = $compteur;
                    }
                }
            }
        }

        //$test=array_merge($caisse,$data);
        return [
            'caisse' => $caisse->load("user"),
            'pompiste' => $caisse->user()->first(),
            'venteTpes' => $caisse->venteTpes()->get(),
            'bonClients' => $caisse->bonClients()->get(),
            'depenses' => $caisse->depenses()->get(),
            'pistolets' => $data,


        ];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Caisse  $caisse
     * @return \Illuminate\Http\Response
     */
    public function edit(Caisse $caisse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Caisse  $caisse
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        /*   if (!Gate::allows('pompiste')) {
            abort(403);
        } */
        $caisse = Caisse::find($id);
        if ($caisse->approuve) {
            abort(403);
        }
        /*   $date_caisse = Carbon::parse($caisse->date_caisse);
        $date = Carbon::parse($request->date_caisse);
        if (!$date_caisse->eq($date)) {
            echo 'different';
            $nombrePompe = Pompe::count();
            $nombreCaisse = Caisse::where('date_caisse', $request->date_caisse)->count();
            if ($nombrePompe == $nombreCaisse) {
                echo'\n'. 0;
            }
            $caisse->update($request->all());
            echo'\modifier'. 1;
        }
        /*   $nombrePompe = Pompe::count();
            $nombreCaisse = Caisse::where('date_caisse', $request->date_caisse)->count();
            if ($nombrePompe == $nombreCaisse ) {
                return 0;
            }
             */
        $caisse->update($request->all());
        return 1;
        // $caisse->update($request->all());
        // return 1;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Caisse  $caisse
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $caisse = Caisse::find($id);
        $caisse->delete();
        return 1;
    }
    public function journee()
    {
        $caisses = Caisse::select('id', 'coffre', 'netVer', 'pompe_id', 'user_id', 'created_at', 'approuve', 'date_caisse', 'ecart')
            ->with('pompe', 'user')
            ->orderBy('date_caisse', 'DESC')
            ->get()
            ->groupBy(function ($date) {
                return $date->date_caisse;  // grouping by day
                //return Carbon::parse($date->created_at)->format('m'); // grouping by months
            });

        $data = [];
        foreach ($caisses as $key => $value) {
            $data[] = $value;
        }
        return ["journees" => $data];
    }
    public function caisse_par_date($date)
    {
        $synthese = null;
        $clients = null;
        $caisses = Caisse::select('id', 'coffre', 'netVer', 'pompe_id', 'user_id', 'created_at', 'approuve', 'date_caisse', 'ecart')
            ->with('pompe', 'user')
            ->where('date_caisse', $date)
            ->get();
        if ($caisses) {
            $synthese = Synthese::where('date', $date)->first();
            if ($synthese) {
                $clients = Client::whereHas('encaissements', function ($q) use ($synthese) {
                    $q->where('synthese_id', $synthese->id);
                })->get();


                $dataSynthese = [];
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
                    $dataSynthese[] = $client;
                }
                $synthese->encaissements = $dataSynthese;
            }
        }
        $data = [];
        foreach ($caisses as $key => $value) {
            $data[] = $value;
        }
        return ["caisses" => $data, 'synthese' => $synthese];
    }

    public function show_journee(Caisse $caisse)
    {
        return $caisse;
    }

    public function approuver_caisse($id)
    {
        if (!Gate::allows('chefpiste')) {
            abort(403);
        }
        $caisse = Caisse::find($id);
        $caisse->update(['approuve' => $caisse->approuve ? false : true]);
        return $caisse;
    }


    public function recapte()
    {
        $tTpe = 0;
        $tClient = 0;
        $tDepense = 0;
        function vente($mois)
        {

            $total = 0;
            $caisses = Caisse::select('id', 'coffre', 'netVer', 'approuve', 'date_caisse', 'ecart')
                ->whereYear('date_caisse', Carbon::now()->year)
                ->whereMonth('date_caisse', $mois)->get();
            foreach ($caisses as  $caisse) {
                $total += $caisse->coffre + $caisse->netVer + $caisse->ecart;
                foreach ($caisse->venteTpes as $ventes) {
                    $total += $ventes->montant;
                }
                foreach ($caisse->depenses as $depense) {
                    $total += $depense->montant;
                }
                foreach ($caisse->bonClients as $bonClient) {
                    $total += $bonClient->montant;
                }

            }


            return $total;
        }
        /**
         * Total des Tpes ,B clients, Depenses
         */
        $caisses = Caisse::select('id', 'coffre', 'pompe_id', 'netVer', 'approuve', 'date_caisse', 'ecart')
            ->with('pompe')
            ->whereYear('date_caisse', Carbon::now()->year)
            ->whereMonth('date_caisse',  Carbon::now()->month)->get();
        foreach ($caisses as  $caisse) {
            foreach ($caisse->venteTpes as $ventes) {
                $tTpe += $ventes->montant;
            }
            foreach ($caisse->depenses as $depense) {
                $tDepense += $depense->montant;
            }
            foreach ($caisse->bonClients as $bonClient) {
                $tClient += $bonClient->montant;
            }
        }

        /**
         * fin
         */

        $months = [];
        //dd(Carbon::now()->month(02)->format('F'));
        $now = Carbon::now();
        $now->setLocale('fr');
        $mois = Carbon::now()->month;
        for ($i = 0; $i < $mois; $i++) {
            $array = [];
            $array = [$now->create()->month(Carbon::now()->month - $i)->format('F'), vente(Carbon::now()->month - $i)];
            array_push($months, $array);
        }



        $monthslavages = [];
        $monthslubs = [];
        $monthsaccs = [];
        $monthslavs = [];
        function ventelavage($mois)
        {
            $total = 0;
            $lavages = Lavage::whereYear("date_lavage", Carbon::now()->year)->whereMonth("date_lavage", $mois)->get();
            $totalLavageMois = 0;
            foreach ($lavages as $lavage) {
                $total += $lavage->carosserie + $lavage->moteur + $lavage->graissage + $lavage->pulv + $lavage->complet;
            }


            return $total;
        }

        function ventelub($mois){
            $total=0;
            $lubs = Recette::whereYear("date_recette", Carbon::now()->year)->whereMonth("date_recette", $mois)->get();
            $totallubmois = 0;
            foreach($lubs as $lub){
                $total += $lub->totallub;
            }
            return $total;
        }
        function venteacc($mois){
            $total=0;
            $accs = Recette::whereYear("date_recette", Carbon::now()->year)->whereMonth("date_recette", $mois)->get();
            $totalaccmois = 0;
            foreach($accs as $acc){
                $total += $acc->totalacc;
            }
            return $total;
        }
        function ventelav($mois){
            $total=0;
            $lavs = Recette::whereYear("date_recette", Carbon::now()->year)->whereMonth("date_recette", $mois)->get();
            $totallavmois = 0;
            foreach($lavs as $lav){
                $total += $lav->totallav;
            }
            return $total;
        }
        /**Lavage courvbe */
        for ($i = 0; $i < $mois; $i++) {
            $array = [];
            $array = [$now->create()->month(Carbon::now()->month - $i)->format('F'), ventelavage(Carbon::now()->month - $i)];
            array_push($monthslavages, $array);
        }
        for ($i = 0; $i < $mois; $i++) {
            $array = [];
            $array = [$now->create()->month(Carbon::now()->month - $i)->format('F'), ventelub(Carbon::now()->month - $i)];
            array_push($monthslubs, $array);
        }
        for ($i = 0; $i < $mois; $i++) {
            $array = [];
            $array = [$now->create()->month(Carbon::now()->month - $i)->format('F'), venteacc(Carbon::now()->month - $i)];
            array_push($monthsaccs, $array);
        }
        for ($i = 0; $i < $mois; $i++) {
            $array = [];
            $array = [$now->create()->month(Carbon::now()->month - $i)->format('F'), ventelav(Carbon::now()->month - $i)];
            array_push($monthslavs, $array);
        }






        $start = Carbon::parse(Carbon::now())->startOfMonth();
        $end = Carbon::parse(Carbon::now())->endOfMonth();

        $dates = [];
        while ($start->lte($end)) {
            $dates[] = $start->copy();
            $start->addDay();
        }



        $day = null;
        foreach ($dates as $key => $value) {
            $jour = Carbon::parse($value)->format('d');
            $totalGaz = 0;
            $totalSup = 0;
            $caisses = Caisse::select('id', 'coffre', 'pompe_id', 'netVer', 'approuve', 'date_caisse', 'ecart')
                ->with('pompe')
                ->whereYear('date_caisse', Carbon::now()->year)
                ->whereMonth('date_caisse',  Carbon::now()->month)
                ->whereDay('date_caisse',  $jour)->get();


            foreach ($caisses as  $caisse) {
                foreach ($caisse->compteurs as $compteur) {

                    if ($compteur->pistolet->carburant == "gasoil") {
                        $sortie = $compteur->indexFerE - $compteur->indexOuvE;
                        $totalGaz +=  $sortie;
                    }
                    if ($compteur->pistolet->carburant == "super") {
                        $sortie = $compteur->indexFerE - $compteur->indexOuvE;
                        $totalSup +=  $sortie;
                    }
                }
                //if($caisse->pompe->pistolets)


            }
            //return $total;
            // $commandes=Commande::with('devi')->whereDay('created_at',Carbon::now()->subDays($i))->get();

            $day[] = ['jour' => $jour, 'gasoil' => $totalGaz, 'super' => $totalSup];
        }

        $lavages = Lavage::whereYear("date_lavage", Carbon::now()->year)->whereMonth("date_lavage", Carbon::now()->month)->get();
        $totalLavageMois = 0;
        foreach ($lavages as $lavage) {
            $totalLavageMois += $lavage->carosserie + $lavage->moteur + $lavage->graissage + $lavage->pulv + $lavage->complet;
        }

        $lubs = Recette::whereYear("date_recette", Carbon::now()->year)->whereMonth("date_recette", $mois)->get();
        $totallubmois = 0;
        foreach($lubs as $lub){
            $totallubmois += $lub->totallub;
        }
        $lavs = Recette::whereYear("date_recette", Carbon::now()->year)->whereMonth("date_recette", $mois)->get();
        $totallavmois = 0;
        foreach($lavs as $lav){
            $totallavmois += $lav->totallav;
        }
        $futs = Recette::whereYear("date_recette", Carbon::now()->year)->whereMonth("date_recette", $mois)->get();
        $totalfutmois = 0;
        foreach($futs as $fut){
            $totalfutmois += $fut->totalfut;
        }
        $accs = Recette::whereYear("date_recette", Carbon::now()->year)->whereMonth("date_recette", $mois)->get();
        $totalaccmois = 0;
        foreach($accs as $acc){
            $totalaccmois += $acc->totalacc;
        }
        $recettes = Recette::whereYear("date_recette", Carbon::now()->year)->whereMonth("date_recette", $mois)->get();
        $totalrecettemois = 0;
        foreach($recettes as $recette){
            $totalrecettemois += $recette->totalacc + $recette->totalfut + $recette->totallav +$recette->totallub;
        }

        return [
            "carburants" => array_reverse($months),
            "courbeslavages" => array_reverse($monthslavages),
            "courbeslubs" => array_reverse($monthslubs),
            "courbesaccs" => array_reverse($monthsaccs),
            "courbeslavs" => array_reverse($monthslavs),
            "courbeCarb" => $day,
            't_tpe' => $tTpe,
            't_bClient' => $tClient,
            't_depense' => $tDepense,
            't_lavage' => $totalLavageMois,
            'carburant' => vente(Carbon::now()->month),
            'lubrifiant' => 0,
            'accessoirs' => 0,
            't_lubMois' => $totallubmois,
            't_lavMois' => $totallavmois,
            't_futMois' => $totalfutmois,
            't_accMois' => $totalaccmois,
            't_recetteMois' => $totalrecettemois
        ];
    }
    /**fin recapte */
    public function rapport_par_date($date)
    {
        $synthese = null;
        $caisses = Caisse::select('id', 'coffre', 'netVer', 'pompe_id', 'user_id', 'created_at', 'approuve', 'date_caisse', 'ecart')
            ->with('pompe.pistolets', 'compteurs', 'user', 'depenses', 'venteTpes', 'bonClients')
            ->where('date_caisse', $date)
            ->get();
        if ($caisses) {
            $synthese = Synthese::where('date', $date)->first();
            if ($synthese) {
                $synthese = $synthese->load(['receptions', 'commande_cars', 'remise_cuves', 'stocks', 'encaissements']);
            }
        }
        $data = [];
        $totalSupJ = 0;
        $totalGazJ = 0;
        foreach ($caisses as $key => $caisse) {
            foreach ($caisse->pompe->pistolets as $piostolet) {
                foreach ($caisse->compteurs as $compteur) {

                    if ($compteur->pistolet->carburant == "gasoil") {
                        $sortie = $compteur->indexFerE - $compteur->indexOuvE;
                        $totalGazJ +=  $sortie;
                    }
                    if ($compteur->pistolet->carburant == "super") {
                        $sortie = $compteur->indexFerE - $compteur->indexOuvE;
                        $totalSupJ +=  $sortie;
                    }
                }
            }
            $data[] = $caisse;
        }
        return ["caisses" => $data, 'synthese' => $synthese, 'totalgaz' => $totalGazJ, 'totalsup' => $totalSupJ];
    }


    /**
     * ventes par pompiste
     */

    public function ventes_commerciale()
    {

        $users = User::has('caisses')
            ->get();
        // dd($users);
        // return  $users;
        $data = [];
        foreach ($users as $user) {
            $total = 0;
            foreach ($user->caisses as $caisse) {
                $madate = new Carbon($caisse->date_caisse);
                if ($madate->month === Carbon::now()->month && $madate->year === Carbon::now()->year) {
                    foreach ($caisse->compteurs as $compteur) {

                        $sortie = $compteur->indexFerE - $compteur->indexOuvE;
                        $total +=  $sortie;
                    }
                }
            }
            # code...
            $data[] = ["user" => $user->name, "total" =>  $total];
        }
        return $data;
    }


    public function ventes_pompistes()
    {
        $start = Carbon::parse(Carbon::now())->startOfMonth();
        $end = Carbon::parse(Carbon::now())->endOfMonth();
        /**recuperation des jour du mois  */
        $dates = [];
        while ($start->lte($end)) {
            $dates[] = $start->copy();
            $start->addDay();
        }

        $day = null;
        foreach ($dates as $key => $value) {
            $totalGaz = 0;
            $totalSup = 0;
            $jour = Carbon::parse($value)->format('d');
            $caisses = Caisse::select('id', 'coffre', 'user_id', 'netVer', 'approuve', 'date_caisse', 'ecart')
                ->where('user_id', Auth::user()->id)
                ->whereYear('date_caisse', Carbon::now()->year)
                ->whereMonth('date_caisse', Carbon::now()->month)
                ->whereDay('date_caisse', $jour)
                ->get();
            $totalCarbjour = 0;

            foreach ($caisses as  $caisse) {
                foreach ($caisse->compteurs as $compteur) {

                    if ($compteur->pistolet->carburant == "gasoil") {
                        $sortie = $compteur->indexFerE - $compteur->indexOuvE;
                        $totalGaz +=  $sortie;
                    }
                    if ($compteur->pistolet->carburant == "super") {
                        $sortie = $compteur->indexFerE - $compteur->indexOuvE;
                        $totalSup +=  $sortie;
                    }
                }
                //if($caisse->pompe->pistolets)
            }
            $day[] = ['jour' => $jour, 'gasoil' => $totalGaz, 'super' => $totalSup];
        }
        return $day;
    }

    public function last_caisse()
    {
        return Caisse::orderBy('date_caisse', 'DESC')->first();
    }
}
