# Données de démonstration Stylux Oil

Deux commandes Artisan permettent de remplir l'application avec un mois d'activité fictive,
puis de tout effacer avant le lancement réel.

## 1. Générer les données fictives

```bash
php artisan stylux:demo --mois=2026-09 --litres=10000
```

Options :

- `--mois=AAAA-MM` : mois à générer (par défaut 2026-09)
- `--litres=10000` : moyenne de litres de carburant vendus par jour (la moyenne sur la période est exacte)
- `--jusquau=AAAA-MM-JJ` : dernier jour généré (par défaut la veille, ou la fin du mois)
- `--force` : sans demande de confirmation

Ce qui est créé, pour tous les profils :

| Espace | Données |
|---|---|
| Pompiste | Une caisse par pompe et par jour, index électroniques et mécaniques qui se suivent, ventes TPE, bons clients, dépenses, coffre, net versé et écarts (manquants) |
| Chef de piste | Synthèse du jour, relevé des cuves à 08h00, livraisons (réceptions et commandes camion) quand une cuve passe sous 35 %, remises en cuve, fiches lubrifiants et accessoires |
| Gérant | Bons clients répartis sur les 30 clients crédit Stylux, environ 70 % des bons de plus d'une semaine encaissés (espèces, chèque, virement, Wave), lavages, recettes du jour |

- Volumes : plus de ventes le vendredi et le samedi, environ 58 % de gasoil, pertes de cuve d'environ 0,3 % (écart pour 1000 réaliste).
- Les deux derniers jours générés restent "à approuver" pour montrer le circuit de validation.
- Les 30 clients crédit Stylux sont créés s'ils n'existent pas (téléphone, email et adresse à "-").
- Les index des pistolets et le niveau des cuves sont mis à jour à la fin de la période.
- Avant toute modification, les index des pistolets et les niveaux des cuves sont sauvegardés dans
  `storage/app/stylux-demo-sauvegarde.json`.
- La commande refuse de s'exécuter si des caisses existent déjà sur la période.

Prérequis : îlots, pompes, pistolets (carburant `super` ou `gasoil` en minuscules, prix), cuves (carburant, capacité),
au moins un compte pompiste (role_id 1) et un chef de piste (role_id 3).

## 2. Remise à zéro avant le lancement

**Faites d'abord une sauvegarde de la base** (phpMyAdmin > Exporter).

```bash
php artisan stylux:reinitialiser
```

Il faut taper `EFFACER` pour confirmer.

- Vide toutes les tables d'activité : caisses, compteurs, bons clients, dépenses, TPE, synthèses, encaissements,
  commandes, réceptions, remises en cuve, relevés de cuves, lavages, recettes, fiches lubrifiants et accessoires,
  inventaires, mouvements magasin.
- Conserve : utilisateurs, rôles, îlots, pompes, pistolets, cuves, clients, lubrifiants (liste et prix), catégories et produits.
- Remet les index des pistolets et le niveau des cuves à leur valeur d'avant la démo.

Le jour du lancement, saisissez dans l'espace admin les **index réels** des pistolets relevés sur les pompes
et le niveau réel des cuves : c'est le point de départ de la première caisse.
