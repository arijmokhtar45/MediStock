-- =====================================================================
-- MediStock - Base de données
-- Conception et développement d'une application web intelligente
-- pour la gestion du stock d'une pharmacie
-- SGBD : MySQL (compatible XAMPP / phpMyAdmin)
-- =====================================================================

DROP DATABASE IF EXISTS medistock;
CREATE DATABASE medistock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medistock;

-- =====================================================================
-- 1) UTILISATEURS
-- =====================================================================
CREATE TABLE utilisateurs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(50)  NOT NULL,
    prenom            VARCHAR(50)  NOT NULL,
    email             VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe      VARCHAR(255) NOT NULL,          -- password_hash() côté PHP
    role              ENUM('administrateur','pharmacien') NOT NULL,
    telephone         VARCHAR(20),
    actif             TINYINT(1) NOT NULL DEFAULT 1,
    date_creation     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 2) CATEGORIES
-- =====================================================================
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

-- =====================================================================
-- 3) FOURNISSEURS
-- =====================================================================
CREATE TABLE fournisseurs (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    nom                VARCHAR(120) NOT NULL,
    contact_personne   VARCHAR(100),
    telephone          VARCHAR(20),
    email              VARCHAR(100),
    adresse            VARCHAR(255),
    solde_du           DECIMAL(12,3) NOT NULL DEFAULT 0.000,  -- montant restant à payer
    date_creation      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- 4) MEDICAMENTS (fiche générale du médicament)
-- =====================================================================
CREATE TABLE medicaments (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    nom                VARCHAR(150) NOT NULL,
    categorie_id       INT,
    fabricant          VARCHAR(120),
    forme              VARCHAR(50),          -- comprimé, sirop, injection...
    dosage             VARCHAR(50),
    code_barre         VARCHAR(50) UNIQUE,
    prix_achat         DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    prix_vente         DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    quantite_minimale  INT NOT NULL DEFAULT 10,
    necessite_ordonnance TINYINT(1) NOT NULL DEFAULT 0,
    description        TEXT,
    actif              TINYINT(1) NOT NULL DEFAULT 1,
    date_creation      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_med_nom (nom),
    INDEX idx_med_code_barre (code_barre)
) ENGINE=InnoDB;

-- =====================================================================
-- 5) LOTS (chaque médicament peut avoir plusieurs lots)
-- =====================================================================
CREATE TABLE lots (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    medicament_id      INT NOT NULL,
    fournisseur_id     INT,
    numero_lot         VARCHAR(50) NOT NULL,
    quantite_initiale  INT NOT NULL,
    quantite           INT NOT NULL,           -- quantité restante (décrémentée par les ventes)
    prix_achat_unitaire DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    date_entree        DATE NOT NULL,
    date_expiration    DATE NOT NULL,
    statut             ENUM('actif','epuise','expire') NOT NULL DEFAULT 'actif',
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id) ON DELETE CASCADE,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL,
    INDEX idx_lot_expiration (date_expiration),
    INDEX idx_lot_medicament (medicament_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 6) COMMANDES (achats auprès des fournisseurs)
-- =====================================================================
CREATE TABLE commandes (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    fournisseur_id        INT NOT NULL,
    utilisateur_id        INT NOT NULL,
    date_commande         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_livraison_prevue DATE,
    date_livraison_reelle DATE NULL,
    statut                ENUM('en_attente','livree_partiellement','livree','annulee') NOT NULL DEFAULT 'en_attente',
    montant_total         DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    montant_paye          DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 7) DETAILS_COMMANDES
-- =====================================================================
CREATE TABLE details_commandes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    commande_id    INT NOT NULL,
    medicament_id  INT NOT NULL,
    quantite       INT NOT NULL,
    prix_unitaire  DECIMAL(10,3) NOT NULL,
    quantite_recue INT NOT NULL DEFAULT 0,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 8) VENTES
