<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tabinventaire;
use Illuminate\Support\Facades\Gate;

class TabinventaireController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return  Tabinventaire::orderBy('created_at', 'DESC')->get();
    }

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
        $tabinventaire=Tabinventaire::create($request->all());
        return $tabinventaire;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ Tabinventaire  $tabinventaire
     * @return \Illuminate\Http\Response
     */
    public function show( Tabinventaire $tabinventaire)
    {
        return $tabinventaire;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ Tabinventaire  $tabinventaire
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,  Tabinventaire $tabinventaire,$id)
    {
        
        $tabinventaire = Tabinventaire::find($id);
        if ($tabinventaire->approuve) {
            abort(403);
        }
        $tabinventaire->update($request->all());
        return 1;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ Tabinventaire  $tabinventaire
     * @return \Illuminate\Http\Response
     */
    public function destroy( Tabinventaire $tabinventaire)
    {
        $tabinventaire->delete();
        return 1;
    }

    public function approuver_stock($id)
    {        
        $tabinventaire = Tabinventaire::find($id);
        $tabinventaire->update(['approuve' => $tabinventaire->approuve ? false : true]);
        return $tabinventaire;
    }
}
