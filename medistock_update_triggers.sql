USE medistock;

DROP TRIGGER IF EXISTS trg_apres_vente;
DROP TRIGGER IF EXISTS trg_verifier_stock_faible;
DROP TRIGGER IF EXISTS trg_verifier_expiration_lot;
DROP TRIGGER IF EXISTS trg_lots_after_insert;
DROP TRIGGER IF EXISTS trg_lots_after_update;
DROP TRIGGER IF EXISTS trg_medicaments_after_update;
DROP EVENT IF EXISTS ev_verifier_expirations;

DELIMITER //

CREATE TRIGGER trg_apres_vente
AFTER INSERT ON details_ventes
FOR EACH ROW
BEGIN
    DECLARE v_utilisateur INT;

    UPDATE lots
    SET quantite = quantite - NEW.quantite,
        statut = IF(quantite - NEW.quantite <= 0, 'epuise', statut)
    WHERE id = NEW.lot_id;

    SELECT utilisateur_id INTO v_utilisateur FROM ventes WHERE id = NEW.vente_id;

    INSERT INTO mouvements_stock (medicament_id, lot_id, type_mouvement, quantite, motif, utilisateur_id)
    VALUES (NEW.medicament_id, NEW.lot_id, 'sortie', -NEW.quantite, CONCAT('Vente #', NEW.vente_id), v_utilisateur);
END //

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
    END IF;
END //

CREATE TRIGGER trg_lots_after_insert
AFTER INSERT ON lots
FOR EACH ROW
BEGIN
    DECLARE v_nom VARCHAR(150);
    IF DATEDIFF(NEW.date_expiration, CURDATE()) BETWEEN 0 AND 30 THEN
        SELECT nom INTO v_nom FROM medicaments WHERE id = NEW.medicament_id;
        INSERT INTO alertes (type_alerte, medicament_id, lot_id, message)
        VALUES ('expiration_proche', NEW.medicament_id, NEW.id,
                CONCAT('Le lot ', NEW.numero_lot, ' de "', v_nom, '" expire le ', NEW.date_expiration));
    END IF;

    INSERT INTO mouvements_stock (medicament_id, lot_id, type_mouvement, quantite, motif, utilisateur_id)
    VALUES (NEW.medicament_id, NEW.id, 'entree', NEW.quantite_initiale,
            CONCAT('Réception lot ', NEW.numero_lot), COALESCE(@medistock_user_id, 1));
END //

CREATE EVENT IF NOT EXISTS ev_verifier_expirations
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO
BEGIN
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
END //
DELIMITER ;
