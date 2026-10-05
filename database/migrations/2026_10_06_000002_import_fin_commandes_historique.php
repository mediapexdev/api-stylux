<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Reprise de l'onglet "Cdes et Chèques" du tableau de trésorerie (juin 2025 - avril 2026).
// Toutes ces commandes sont réglées : elles servent d'historique et ne pèsent pas sur le plafond.
class ImportFinCommandesHistorique extends Migration
{
    public function up()
    {
        // Repart de zéro si une tentative précédente a été interrompue
        DB::table('fin_commandes')->where('source', 'import')->delete();
        $fichier = database_path('data/fin_commandes_historique.json');
        if (!is_file($fichier)) {
            return;
        }
        $lignes = json_decode(file_get_contents($fichier), true) ?: [];
        $now = now();
        $rows = [];
        $date = function ($v) {
            return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        };
        foreach ($lignes as $l) {
            $paiement = $date($l['date_paiement']) ?: $date($l['echeance']);
            $commande = $date($l['date_commande']) ?: $date($l['date_livraison']) ?: $paiement;
            if (!$commande) {
                continue;
            }
            $rows[] = [
                'numero' => $l['numero'] ? substr($l['numero'], 0, 40) : null,
                'type' => $l['type'],
                'date_commande' => $commande,
                'super_l' => $l['super_l'] ?: 0,
                'gasoil_l' => $l['gasoil_l'] ?: 0,
                'prix_super' => null,
                'prix_gasoil' => null,
                'montant' => $l['montant'],
                'date_livraison' => $date($l['date_livraison']),
                'echeance' => $date($l['echeance']),
                'statut' => 'payee',
                'date_paiement' => $paiement,
                'mode_paiement' => $l['montant_cheque'] > 0 ? 'cheque' : null,
                'ajustements' => $l['ajustements'] ? json_encode($l['ajustements']) : null,
                'montant_paye' => $l['montant_paye'] ?: null,
                'commentaire' => trim(($l['commentaire'] ?? '') . ($l['date_paiement'] ? '' : ' Paiement non renseigné dans le classeur.')) ?: null,
                'source' => 'import',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 50) as $lot) {
                DB::table('fin_commandes')->insert($lot);
            }
        });
    }

    public function down()
    {
        DB::table('fin_commandes')->where('source', 'import')->delete();
    }
}
