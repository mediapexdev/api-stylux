<?php

namespace App\Console\Commands;

use App\Services\PaieCalcul;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Données fictives pour la prise en main de l'appli (caisses, journées, lubrifiants, lavages, finance, paie).
 * Chaque ligne créée est notée dans la table demo_lignes : "php artisan demo:effacer" les supprime toutes,
 * sans toucher aux vraies données. Les jours qui ont déjà de vraies caisses sont laissés de côté.
 * Les index courants des pistolets et le stock courant des cuves ne sont jamais modifiés.
 *
 *   php artisan demo:generer                (octobre 2026, jusqu'à la veille)
 *   php artisan demo:generer --mois=2026-10 --jusqua=2026-10-29
 *   php artisan demo:effacer
 */
class DemoGenerer extends Command
{
    protected $signature = 'demo:generer {--mois=2026-10} {--jusqua=}';
    protected $description = "Crée des données fictives (marquées démo) pour un mois, supprimables avec demo:effacer";

    private $now;
    private $compte = [];

    // ---------------------------------------------------------------- Outils

    private function ins($table, array $row)
    {
        if (Schema::hasColumn($table, 'created_at') && !isset($row['created_at'])) {
            $row['created_at'] = $this->now;
            $row['updated_at'] = $this->now;
        }
        $id = DB::table($table)->insertGetId($row);
        DB::table('demo_lignes')->insert(['table_name' => $table, 'row_id' => $id, 'created_at' => $this->now]);
        $this->compte[$table] = ($this->compte[$table] ?? 0) + 1;
        return $id;
    }

    private function alea($min, $max)
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    private function choix(array $t)
    {
        return $t[array_rand($t)];
    }

    private function arrondi($v, $pas)
    {
        return round($v / $pas) * $pas;
    }

    private function estSuper($carburant)
    {
        return stripos((string) $carburant, 'super') !== false;
    }

    // ---------------------------------------------------------------- Commande

    public function handle()
    {
        $mois = $this->option('mois');
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $this->error('Mois invalide (AAAA-MM).');
            return 1;
        }
        mt_srand(crc32('stylux-' . $mois));
        $this->now = now();
        $debut = Carbon::parse($mois . '-01');
        $fin = $this->option('jusqua') ? Carbon::parse($this->option('jusqua')) : Carbon::yesterday();
        if ($fin->gt($debut->copy()->endOfMonth())) {
            $fin = $debut->copy()->endOfMonth();
        }
        if ($fin->lt($debut)) {
            $this->error('Aucun jour à générer.');
            return 1;
        }

        if (!Schema::hasTable('demo_lignes')) {
            Schema::create('demo_lignes', function (Blueprint $table) {
                $table->id();
                $table->string('table_name', 60);
                $table->unsignedBigInteger('row_id');
                $table->timestamp('created_at')->nullable();
                $table->index(['table_name', 'row_id']);
            });
        } elseif (DB::table('demo_lignes')->exists()) {
            $this->error("Des données de démo existent déjà. Lancez d'abord : php artisan demo:effacer");
            return 1;
        }

        // Référentiels réels (lus, jamais modifiés)
        $pompes = DB::table('pompes')->whereNull('deleted_at')->orderBy('numero')->get();
        $pistolets = DB::table('pistolets')->whereNull('deleted_at')->whereIn('pompe_id', $pompes->pluck('id'))->get();
        $reservoirs = DB::table('reservoirs')->whereNull('deleted_at')->get();
        $pompistes = DB::table('users')->whereNull('deleted_at')->where('role_id', 1)->orderBy('id')->get();
        $chef = DB::table('users')->whereNull('deleted_at')->where('role_id', 3)->orderBy('id')->first();
        $gerant = DB::table('users')->whereNull('deleted_at')->where('role_id', 2)->orderBy('id')->first();
        if ($pompes->isEmpty() || $pistolets->isEmpty() || $pompistes->isEmpty()) {
            $this->error('Il faut au moins une pompe, des pistolets et un pompiste.');
            return 1;
        }

        // Jours à générer : ceux qui n'ont pas de vraies caisses
        $jours = [];
        for ($d = $debut->copy(); $d->lte($fin); $d->addDay()) {
            if (DB::table('caisses')->whereNull('deleted_at')->where('date_caisse', $d->toDateString())->exists()) {
                $this->line("  {$d->toDateString()} : vraies caisses présentes, jour laissé tel quel");
                continue;
            }
            $jours[] = $d->toDateString();
        }
        if (!$jours) {
            $this->error('Tous les jours du mois ont déjà de vraies caisses.');
            return 1;
        }

        DB::transaction(function () use ($jours, $pompes, $pistolets, $reservoirs, $pompistes, $chef, $gerant, $mois, $fin) {
            $clients = $this->clients();
            $ventes = $this->volumes($jours, $pistolets);
            $sortiesJour = $this->caisses($jours, $pompes, $pistolets, $pompistes, $chef, $clients, $ventes);
            $livraisons = $this->stocks($jours, $reservoirs, $sortiesJour, $gerant, $fin);
            $this->finance($livraisons);
            $lub = $this->lubrifiants($jours, $pompistes, $gerant);
            $this->recettes($jours, $lub);
            $this->encaissements($jours, $clients);
            $this->paie($mois, $pompistes, $chef, $gerant);
        });

        $this->info('Données de démo créées pour ' . count($jours) . ' jour(s) :');
        foreach ($this->compte as $t => $n) {
            $this->line("  {$t} : {$n}");
        }
        $this->info('Pour tout supprimer : php artisan demo:effacer');
        return 0;
    }

    /** Clients crédit : les vrais s'il y en a, sinon 4 clients de démo */
    private function clients()
    {
        $ids = DB::table('clients')->whereNull('deleted_at')->orderBy('id')->limit(8)->pluck('id')->all();
        if (count($ids) >= 3) {
            return $ids;
        }
        foreach ([['Transports Ndiaye & Fils', '771234501'], ['Entreprise BTP Sahel', '771234502'], ['Taxi Groupement Thiaroye', '771234503'], ['Boulangerie Darou Salam', '771234504']] as $c) {
            $ids[] = $this->ins('clients', ['nom' => $c[0] . ' (démo)', 'telephone' => $c[1], 'email' => 'demo@styluxoil.com', 'adresse' => 'Thiaroye']);
        }
        return $ids;
    }

    /** Litres vendus par pistolet et par jour */
    private function volumes(array $jours, $pistolets)
    {
        $v = [];
        foreach ($jours as $j) {
            $dow = Carbon::parse($j)->dayOfWeek;
            $f = $dow === 0 ? 0.75 : ($dow === 6 ? 0.9 : ($dow === 5 ? 1.08 : 1));
            foreach ($pistolets as $p) {
                $base = $this->estSuper($p->carburant) ? 650 : 1450;
                $v[$j][$p->id] = round($base * $f * $this->alea(0.8, 1.2), 2);
            }
        }
        return $v;
    }

    /** Caisses, compteurs, paiements électroniques, bons clients et dépenses */
    private function caisses(array $jours, $pompes, $pistolets, $pompistes, $chef, array $clients, array $ventes)
    {
        // Chaîne d'index qui se termine sur l'index actuel de chaque pistolet
        $index = [];
        foreach ($pistolets as $p) {
            $total = 0;
            foreach ($jours as $j) {
                $total += $ventes[$j][$p->id];
            }
            $e = (float) $p->indexE;
            $ecartM = max(5, $e - (float) $p->indexM);
            $index[$p->id] = ['e' => max(0, $e - $total), 'ecartM' => $ecartM];
        }

        $limite = Carbon::today()->subDays(2)->toDateString();
        $sorties = [];
        foreach ($jours as $n => $j) {
            $sorties[$j] = ['super' => 0, 'gasoil' => 0];
            foreach ($pompes as $k => $pompe) {
                // Deux pompistes par jour sur le mois, en alternance
                $pompiste = $pompistes[($n + intdiv($k, 2)) % count($pompistes)];
                $approuve = $j <= $limite;
                $caisseId = $this->ins('caisses', [
                    'date_caisse' => $j, 'horaire' => 24, 'pompe_id' => $pompe->id, 'user_id' => $pompiste->id,
                    'netVer' => 0, 'coffre' => 0, 'ecart' => 0, 'approuve' => $approuve ? 1 : 0,
                    'approuve_par' => $approuve && $chef ? $chef->id : null,
                    'approuve_le' => $approuve ? Carbon::parse($j)->addDay()->setTime(8, 30) : null,
                ]);
                $montant = 0;
                foreach ($pistolets->where('pompe_id', $pompe->id) as $p) {
                    $ouv = $index[$p->id]['e'];
                    $fer = round($ouv + $ventes[$j][$p->id], 2);
                    $index[$p->id]['e'] = $fer;
                    $prix = (float) $p->prix;
                    $this->ins('compteurs', [
                        'caisse_id' => $caisseId, 'pistolet_id' => $p->id, 'prix' => $prix,
                        'indexOuvE' => $ouv, 'indexFerE' => $fer,
                        'indexOuvM' => round($ouv - $index[$p->id]['ecartM'], 2), 'indexFerM' => round($fer - $index[$p->id]['ecartM'] + $this->alea(-3, 3), 2),
                    ]);
                    $montant += $ventes[$j][$p->id] * $prix;
                    $sorties[$j][$this->estSuper($p->carburant) ? 'super' : 'gasoil'] += $ventes[$j][$p->id];
                }
                $montant = round($montant);

                // Paiements non reçus en espèces
                $nonRecu = 0;
                foreach (['wave' => [0.01, 0.06], 'orange_money' => [0, 0.03], 'carte' => [0, 0.03], 'carte_shell' => [0, 0.08]] as $mode => $t) {
                    if ($mode !== 'wave' && mt_rand(1, 100) > 55) {
                        continue;
                    }
                    $m = $this->arrondi($montant * $this->alea($t[0], $t[1]), 500);
                    if ($m > 0) {
                        $this->ins('vente_tpes', ['mode' => $mode, 'numero_carte' => '-', 'montant' => $m, 'caisse_id' => $caisseId]);
                        $nonRecu += $m;
                    }
                }
                for ($b = 0, $nb = mt_rand(0, 2); $b < $nb; $b++) {
                    $m = $this->arrondi($this->alea(10000, 150000), 5000);
                    $this->ins('bon_clients', ['montant' => $m, 'caisse_id' => $caisseId, 'client_id' => $this->choix($clients), 'etat' => 0]);
                    $nonRecu += $m;
                }
                for ($b = 0, $nb = mt_rand(0, 2); $b < $nb; $b++) {
                    $m = $this->arrondi($this->alea(1000, 10000), 500);
                    $this->ins('depenses', ['justificatif' => $this->choix(['Transport', 'Eau', 'Crédit téléphone', 'Petit matériel', 'Repas équipe', 'Gasoil baie']), 'montant' => $m, 'caisse_id' => $caisseId]);
                    $nonRecu += $m;
                }

                // Clôture : coffre, net versé, écart (le plus souvent nul, parfois un petit manquant)
                $carburant = $montant - $nonRecu;
                $coffre = max(0, $this->arrondi($carburant * $this->alea(0.4, 0.6), 5000));
                $aVerser = $carburant - $coffre;
                $r = mt_rand(1, 100);
                $netVer = $r <= 75 ? $aVerser : ($r <= 95 ? $aVerser - $this->arrondi($this->alea(500, 5000), 500) : $aVerser + 500);
                DB::table('caisses')->where('id', $caisseId)->update(['coffre' => $coffre, 'netVer' => $netVer, 'ecart' => $aVerser - $netVer]);
            }
        }
        return $sorties;
    }

    /** Synthèses : stocks à 07h00, réceptions de camions, commandes, contrôle des stocks cohérent */
    private function stocks(array $jours, $reservoirs, array $sorties, $gerant, Carbon $fin)
    {
        $livraisons = [];
        if ($reservoirs->isEmpty()) {
            return $livraisons;
        }
        $parProduit = ['super' => [], 'gasoil' => []];
        foreach ($reservoirs as $r) {
            $parProduit[$this->estSuper($r->carburant) ? 'super' : 'gasoil'][] = $r;
        }
        $capa = [];
        $stock = [];
        foreach ($parProduit as $p => $rs) {
            $capa[$p] = array_sum(array_map(function ($r) {
                return (float) $r->capacite;
            }, $rs));
            $stock[$p] = round($capa[$p] * 0.55);
        }
        $limiteApprob = Carbon::today()->subDays(4)->toDateString();
        $numero = 7300;

        foreach ($jours as $j) {
            $synthese = DB::table('syntheses')->whereNull('deleted_at')->where('date', $j)->first();
            if ($synthese) {
                $sid = $synthese->id; // synthèse vide créée en ouvrant la page : on la complète sans la modifier
            } else {
                $ok = $j <= $limiteApprob;
                $sid = $this->ins('syntheses', ['date' => $j, 'etat' => $ok ? 1 : 0, 'user_id' => null,
                    'approuve_par' => $ok && $gerant ? $gerant->id : null, 'approuve_le' => $ok ? Carbon::parse($j)->addDays(2)->setTime(10, 0) : null]);
            }

            // Livraison du jour si le stock passe sous 2 jours de ventes
            $rec = ['super' => 0, 'gasoil' => 0];
            $libre = ['super' => $capa['super'] - $stock['super'], 'gasoil' => $capa['gasoil'] - $stock['gasoil']];
            if ($capa['gasoil'] > 0 && $stock['gasoil'] < 2 * 8700 || $capa['super'] > 0 && $stock['super'] < 2 * 1300) {
                foreach ([[5000, 15000], [3000, 17000], [2000, 18000], [0, 20000], [3000, 12000], [0, 15000], [2000, 10000], [0, 10000]] as $c) {
                    if ($c[0] <= $libre['super'] && $c[1] <= $libre['gasoil'] && ($c[0] > 0 || $capa['super'] == 0 || $stock['super'] >= 2600)) {
                        $rec = ['super' => $c[0], 'gasoil' => $c[1]];
                        break;
                    }
                }
                if ($rec['super'] + $rec['gasoil'] == 0) {
                    // Petites cuves : on remplit ce qui tient, par tranches de 1 000 L
                    $rec = ['super' => min(5000, floor($libre['super'] / 1000) * 1000), 'gasoil' => floor($libre['gasoil'] / 1000) * 1000];
                }
            }

            foreach ($parProduit as $p => $rs) {
                foreach ($rs as $r) {
                    $part = $capa[$p] > 0 ? (float) $r->capacite / $capa[$p] : 0;
                    $this->ins('stocks', ['capacite' => round($stock[$p] * $part), 'reservoir_id' => $r->id, 'synthese_id' => $sid]);
                    if ($rec[$p] > 0) {
                        $this->ins('receptions', ['capacite' => round($rec[$p] * $part), 'reservoir_id' => $r->id, 'synthese_id' => $sid]);
                    }
                }
            }
            if ($rec['super'] + $rec['gasoil'] > 0) {
                $numero++;
                $cde = '4500' . $numero;
                $bl = '80' . $numero;
                $this->ins('commande_cars', ['numero_cde' => $cde, 'numero_bl' => $bl, 'heure' => mt_rand(8, 15), 'synthese_id' => $sid]);
                $livraisons[] = ['date' => $j, 'super' => $rec['super'], 'gasoil' => $rec['gasoil'], 'numero' => $cde, 'bl' => $bl];
            }

            // Stock du lendemain : théorique + petit écart de mesure (dans la tolérance de 5 pour 1000)
            foreach (['super', 'gasoil'] as $p) {
                $theo = $stock[$p] + $rec[$p] - $sorties[$j][$p];
                $stock[$p] = max(0, round($theo + $sorties[$j][$p] * $this->alea(-0.004, 0.002)));
            }
        }
        return $livraisons;
    }

    /** Commandes de camions dans Finance (source = demo) : payées si l'échéance est passée */
    private function finance(array $livraisons)
    {
        if (!Schema::hasTable('fin_commandes')) {
            return;
        }
        $p = DB::table('fin_parametres')->pluck('valeur', 'cle');
        $ps = (float) ($p['prix_super'] ?? 976.6778);
        $pg = (float) ($p['prix_gasoil'] ?? 741.6778);
        $auj = Carbon::today();
        $cheque = 2004100;
        foreach ($livraisons as $n => $l) {
            $montant = round($l['super'] * $ps + $l['gasoil'] * $pg, 2);
            $liv = Carbon::parse($l['date']);
            $ech = $liv->copy()->addDays(7);
            $payee = $ech->lt($auj);
            $row = [
                'numero' => $l['numero'], 'type' => 'carburant', 'date_commande' => $liv->copy()->subDays(2)->toDateString(),
                'super_l' => $l['super'], 'gasoil_l' => $l['gasoil'], 'prix_super' => $ps, 'prix_gasoil' => $pg, 'montant' => $montant,
                'date_livraison' => $l['date'], 'numero_bl' => $l['bl'], 'numero_facture' => '15000' . (78000 + $n), 'echeance' => $ech->toDateString(),
                'statut' => $payee ? 'payee' : 'livree', 'source' => 'demo', 'commentaire' => 'Donnée de démonstration',
            ];
            if ($payee) {
                $row += ['date_paiement' => $ech->toDateString(), 'mode_paiement' => 'cheque', 'banque' => 'BOA', 'numero_cheque' => (string) ($cheque++), 'montant_paye' => $montant];
            }
            $this->ins('fin_commandes', $row);
        }
    }

    /** Lubrifiants : réception en magasin, réassorts du présentoir, ventes quotidiennes. Effet nul sur le stock réel une fois effacé. */
    private function lubrifiants(array $jours, $pompistes, $gerant)
    {
        $parJour = [];
        if (!Schema::hasTable('lub_produits')) {
            return $parJour;
        }
        $produits = DB::table('lub_produits')->where('actif', true)->where('prix_vente', '>', 0)->orderBy('ordre')->get();
        if ($produits->isEmpty()) {
            return $parJour;
        }
        $vendus = [];
        $lignes = [];
        foreach ($jours as $j) {
            $parJour[$j] = 0;
            $choisis = $produits->random(min($produits->count(), mt_rand(4, 9)));
            foreach ($choisis as $p) {
                $q = $p->unite === 'fut' ? 0 : ($p->unite === 'litre' || $p->unite === 'kg' ? mt_rand(2, 12) : mt_rand(1, 5));
                if ($q <= 0) {
                    continue;
                }
                $lignes[] = [$j, $p, $q];
                $vendus[$p->id] = ($vendus[$p->id] ?? 0) + $q;
                $parJour[$j] += $q * (float) $p->prix_vente;
            }
        }
        $user = $gerant ? $gerant->id : null;
        // Réception unique le premier jour, réassort du présentoir du même volume
        foreach ($vendus as $pid => $q) {
            $p = $produits->firstWhere('id', $pid);
            $base = ['date' => $jours[0], 'produit_id' => $pid, 'reference' => 'BL DEMO', 'user_id' => $user, 'commentaire' => 'Donnée de démonstration'];
            $this->ins('lub_mouvements', $base + ['lot' => 'DEMO-REC', 'type' => 'reception', 'emplacement' => 'magasin', 'quantite' => $q, 'prix_unitaire' => $p->prix_achat]);
            $this->ins('lub_mouvements', $base + ['lot' => 'DEMO-REA', 'type' => 'reassort', 'emplacement' => 'magasin', 'quantite' => -$q, 'prix_unitaire' => $p->prix_achat]);
            $this->ins('lub_mouvements', $base + ['lot' => 'DEMO-REA', 'type' => 'reassort', 'emplacement' => 'presentoir', 'quantite' => $q, 'prix_unitaire' => $p->prix_achat]);
        }
        foreach ($lignes as $n => $l) {
            list($j, $p, $q) = $l;
            $this->ins('lub_mouvements', [
                'lot' => 'DEMO-VEN-' . str_replace('-', '', $j), 'date' => $j, 'type' => 'vente', 'produit_id' => $p->id, 'emplacement' => 'presentoir',
                'quantite' => -$q, 'prix_unitaire' => $p->prix_vente, 'poste' => $n % 2 ? 'soir' : 'matin',
                'pompiste_id' => $pompistes[$n % count($pompistes)]->id, 'user_id' => $user, 'commentaire' => 'Donnée de démonstration',
            ]);
        }
        // Commande de lubrifiants correspondante dans Finance
        if (Schema::hasTable('fin_commandes') && $vendus) {
            $montant = 0;
            foreach ($vendus as $pid => $q) {
                $montant += $q * (float) $produits->firstWhere('id', $pid)->prix_achat;
            }
            if ($montant > 0) {
                $liv = Carbon::parse($jours[0]);
                $this->ins('fin_commandes', [
                    'numero' => 'LUB-DEMO', 'type' => 'lubrifiant', 'date_commande' => $liv->copy()->subDays(2)->toDateString(), 'montant' => round($montant, 2),
                    'date_livraison' => $jours[0], 'numero_bl' => 'BL DEMO', 'echeance' => $liv->copy()->addDays(30)->toDateString(),
                    'statut' => 'livree', 'source' => 'demo', 'commentaire' => 'Donnée de démonstration',
                ]);
            }
        }
        return $parJour;
    }

    /** Lavages et recettes du jour (lubrifiants, accessoires, lavages) */
    private function recettes(array $jours, array $lub)
    {
        $formules = [
            ['Voiture', ['complet' => 6000]], ['Voiture', ['carosserie' => 3000]], ['Voiture', ['carosserie' => 3000, 'moteur' => 3000]],
            ['4x4', ['complet' => 8000]], ['4x4', ['carosserie' => 5000]], ['Taxi', ['carosserie' => 2500]],
            ['Camion', ['graissage' => 7500]], ['Voiture', ['pulv' => 2000]], ['4x4', ['graissage' => 5000, 'pulv' => 2000]],
        ];
        $lettres = ['AA', 'AB', 'AD', 'AE', 'AF', 'BA', 'BB', 'BC'];
        foreach ($jours as $j) {
            if (DB::table('recettes')->where('date_recette', $j)->exists() || DB::table('lavages')->where('date_lavage', $j)->exists()) {
                continue; // recettes réelles déjà saisies ce jour-là
            }
            $totalLav = 0;
            for ($i = 0, $n = mt_rand(2, 9); $i < $n; $i++) {
                $f = $this->choix($formules);
                $row = ['num_vehicule' => 'DK-' . mt_rand(1000, 9999) . '-' . $this->choix($lettres), 'type' => $f[0], 'date_lavage' => $j,
                    'carosserie' => 0, 'moteur' => 0, 'graissage' => 0, 'pulv' => 0, 'complet' => 0];
                foreach ($f[1] as $k => $v) {
                    $row[$k] = $v;
                    $totalLav += $v;
                }
                $this->ins('lavages', $row);
            }
            $this->ins('recettes', [
                'date_recette' => $j, 'totallav' => $totalLav, 'totallub' => round($lub[$j] ?? 0),
                'totalacc' => $this->arrondi($this->alea(0, 45000), 500), 'totalfut' => mt_rand(1, 10) === 1 ? 5000 : 0,
            ]);
        }
    }

    /** Encaissements : chaque semaine, quelques clients règlent leurs bons de démo */
    private function encaissements(array $jours, array $clients)
    {
        foreach ($jours as $j) {
            if (Carbon::parse($j)->dayOfWeek !== 5) {
                continue; // le vendredi
            }
            $sid = DB::table('syntheses')->whereNull('deleted_at')->where('date', $j)->value('id');
            if (!$sid) {
                continue;
            }
            $demoBons = DB::table('demo_lignes')->where('table_name', 'bon_clients')->pluck('row_id');
            $bons = DB::table('bon_clients')->whereIn('id', $demoBons)->whereNull('encaissement_id')
                ->whereIn('caisse_id', DB::table('caisses')->where('date_caisse', '<', $j)->pluck('id'))->get();
            foreach ($bons->groupBy('client_id') as $client => $liste) {
                if (mt_rand(1, 100) > 60) {
                    continue;
                }
                $type = $this->choix(['espece', 'cheque', 'virement']);
                foreach ($liste as $b) {
                    $eid = $this->ins('encaissements', ['type' => $type, 'synthese_id' => $sid, 'client_id' => $client, 'etat' => 0]);
                    DB::table('bon_clients')->where('id', $b->id)->update(['encaissement_id' => $eid]);
                }
            }
        }
    }

    /** Paie : bulletins du mois pour les salariés (fiches créées depuis les comptes s'il n'y en a pas) */
    private function paie($mois, $pompistes, $chef, $gerant)
    {
        if (!Schema::hasTable('paie_employes') || !class_exists(PaieCalcul::class)) {
            return;
        }
        $employes = DB::table('paie_employes')->where('actif', true)->get();
        if ($employes->isEmpty()) {
            $equipe = [];
            foreach ($pompistes as $u) {
                $equipe[] = [$u, 'Pompiste', 90000, 0];
            }
            if ($chef) {
                $equipe[] = [$chef, 'Chef de piste', 180000, 20000];
            }
            $i = 0;
            foreach ($equipe as $e) {
                list($u, $fonction, $base, $sur) = $e;
                $nom = preg_split('/\s+/', trim($u->name), 2);
                $this->ins('paie_employes', [
                    'user_id' => $u->id, 'matricule' => 'D' . str_pad(++$i, 3, '0', STR_PAD_LEFT), 'prenom' => $nom[0], 'nom' => ($nom[1] ?? '') . ' (démo)',
                    'fonction' => $fonction, 'type_contrat' => 'CDI', 'date_embauche' => Carbon::parse($mois . '-01')->subMonths(mt_rand(6, 70))->toDateString(),
                    'situation' => $this->choix(['celibataire', 'marie', 'marie']), 'enfants' => mt_rand(0, 3), 'salaire_base' => $base, 'sursalaire' => $sur,
                    'indemnite_transport' => 26000, 'ipm' => 1, 'mode_paiement' => $this->choix(['especes', 'wave']), 'actif' => 1,
                ]);
            }
            $this->ins('paie_employes', [
                'matricule' => 'D' . str_pad(++$i, 3, '0', STR_PAD_LEFT), 'prenom' => 'Ibrahima', 'nom' => 'Sarr (démo)', 'fonction' => 'Laveur',
                'type_contrat' => 'CDD', 'date_embauche' => Carbon::parse($mois . '-01')->subMonths(4)->toDateString(), 'situation' => 'celibataire',
                'enfants' => 0, 'salaire_base' => 70000, 'sursalaire' => 0, 'indemnite_transport' => 20000, 'ipm' => 0, 'mode_paiement' => 'especes', 'actif' => 1,
            ]);
            $employes = DB::table('paie_employes')->where('actif', true)->get();
        }

        $params = PaieCalcul::defauts();
        foreach (DB::table('paie_parametres')->pluck('valeur', 'cle') as $cle => $valeur) {
            if (in_array($cle, ['ir_bareme', 'ir_reductions', 'trimf_bareme'], true)) {
                $d = json_decode($valeur, true);
                if (is_array($d)) {
                    $params[$cle] = $d;
                }
            } elseif (in_array($cle, ['raison_sociale', 'etablissement', 'ninea', 'rccm', 'numero_ipres', 'numero_css', 'adresse'], true)) {
                $params[$cle] = (string) $valeur;
            } else {
                $params[$cle] = (float) $valeur;
            }
        }
        $moteur = new PaieCalcul($params);

        foreach ($employes->values() as $n => $e) {
            if (DB::table('paie_bulletins')->where('employe_id', $e->id)->where('mois', $mois)->exists()) {
                continue;
            }
            $tab = (array) $e;
            $tab['primes_fixes'] = $e->primes_fixes ? json_decode($e->primes_fixes, true) : [];
            if ((float) $tab['salaire_base'] <= 0) {
                $tab['salaire_base'] = 90000; // fiche réelle sans salaire : montant d'exemple pour le bulletin de démo
            }
            $v = [];
            if (mt_rand(1, 100) <= 60) {
                $v['hs15'] = mt_rand(4, 16);
            }
            if (mt_rand(1, 100) <= 25) {
                $v['hs60'] = mt_rand(2, 8);
            }
            if (mt_rand(1, 100) <= 15) {
                $v['jours_absence'] = mt_rand(1, 2);
            }
            if (mt_rand(1, 100) <= 40) {
                $v['primes'] = [['libelle' => 'Prime de rendement', 'montant' => $this->arrondi($this->alea(5000, 25000), 1000), 'imposable' => true]];
            }
            if (mt_rand(1, 100) <= 35) {
                $v['retenues'] = [['libelle' => 'Avance sur salaire', 'montant' => $this->arrondi($this->alea(10000, 40000), 5000)]];
            }
            $r = $moteur->calculer($tab, $mois, $v);
            $statut = $n === 0 ? 'brouillon' : 'valide';
            $snap = array_intersect_key($tab, array_flip(['matricule', 'prenom', 'nom', 'fonction', 'categorie', 'date_embauche', 'type_contrat', 'cadre', 'situation', 'enfants', 'numero_ipres', 'numero_css', 'cni', 'salaire_base', 'mode_paiement']));
            $snap['parts'] = $r['fiscal']['parts'];
            $this->ins('paie_bulletins', [
                'employe_id' => $e->id, 'mois' => $mois, 'variables' => $v ? json_encode($v) : null, 'lignes' => json_encode($r), 'employe' => json_encode($snap),
                'brut' => $r['brut'], 'brut_imposable' => $r['brut_imposable'], 'retenues' => $r['retenues_total'], 'ir' => $r['ir'], 'trimf' => $r['trimf'],
                'net' => $r['net'], 'charges_patronales' => $r['charges_patronales'], 'cout_total' => $r['cout_total'], 'statut' => $statut,
                'valide_par' => $statut === 'valide' && $gerant ? $gerant->id : null, 'valide_le' => $statut === 'valide' ? $this->now : null,
            ]);
        }
    }
}
