<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LubProduit extends Model
{
    protected $table = 'lub_produits';
    protected $guarded = [];
    protected $casts = [
        'actif' => 'boolean', 'contenance' => 'float', 'colis_qte' => 'integer',
        'prix_vente' => 'float', 'prix_achat' => 'float', 'seuil' => 'float',
    ];
}
