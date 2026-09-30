<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LubInventaire extends Model
{
    protected $table = 'lub_inventaires';
    protected $guarded = [];
    protected $casts = ['valide_le' => 'datetime'];

    public function lignes()
    {
        return $this->hasMany(LubInventaireLigne::class, 'inventaire_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function validateur()
    {
        return $this->belongsTo(User::class, 'valide_par')->withTrashed();
    }
}
