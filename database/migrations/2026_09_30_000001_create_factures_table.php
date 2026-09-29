<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Factures clients crédit : numérotation continue par année (FAC-2026-0001) et contenu figé
class CreateFacturesTable extends Migration
{
    public function up()
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('date_facture');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->double('montant', 15, 2)->default(0);
            // Lignes figées au moment de l'émission : [{bon_id, date, designation, montant}]
            $table->longText('lignes');
            // Coordonnées du client au moment de l'émission
            $table->longText('client_snapshot')->nullable();
            $table->string('statut', 20)->default('emise'); // emise | annulee
            $table->string('motif_annulation')->nullable();
            $table->timestamps();
            $table->unique(['annee', 'sequence']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('factures');
    }
}
