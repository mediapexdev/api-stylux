<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Prix gérant au centime de franc près (976,6778 F/L) : 3 décimales arrondissaient la valeur des camions
class PrixExactsFinCommandes extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE fin_commandes MODIFY prix_super DOUBLE(14,6) NULL');
        DB::statement('ALTER TABLE fin_commandes MODIFY prix_gasoil DOUBLE(14,6) NULL');
        // Commandes en cours : reprise du prix exact
        DB::table('fin_commandes')->where('type', 'carburant')->whereIn('statut', ['programmee'])
            ->where('prix_super', 976.678)->update(['prix_super' => 976.6778]);
        DB::table('fin_commandes')->where('type', 'carburant')->whereIn('statut', ['programmee'])
            ->where('prix_gasoil', 741.678)->update(['prix_gasoil' => 741.6778]);
    }

    public function down()
    {
        DB::statement('ALTER TABLE fin_commandes MODIFY prix_super DOUBLE(12,3) NULL');
        DB::statement('ALTER TABLE fin_commandes MODIFY prix_gasoil DOUBLE(12,3) NULL');
    }
}
