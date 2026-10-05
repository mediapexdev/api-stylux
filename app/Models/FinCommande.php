<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinCommande extends Model
{
    protected $table = 'fin_commandes';

    protected $guarded = ['id'];

    protected $casts = [
        'ajustements' => 'array',
        'super_l' => 'float',
        'gasoil_l' => 'float',
        'prix_super' => 'float',
        'prix_gasoil' => 'float',
        'montant' => 'float',
        'montant_paye' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
