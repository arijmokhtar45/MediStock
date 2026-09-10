-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 10 sep. 2026 à 13:24
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `medistock`
--

-- --------------------------------------------------------

--
-- Structure de la table `alertes`
--

CREATE TABLE `alertes` (
  `id` int(11) NOT NULL,
  `type_alerte` enum('stock_faible','expiration_proche','expiration_depassee','rupture_prevue_ia') NOT NULL,
  `medicament_id` int(11) DEFAULT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `commande_id` int(11) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `statut` enum('active','traitee') NOT NULL DEFAULT 'active',
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `alertes`
--

INSERT INTO `alertes` (`id`, `type_alerte`, `medicament_id`, `lot_id`, `commande_id`, `message`, `statut`, `date_creation`, `date_traitement`) VALUES
(1, 'expiration_proche', 2, 2, NULL, 'Le lot LOT-2026-002 de \"Amoxicilline\" expire le 2026-08-15', 'active', '2026-07-28 19:03:05', NULL),
(2, 'rupture_prevue_ia', 1, NULL, NULL, 'Doliprane risque une rupture dans 30 jours (prévision IA, au rythme de vente actuel).', 'traitee', '2026-08-01 14:43:46', '2026-09-06 19:46:52'),
(4, 'stock_faible', 3, NULL, NULL, 'Stock faible : veuillez réapprovisionner \"Efferalgan\" (reste 14)', 'active', '2026-08-04 16:17:00', NULL),
(5, 'stock_faible', 2, NULL, NULL, 'Stock faible : veuillez réapprovisionner \"Amoxicilline\" (reste 0)', 'active', '2026-08-05 16:17:05', NULL),
(6, 'rupture_prevue_ia', 2, NULL, NULL, 'Amoxicilline risque une rupture dans 0 jours (prévision IA, au rythme de vente actuel).', 'traitee', '2026-08-05 16:17:21', '2026-08-29 14:27:15'),
(7, 'expiration_proche', 5, 6, NULL, 'Le lot LOT-2026-001 de \"kakaoo\" expire le 2026-08-06', 'traitee', '2026-08-05 16:25:46', '2026-08-05 16:27:39'),
(8, 'rupture_prevue_ia', 2, NULL, NULL, 'Amoxicilline risque une rupture dans 23 jours (prévision IA).', 'active', '2026-09-06 19:46:52', NULL),
(9, 'rupture_prevue_ia', 3, NULL, NULL, 'Efferalgan risque une rupture dans 0 jours (prévision IA).', 'active', '2026-09-06 19:46:52', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `nom` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `description`) VALUES
(1, 'Antalgique', 'Médicaments contre la douleur'),
(2, 'Antibiotique', 'Traitement des infections bactériennes'),
(3, 'Antipyrétique', 'Contre la fièvre'),
(4, 'Vitamines', 'Compléments alimentaires');

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(11) NOT NULL,
  `fournisseur_id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `date_commande` datetime NOT NULL DEFAULT current_timestamp(),
  `date_livraison_prevue` date DEFAULT NULL,
  `date_livraison_reelle` date DEFAULT NULL,
  `statut` enum('en_attente','livree_partiellement','livree','annulee') NOT NULL DEFAULT 'en_attente',
  `montant_total` decimal(12,3) NOT NULL DEFAULT 0.000,
  `montant_paye` decimal(12,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `commandes`
--

INSERT INTO `commandes` (`id`, `fournisseur_id`, `utilisateur_id`, `date_commande`, `date_livraison_prevue`, `date_livraison_reelle`, `statut`, `montant_total`, `montant_paye`) VALUES
(1, 1, 1, '2026-08-29 14:51:54', '2026-08-30', '2026-08-29', 'livree', 50.000, 0.000),
(2, 2, 1, '2026-08-29 15:30:23', '2026-08-30', '2026-08-29', 'livree', 25.000, 0.000);

-- --------------------------------------------------------

--
-- Structure de la table `details_commandes`
--

CREATE TABLE `details_commandes` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,3) NOT NULL,
  `quantite_recue` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `details_commandes`
--

INSERT INTO `details_commandes` (`id`, `commande_id`, `medicament_id`, `quantite`, `prix_unitaire`, `quantite_recue`) VALUES
(1, 1, 4, 10, 5.000, 0),
(2, 2, 4, 5, 5.000, 5);

-- --------------------------------------------------------

--
-- Structure de la table `details_ventes`
--

CREATE TABLE `details_ventes` (
  `id` int(11) NOT NULL,
  `vente_id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,3) NOT NULL,
  `sous_total` decimal(12,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `details_ventes`
--

INSERT INTO `details_ventes` (`id`, `vente_id`, `medicament_id`, `lot_id`, `quantite`, `prix_unitaire`, `sous_total`) VALUES
(1, 1, 2, 2, 1, 5.500, 5.500),
(2, 2, 1, 1, 20, 2.500, 50.000),
(3, 3, 1, 1, 25, 2.500, 62.500),
(4, 4, 1, 1, 30, 2.500, 75.000),
(5, 5, 1, 1, 35, 2.500, 87.500),
(6, 6, 1, 1, 40, 2.500, 100.000),
(7, 7, 3, 3, 14, 3.200, 44.800),
(8, 8, 2, 2, 19, 5.500, 104.500),
(9, 10, 2, 5, 2, 5.500, 11.000);

--
-- Déclencheurs `details_ventes`
--
DELIMITER $$
CREATE TRIGGER `trg_apres_vente` AFTER INSERT ON `details_ventes` FOR EACH ROW BEGIN
    DECLARE v_utilisateur INT;

    UPDATE lots
    SET quantite = quantite - NEW.quantite,
        statut = IF(quantite - NEW.quantite <= 0, 'epuise', statut)
    WHERE id = NEW.lot_id;

    SELECT utilisateur_id INTO v_utilisateur FROM ventes WHERE id = NEW.vente_id;

    INSERT INTO mouvements_stock (medicament_id, lot_id, type_mouvement, quantite, motif, utilisateur_id)
    VALUES (NEW.medicament_id, NEW.lot_id, 'sortie', -NEW.quantite, CONCAT('Vente #', NEW.vente_id), v_utilisateur);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `fournisseurs`
--

CREATE TABLE `fournisseurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(120) NOT NULL,
  `contact_personne` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `solde_du` decimal(12,3) NOT NULL DEFAULT 0.000,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fournisseurs`
--

INSERT INTO `fournisseurs` (`id`, `nom`, `contact_personne`, `telephone`, `email`, `adresse`, `solde_du`, `date_creation`) VALUES
(1, 'Pharma Distrib Tunisie', 'Mohamed Ali', '71123456', 'contact@pharmadistrib.tn', NULL, 0.000, '2026-07-28 19:03:05'),
(2, 'MediSup', 'Ines Bouazizi', '71987654', 'contact@medisup.tn', NULL, 0.000, '2026-07-28 19:03:05');

-- --------------------------------------------------------

--
-- Structure de la table `lots`
--

CREATE TABLE `lots` (
  `id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `fournisseur_id` int(11) DEFAULT NULL,
  `numero_lot` varchar(50) NOT NULL,
  `quantite_initiale` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_achat_unitaire` decimal(10,3) NOT NULL DEFAULT 0.000,
  `date_entree` date NOT NULL,
  `date_expiration` date NOT NULL,
  `statut` enum('actif','epuise','expire') NOT NULL DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `lots`
--

INSERT INTO `lots` (`id`, `medicament_id`, `fournisseur_id`, `numero_lot`, `quantite_initiale`, `quantite`, `prix_achat_unitaire`, `date_entree`, `date_expiration`, `statut`) VALUES
(1, 1, 1, 'LOT-2026-001', 15, 50, 1.200, '2026-06-01', '2026-12-31', 'actif'),
(2, 2, 1, 'LOT-2026-002', 20, 0, 3.000, '2026-06-05', '2026-08-15', 'epuise'),
(3, 3, 2, 'LOT-2026-003', 30, 0, 1.800, '2026-07-01', '2027-05-01', 'epuise'),
(4, 4, 2, 'LOT-2026--004', 50, 50, 5.000, '2026-07-31', '2027-12-31', 'actif'),
(5, 2, 1, 'LOT-2026-001', 10, 8, 5.500, '2026-08-05', '2027-12-31', 'actif'),
(6, 5, 2, 'LOT-2026-001', 3, 3, 2000.000, '2026-01-08', '2026-08-06', 'actif'),
(7, 5, 1, 'LOT-2026-001', 5, 5, 2000.000, '2026-07-08', '2026-08-03', 'actif'),
(8, 4, 2, 'LOT-2026--004', 5, 5, 5.000, '2026-08-29', '2026-09-29', 'actif');

--
-- Déclencheurs `lots`
--
DELIMITER $$
CREATE TRIGGER `trg_verifier_expiration_lot` AFTER INSERT ON `lots` FOR EACH ROW BEGIN
    DECLARE v_nom VARCHAR(150);
    IF DATEDIFF(NEW.date_expiration, CURDATE()) BETWEEN 0 AND 30 THEN
        SELECT nom INTO v_nom FROM medicaments WHERE id = NEW.medicament_id;
        INSERT INTO alertes (type_alerte, medicament_id, lot_id, message)
        VALUES ('expiration_proche', NEW.medicament_id, NEW.id,
                CONCAT('Le lot ', NEW.numero_lot, ' de "', v_nom, '" expire le ', NEW.date_expiration));
    END IF;

    INSERT INTO mouvements_stock (medicament_id, lot_id, type_mouvement, quantite, motif, utilisateur_id)
    VALUES (NEW.medicament_id, NEW.id, 'entree', NEW.quantite_initiale,
            CONCAT('Réception lot ', NEW.numero_lot), 1);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_verifier_stock_faible` AFTER UPDATE ON `lots` FOR EACH ROW BEGIN
    DECLARE v_total INT;
    DECLARE v_min INT;
    DECLARE v_nom VARCHAR(150);
    DECLARE v_deja_alerte INT;

    SELECT COALESCE(SUM(quantite),0) INTO v_total
    FROM lots WHERE medicament_id = NEW.medicament_id AND statut = 'actif';

    SELECT quantite_minimale, nom INTO v_min, v_nom
    FROM medicaments WHERE id = NEW.medicament_id;

    IF v_total <= v_min THEN
        SELECT COUNT(*) INTO v_deja_alerte
        FROM alertes
        WHERE medicament_id = NEW.medicament_id AND type_alerte = 'stock_faible' AND statut = 'active';

        IF v_deja_alerte = 0 THEN
            INSERT INTO alertes (type_alerte, medicament_id, message)
            VALUES ('stock_faible', NEW.medicament_id,
                    CONCAT('Stock faible : veuillez réapprovisionner "', v_nom, '" (reste ', v_total, ')'));
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `medicaments`
--

CREATE TABLE `medicaments` (
  `id` int(11) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `categorie_id` int(11) DEFAULT NULL,
  `fabricant` varchar(120) DEFAULT NULL,
  `forme` varchar(50) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `code_barre` varchar(50) DEFAULT NULL,
  `prix_achat` decimal(10,3) NOT NULL DEFAULT 0.000,
  `prix_vente` decimal(10,3) NOT NULL DEFAULT 0.000,
  `quantite_minimale` int(11) NOT NULL DEFAULT 10,
  `necessite_ordonnance` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `medicaments`
--

INSERT INTO `medicaments` (`id`, `nom`, `categorie_id`, `fabricant`, `forme`, `dosage`, `code_barre`, `prix_achat`, `prix_vente`, `quantite_minimale`, `necessite_ordonnance`, `description`, `actif`, `date_creation`) VALUES
(1, 'Doliprane', 1, 'Sanofi', 'Comprimé', '500mg', '6111234567890', 1.200, 2.500, 10, 0, NULL, 1, '2026-07-28 19:03:05'),
(2, 'Amoxicilline', 2, 'Adwya', 'Gélule', '500mg', '6111234567891', 3.000, 5.500, 5, 0, '', 1, '2026-07-28 19:03:05'),
(3, 'Efferalgan', 3, 'UPSA', 'Comprimé effervescent', '1g', '6111234567892', 1.800, 3.200, 15, 0, NULL, 1, '2026-07-28 19:03:05'),
(4, 'Fervex', 2, '', '', '', NULL, 5.000, 7.000, 30, 0, '', 1, '2026-07-31 11:24:35'),
(5, 'kakaoo', 4, 'Adwya', '', '', NULL, 2.000, 3000.000, 5, 0, '', 1, '2026-08-05 16:24:41');

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_stock`
--

CREATE TABLE `mouvements_stock` (
  `id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `type_mouvement` enum('entree','sortie','ajustement_inventaire') NOT NULL,
  `quantite` int(11) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `date_mouvement` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mouvements_stock`
--

INSERT INTO `mouvements_stock` (`id`, `medicament_id`, `lot_id`, `type_mouvement`, `quantite`, `motif`, `utilisateur_id`, `date_mouvement`) VALUES
(1, 1, 1, 'entree', 15, 'Réception lot LOT-2026-001', 1, '2026-07-28 19:03:05'),
(2, 2, 2, 'entree', 20, 'Réception lot LOT-2026-002', 1, '2026-07-28 19:03:05'),
(3, 3, 3, 'entree', 30, 'Réception lot LOT-2026-003', 1, '2026-07-28 19:03:05'),
(4, 4, 4, 'entree', 50, 'Réception lot LOT-2026--004', 1, '2026-07-31 13:05:55'),
(5, 2, 2, 'sortie', -1, 'Vente #1', 1, '2026-07-31 14:59:05'),
(6, 1, 1, 'sortie', -20, 'Vente #2', 2, '2026-08-01 12:57:35'),
(7, 1, 1, 'sortie', -25, 'Vente #3', 2, '2026-08-01 12:57:35'),
(8, 1, 1, 'sortie', -30, 'Vente #4', 2, '2026-08-01 12:57:35'),
(9, 1, 1, 'sortie', -35, 'Vente #5', 2, '2026-08-01 12:57:36'),
(10, 1, 1, 'sortie', -40, 'Vente #6', 2, '2026-08-01 12:57:36'),
(11, 3, 3, 'sortie', -14, 'Vente #7', 1, '2026-08-05 15:15:03'),
(12, 2, 2, 'sortie', -19, 'Vente #8', 1, '2026-08-05 16:17:05'),
(13, 2, 5, 'entree', 10, 'Réception lot LOT-2026-001', 1, '2026-08-05 16:22:11'),
(14, 5, 6, 'entree', 3, 'Réception lot LOT-2026-001', 1, '2026-08-05 16:25:46'),
(15, 5, 7, 'entree', 5, 'Réception lot LOT-2026-001', 1, '2026-08-05 16:26:40'),
(16, 4, 8, 'entree', 5, 'Réception lot LOT-2026--004', 1, '2026-08-29 15:31:15'),
(17, 2, 5, 'sortie', -2, 'Vente #10', 1, '2026-09-07 21:42:10');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('administrateur','pharmacien') NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `derniere_connexion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `telephone`, `actif`, `date_creation`, `derniere_connexion`) VALUES
(1, 'Ben Salah', 'Amine', 'admin@medistock.tn', '$2y$10$LlPHhxeng84XI9nVKRFnteWknHn2X32k//KOAV4UTxEPjwlERkhnW', 'administrateur', NULL, 1, '2026-07-28 19:03:05', '2026-09-10 12:11:55'),
(2, 'Trabelsi', 'Sarra', 'pharmacien@medistock.tn', '$2y$10$kLqX01Samuu.lmmucTHd.OxSE5G4.aNnx04ieRBnT37edWv99S5qa', 'pharmacien', NULL, 1, '2026-07-28 19:03:05', '2026-09-09 22:28:36'),
(3, 'Gharbi', 'Youssef', 'stock@medistock.tn', '$2y$10$bqywTmHz2rxtaYV04chkUOzWP/5o09Q3aB1eMC1yMbSVQ2Fep5eRe', 'pharmacien', NULL, 0, '2026-07-28 19:03:05', '2026-09-09 22:33:21');

-- --------------------------------------------------------

--
-- Structure de la table `ventes`
--

CREATE TABLE `ventes` (
  `id` int(11) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `date_vente` datetime NOT NULL DEFAULT current_timestamp(),
  `montant_total` decimal(12,3) NOT NULL DEFAULT 0.000,
  `mode_paiement` enum('especes','carte','cheque') NOT NULL DEFAULT 'especes',
  `type_document` enum('ticket','facture') NOT NULL DEFAULT 'ticket'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ventes`
--

INSERT INTO `ventes` (`id`, `utilisateur_id`, `date_vente`, `montant_total`, `mode_paiement`, `type_document`) VALUES
(1, 1, '2026-07-31 14:59:05', 5.500, 'especes', 'ticket'),
(2, 2, '2026-03-01 00:00:00', 12.500, 'especes', 'ticket'),
(3, 2, '2026-04-01 00:00:00', 62.500, 'especes', 'ticket'),
(4, 2, '2026-05-01 00:00:00', 75.000, 'especes', 'ticket'),
(5, 2, '2026-06-01 00:00:00', 87.500, 'especes', 'ticket'),
(6, 2, '2026-07-01 00:00:00', 100.000, 'especes', 'ticket'),
(7, 1, '2026-08-05 15:15:03', 44.800, 'especes', 'ticket'),
(8, 1, '2026-08-05 16:17:05', 104.500, 'especes', 'ticket'),
(10, 1, '2026-09-07 21:42:10', 11.000, 'especes', 'ticket');

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_lots_fefo`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_lots_fefo` (
`id` int(11)
,`medicament_id` int(11)
,`fournisseur_id` int(11)
,`numero_lot` varchar(50)
,`quantite_initiale` int(11)
,`quantite` int(11)
,`prix_achat_unitaire` decimal(10,3)
,`date_entree` date
,`date_expiration` date
,`statut` enum('actif','epuise','expire')
,`nom_medicament` varchar(150)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_stock_medicaments`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_stock_medicaments` (
`medicament_id` int(11)
,`nom` varchar(150)
,`quantite_minimale` int(11)
,`quantite_totale` decimal(32,0)
,`stock_faible` int(1)
);

-- --------------------------------------------------------

--
-- Structure de la vue `v_lots_fefo`
--
DROP TABLE IF EXISTS `v_lots_fefo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_lots_fefo`  AS SELECT `l`.`id` AS `id`, `l`.`medicament_id` AS `medicament_id`, `l`.`fournisseur_id` AS `fournisseur_id`, `l`.`numero_lot` AS `numero_lot`, `l`.`quantite_initiale` AS `quantite_initiale`, `l`.`quantite` AS `quantite`, `l`.`prix_achat_unitaire` AS `prix_achat_unitaire`, `l`.`date_entree` AS `date_entree`, `l`.`date_expiration` AS `date_expiration`, `l`.`statut` AS `statut`, `m`.`nom` AS `nom_medicament` FROM (`lots` `l` join `medicaments` `m` on(`m`.`id` = `l`.`medicament_id`)) WHERE `l`.`statut` = 'actif' AND `l`.`quantite` > 0 ORDER BY `l`.`date_expiration` ASC ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_stock_medicaments`
--
DROP TABLE IF EXISTS `v_stock_medicaments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_stock_medicaments`  AS SELECT `m`.`id` AS `medicament_id`, `m`.`nom` AS `nom`, `m`.`quantite_minimale` AS `quantite_minimale`, coalesce(sum(`l`.`quantite`),0) AS `quantite_totale`, CASE WHEN coalesce(sum(`l`.`quantite`),0) <= `m`.`quantite_minimale` THEN 1 ELSE 0 END AS `stock_faible` FROM (`medicaments` `m` left join `lots` `l` on(`l`.`medicament_id` = `m`.`id` and `l`.`statut` = 'actif' and `l`.`date_expiration` >= curdate())) GROUP BY `m`.`id`, `m`.`nom`, `m`.`quantite_minimale` ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `alertes`
--
ALTER TABLE `alertes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicament_id` (`medicament_id`),
  ADD KEY `lot_id` (`lot_id`),
  ADD KEY `commande_id` (`commande_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fournisseur_id` (`fournisseur_id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `details_commandes`
--
ALTER TABLE `details_commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `commande_id` (`commande_id`),
  ADD KEY `medicament_id` (`medicament_id`);

--
-- Index pour la table `details_ventes`
--
ALTER TABLE `details_ventes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vente_id` (`vente_id`),
  ADD KEY `medicament_id` (`medicament_id`),
  ADD KEY `lot_id` (`lot_id`);

--
-- Index pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `lots`
--
ALTER TABLE `lots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fournisseur_id` (`fournisseur_id`),
  ADD KEY `idx_lot_expiration` (`date_expiration`),
  ADD KEY `idx_lot_medicament` (`medicament_id`);

--
-- Index pour la table `medicaments`
--
ALTER TABLE `medicaments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_barre` (`code_barre`),
  ADD KEY `categorie_id` (`categorie_id`),
  ADD KEY `idx_med_nom` (`nom`),
  ADD KEY `idx_med_code_barre` (`code_barre`);

--
-- Index pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicament_id` (`medicament_id`),
  ADD KEY `lot_id` (`lot_id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `alertes`
--
ALTER TABLE `alertes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `details_commandes`
--
ALTER TABLE `details_commandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `details_ventes`
--
ALTER TABLE `details_ventes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `lots`
--
ALTER TABLE `lots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `medicaments`
--
ALTER TABLE `medicaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `ventes`
--
ALTER TABLE `ventes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `alertes`
--
ALTER TABLE `alertes`
  ADD CONSTRAINT `alertes_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertes_ibfk_2` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertes_ibfk_3` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`),
  ADD CONSTRAINT `commandes_ibfk_2` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `details_commandes`
--
ALTER TABLE `details_commandes`
  ADD CONSTRAINT `details_commandes_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `details_commandes_ibfk_2` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`);

--
-- Contraintes pour la table `details_ventes`
--
ALTER TABLE `details_ventes`
  ADD CONSTRAINT `details_ventes_ibfk_1` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `details_ventes_ibfk_2` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`),
  ADD CONSTRAINT `details_ventes_ibfk_3` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`);

--
-- Contraintes pour la table `lots`
--
ALTER TABLE `lots`
  ADD CONSTRAINT `lots_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lots_ibfk_2` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `medicaments`
--
ALTER TABLE `medicaments`
  ADD CONSTRAINT `medicaments_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  ADD CONSTRAINT `mouvements_stock_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`),
  ADD CONSTRAINT `mouvements_stock_ibfk_2` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mouvements_stock_ibfk_3` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD CONSTRAINT `ventes_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

DELIMITER $$
--
-- Évènements
--
CREATE DEFINER=`root`@`localhost` EVENT `ev_verifier_expirations` ON SCHEDULE EVERY 1 DAY STARTS '2026-08-06 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    INSERT INTO alertes (type_alerte, medicament_id, lot_id, message)
    SELECT 'expiration_proche', l.medicament_id, l.id,
           CONCAT('Le lot ', l.numero_lot, ' de "', m.nom,
                  '" expire le ', l.date_expiration)
    FROM lots l
    JOIN medicaments m ON m.id = l.medicament_id
    WHERE l.statut = 'actif'
      AND l.quantite > 0
      AND DATEDIFF(l.date_expiration, CURDATE()) BETWEEN 0 AND 30
      AND NOT EXISTS (
          SELECT 1
          FROM alertes a
          WHERE a.lot_id = l.id
            AND a.type_alerte = 'expiration_proche'
      );

    UPDATE lots SET statut = 'expire' WHERE date_expiration < CURDATE() AND statut != 'expire';

    INSERT INTO alertes (type_alerte, medicament_id, lot_id, message)
    SELECT 'expiration_depassee', l.medicament_id, l.id,
           CONCAT('Le lot ', l.numero_lot, ' de "', m.nom, '" a dépassé sa date d''expiration')
    FROM lots l
    JOIN medicaments m ON m.id = l.medicament_id
    WHERE l.statut = 'expire'
    AND NOT EXISTS (
        SELECT 1 FROM alertes a
        WHERE a.lot_id = l.id AND a.type_alerte = 'expiration_depassee'
    );
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
