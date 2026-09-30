<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Les GADUS se vendent au seau (18 kg) et au fût entier (180 kg), pas au kg
class GadusAuSeauEtAuFut extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('lub_produits')) {
            return;
        }
        DB::table('lub_produits')->where('designation', 'like', 'GADUS 18 %')->where('designation', 'not like', 'GADUS 180%')->update([
            'designation' => 'GADUS 18 KG (seau)', 'unite' => 'seau', 'contenance' => 18, 'colis_qte' => 1, 'colis' => 'seau',
            'prix_vente' => 93600, 'prix_achat' => 87472.26, 'seuil' => 2, 'updated_at' => now(),
        ]);
        DB::table('lub_produits')->where('designation', 'like', 'GADUS 180%')->update([
            'designation' => 'GADUS 180 KG (fût)', 'unite' => 'fut', 'contenance' => 180, 'colis_qte' => 1, 'colis' => 'fût',
            'prix_vente' => 909000, 'prix_achat' => 856000.8, 'seuil' => 0, 'updated_at' => now(),
        ]);
    }

    public function down()
    {
    }
}
