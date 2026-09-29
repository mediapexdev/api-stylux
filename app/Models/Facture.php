<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facture extends Model
{
    protected $guarded = [];

    protected $casts = [
        'lignes' => 'array',
        'client_snapshot' => 'array',
        'montant' => 'float',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
