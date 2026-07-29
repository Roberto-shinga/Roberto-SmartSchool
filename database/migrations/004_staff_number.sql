-- ============================================================
--  SmartSchool RDC — Migration 004
--  Matricule generique du personnel (staff_number)
--
--  Les enseignants ont deja un matricule dans teachers.employee_id.
--  Le Comptable (et tout futur role de personnel sans table dediee)
--  utilise ce champ generique sur users.
--
--  A importer sur une base smartschool DEJA existante.
-- ============================================================

USE smartschool;

ALTER TABLE users
  ADD COLUMN staff_number VARCHAR(30) NULL UNIQUE AFTER phone;
