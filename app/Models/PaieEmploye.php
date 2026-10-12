<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaieEmploye extends Model
{
    protected $table = 'paie_employes';

    protected $guarded = ['id'];

    protected $casts = [
        'primes_fixes' => 'array',
        'cadre' => 'boolean',
        'ipm' => 'boolean',
        'actif' => 'boolean',
        'conjoint_sans_revenu' => 'boolean',
        'enfants' => 'integer',
        'parts' => 'float',
        'salaire_base' => 'float',
        'sursalaire' => 'float',
        'indemnite_transport' => 'float',
    ];

    public function bulletins()
    {
        return $this->hasMany(PaieBulletin::class, 'employe_id');
    }

    public function getNomCompletAttribute()
    {
        return trim($this->prenom . ' ' . $this->nom);
    }
}
