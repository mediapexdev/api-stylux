<?php

namespace App\Console\Commands;

use App\Support\StyluxDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StyluxReinitialiser extends Command
{
    protected $signature = 'stylux:reinitialiser
        {--garder-sauvegarde : Conserver le fichier de sauvegarde des index après restauration}
        {--force : Ne pas demander de confirmation}';

    protected $description = "Remise à zéro avant le lancement : efface toute l'activité (caisses, synthèses, bons, fiches...) et garde le paramétrage";

    public function handle()
    {
        $this->error('ATTENTION : toutes les données d\'activité vont être définitivement effacées.');
        $this->line('Conservés : utilisateurs, îlots, pompes, pistolets, cuves, clients, produits et lubrifiants.');
        $this->line('Base de données : ' . DB::connection()->getDatabaseName());
        $this->line('Conseil : faites une sauvegarde de la base avant (phpMyAdmin > Exporter).');
        if (!$this->option('force')) {
            if ($this->ask('Tapez EFFACER pour confirmer') !== 'EFFACER') {
                $this->info('Annulé, rien n\'a été modifié.');
                return 1;
            }
        }

        DB::beginTransaction();
        try {
            $r = StyluxDemo::reinitialiser(storage_path('app'), $this->option('garder-sauvegarde'));
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return 1;
        }

        $rows = [];
        foreach ($r['tables'] as $t => $n) {
            $rows[] = [$t, $n];
        }
        $this->table(['Table vidée', 'Lignes supprimées'], $rows);
        if ($r['restauration']) {
            $this->info('Index des pistolets et niveaux des cuves remis à leur valeur d\'avant la démo.');
        } else {
            $this->warn('Aucune sauvegarde de démo trouvée : pensez à saisir les index réels des pistolets et le niveau des cuves dans l\'espace admin.');
        }
        $this->info('Remise à zéro terminée.');
        return 0;
    }
}
