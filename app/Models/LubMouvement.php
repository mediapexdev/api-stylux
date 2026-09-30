<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LubMouvement extends Model
{
    protected $table = 'lub_mouvements';
    protected $guarded = [];
    protected $casts = ['quantite' => 'float', 'prix_unitaire' => 'float'];

    public function produit()
    {
        return $this->belongsTo(LubProduit::class, 'produit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function pompiste()
    {
        return $this->belongsTo(User::class, 'pompiste_id')->withTrashed();
    }
}
