-- ============================================================
--  SmartSchool RDC — Base de donnees complete
--  Conforme au cahier des charges v1.0
--  Importer dans phpMyAdmin (base vide : smartschool)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS smartschool;
CREATE DATABASE smartschool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartschool;

-- ── roles ────────────────────────────────────────────────────
CREATE TABLE roles (
  id    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name  VARCHAR(50)      NOT NULL,
  label VARCHAR(100)     NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO roles (name, label) VALUES
  ('super_admin', 'Super Administrateur'),
  ('admin',       'Administrateur'),
  ('teacher',     'Enseignant'),
  ('student',     'Eleve'),
  ('parent',      'Parent'),
  ('accountant',  'Comptable');

-- ── users ────────────────────────────────────────────────────
CREATE TABLE users (
  id                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  role_id              TINYINT UNSIGNED  NOT NULL,
  username             VARCHAR(80)       NOT NULL,
  email                VARCHAR(191)      DEFAULT NULL,
  password             VARCHAR(255)      NOT NULL,
  first_name           VARCHAR(80)       NOT NULL,
  last_name            VARCHAR(80)       NOT NULL,
  phone                VARCHAR(30)       DEFAULT NULL,
  staff_number         VARCHAR(30)       DEFAULT NULL UNIQUE,
  address              TEXT              DEFAULT NULL,
  gender               ENUM('M','F')     DEFAULT 'M',
  date_of_birth        DATE              DEFAULT NULL,
  avatar               VARCHAR(255)      DEFAULT NULL,
  is_active            TINYINT(1)        NOT NULL DEFAULT 1,
  must_change_password TINYINT(1)        NOT NULL DEFAULT 0,
  last_login           TIMESTAMP         NULL DEFAULT NULL,
  created_at           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email    (email),
  KEY idx_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ── levels (niveaux scolaires RDC) ───────────────────────────
CREATE TABLE levels (
  id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80)      NOT NULL,
  code        VARCHAR(20)      NOT NULL,
  order_index TINYINT UNSIGNED DEFAULT 0,
  is_active   TINYINT(1)       NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code)
) ENGINE=InnoDB;

INSERT INTO levels (name, code, order_index) VALUES
  ('Primaire',       'PRIM',  1),
  ('Cycle terminal', 'CYCLE', 2),
  ('Humanites',      'HUM',   3);

