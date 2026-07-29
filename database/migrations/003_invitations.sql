-- ============================================================
--  SmartSchool RDC — Migration 003
--  Systeme d'invitation (enseignants, comptables)
--
--  A importer sur une base smartschool DEJA existante.
-- ============================================================

USE smartschool;

CREATE TABLE IF NOT EXISTS invitations (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  role_id      TINYINT UNSIGNED NOT NULL,
  token_hash   VARCHAR(64)  NOT NULL,
  invited_by   INT UNSIGNED NOT NULL,
  status       ENUM('pending','used','expired','cancelled') NOT NULL DEFAULT 'pending',
  expires_at   TIMESTAMP    NOT NULL,
  used_at      TIMESTAMP    NULL DEFAULT NULL,
  cancelled_at TIMESTAMP    NULL DEFAULT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_token (token_hash),
  KEY idx_user_status (user_id, status),
  CONSTRAINT fk_inv_user    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_inviter FOREIGN KEY (invited_by) REFERENCES users(id)
) ENGINE=InnoDB;
