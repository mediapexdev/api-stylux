<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaieBulletin extends Model
{
    protected $table = 'paie_bulletins';

    protected $guarded = ['id'];

    protected $casts = [
        'variables' => 'array',
        'lignes' => 'array',
        'employe' => 'array',
        'brut' => 'float',
        'brut_imposable' => 'float',
        'retenues' => 'float',
        'ir' => 'float',
        'trimf' => 'float',
        'net' => 'float',
        'charges_patronales' => 'float',
        'cout_total' => 'float',
    ];

    public function salarie()
    {
        return $this->belongsTo(PaieEmploye::class, 'employe_id');
    }

    public function validateur()
    {
        return $this->belongsTo(User::class, 'valide_par');
    }
}
