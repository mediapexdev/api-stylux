<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Signatures : qui a approuvé une caisse ou une journée (synthèse), et quand
class AddSignaturesToCaissesAndSynthesesTable extends Migration
{
    public function up()
    {
        foreach (['caisses', 'syntheses'] as $t) {
            if (!Schema::hasColumn($t, 'approuve_par')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unsignedBigInteger('approuve_par')->nullable();
                    $table->timestamp('approuve_le')->nullable();
                });
            }
        }
    }

    public function down()
    {
        foreach (['caisses', 'syntheses'] as $t) {
            if (Schema::hasColumn($t, 'approuve_par')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropColumn(['approuve_par', 'approuve_le']);
                });
            }
        }
    }
}
