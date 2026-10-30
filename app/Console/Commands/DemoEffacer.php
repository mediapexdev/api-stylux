<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Supprime toutes les données créées par demo:generer, et seulement elles (liste tenue dans demo_lignes) */
class DemoEffacer extends Command
{
    protected $signature = 'demo:effacer';
    protected $description = 'Supprime toutes les données de démonstration';

    public function handle()
    {
        if (!Schema::hasTable('demo_lignes')) {
            $this->info('Aucune donnée de démo à supprimer.');
            return 0;
        }
        $parTable = DB::table('demo_lignes')->orderByDesc('id')->get()->groupBy('table_name');
        DB::transaction(function () use ($parTable) {
            foreach ($parTable as $table => $lignes) {
                $ids = $lignes->pluck('row_id')->all();
                foreach (array_chunk($ids, 500) as $lot) {
                    DB::table($table)->whereIn('id', $lot)->delete();
                }
                $this->line("  {$table} : " . count($ids) . ' ligne(s) supprimée(s)');
            }
        });
        Schema::drop('demo_lignes');
        $this->info('Données de démo supprimées. Les vraies données sont intactes.');
        return 0;
    }
}
