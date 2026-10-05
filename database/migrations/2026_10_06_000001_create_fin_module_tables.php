<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Module Finance : commandes de carburants et lubrifiants (commande, livraison, facture, paiement)
// et paramètres (plafond bancaire, prix gérant, délais).
class CreateFinModuleTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('fin_commandes')) {
            Schema::create('fin_commandes', function (Blueprint $table) {
                $table->id();
                $table->string('numero', 40)->nullable();          // n° de commande (ZOR)
                $table->string('type', 12)->default('carburant');  // carburant | lubrifiant
                $table->date('date_commande');
                $table->double('super_l', 12, 2)->default(0);
                $table->double('gasoil_l', 12, 2)->default(0);
                $table->double('prix_super', 12, 3)->nullable();
                $table->double('prix_gasoil', 12, 3)->nullable();
                $table->double('montant', 14, 2)->default(0);       // valeur brute de la commande
                $table->date('date_livraison')->nullable();
                $table->string('numero_bl', 40)->nullable();
                $table->string('numero_facture', 40)->nullable();
                $table->date('echeance')->nullable();
                $table->string('statut', 12)->default('programmee'); // programmee | livree | payee
                $table->date('date_paiement')->nullable();
                $table->string('mode_paiement', 20)->nullable();     // cheque | especes | virement
                $table->string('banque', 20)->nullable();            // BOA | BASN
                $table->string('numero_cheque', 40)->nullable();
                $table->json('ajustements')->nullable();             // {"ZH": -1200000, "ZL": 300000, ...}
                $table->double('montant_paye', 14, 2)->nullable();
                $table->text('commentaire')->nullable();
                $table->string('source', 12)->nullable();            // import
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
                $table->index(['statut', 'echeance']);
                $table->index('date_commande');
            });
        }

        if (!Schema::hasTable('fin_parametres')) {
            Schema::create('fin_parametres', function (Blueprint $table) {
                $table->string('cle', 60)->primary();
                $table->text('valeur')->nullable();
                $table->timestamps();
            });
            $now = now();
            $defauts = [
                'plafond' => 90000000,          // plafond BOA sans OCL (convention AG4S)
                'prix_super' => 906.678,        // prix gérant (cession + loyer piste)
                'prix_gasoil' => 666.678,
                'delai_livraison' => 2,         // jours entre commande et livraison
                'delai_carburant' => 7,         // jours entre livraison et échéance
                'delai_lubrifiant' => 30,
                'tpe_depuis' => date('Y-m-d'),  // point de départ du solde TPE
                'tpe_solde_initial' => 0,
            ];
            foreach ($defauts as $cle => $valeur) {
                DB::table('fin_parametres')->insert(['cle' => $cle, 'valeur' => (string) $valeur, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('fin_parametres');
        Schema::dropIfExists('fin_commandes');
    }
}
