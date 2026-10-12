<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Module Paie : salariés, bulletins de salaire mensuels et paramètres (taux, plafonds, barèmes)
class CreatePaieTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('paie_employes')) {
            Schema::create('paie_employes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();   // compte de l'appli, si le salarié en a un
                $table->string('matricule', 20)->nullable();
                $table->string('prenom', 80);
                $table->string('nom', 80);
                $table->string('fonction', 80)->nullable();
                $table->string('categorie', 40)->nullable();          // catégorie de la convention collective
                $table->date('date_naissance')->nullable();
                $table->date('date_embauche')->nullable();
                $table->date('date_sortie')->nullable();
                $table->string('type_contrat', 20)->default('CDI');   // CDI, CDD, stage, journalier
                $table->boolean('cadre')->default(false);
                $table->string('situation', 20)->default('celibataire'); // celibataire, marie, divorce, veuf
                $table->unsignedTinyInteger('enfants')->default(0);
                $table->boolean('conjoint_sans_revenu')->default(false);
                $table->decimal('parts', 3, 1)->nullable();            // forcé si renseigné
                $table->double('salaire_base', 14, 2)->default(0);
                $table->double('sursalaire', 14, 2)->default(0);
                $table->double('indemnite_transport', 14, 2)->default(0);
                $table->json('primes_fixes')->nullable();               // [{libelle, montant, imposable}]
                $table->boolean('ipm')->default(false);
                $table->string('numero_ipres', 30)->nullable();
                $table->string('numero_css', 30)->nullable();
                $table->string('cni', 30)->nullable();
                $table->string('telephone', 30)->nullable();
                $table->string('mode_paiement', 20)->default('especes'); // especes, virement, wave, orange_money, cheque
                $table->string('rib', 60)->nullable();
                $table->boolean('actif')->default(true);
                $table->text('commentaire')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('paie_bulletins')) {
            Schema::create('paie_bulletins', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employe_id');
                $table->char('mois', 7);                                 // AAAA-MM
                $table->json('variables')->nullable();                   // absences, heures sup, primes, avances du mois
                $table->json('lignes')->nullable();                      // détail calculé du bulletin
                $table->json('employe')->nullable();                     // instantané du salarié au moment du calcul
                $table->double('brut', 14, 2)->default(0);
                $table->double('brut_imposable', 14, 2)->default(0);
                $table->double('retenues', 14, 2)->default(0);
                $table->double('ir', 14, 2)->default(0);
                $table->double('trimf', 14, 2)->default(0);
                $table->double('net', 14, 2)->default(0);
                $table->double('charges_patronales', 14, 2)->default(0);
                $table->double('cout_total', 14, 2)->default(0);
                $table->string('statut', 12)->default('brouillon');      // brouillon, valide, paye
                $table->date('date_paiement')->nullable();
                $table->string('mode_paiement', 20)->nullable();
                $table->string('reference_paiement', 60)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('valide_par')->nullable();
                $table->timestamp('valide_le')->nullable();
                $table->timestamps();
                $table->unique(['employe_id', 'mois']);
                $table->index('mois');
            });
        }
        if (!Schema::hasTable('paie_parametres')) {
            Schema::create('paie_parametres', function (Blueprint $table) {
                $table->id();
                $table->string('cle', 60)->unique();
                $table->text('valeur')->nullable();
                $table->timestamps();
            });
            $now = now();
            foreach (\App\Services\PaieCalcul::defauts() as $cle => $valeur) {
                DB::table('paie_parametres')->insert([
                    'cle' => $cle,
                    'valeur' => is_array($valeur) ? json_encode($valeur) : (string) $valeur,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('paie_bulletins');
        Schema::dropIfExists('paie_employes');
        Schema::dropIfExists('paie_parametres');
    }
}