-- =====================================================================
CREATE TABLE ventes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT NOT NULL,
    date_vente      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    montant_total   DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    mode_paiement   ENUM('especes','carte','cheque') NOT NULL DEFAULT 'especes',
    type_document   ENUM('ticket','facture') NOT NULL DEFAULT 'ticket',
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 9) DETAILS_VENTES (le lot est choisi selon FEFO : First Expired, First Out)
-- =====================================================================
CREATE TABLE details_ventes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    vente_id       INT NOT NULL,
    medicament_id  INT NOT NULL,
    lot_id         INT NOT NULL,
    quantite       INT NOT NULL,
    prix_unitaire  DECIMAL(10,3) NOT NULL,
    sous_total     DECIMAL(12,3) NOT NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id),
    FOREIGN KEY (lot_id) REFERENCES lots(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 10) MOUVEMENTS_STOCK (historique : entrées, sorties, ajustements inventaire)
-- =====================================================================
CREATE TABLE mouvements_stock (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    medicament_id  INT NOT NULL,
    lot_id         INT,
    type_mouvement ENUM('entree','sortie','ajustement_inventaire') NOT NULL,
    quantite       INT NOT NULL,           -- positive ou négative selon le type
    motif          VARCHAR(255),
    utilisateur_id INT NOT NULL,
    date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id),
    FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 11) ALERTES
-- =====================================================================
CREATE TABLE alertes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    type_alerte    ENUM('stock_faible','expiration_proche','expiration_depassee','rupture_prevue_ia') NOT NULL,
    medicament_id  INT,
    lot_id         INT,
    commande_id    INT,
    message        VARCHAR(255) NOT NULL,
    statut         ENUM('active','traitee') NOT NULL DEFAULT 'active',
    date_creation  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME NULL,
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id) ON DELETE CASCADE,
    FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- VUE : Stock disponible par médicament (somme des lots actifs, non expirés)
-- =====================================================================
CREATE VIEW v_stock_medicaments AS
SELECT
    m.id AS medicament_id,
    m.nom,
    m.quantite_minimale,
    COALESCE(SUM(l.quantite),0) AS quantite_totale,
    CASE WHEN COALESCE(SUM(l.quantite),0) <= m.quantite_minimale THEN 1 ELSE 0 END AS stock_faible
FROM medicaments m
LEFT JOIN lots l ON l.medicament_id = m.id AND l.statut = 'actif' AND l.date_expiration >= CURDATE()
GROUP BY m.id, m.nom, m.quantite_minimale;

