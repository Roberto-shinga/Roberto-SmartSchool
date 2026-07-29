-- ============================================================
--  SmartSchool RDC — Migration 005
--  Correction du mot de passe des comptes de demonstration
--
--  Le hash bcrypt insere a l'origine dans smartschool.sql ne
--  correspondait PAS au mot de passe documente "SmartSchool2025!"
--  (probable erreur lors de la generation initiale du script SQL).
--  Cette migration remet les 3 comptes de demo sur le bon mot de passe.
--
--  A importer sur une base smartschool DEJA existante.
-- ============================================================

USE smartschool;

UPDATE users
SET password = '$2y$12$bTsMW.iI0bmVbJCm4Gh3h.5wY/JyfDzfV7LiuQugNArbTSI8A9vQu'
WHERE username IN ('superadmin', 'admin', 'comptable');
