-- =====================================================================
-- Stylux Oil - rattrapage des migrations non appliquées sur la base en ligne
-- A utiliser SEULEMENT si "php artisan migrate" n'est pas possible (pas de SSH).
-- phpMyAdmin > base de l'API > onglet SQL > coller ce fichier > Exécuter.
-- Faites d'abord une sauvegarde (onglet Exporter).
-- Correspond aux migrations 2021_10_15 à 2022_04_06 du dépôt api-stylux.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `telephone` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `adresse` varchar(255) NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `encaissements` ADD COLUMN `client_id` bigint unsigned NULL;
ALTER TABLE `bon_clients` DROP COLUMN `nom_client`;
ALTER TABLE `bon_clients` ADD COLUMN `client_id` bigint unsigned NULL;
ALTER TABLE `pistolets` DROP COLUMN `reservoir_id`;
ALTER TABLE `encaissements` ADD COLUMN `etat` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `bon_clients` ADD COLUMN `etat` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `bon_clients` ADD COLUMN `encaissement_id` bigint unsigned NULL;
ALTER TABLE `encaissements` DROP COLUMN `montant`;

CREATE TABLE IF NOT EXISTS `lavages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `num_vehicule` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `carosserie` double(12,3) NULL DEFAULT 0,
  `moteur` double(12,3) NULL DEFAULT 0,
  `graissage` double(12,3) NULL DEFAULT 0,
  `pulv` double(12,3) NULL DEFAULT 0,
  `complet` double(12,3) NULL DEFAULT 0,
  `date_lavage` date NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entree_magasins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_entree` date NULL,
  `qte_entree` varchar(255) NOT NULL,
  `produit_id` bigint unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entre_m_s` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prix` int NOT NULL,
  `date_entre` date NOT NULL,
  `quantite` int NOT NULL,
  `produit_id` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sortie_m_s` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prix` int NOT NULL,
  `date_sortie` date NOT NULL,
  `quantite` int NOT NULL,
  `produit_id` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recettes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `totallub` double(12,3) NULL DEFAULT 0,
  `totallav` double(12,3) NULL DEFAULT 0,
  `totalacc` double(12,3) NULL DEFAULT 0,
  `date_recette` date NULL,
  `totalfut` double(12,3) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `produits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `categorie_id` bigint unsigned NOT NULL,
  `nom` varchar(255) NOT NULL,
  `pu` varchar(255) NOT NULL,
  `qte_initiale` double(12,3) NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventaires` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_inventaire` date NOT NULL,
  `produit_id` bigint unsigned NOT NULL,
  `qte_reelle` double(12,3) NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tabinventaires` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_inv` date NULL,
  `approuve` tinyint(1) NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tablubs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dateEnreg` date NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `approuve` tinyint(1) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tabaccs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dateEnreg` date NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `approuve` tinyint(1) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lubrifiants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_lubrifiant` date NULL,
  `produit` varchar(255) NOT NULL,
  `ouverture` double(12,3) NULL DEFAULT 0,
  `entrant` double(12,3) NULL DEFAULT 0,
  `prixunitaire` double(12,3) NULL DEFAULT 0,
  `fermeture` double(12,3) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accessoires` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_accessoire` date NULL,
  `produit` varchar(255) NOT NULL,
  `ouverture` double(12,3) NULL DEFAULT 0,
  `entrant` double(12,3) NULL DEFAULT 0,
  `prixunitaire` double(12,3) NULL DEFAULT 0,
  `fermeture` double(12,3) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `magasins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_inventaire` date NULL,
  `produit` varchar(255) NOT NULL,
  `qteI` double(12,3) NULL DEFAULT 0,
  `puI` double(12,3) NULL DEFAULT 0,
  `qteE` double(12,3) NULL DEFAULT 0,
  `puE` double(12,3) NULL DEFAULT 0,
  `qteS` double(12,3) NULL DEFAULT 0,
  `puS` double(12,3) NULL DEFAULT 0,
  `qteF` double(12,3) NULL DEFAULT 0,
  `qteR` double(12,3) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lubs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `prix` double(12,3) NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liste des lubrifiants (reprise du seeder LubrifiantsTableSeeder)
INSERT INTO `lubs` (`nom`, `prix`) SELECT * FROM (
  SELECT 'HELIX  HX5 15W50   1L', 3000 UNION ALL SELECT 'HELIX  HX5 15W50   5L', 13000 UNION ALL
  SELECT 'HELIX HX3 50 1L', 2400 UNION ALL SELECT 'HELIX HX3 50 5L', 10600 UNION ALL
  SELECT 'HELIX HX7 1L', 3500 UNION ALL SELECT 'HELIX HX7 4L', 13500 UNION ALL
  SELECT 'RIMULA R1 50 1L', 2000 UNION ALL SELECT 'RIMULA R1 50 5L', 10500 UNION ALL
  SELECT 'RIMULA R1 50 VRAC', 1600 UNION ALL SELECT 'RIMULA R2 50 1L', 2300 UNION ALL
  SELECT 'RIMULA R2 50 5L', 10750 UNION ALL SELECT 'RIMULA R2 50 20L', 40000 UNION ALL
  SELECT 'ATF  SPIRAX 1L', 3000 UNION ALL SELECT 'ULTRA 4L', 22000
) AS l WHERE NOT EXISTS (SELECT 1 FROM `lubs`);

-- Enregistre ces migrations comme faites (pour que "php artisan migrate" ne les rejoue pas plus tard)
SET @b := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m, @b FROM (
  SELECT '2021_10_15_105225_create_clients_table' AS m UNION ALL
  SELECT '2021_10_15_115845_add_column_client_id_to_encaissement_table' UNION ALL
  SELECT '2021_10_27_140900_add_client_id_and_remove_nom_client_to_bon_clients' UNION ALL
  SELECT '2021_11_08_150733_delete_id_reservoi_to_pistolets_table' UNION ALL
  SELECT '2021_11_26_132002_add_etat_to_encaissement_table' UNION ALL
  SELECT '2021_11_29_133935_add_etat_bon_client_table' UNION ALL
  SELECT '2021_11_29_142024_delete_montant_to_encaissement_table' UNION ALL
  SELECT '2021_12_08_105816_create_lavages_table' UNION ALL
  SELECT '2021_12_30_094423_create_categories_table' UNION ALL
  SELECT '2022_01_06_115447_create_entree_magasins_table' UNION ALL
  SELECT '2022_01_07_105750_create_entre_m_s_table' UNION ALL
  SELECT '2022_01_07_105842_create_sortie_m_s_table' UNION ALL
  SELECT '2022_01_24_110536_create_recettes_table' UNION ALL
  SELECT '2022_02_02_095658_create_produits_table' UNION ALL
  SELECT '2022_02_02_110722_create_inventaires_table' UNION ALL
  SELECT '2022_02_14_131215_create_tabinventaires_table' UNION ALL
  SELECT '2022_02_17_103518_create_tablubs_table' UNION ALL
  SELECT '2022_02_17_111622_create_tabaccs_table' UNION ALL
  SELECT '2022_02_23_131318_create_lubrifiants_table' UNION ALL
  SELECT '2022_02_23_131414_create_accessoires_table' UNION ALL
  SELECT '2022_03_07_111834_create_magasins_table' UNION ALL
  SELECT '2022_04_06_154129_create_lubs_table'
) AS x WHERE m NOT IN (SELECT `migration` FROM `migrations`);