-- =====================================================================
-- VUE : Lots triés en FEFO (le lot le plus proche de l'expiration sort en premier)
-- =====================================================================
CREATE VIEW v_lots_fefo AS
SELECT l.*, m.nom AS nom_medicament
FROM lots l
JOIN medicaments m ON m.id = l.medicament_id
WHERE l.statut = 'actif' AND l.quantite > 0
ORDER BY l.date_expiration ASC;

-- =====================================================================
-- TRIGGER : après une vente (details_ventes), on décrémente le lot
-- et on enregistre le mouvement de stock
-- =====================================================================
DELIMITER //
CREATE TRIGGER trg_apres_vente
AFTER INSERT ON details_ventes
FOR EACH ROW
BEGIN
    DECLARE v_utilisateur INT;

    -- décrémenter la quantité du lot choisi (FEFO géré côté PHP au moment de la vente)
    UPDATE lots
    SET quantite = quantite - NEW.quantite,
        statut = IF(quantite - NEW.quantite <= 0, 'epuise', statut)
    WHERE id = NEW.lot_id;

    SELECT utilisateur_id INTO v_utilisateur FROM ventes WHERE id = NEW.vente_id;

    INSERT INTO mouvements_stock (medicament_id, lot_id, type_mouvement, quantite, motif, utilisateur_id)
    VALUES (NEW.medicament_id, NEW.lot_id, 'sortie', -NEW.quantite, CONCAT('Vente #', NEW.vente_id), v_utilisateur);
END //
DELIMITER ;

SET GLOBAL event_scheduler = ON;

-- =====================================================================
-- TRIGGER : après INSERTION d'un lot (Vérif stock faible + Expiration + Mouvement)
-- =====================================================================
DELIMITER //
CREATE TRIGGER trg_lots_after_insert
AFTER INSERT ON lots
FOR EACH ROW
BEGIN
    DECLARE v_total INT;
    DECLARE v_min INT;
    DECLARE v_nom VARCHAR(150);
    DECLARE v_deja_alerte INT;

    -- Mouvement de stock automatique pour l'entrée
    INSERT INTO mouvements_stock (medicament_id, lot_id, type_mouvement, quantite, motif, utilisateur_id)
    VALUES (NEW.medicament_id, NEW.id, 'entree', NEW.quantite_initiale, CONCAT('Réception lot ', NEW.numero_lot), COALESCE(@medistock_user_id, 1));

    -- Vérification expiration proche
    IF DATEDIFF(NEW.date_expiration, CURDATE()) BETWEEN 0 AND 30 THEN
        SELECT nom INTO v_nom FROM medicaments WHERE id = NEW.medicament_id;
        INSERT INTO alertes (type_alerte, medicament_id, lot_id, message)
        VALUES ('expiration_proche', NEW.medicament_id, NEW.id,
                CONCAT('Le lot ', NEW.numero_lot, ' de "', v_nom, '" expire le ', NEW.date_expiration));
    END IF;

    -- Vérification stock faible
    SELECT COALESCE(SUM(quantite),0) INTO v_total
    FROM lots WHERE medicament_id = NEW.medicament_id AND statut = 'actif' AND date_expiration >= CURDATE();

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
    ELSE
        UPDATE alertes SET statut = 'traitee', date_traitement = NOW()
        WHERE medicament_id = NEW.medicament_id AND type_alerte = 'stock_faible' AND statut = 'active';
    END IF;
END //
DELIMITER ;

-- =====================================================================
-- TRIGGER : après MISE À JOUR d'un lot (ex: après une vente)
-- =====================================================================
DELIMITER //
CREATE TRIGGER trg_lots_after_update
AFTER UPDATE ON lots
FOR EACH ROW
BEGIN
    DECLARE v_total INT;
    DECLARE v_min INT;
    DECLARE v_nom VARCHAR(150);
    DECLARE v_deja_alerte INT;

    SELECT COALESCE(SUM(quantite),0) INTO v_total
    FROM lots WHERE medicament_id = NEW.medicament_id AND statut = 'actif' AND date_expiration >= CURDATE();

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
    ELSE
        UPDATE alertes SET statut = 'traitee', date_traitement = NOW()
        WHERE medicament_id = NEW.medicament_id AND type_alerte = 'stock_faible' AND statut = 'active';
    END IF;
END //
DELIMITER ;

-- =====================================================================
-- TRIGGER : après MISE À JOUR d'un médicament (changement seuil min)
-- =====================================================================
DELIMITER //
CREATE TRIGGER trg_medicaments_after_update
AFTER UPDATE ON medicaments
FOR EACH ROW
BEGIN
    DECLARE v_total INT;
    DECLARE v_deja_alerte INT;

    IF OLD.quantite_minimale <> NEW.quantite_minimale THEN
        SELECT COALESCE(SUM(quantite),0) INTO v_total
        FROM lots WHERE medicament_id = NEW.id AND statut = 'actif' AND date_expiration >= CURDATE();

        IF v_total <= NEW.quantite_minimale THEN
            SELECT COUNT(*) INTO v_deja_alerte
            FROM alertes
            WHERE medicament_id = NEW.id AND type_alerte = 'stock_faible' AND statut = 'active';

            IF v_deja_alerte = 0 THEN
                INSERT INTO alertes (type_alerte, medicament_id, message)
                VALUES ('stock_faible', NEW.id,
                        CONCAT('Stock faible (nouveau seuil) : "', NEW.nom, '" (reste ', v_total, ')'));
            END IF;
        ELSE
            UPDATE alertes SET statut = 'traitee', date_traitement = NOW()
            WHERE medicament_id = NEW.id AND type_alerte = 'stock_faible' AND statut = 'active';
        END IF;
    END IF;
END //
DELIMITER ;

-- =====================================================================
-- EVENT : vérification quotidienne (Expirations + Stock Faible)
-- =====================================================================
DELIMITER //
CREATE EVENT IF NOT EXISTS ev_verifier_expirations
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO
BEGIN
    UPDATE lots SET statut = 'expire' WHERE date_expiration < CURDATE() AND statut != 'expire';

    INSERT INTO alertes (type_alerte, medicament_id, lot_id, message)
    SELECT 'expiration_depassee', l.medicament_id, l.id,
           CONCAT('Le lot ', l.numero_lot, ' de "', m.nom, '" a dépassé sa date d''expiration')
    FROM lots l
    JOIN medicaments m ON m.id = l.medicament_id
    WHERE l.statut = 'expire'
    AND NOT EXISTS (
        SELECT 1 FROM alertes a WHERE a.lot_id = l.id AND a.type_alerte = 'expiration_depassee'
    );

    INSERT INTO alertes (type_alerte, medicament_id, lot_id, message)
    SELECT 'expiration_proche', l.medicament_id, l.id,
           CONCAT('Le lot ', l.numero_lot, ' de "', m.nom, '" expire le ', l.date_expiration)
    FROM lots l
    JOIN medicaments m ON m.id = l.medicament_id
    WHERE l.statut = 'actif'
      AND l.quantite > 0
      AND DATEDIFF(l.date_expiration, CURDATE()) BETWEEN 0 AND 30
      AND NOT EXISTS (
          SELECT 1 FROM alertes a WHERE a.lot_id = l.id AND a.type_alerte = 'expiration_proche'
      );
      
    INSERT INTO alertes (type_alerte, medicament_id, message)
    SELECT 'stock_faible', v.medicament_id, CONCAT('Stock faible (suite expiration) : "', v.nom, '" (reste ', v.quantite_totale, ')')
    FROM v_stock_medicaments v
    WHERE v.stock_faible = 1
    AND NOT EXISTS (
        SELECT 1 FROM alertes a WHERE a.medicament_id = v.medicament_id AND a.type_alerte = 'stock_faible' AND a.statut = 'active'
    );
END //
DELIMITER ;

-- =====================================================================
-- DONNEES DE TEST
-- =====================================================================
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES
('Ben Salah', 'Amine', 'admin@medistock.tn', '$2y$10$exempleHashRemplacerParPasswordHash', 'administrateur'),
('Trabelsi', 'Sarra', 'pharmacien@medistock.tn', '$2y$10$exempleHashRemplacerParPasswordHash', 'pharmacien');

INSERT INTO categories (nom, description) VALUES
('Antalgique', 'Médicaments contre la douleur'),
('Antibiotique', 'Traitement des infections bactériennes'),
('Antipyrétique', 'Contre la fièvre'),
('Vitamines', 'Compléments alimentaires');

INSERT INTO fournisseurs (nom, contact_personne, telephone, email) VALUES
('Pharma Distrib Tunisie', 'Mohamed Ali', '71123456', 'contact@pharmadistrib.tn'),
('MediSup', 'Ines Bouazizi', '71987654', 'contact@medisup.tn');

INSERT INTO medicaments (nom, categorie_id, fabricant, forme, dosage, code_barre, prix_achat, prix_vente, quantite_minimale) VALUES
('Doliprane', 1, 'Sanofi', 'Comprimé', '500mg', '6111234567890', 1.200, 2.500, 10),
('Amoxicilline', 2, 'Adwya', 'Gélule', '500mg', '6111234567891', 3.000, 5.500, 10),
('Efferalgan', 3, 'UPSA', 'Comprimé effervescent', '1g', '6111234567892', 1.800, 3.200, 15);

INSERT INTO lots (medicament_id, fournisseur_id, numero_lot, quantite_initiale, quantite, prix_achat_unitaire, date_entree, date_expiration) VALUES
(1, 1, 'LOT-2026-001', 15, 15, 1.200, '2026-06-01', '2026-12-31'),
(2, 1, 'LOT-2026-002', 20, 20, 3.000, '2026-06-05', '2026-08-15'),
(3, 2, 'LOT-2026-003', 30, 30, 1.800, '2026-07-01', '2027-05-01');

-- =====================================================================
-- Exemple : simuler une vente de 6 boîtes de Doliprane (lot 1)
-- => déclenche trg_apres_vente puis trg_verifier_stock_faible automatiquement
-- =====================================================================
-- INSERT INTO ventes (utilisateur_id, montant_total, mode_paiement, type_document)
-- VALUES (2, 15.000, 'especes', 'ticket');
--
-- INSERT INTO details_ventes (vente_id, medicament_id, lot_id, quantite, prix_unitaire, sous_total)
-- VALUES (LAST_INSERT_ID(), 1, 1, 6, 2.500, 15.000);
