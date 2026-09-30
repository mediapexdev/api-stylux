<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Module lubrifiants : catalogue, mouvements (magasin / présentoirs), inventaires de contrôle.
// Le stock n'est jamais saisi directement : il est la somme des mouvements.
class CreateLubModuleTables extends Migration
{
    public function up()
    {
        Schema::create('lub_produits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ordre')->default(0);
            $table->string('code', 30)->nullable();
            $table->string('designation');
            $table->string('famille', 40)->nullable();
            $table->string('unite', 10)->default('bidon');      // bidon | litre | kg | seau | fut (unité de vente)
            $table->double('contenance', 10, 3)->default(1);    // litres ou kg par unité de vente
            $table->unsignedInteger('colis_qte')->default(1);   // unités par carton / fût / seau
            $table->string('colis', 20)->default('carton');
            $table->double('prix_vente', 12, 2)->default(0);
            $table->double('prix_achat', 12, 2)->default(0);
            $table->double('seuil', 10, 2)->default(0);         // alerte de réapprovisionnement (unités)
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('lub_mouvements', function (Blueprint $table) {
            $table->id();
            $table->string('lot', 40)->index();                // regroupe les lignes d'une même opération
            $table->date('date')->index();
            $table->string('type', 20);                         // reception | reassort | vente | ajustement | inventaire
            $table->unsignedBigInteger('produit_id')->index();
            $table->string('emplacement', 12);                  // magasin | presentoir
            $table->double('quantite', 12, 3);                  // + entrée, - sortie (en unités de vente)
            $table->double('prix_unitaire', 12, 2)->default(0);
            $table->string('reference', 60)->nullable();        // BL, n° inventaire...
            $table->string('poste', 10)->nullable();            // matin | soir (ventes)
            $table->unsignedBigInteger('pompiste_id')->nullable();
            $table->unsignedBigInteger('inventaire_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('commentaire')->nullable();
            $table->timestamps();
        });

        Schema::create('lub_inventaires', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->date('date');
            $table->string('emplacement', 12);                  // magasin | presentoir | tous
            $table->string('statut', 12)->default('brouillon'); // brouillon | valide
            $table->string('commentaire')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('valide_par')->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();
        });

        Schema::create('lub_inventaire_lignes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventaire_id')->index();
            $table->unsignedBigInteger('produit_id');
            $table->string('emplacement', 12);
            $table->double('theorique', 12, 3)->default(0);
            $table->double('compte', 12, 3)->nullable();
            $table->double('ecart', 12, 3)->nullable();
            $table->double('prix_achat', 12, 2)->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['ordre' => 1, 'code' => '12847', 'designation' => 'HELIX HX5 15W50 1L', 'famille' => 'Helix', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 3800, 'prix_achat' => 3500, 'seuil' => 24],
            ['ordre' => 2, 'code' => null, 'designation' => 'ADVANCE 4T AX5 15W40 1L', 'famille' => 'Advance', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 3800, 'prix_achat' => 3500, 'seuil' => 24],
            ['ordre' => 3, 'code' => '12850', 'designation' => 'HELIX HX5 15W40 5L', 'famille' => 'Helix', 'unite' => 'bidon', 'contenance' => 5, 'colis_qte' => 3, 'colis' => 'carton', 'prix_vente' => 16500, 'prix_achat' => 15245.6, 'seuil' => 9],
            ['ordre' => 4, 'code' => null, 'designation' => 'HELIX HX3 20W50 1L', 'famille' => 'Helix', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 3500, 'prix_achat' => 3400, 'seuil' => 12],
            ['ordre' => 5, 'code' => null, 'designation' => 'ADVANCE 4T AX3 20W50 1L', 'famille' => 'Advance', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 3400, 'prix_achat' => 3200, 'seuil' => 12],
            ['ordre' => 6, 'code' => null, 'designation' => 'HELIX HX3 20W50 5L', 'famille' => 'Helix', 'unite' => 'bidon', 'contenance' => 5, 'colis_qte' => 3, 'colis' => 'carton', 'prix_vente' => 16000, 'prix_achat' => 15100.07, 'seuil' => 6],
            ['ordre' => 7, 'code' => '23365', 'designation' => 'HELIX HX7 10W40 1L', 'famille' => 'Helix', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 5750, 'prix_achat' => 5350.02, 'seuil' => 12],
            ['ordre' => 8, 'code' => null, 'designation' => 'ADVANCE 4T AX7 10W40 1L', 'famille' => 'Advance', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 6000, 'prix_achat' => 4738, 'seuil' => 12],
            ['ordre' => 9, 'code' => '23366', 'designation' => 'HELIX HX7 10W40 4L', 'famille' => 'Helix', 'unite' => 'bidon', 'contenance' => 4, 'colis_qte' => 4, 'colis' => 'carton', 'prix_vente' => 20800, 'prix_achat' => 19100.07, 'seuil' => 8],
            ['ordre' => 10, 'code' => null, 'designation' => 'RIMULA R1 50 1L', 'famille' => 'Rimula', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 3200, 'prix_achat' => 2600.03, 'seuil' => 12],
            ['ordre' => 11, 'code' => null, 'designation' => 'RIMULA R1 50 5L', 'famille' => 'Rimula', 'unite' => 'bidon', 'contenance' => 5, 'colis_qte' => 3, 'colis' => 'carton', 'prix_vente' => 15000, 'prix_achat' => 13999.91, 'seuil' => 6],
            ['ordre' => 12, 'code' => null, 'designation' => 'RIMULA R1 50 VRAC (fût 209 L)', 'famille' => 'Rimula', 'unite' => 'litre', 'contenance' => 1, 'colis_qte' => 209, 'colis' => 'fût', 'prix_vente' => 2300, 'prix_achat' => 2100, 'seuil' => 209],
            ['ordre' => 13, 'code' => null, 'designation' => 'RIMULA R2 50 1L', 'famille' => 'Rimula', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 3400, 'prix_achat' => 3100.06, 'seuil' => 12],
            ['ordre' => 14, 'code' => null, 'designation' => 'RIMULA R2 50 5L', 'famille' => 'Rimula', 'unite' => 'bidon', 'contenance' => 5, 'colis_qte' => 3, 'colis' => 'carton', 'prix_vente' => 15500, 'prix_achat' => 14250.07, 'seuil' => 9],
            ['ordre' => 15, 'code' => '12874', 'designation' => 'RIMULA R2 50 20L', 'famille' => 'Rimula', 'unite' => 'bidon', 'contenance' => 20, 'colis_qte' => 1, 'colis' => 'bidon', 'prix_vente' => 54000, 'prix_achat' => 50000.14, 'seuil' => 4],
            ['ordre' => 16, 'code' => null, 'designation' => 'SPIRAX ATF 1L', 'famille' => 'Spirax', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 5000, 'prix_achat' => 4649.89, 'seuil' => 12],
            ['ordre' => 17, 'code' => '23367', 'designation' => 'HELIX ULTRA 5W40 1L', 'famille' => 'Ultra', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 10500, 'prix_achat' => 9900, 'seuil' => 6],
            ['ordre' => 18, 'code' => '23368', 'designation' => 'HELIX ULTRA 5W40 4L', 'famille' => 'Ultra', 'unite' => 'bidon', 'contenance' => 4, 'colis_qte' => 4, 'colis' => 'carton', 'prix_vente' => 38400, 'prix_achat' => 34399.95, 'seuil' => 4],
            ['ordre' => 19, 'code' => '23359', 'designation' => 'HELIX ULTRA 5W30 5L', 'famille' => 'Ultra', 'unite' => 'bidon', 'contenance' => 5, 'colis_qte' => 3, 'colis' => 'carton', 'prix_vente' => 48000, 'prix_achat' => 39749.87, 'seuil' => 3],
            ['ordre' => 20, 'code' => null, 'designation' => 'HELIX ULTRA 5W30 1L', 'famille' => 'Ultra', 'unite' => 'bidon', 'contenance' => 1, 'colis_qte' => 12, 'colis' => 'carton', 'prix_vente' => 10750, 'prix_achat' => 8850, 'seuil' => 6],
            ['ordre' => 21, 'code' => '23291', 'designation' => 'GADUS 18 KG (seau)', 'famille' => 'Graisse', 'unite' => 'seau', 'contenance' => 18, 'colis_qte' => 1, 'colis' => 'seau', 'prix_vente' => 93600, 'prix_achat' => 87472.26, 'seuil' => 2],
            ['ordre' => 22, 'code' => null, 'designation' => 'GADUS 180 KG (fût)', 'famille' => 'Graisse', 'unite' => 'fut', 'contenance' => 180, 'colis_qte' => 1, 'colis' => 'fût', 'prix_vente' => 909000, 'prix_achat' => 856000.8, 'seuil' => 0],
        ] as $p) {
            DB::table('lub_produits')->insert($p + ['actif' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('lub_inventaire_lignes');
        Schema::dropIfExists('lub_inventaires');
        Schema::dropIfExists('lub_mouvements');
        Schema::dropIfExists('lub_produits');
    }
}
