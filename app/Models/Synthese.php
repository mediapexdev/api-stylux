<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Synthese extends Model
{
    use HasFactory ,SoftDeletes;
    protected $guarded=[];
    public function encaissements()
    {
       return $this->hasMany(Encaissement::class);
    }

    public function commande_cars()
    {
       return $this->hasMany(CommandeCar::class);
    }
    public function receptions()
    {
       return $this->hasMany(Reception::class);
    }
    public function remise_cuves()
    {
       return $this->hasMany(RemiseCuve::class);
    }
    public function stocks()
    {
       return $this->hasMany(Stock::class);
    }    
    

    // Signature : utilisateur qui a approuvé
    public function approbateur()
    {
        return $this->belongsTo(User::class, 'approuve_par')->withTrashed();
    }
}
