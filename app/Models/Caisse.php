<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Caisse extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
   
   
    //protected $with = ['pompe'];

    public function venteTpes()
    {
        return $this->hasMany(VenteTpe::class);
    }
    public function depenses()
    {
        return $this->hasMany(Depense::class);
    }
    public function bonClients()
    {
        return $this->hasMany(BonClient::class);
    }
    public function compteurs()
    {
        return $this->hasMany(Compteur::class);
        
    }


    public function user()
    {
        return  $this->belongsTo(User::class);
    }

    public function pompe()
    {
        return  $this->belongsTo(Pompe::class);
    }
   

    // Signature : utilisateur qui a approuvé
    public function approbateur()
    {
        return $this->belongsTo(User::class, 'approuve_par')->withTrashed();
    }
}
