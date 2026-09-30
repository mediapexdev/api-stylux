<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LubInventaireLigne extends Model
{
    protected $table = 'lub_inventaire_lignes';
    protected $guarded = [];
    protected $casts = ['theorique' => 'float', 'compte' => 'float', 'ecart' => 'float', 'prix_achat' => 'float'];
}
