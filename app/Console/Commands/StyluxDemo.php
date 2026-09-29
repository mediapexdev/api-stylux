<?php

namespace App\Console\Commands;

use App\Support\StyluxDemo as Demo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StyluxDemo extends Command
{
    protected $signature = 'stylux:demo
        {--mois=2026-09 : Mois à générer (AAAA-MM)}
        {--litres=10000 : Moyenne de litres de carburant vendus par jour}
        {--jusquau= : Dernier jour généré (AAAA-MM-JJ), par défaut la veille ou la fin du mois}
        {--force : Ne pas demander de confirmation}';

    protected $description = "Crée des données fictives d'un mois (caisses, index, bons clients, synthèses, stocks, lubrifiants, lavages...) pour la démonstration";

    public function handle()
    {
        $mois = $this->option('mois');
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $this->error('Format attendu pour --mois : AAAA-MM');
            return 1;
        }
        $jusquau = $this->option('jusquau') ?: date('Y-m-d', strtotime('-1 day'));
        $litres = (float) $this->option('litres');

        $this->warn("Données FICTIVES pour $mois jusqu'au $jusquau, environ $litres L/jour.");
        $this->line("Base de données : " . DB::connection()->getDatabaseName());
        if (!$this->option('force') && !$this->confirm('Continuer ?')) {
            return 1;
        }

        $demo = new Demo(function ($m) {
            $this->line($m);
        });
        DB::beginTransaction();
        try {
            $s = $demo->generer($mois, $litres, $jusquau, storage_path('app'));
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return 1;
        }

        $this->info("Terminé : {$s['jours']} jours du {$s['du']} au {$s['au']}");
        $this->table(['Indicateur', 'Valeur'], [
            ['Litres vendus', number_format($s['litres'], 0, ',', ' ')],
            ['Moyenne par jour', number_format($s['moyenne'], 0, ',', ' ') . ' L'],
            ['Chiffre carburant', number_format($s['montant'], 0, ',', ' ') . ' F'],
            ['Caisses', $s['caisses']],
            ['Bons clients', $s['bons']],
            ['Bons encaissés', $s['encaissements']],
        ]);
        $this->line("Pour tout effacer avant le lancement : php artisan stylux:reinitialiser");
        return 0;
    }
}
