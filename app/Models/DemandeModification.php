<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemandeModification extends Model
{
    protected $guarded = [];

    protected $casts = ['traite_le' => 'datetime'];

    public function caisse()
    {
        return $this->belongsTo(Caisse::class)->withTrashed();
    }

    public function demandeur()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function decideur()
    {
        return $this->belongsTo(User::class, 'traite_par')->withTrashed();
    }
}
