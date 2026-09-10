-- MediStock : suppression du rôle responsable_stock
-- À exécuter une fois sur la base existante (phpMyAdmin ou mysql CLI)

USE medistock;

-- Convertir les anciens comptes stock en pharmacien (désactivés)
UPDATE utilisateurs
SET role = 'pharmacien', actif = 0
WHERE role = 'responsable_stock';

-- Restreindre l'ENUM aux deux rôles métier
ALTER TABLE utilisateurs
  MODIFY role ENUM('administrateur','pharmacien') NOT NULL;
