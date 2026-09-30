<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Demandes de correction d'une caisse déjà approuvée (pompiste -> chef de piste / gérant)
class CreateDemandeModificationsTable extends Migration
{
    public function up()
    {
        Schema::create('demande_modifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caisse_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();      // demandeur
            $table->text('motif');
            $table->string('statut', 20)->default('en_attente');    // en_attente | acceptee | refusee
            $table->unsignedBigInteger('traite_par')->nullable();
            $table->text('reponse')->nullable();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('demande_modifications');
    }
}