-- ── school_options (options/filieres) ────────────────────────
CREATE TABLE school_options (
  id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name      VARCHAR(100)      NOT NULL,
  code      VARCHAR(20)       NOT NULL,
  level_id  TINYINT UNSIGNED  DEFAULT NULL,
  is_active TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  CONSTRAINT fk_opt_level FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO school_options (name, code, level_id) VALUES
  ('Scientifique',           'SCI',  3),
  ('Litteraire',             'LIT',  3),
  ('Pedagogie',              'PED',  3),
  ('Commerciale et Gestion', 'COM',  3),
  ('Electricite',            'ELEC', 3),
  ('Coupe et Couture',       'CC',   3),
  ('Informatique',           'INFO', 3),
  ('Generale',               'GEN',  NULL);

-- ── academic_years ───────────────────────────────────────────
CREATE TABLE academic_years (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(20)       NOT NULL,
  start_date DATE              NOT NULL,
  end_date   DATE              NOT NULL,
  is_current TINYINT(1)        NOT NULL DEFAULT 0,
  created_at TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_name (name)
) ENGINE=InnoDB;

INSERT INTO academic_years (name, start_date, end_date, is_current) VALUES
  ('2024-2025', '2024-09-01', '2025-06-30', 1);

-- ── terms (periodes/trimestres) ──────────────────────────────
CREATE TABLE terms (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  name             VARCHAR(50)       NOT NULL,
  start_date       DATE              NOT NULL,
  end_date         DATE              NOT NULL,
  is_current       TINYINT(1)        NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_terms_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO terms (academic_year_id, name, start_date, end_date, is_current) VALUES
  (1, 'Trimestre 1', '2024-09-01', '2024-12-15', 0),
  (1, 'Trimestre 2', '2025-01-07', '2025-03-30', 1),
  (1, 'Trimestre 3', '2025-04-07', '2025-06-30', 0);

-- ── classes ──────────────────────────────────────────────────
CREATE TABLE classes (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  level_id         TINYINT UNSIGNED  DEFAULT NULL,
  option_id        SMALLINT UNSIGNED DEFAULT NULL,
  name             VARCHAR(80)       NOT NULL,
  grade_year       TINYINT UNSIGNED  DEFAULT NULL,
  section          VARCHAR(10)       DEFAULT 'A',
  capacity         TINYINT UNSIGNED  DEFAULT 40,
  room             VARCHAR(30)       DEFAULT NULL,
  class_teacher_id INT UNSIGNED      DEFAULT NULL,
  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_cls_year    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_cls_level   FOREIGN KEY (level_id)         REFERENCES levels(id) ON DELETE SET NULL,
  CONSTRAINT fk_cls_option  FOREIGN KEY (option_id)        REFERENCES school_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_cls_teacher FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Exemples de classes (Primaire + Humanites)
INSERT INTO classes (academic_year_id, level_id, name, grade_year, section, capacity, room) VALUES
  (1, 1, '1ere Primaire A', 1, 'A', 40, 'Salle 01'),
  (1, 1, '2eme Primaire A', 2, 'A', 40, 'Salle 02'),
  (1, 1, '3eme Primaire A', 3, 'A', 40, 'Salle 03'),
  (1, 1, '4eme Primaire A', 4, 'A', 40, 'Salle 04'),
  (1, 1, '5eme Primaire A', 5, 'A', 40, 'Salle 05'),
  (1, 1, '6eme Primaire A', 6, 'A', 40, 'Salle 06'),
  (1, 2, '7eme Annee A',    7, 'A', 40, 'Salle 07'),
  (1, 2, '8eme Annee A',    8, 'A', 40, 'Salle 08'),
  (1, 3, '1ere Humanite A', 1, 'A', 40, 'Salle 09'),
  (1, 3, '2eme Humanite A', 2, 'A', 40, 'Salle 10');

-- ── teachers ─────────────────────────────────────────────────
CREATE TABLE teachers (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED  NOT NULL,
  employee_id   VARCHAR(20)   NOT NULL,
  hire_date     DATE          NOT NULL,
  qualification VARCHAR(200)  DEFAULT NULL,
  speciality    VARCHAR(150)  DEFAULT NULL,
  salary        DECIMAL(12,2) DEFAULT 0.00,
  status        ENUM('actif','conge','retraite') NOT NULL DEFAULT 'actif',
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user   (user_id),
  UNIQUE KEY uq_emp_id (employee_id),
  CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── teacher_assignments (affectations enseignant→classe+matiere)
CREATE TABLE teacher_assignments (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  teacher_id       INT UNSIGNED      NOT NULL,
  class_id         SMALLINT UNSIGNED NOT NULL,
  subject_id       SMALLINT UNSIGNED NOT NULL,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  is_titular       TINYINT(1)        NOT NULL DEFAULT 0,
  hours_per_week   TINYINT UNSIGNED  DEFAULT 2,
  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assign (teacher_id, class_id, subject_id, academic_year_id),
  CONSTRAINT fk_ta_teacher FOREIGN KEY (teacher_id)       REFERENCES teachers(id)        ON DELETE CASCADE,
  CONSTRAINT fk_ta_class   FOREIGN KEY (class_id)         REFERENCES classes(id)         ON DELETE CASCADE,
  CONSTRAINT fk_ta_subject FOREIGN KEY (subject_id)       REFERENCES subjects(id)        ON DELETE CASCADE,
  CONSTRAINT fk_ta_year    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB;

-- ── subjects ─────────────────────────────────────────────────
CREATE TABLE subjects (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100)      NOT NULL,
  code        VARCHAR(20)       NOT NULL,
  coefficient DECIMAL(4,2)      NOT NULL DEFAULT 1.00,
  color       VARCHAR(7)        DEFAULT '#6366f1',
  level_id    TINYINT UNSIGNED  DEFAULT NULL,
  is_active   TINYINT(1)        NOT NULL DEFAULT 1,
  created_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  CONSTRAINT fk_subj_level FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO subjects (name, code, coefficient, color) VALUES
  ('Mathematiques',      'MATH', 4.00, '#6366f1'),
  ('Francais',           'FRA',  4.00, '#8b5cf6'),
  ('Anglais',            'ANG',  3.00, '#3b82f6'),
  ('Sciences',           'SCI',  3.00, '#10b981'),
  ('Histoire-Geo',       'HGE',  2.00, '#f59e0b'),
  ('Education Physique', 'EPS',  1.00, '#ef4444'),
  ('Eveil',              'EVE',  2.00, '#06b6d4'),
  ('Religion Morale',    'REL',  1.00, '#a855f7'),
  ('Informatique',       'INFO', 2.00, '#14b8a6'),
  ('Chimie',             'CHI',  3.00, '#ec4899'),
  ('Physique',           'PHY',  3.00, '#f97316'),
  ('Biologie',           'BIO',  3.00, '#22c55e');

-- ── students ─────────────────────────────────────────────────
CREATE TABLE students (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED      DEFAULT NULL,
  class_id         SMALLINT UNSIGNED DEFAULT NULL,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  level_id         TINYINT UNSIGNED  DEFAULT NULL,
  option_id        SMALLINT UNSIGNED DEFAULT NULL,
  grade_year       TINYINT UNSIGNED  DEFAULT NULL,
  student_number   VARCHAR(20)       NOT NULL,
  enrollment_date  DATE              NOT NULL,
  has_account      TINYINT(1)        NOT NULL DEFAULT 0,
  first_login      TINYINT(1)        NOT NULL DEFAULT 1,
  previous_school  VARCHAR(200)      DEFAULT NULL,
  medical_notes    TEXT              DEFAULT NULL,
  scholarship      TINYINT(1)        NOT NULL DEFAULT 0,
  status           ENUM('actif','suspendu','diplome','transfere') NOT NULL DEFAULT 'actif',
  -- Infos identite (pour eleves primaire sans compte user)
  first_name       VARCHAR(80)       DEFAULT NULL,
  last_name        VARCHAR(80)       DEFAULT NULL,
  gender           ENUM('M','F')     DEFAULT NULL,
  date_of_birth    DATE              DEFAULT NULL,
  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_number (student_number),
  CONSTRAINT fk_students_user   FOREIGN KEY (user_id)          REFERENCES users(id)          ON DELETE SET NULL,
  CONSTRAINT fk_students_class  FOREIGN KEY (class_id)         REFERENCES classes(id)        ON DELETE SET NULL,
  CONSTRAINT fk_students_year   FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_students_level  FOREIGN KEY (level_id)         REFERENCES levels(id)         ON DELETE SET NULL,
  CONSTRAINT fk_students_option FOREIGN KEY (option_id)        REFERENCES school_options(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── parent_student ───────────────────────────────────────────
CREATE TABLE parent_student (
  parent_id    INT UNSIGNED NOT NULL,
  student_id   INT UNSIGNED NOT NULL,
  relationship VARCHAR(30)  DEFAULT 'parent',
  is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (parent_id, student_id),
  CONSTRAINT fk_ps_parent  FOREIGN KEY (parent_id)  REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_ps_student FOREIGN KEY (student_id) REFERENCES students(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── grades ───────────────────────────────────────────────────
CREATE TABLE grades (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  student_id     INT UNSIGNED      NOT NULL,
  assignment_id  INT UNSIGNED      NOT NULL,
  term_id        SMALLINT UNSIGNED NOT NULL,
  grade_type     ENUM('controle','interrogation','examen','tp','projet','devoir') NOT NULL DEFAULT 'controle',
  title          VARCHAR(200)      DEFAULT NULL,
  score          DECIMAL(5,2)      NOT NULL,
  max_score      DECIMAL(5,2)      NOT NULL DEFAULT 20.00,
  coefficient    DECIMAL(4,2)      NOT NULL DEFAULT 1.00,
  date           DATE              NOT NULL,
  comment        TEXT              DEFAULT NULL,
  graded_by      INT UNSIGNED      NOT NULL,
  created_at     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_grades_student    FOREIGN KEY (student_id)    REFERENCES students(id)            ON DELETE CASCADE,
  CONSTRAINT fk_grades_assignment FOREIGN KEY (assignment_id) REFERENCES teacher_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_grades_term       FOREIGN KEY (term_id)       REFERENCES terms(id),
  CONSTRAINT fk_grades_grader     FOREIGN KEY (graded_by)     REFERENCES users(id)
) ENGINE=InnoDB;

-- ── attendance ───────────────────────────────────────────────
CREATE TABLE attendance (
  id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  student_id    INT UNSIGNED      NOT NULL,
  class_id      SMALLINT UNSIGNED NOT NULL,
  assignment_id INT UNSIGNED      DEFAULT NULL,
  date          DATE              NOT NULL,
  status        ENUM('present','absent','retard','excuse') NOT NULL DEFAULT 'present',
  reason        TEXT              DEFAULT NULL,
  recorded_by   INT UNSIGNED      NOT NULL,
  created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance (student_id, class_id, date),
  CONSTRAINT fk_att_student FOREIGN KEY (student_id)   REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_class   FOREIGN KEY (class_id)     REFERENCES classes(id),
  CONSTRAINT fk_att_recorder FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ── report_cards ─────────────────────────────────────────────
CREATE TABLE report_cards (
  id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  student_id      INT UNSIGNED      NOT NULL,
  term_id         SMALLINT UNSIGNED NOT NULL,
  average         DECIMAL(5,2)      DEFAULT NULL,
  `rank`          SMALLINT UNSIGNED DEFAULT NULL,
  class_average   DECIMAL(5,2)      DEFAULT NULL,
  total_students  SMALLINT UNSIGNED DEFAULT NULL,
  conduct         ENUM('Excellent','Tres bien','Bien','Passable','A ameliorer') DEFAULT NULL,
  teacher_comment TEXT              DEFAULT NULL,
  admin_comment   TEXT              DEFAULT NULL,
  is_published    TINYINT(1)        NOT NULL DEFAULT 0,
  generated_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_report (student_id, term_id),
  CONSTRAINT fk_rc_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_rc_term    FOREIGN KEY (term_id)    REFERENCES terms(id)
) ENGINE=InnoDB;

-- ── timetable ────────────────────────────────────────────────
CREATE TABLE timetable (
  id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  assignment_id INT UNSIGNED      NOT NULL,
  day_of_week   TINYINT UNSIGNED  NOT NULL,
  start_time    TIME              NOT NULL,
  end_time      TIME              NOT NULL,
  room          VARCHAR(30)       DEFAULT NULL,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_tt_assign FOREIGN KEY (assignment_id)     REFERENCES teacher_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tt_year   FOREIGN KEY (academic_year_id)  REFERENCES academic_years(id)
) ENGINE=InnoDB;

-- ── fee_categories ───────────────────────────────────────────
CREATE TABLE fee_categories (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100)      NOT NULL,
  description  TEXT              DEFAULT NULL,
  is_recurring TINYINT(1)        NOT NULL DEFAULT 1,
  created_at   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO fee_categories (name, description, is_recurring) VALUES
  ('Frais de scolarite',   'Frais annuels de scolarisation',    1),
  ('Frais inscription',    'Frais de debut annee scolaire',     0),
  ('Frais examens',        'Frais de passage des examens',      0),
  ('Cantine',              'Frais de restauration scolaire',    1),
  ('Transport',            'Frais de transport scolaire',       1),
  ('Laboratoire',          'Frais utilisation laboratoire',     1),
  ('Materiel scolaire',    'Livres et fournitures',             0),
  ('Activites extra',      'Activites parascolaires',           0);

-- ── fees ─────────────────────────────────────────────────────
CREATE TABLE fees (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id      SMALLINT UNSIGNED NOT NULL,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  level_id         TINYINT UNSIGNED  DEFAULT NULL,
  class_id         SMALLINT UNSIGNED DEFAULT NULL,
  amount           DECIMAL(12,2)     NOT NULL,
  due_date         DATE              DEFAULT NULL,
  description      TEXT              DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_fees_cat   FOREIGN KEY (category_id)      REFERENCES fee_categories(id),
  CONSTRAINT fk_fees_year  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_fees_level FOREIGN KEY (level_id)         REFERENCES levels(id) ON DELETE SET NULL,
  CONSTRAINT fk_fees_class FOREIGN KEY (class_id)         REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Frais par defaut
INSERT INTO fees (category_id, academic_year_id, amount, due_date, description) VALUES
  (1, 1, 150000, '2024-10-01', 'Frais annuels 2024-2025'),
  (2, 1,  25000, '2024-09-15', 'Inscription annee scolaire'),
  (3, 1,  30000, '2025-05-01', 'Frais examens fin annee');

-- ── payments ─────────────────────────────────────────────────
CREATE TABLE payments (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  student_id     INT UNSIGNED      NOT NULL,
  fee_id         SMALLINT UNSIGNED NOT NULL,
  amount_paid    DECIMAL(12,2)     NOT NULL,
  payment_date   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  payment_method ENUM('especes','virement','cheque','mobile_money','carte') NOT NULL DEFAULT 'especes',
  receipt_number VARCHAR(30)       NOT NULL,
  status         ENUM('en_attente','paye','partiel','rejete','annule') NOT NULL DEFAULT 'paye',
  collected_by   INT UNSIGNED      NOT NULL,
  notes          TEXT              DEFAULT NULL,
  created_at     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_receipt (receipt_number),
  CONSTRAINT fk_pay_student   FOREIGN KEY (student_id)   REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_fee       FOREIGN KEY (fee_id)       REFERENCES fees(id),
  CONSTRAINT fk_pay_collector FOREIGN KEY (collected_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ── payment_proofs (preuves bancaires) ───────────────────────
CREATE TABLE payment_proofs (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id    INT UNSIGNED NOT NULL,
  file_name     VARCHAR(255) NOT NULL,
  file_path     VARCHAR(500) NOT NULL,
  reference     VARCHAR(100) DEFAULT NULL,
  uploaded_by   INT UNSIGNED NOT NULL,
  verified_by   INT UNSIGNED DEFAULT NULL,
  verified_at   TIMESTAMP    NULL DEFAULT NULL,
  status        ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente',
  reject_reason TEXT         DEFAULT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_proof_payment  FOREIGN KEY (payment_id)  REFERENCES payments(id) ON DELETE CASCADE,
  CONSTRAINT fk_proof_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id),
  CONSTRAINT fk_proof_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── notifications ────────────────────────────────────────────
CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(200) NOT NULL,
  message    TEXT         NOT NULL,
  type       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  link       VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_read (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── messages ─────────────────────────────────────────────────
CREATE TABLE messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id   INT UNSIGNED NOT NULL,
  receiver_id INT UNSIGNED NOT NULL,
  subject     VARCHAR(200) NOT NULL,
  body        TEXT         NOT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  parent_id   INT UNSIGNED DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_parent   FOREIGN KEY (parent_id)   REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── system_settings ──────────────────────────────────────────
CREATE TABLE system_settings (
  id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(80)       NOT NULL,
  setting_value TEXT              DEFAULT NULL,
  label         VARCHAR(150)      DEFAULT NULL,
  created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_key (setting_key)
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value, label) VALUES
  ('school_name',    'SmartSchool',              'Nom de etablissement'),
  ('school_address', 'Kinshasa, RDC',            'Adresse'),
  ('school_phone',   '+243 00 000 0000',         'Telephone'),
  ('school_email',   'contact@smartschool.cd',   'Email'),
  ('currency',       'FC',                       'Devise'),
  ('currency_name',  'Franc Congolais',          'Nom devise'),
  ('max_grade',      '20',                       'Note maximale'),
  ('passing_grade',  '10',                       'Note de passage'),
  ('school_motto',   'Excellence et Savoir',     'Devise ecole'),
  ('school_year',    '2024-2025',                'Annee scolaire');

-- ── activity_logs ────────────────────────────────────────────
CREATE TABLE activity_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED DEFAULT NULL,
  action      VARCHAR(100) NOT NULL,
  description TEXT         DEFAULT NULL,
  ip_address  VARCHAR(45)  DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── SmartSchool Learning ─────────────────────────────────────
CREATE TABLE courses (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  title            VARCHAR(200)      NOT NULL,
  description      TEXT              DEFAULT NULL,
  subject_id       SMALLINT UNSIGNED NOT NULL,
  level_id         TINYINT UNSIGNED  DEFAULT NULL,
  option_id        SMALLINT UNSIGNED DEFAULT NULL,
  grade_year       TINYINT UNSIGNED  DEFAULT NULL,
  teacher_id       INT UNSIGNED      NOT NULL,
  cover_image      VARCHAR(255)      DEFAULT NULL,
  is_published     TINYINT(1)        NOT NULL DEFAULT 0,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_crs_subject FOREIGN KEY (subject_id)       REFERENCES subjects(id),
  CONSTRAINT fk_crs_level   FOREIGN KEY (level_id)         REFERENCES levels(id) ON DELETE SET NULL,
  CONSTRAINT fk_crs_option  FOREIGN KEY (option_id)        REFERENCES school_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_crs_teacher FOREIGN KEY (teacher_id)       REFERENCES teachers(id),
  CONSTRAINT fk_crs_year    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB;

CREATE TABLE lessons (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id    INT UNSIGNED NOT NULL,
  title        VARCHAR(200) NOT NULL,
  content      LONGTEXT     DEFAULT NULL,
  order_index  SMALLINT     DEFAULT 0,
  duration_min SMALLINT     DEFAULT NULL,
  is_published TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_les_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE learning_resources (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  lesson_id    INT UNSIGNED  DEFAULT NULL,
  course_id    INT UNSIGNED  NOT NULL,
  title        VARCHAR(200)  NOT NULL,
  type         ENUM('pdf','video','image','lien','document') NOT NULL DEFAULT 'pdf',
  file_path    VARCHAR(500)  DEFAULT NULL,
  external_url VARCHAR(500)  DEFAULT NULL,
  order_index  SMALLINT      DEFAULT 0,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_res_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL,
  CONSTRAINT fk_res_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quizzes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id    INT UNSIGNED NOT NULL,
  lesson_id    INT UNSIGNED DEFAULT NULL,
  title        VARCHAR(200) NOT NULL,
  description  TEXT         DEFAULT NULL,
  duration_min SMALLINT     DEFAULT NULL,
  max_attempts TINYINT      DEFAULT 3,
  pass_score   TINYINT      DEFAULT 50,
  is_published TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_qz_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_qz_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE quiz_questions (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quiz_id     INT UNSIGNED NOT NULL,
  question    TEXT         NOT NULL,
  type        ENUM('qcm','vrai_faux','texte') NOT NULL DEFAULT 'qcm',
  options     JSON         DEFAULT NULL,
  correct     VARCHAR(500) DEFAULT NULL,
  explanation TEXT         DEFAULT NULL,
  points      TINYINT      DEFAULT 1,
  order_index SMALLINT     DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_qq_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quiz_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quiz_id      INT UNSIGNED NOT NULL,
  student_id   INT UNSIGNED NOT NULL,
  score        TINYINT      DEFAULT 0,
  total_points TINYINT      DEFAULT 0,
  answers      JSON         DEFAULT NULL,
  passed       TINYINT(1)   NOT NULL DEFAULT 0,
  started_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at  TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_att_quiz    FOREIGN KEY (quiz_id)    REFERENCES quizzes(id)  ON DELETE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lesson_progress (
  student_id   INT UNSIGNED NOT NULL,
  lesson_id    INT UNSIGNED NOT NULL,
  completed    TINYINT(1)   NOT NULL DEFAULT 0,
  completed_at TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (student_id, lesson_id),
  CONSTRAINT fk_lp_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_lp_lesson  FOREIGN KEY (lesson_id)  REFERENCES lessons(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE badges (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  description TEXT         DEFAULT NULL,
  icon        VARCHAR(50)  DEFAULT 'bx-star',
  color       VARCHAR(7)   DEFAULT '#6366f1',
  condition   VARCHAR(200) DEFAULT NULL,
  points      SMALLINT     DEFAULT 10,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO badges (name, description, icon, color, points) VALUES
  ('Premier pas',   'Premier quiz reussi',        'bx-star',      '#f59e0b', 10),
  ('Assidu',        '5 cours consultes',          'bx-book-open', '#6366f1', 20),
  ('Expert',        'Score parfait a un quiz',    'bx-trophy',    '#10b981', 50),
  ('Curieux',       'Premier cours termine',      'bx-search',    '#3b82f6', 15),
  ('Champion',      '10 quiz reussis',            'bx-award',     '#ec4899', 100);

CREATE TABLE student_badges (
  student_id INT UNSIGNED NOT NULL,
  badge_id   INT UNSIGNED NOT NULL,
  earned_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id, badge_id),
  CONSTRAINT fk_sb_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_sb_badge   FOREIGN KEY (badge_id)   REFERENCES badges(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── two_factor_codes (double authentification Super Admin) ──
CREATE TABLE two_factor_codes (
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

-- ── system_setup (assistant de configuration initiale) ───────
CREATE TABLE system_setup (
  id               TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setup_completed  TINYINT(1)   NOT NULL DEFAULT 0,
  current_step     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  completed_at     TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO system_setup (setup_completed, current_step) VALUES (0, 1);

-- ── invitations (enseignants, comptables) ────────────────────
CREATE TABLE invitations (
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

-- ── Comptes par defaut ───────────────────────────────────────
-- Mot de passe : SmartSchool2025!
INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, must_change_password) VALUES
  (1, 'superadmin', 'superadmin@smartschool.cd', '$2y$12$bTsMW.iI0bmVbJCm4Gh3h.5wY/JyfDzfV7LiuQugNArbTSI8A9vQu', 'Super',    'Admin',   NULL, 0),
  (2, 'admin',      'admin@smartschool.cd',       '$2y$12$bTsMW.iI0bmVbJCm4Gh3h.5wY/JyfDzfV7LiuQugNArbTSI8A9vQu', 'Directeur','Kabila',  NULL, 0),
  (6, 'comptable',  'comptable@smartschool.cd',   '$2y$12$bTsMW.iI0bmVbJCm4Gh3h.5wY/JyfDzfV7LiuQugNArbTSI8A9vQu', 'Paul',     'Mbuyi',   NULL, 0);

SET FOREIGN_KEY_CHECKS = 1;

SELECT CONCAT('SmartSchool RDC — ', COUNT(*), ' tables creees avec succes !') AS resultat
FROM information_schema.TABLES WHERE table_schema = 'smartschool';
