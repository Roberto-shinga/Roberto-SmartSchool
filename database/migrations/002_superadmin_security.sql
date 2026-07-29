-- ============================================================
--  SmartSchool RDC — Migration 002
--  Securite Super Administrateur : double authentification
--  et suivi de la configuration initiale (setup wizard)
--
--  A importer sur une base smartschool DEJA existante.
--  (Ces tables sont deja incluses si tu reimportes le fichier
--   database/smartschool.sql complet a partir de zero.)
-- ============================================================

USE smartschool;

-- ── Codes de verification (2FA) — connexion Super Administrateur
CREATE TABLE IF NOT EXISTS two_factor_codes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  code_hash   VARCHAR(255) NOT NULL,
  purpose     VARCHAR(40)  NOT NULL DEFAULT 'login',
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used        TINYINT(1)   NOT NULL DEFAULT 0,
  expires_at  TIMESTAMP    NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_purpose (user_id, purpose, used),
  CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Suivi de l'assistant de configuration initiale ───────────
CREATE TABLE IF NOT EXISTS system_setup (
  id               TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setup_completed  TINYINT(1)   NOT NULL DEFAULT 0,
  current_step     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  completed_at     TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO system_setup (setup_completed, current_step)
SELECT 0, 1 WHERE NOT EXISTS (SELECT 1 FROM system_setup);
