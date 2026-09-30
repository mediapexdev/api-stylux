<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Le premier comptage d'un produit est son stock initial, pas un écart d'inventaire
class LubStockInitial extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('lub_mouvements')) {
            return;
        }
        $premiers = DB::table('lub_mouvements')->select('produit_id', DB::raw('MIN(id) as id'))->groupBy('produit_id')->pluck('id');
        DB::table('lub_mouvements')->whereIn('id', $premiers)->where('type', 'inventaire')->get()->each(function ($m) {
            // toutes les lignes du même inventaire pour ce produit (magasin et présentoirs)
            DB::table('lub_mouvements')->where('lot', $m->lot)->where('produit_id', $m->produit_id)->where('type', 'inventaire')
                ->update(['type' => 'initial', 'commentaire' => 'Stock initial']);
        });
    }

    public function down()
    {
    }
}
