<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Reprise de l'onglet "Cdes et Chèques" du tableau de trésorerie (juin 2025 - avril 2026).
// Toutes ces commandes sont réglées : elles servent d'historique et ne pèsent pas sur le plafond.
class ImportFinCommandesHistorique extends Migration
{
    public function up()
    {
        if (DB::table('fin_commandes')->where('source', 'import')->exists()) {
            return;
        }
        $fichier = database_path('data/fin_commandes_historique.json');
        if (!is_file($fichier)) {
            return;
        }
        $lignes = json_decode(file_get_contents($fichier), true) ?: [];
        $now = now();
        $rows = [];
        foreach ($lignes as $l) {
            $carb = $l['type'] === 'carburant';
            $paiement = $l['date_paiement'] ?: $l['echeance'];
            $rows[] = [
                'numero' => $l['numero'],
                'type' => $l['type'],
                'date_commande' => $l['date_commande'],
                'super_l' => $l['super_l'] ?: 0,
                'gasoil_l' => $l['gasoil_l'] ?: 0,
                'prix_super' => null,
                'prix_gasoil' => null,
                'montant' => $l['montant'],
                'date_livraison' => $l['date_livraison'],
                'echeance' => $l['echeance'],
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
        foreach (array_chunk($rows, 100) as $lot) {
            DB::table('fin_commandes')->insert($lot);
        }
    }

    public function down()
    {
        DB::table('fin_commandes')->where('source', 'import')->delete();
    }
}
