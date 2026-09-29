<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mode de paiement électronique : carte (TPE), wave ou orange_money. Les lignes existantes restent "carte".
class AddModeToVenteTpesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('vente_tpes', 'mode')) {
            Schema::table('vente_tpes', function (Blueprint $table) {
                $table->string('mode', 20)->default('carte')->after('id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('vente_tpes', 'mode')) {
            Schema::table('vente_tpes', function (Blueprint $table) {
                $table->dropColumn('mode');
            });
        }
    }
}
