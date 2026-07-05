-- ============================================================
--  SmartSchool — Base de donnees complete v2
--  Importer dans phpMyAdmin
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Suppression et recreation propre
DROP DATABASE IF EXISTS smartschool;
CREATE DATABASE smartschool
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE smartschool;

-- ============================================================
--  TABLE : roles
-- ============================================================
CREATE TABLE roles (
  id          TINYINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(50)       NOT NULL,
  label       VARCHAR(100)      NOT NULL,
  created_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (name, label) VALUES
  ('super_admin', 'Super Administrateur'),
  ('admin',       'Administrateur'),
  ('teacher',     'Enseignant'),
  ('student',     'Eleve'),
  ('parent',      'Parent'),
  ('accountant',  'Comptable');

-- ============================================================
--  TABLE : users
-- ============================================================
CREATE TABLE users (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  role_id        TINYINT UNSIGNED  NOT NULL,
  username       VARCHAR(80)       NOT NULL,
  email          VARCHAR(191)      NOT NULL,
  password       VARCHAR(255)      NOT NULL,
  first_name     VARCHAR(80)       NOT NULL,
  last_name      VARCHAR(80)       NOT NULL,
  phone          VARCHAR(20)       DEFAULT NULL,
  address        TEXT              DEFAULT NULL,
  avatar         VARCHAR(255)      DEFAULT 'default.png',
  gender         ENUM('M','F')     DEFAULT 'M',
  date_of_birth  DATE              DEFAULT NULL,
  is_active      TINYINT(1)        NOT NULL DEFAULT 1,
  last_login     TIMESTAMP         NULL DEFAULT NULL,
  created_at     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email (email),
  KEY idx_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mot de passe : SmartSchool2025!
INSERT INTO users (role_id, username, email, password, first_name, last_name, phone, is_active) VALUES
(1, 'superadmin',    'superadmin@smartschool.fr', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super',  'Admin',   '+33100000001', 1),
(2, 'admin',         'admin@smartschool.fr',       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marie',  'Dupont',  '+33100000002', 1),
(3, 'prof.martin',   'martin@smartschool.fr',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jean',   'Martin',  '+33100000003', 1),
(4, 'eleve.durand',  'eleve@smartschool.fr',       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lucas',  'Durand',  '+33100000004', 1),
(5, 'parent.durand', 'parent@smartschool.fr',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sophie', 'Durand',  '+33600000005', 1),
(6, 'comptable',     'comptable@smartschool.fr',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Paul',   'Leblanc', '+33100000006', 1);

-- ============================================================
--  TABLE : academic_years
-- ============================================================
CREATE TABLE academic_years (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(20)       NOT NULL,
  start_date  DATE              NOT NULL,
  end_date    DATE              NOT NULL,
  is_current  TINYINT(1)        NOT NULL DEFAULT 0,
  created_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO academic_years (name, start_date, end_date, is_current) VALUES
  ('2023-2024', '2023-09-01', '2024-06-30', 0),
  ('2024-2025', '2024-09-01', '2025-06-30', 1);

-- ============================================================
--  TABLE : classes
-- ============================================================
CREATE TABLE classes (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  name             VARCHAR(50)       NOT NULL,
  level            VARCHAR(30)       DEFAULT NULL,
  section          VARCHAR(10)       DEFAULT NULL,
  capacity         TINYINT UNSIGNED  DEFAULT 35,
  room             VARCHAR(20)       DEFAULT NULL,
  class_teacher_id INT UNSIGNED      DEFAULT NULL,
  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_classes_year    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_classes_teacher FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO classes (academic_year_id, name, level, section, capacity, room) VALUES
  (2, '6eme A', '6eme', 'A', 35, 'Salle 01'),
  (2, '6eme B', '6eme', 'B', 35, 'Salle 02'),
  (2, '5eme A', '5eme', 'A', 35, 'Salle 03'),
  (2, '4eme A', '4eme', 'A', 35, 'Salle 04'),
  (2, '3eme A', '3eme', 'A', 35, 'Salle 05');

-- ============================================================
--  TABLE : teachers
-- ============================================================
CREATE TABLE teachers (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED  NOT NULL,
  employee_id   VARCHAR(20)   NOT NULL,
  hire_date     DATE          NOT NULL,
  qualification VARCHAR(150)  DEFAULT NULL,
  speciality    VARCHAR(100)  DEFAULT NULL,
  salary        DECIMAL(10,2) DEFAULT 0.00,
  status        ENUM('actif','conge','retraite') NOT NULL DEFAULT 'actif',
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user    (user_id),
  UNIQUE KEY uq_emp_id  (employee_id),
  CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO teachers (user_id, employee_id, hire_date, qualification, speciality, salary) VALUES
  (3, 'EMP-001', '2020-09-01', 'Master Mathematiques', 'Maths et Informatique', 350000.00);

-- ============================================================
--  TABLE : students
-- ============================================================
CREATE TABLE students (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED      NOT NULL,
  class_id         SMALLINT UNSIGNED DEFAULT NULL,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  student_number   VARCHAR(20)       NOT NULL,
  enrollment_date  DATE              NOT NULL,
  previous_school  VARCHAR(150)      DEFAULT NULL,
  medical_notes    TEXT              DEFAULT NULL,
  scholarship      TINYINT(1)        NOT NULL DEFAULT 0,
  status           ENUM('actif','suspendu','diplome','transfere') NOT NULL DEFAULT 'actif',
  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user           (user_id),
  UNIQUE KEY uq_student_number (student_number),
  CONSTRAINT fk_students_user  FOREIGN KEY (user_id)          REFERENCES users(id)           ON DELETE CASCADE,
  CONSTRAINT fk_students_class FOREIGN KEY (class_id)         REFERENCES classes(id)         ON DELETE SET NULL,
  CONSTRAINT fk_students_year  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO students (user_id, class_id, academic_year_id, student_number, enrollment_date, status) VALUES
  (4, 1, 2, 'STU-2024-0001', '2024-09-02', 'actif');

-- ============================================================
--  TABLE : parent_student
-- ============================================================
CREATE TABLE parent_student (
  parent_id    INT UNSIGNED NOT NULL,
  student_id   INT UNSIGNED NOT NULL,
  relationship VARCHAR(30)  DEFAULT 'parent',
  is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (parent_id, student_id),
  CONSTRAINT fk_ps_parent  FOREIGN KEY (parent_id)  REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_ps_student FOREIGN KEY (student_id) REFERENCES students(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO parent_student (parent_id, student_id, relationship, is_primary) VALUES
  (5, 1, 'mere', 1);

-- ============================================================
--  TABLE : subjects
-- ============================================================
CREATE TABLE subjects (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100)      NOT NULL,
  code        VARCHAR(20)       NOT NULL,
  coefficient DECIMAL(4,2)      NOT NULL DEFAULT 1.00,
  color       VARCHAR(7)        DEFAULT '#6366f1',
  is_active   TINYINT(1)        NOT NULL DEFAULT 1,
  created_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO subjects (name, code, coefficient, color) VALUES
  ('Mathematiques',   'MATH', 4.00, '#6366f1'),
  ('Francais',        'FRA',  4.00, '#8b5cf6'),
  ('Anglais',         'ANG',  3.00, '#3b82f6'),
  ('Sciences',        'SCI',  3.00, '#10b981'),
  ('Histoire-Geo',    'HGE',  2.00, '#f59e0b'),
  ('Education Phys.', 'EPS',  1.00, '#ef4444'),
  ('Arts Plastiques', 'ART',  1.00, '#ec4899'),
  ('Informatique',    'INFO', 2.00, '#14b8a6');

-- ============================================================
--  TABLE : terms (trimestres)
-- ============================================================
CREATE TABLE terms (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  name             VARCHAR(50)       NOT NULL,
  start_date       DATE              NOT NULL,
  end_date         DATE              NOT NULL,
  is_current       TINYINT(1)        NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_terms_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO terms (academic_year_id, name, start_date, end_date, is_current) VALUES
  (2, 'Trimestre 1', '2024-09-01', '2024-12-15', 0),
  (2, 'Trimestre 2', '2025-01-07', '2025-03-30', 1),
  (2, 'Trimestre 3', '2025-04-07', '2025-06-30', 0);

-- ============================================================
--  TABLE : class_subjects
-- ============================================================
CREATE TABLE class_subjects (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  class_id         SMALLINT UNSIGNED NOT NULL,
  subject_id       SMALLINT UNSIGNED NOT NULL,
  teacher_id       INT UNSIGNED      NOT NULL,
  hours_per_week   TINYINT UNSIGNED  DEFAULT 2,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_class_subject (class_id, subject_id, academic_year_id),
  CONSTRAINT fk_cs_class   FOREIGN KEY (class_id)         REFERENCES classes(id)         ON DELETE CASCADE,
  CONSTRAINT fk_cs_subject FOREIGN KEY (subject_id)       REFERENCES subjects(id)        ON DELETE CASCADE,
  CONSTRAINT fk_cs_teacher FOREIGN KEY (teacher_id)       REFERENCES teachers(id)        ON DELETE CASCADE,
  CONSTRAINT fk_cs_year    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO class_subjects (class_id, subject_id, teacher_id, hours_per_week, academic_year_id) VALUES
  (1, 1, 1, 4, 2),
  (1, 8, 1, 2, 2);

-- ============================================================
--  TABLE : grades
-- ============================================================
CREATE TABLE grades (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED      NOT NULL,
  class_subject_id INT UNSIGNED      NOT NULL,
  term_id          SMALLINT UNSIGNED NOT NULL,
  grade_type       ENUM('devoir','interrogation','examen','tp','projet') NOT NULL DEFAULT 'devoir',
  title            VARCHAR(150)      DEFAULT NULL,
  score            DECIMAL(5,2)      NOT NULL,
  max_score        DECIMAL(5,2)      NOT NULL DEFAULT 20.00,
  coefficient      DECIMAL(4,2)      NOT NULL DEFAULT 1.00,
  date             DATE              NOT NULL,
  comment          TEXT              DEFAULT NULL,
  graded_by        INT UNSIGNED      NOT NULL,
  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_grades_student FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  CONSTRAINT fk_grades_cs      FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_grades_term    FOREIGN KEY (term_id)          REFERENCES terms(id),
  CONSTRAINT fk_grades_grader  FOREIGN KEY (graded_by)        REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO grades (student_id, class_subject_id, term_id, grade_type, title, score, max_score, date, graded_by) VALUES
  (1, 1, 2, 'devoir', 'Devoir 1 - Algebre', 14.50, 20, '2025-01-20', 3),
  (1, 1, 2, 'devoir', 'Devoir 2 - Geometrie', 16.00, 20, '2025-02-10', 3),
  (1, 2, 2, 'devoir', 'TP Informatique 1', 17.00, 20, '2025-01-25', 3);

-- ============================================================
--  TABLE : attendance
-- ============================================================
CREATE TABLE attendance (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED      NOT NULL,
  class_subject_id INT UNSIGNED      DEFAULT NULL,
  class_id         SMALLINT UNSIGNED NOT NULL,
  date             DATE              NOT NULL,
  status           ENUM('present','absent','retard','excuse') NOT NULL DEFAULT 'present',
  reason           TEXT              DEFAULT NULL,
  recorded_by      INT UNSIGNED      NOT NULL,
  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance (student_id, class_id, date),
  CONSTRAINT fk_att_student  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_class    FOREIGN KEY (class_id)    REFERENCES classes(id),
  CONSTRAINT fk_att_recorder FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE : fee_categories
-- ============================================================
CREATE TABLE fee_categories (
  id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100)      NOT NULL,
  description  TEXT              DEFAULT NULL,
  is_recurring TINYINT(1)        NOT NULL DEFAULT 1,
  created_at   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fee_categories (name, description, is_recurring) VALUES
  ('Frais de scolarite', 'Frais annuels de scolarisation',   1),
  ('Frais inscription',  'Frais de debut annee scolaire',    0),
  ('Cantine',            'Frais de restauration scolaire',   1),
  ('Transport',          'Frais de transport scolaire',      1),
  ('Activites extra',    'Activites parascolaires',          0),
  ('Materiel scolaire',  'Livres et fournitures',            0);

-- ============================================================
--  TABLE : fees
-- ============================================================
CREATE TABLE fees (
  id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id      SMALLINT UNSIGNED NOT NULL,
  academic_year_id SMALLINT UNSIGNED NOT NULL,
  class_id         SMALLINT UNSIGNED DEFAULT NULL,
  amount           DECIMAL(10,2)     NOT NULL,
  due_date         DATE              DEFAULT NULL,
  description      TEXT              DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_fees_cat   FOREIGN KEY (category_id)      REFERENCES fee_categories(id),
  CONSTRAINT fk_fees_year  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_fees_class FOREIGN KEY (class_id)         REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fees (category_id, academic_year_id, amount, due_date, description) VALUES
  (1, 2, 150000, '2024-10-01', 'Frais annuels 2024-2025'),
  (2, 2,  25000, '2024-09-15', 'Inscription annee scolaire'),
  (3, 2,  45000, '2024-10-01', 'Cantine annuelle');

-- ============================================================
--  TABLE : payments
-- ============================================================
CREATE TABLE payments (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  student_id     INT UNSIGNED      NOT NULL,
  fee_id         SMALLINT UNSIGNED NOT NULL,
  amount_paid    DECIMAL(10,2)     NOT NULL,
  payment_date   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  payment_method ENUM('especes','virement','cheque','mobile_money','carte') NOT NULL DEFAULT 'especes',
  receipt_number VARCHAR(30)       NOT NULL,
  status         ENUM('paye','partiel','annule') NOT NULL DEFAULT 'paye',
  collected_by   INT UNSIGNED      NOT NULL,
  notes          TEXT              DEFAULT NULL,
  created_at     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_receipt (receipt_number),
  CONSTRAINT fk_pay_student   FOREIGN KEY (student_id)   REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_fee       FOREIGN KEY (fee_id)       REFERENCES fees(id),
  CONSTRAINT fk_pay_collector FOREIGN KEY (collected_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO payments (student_id, fee_id, amount_paid, payment_method, receipt_number, status, collected_by) VALUES
  (1, 1, 150000, 'especes',      'REC-20240902-001', 'paye',    6),
  (1, 2,  25000, 'mobile_money', 'REC-20240902-002', 'paye',    6),
  (1, 3,  45000, 'especes',      'REC-20241001-003', 'partiel', 6);

-- ============================================================
--  TABLE : notifications
-- ============================================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO notifications (user_id, title, message, type, is_read) VALUES
  (2, 'Nouveau paiement',      'Lucas Durand a effectue un paiement de 150 000 FCFA',   'success', 0),
  (2, 'Absence signalee',      'Un eleve a ete absent aujourd\'\'hui',                   'warning', 0),
  (2, 'Nouveau bulletin pret', 'Le bulletin du trimestre 2 est disponible',              'info',    1);

-- ============================================================
--  TABLE : messages
-- ============================================================
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
  KEY idx_receiver (receiver_id, is_read),
  CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_parent   FOREIGN KEY (parent_id)   REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO messages (sender_id, receiver_id, subject, body, is_read) VALUES
  (5, 2, 'Question sur les frais', 'Bonjour, je voudrais avoir des informations sur les frais de scolarite pour cette annee. Merci.', 0),
  (3, 2, 'Reunion pedagogique',    'Bonjour, je propose une reunion pedagogique pour le vendredi 28 mars. Etes-vous disponible ?',    0);

-- ============================================================
--  TABLE : system_settings
-- ============================================================
CREATE TABLE system_settings (
  id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(80)       NOT NULL,
  setting_value TEXT              DEFAULT NULL,
  label         VARCHAR(150)      DEFAULT NULL,
  created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (setting_key, setting_value, label) VALUES
  ('school_name',    'SmartSchool',           'Nom de l\'ecole'),
  ('school_address', '123 Rue de l\'Ecole',   'Adresse'),
  ('school_phone',   '+225 00 00 00 00',      'Telephone'),
  ('school_email',   'contact@smartschool.fr','Email'),
  ('currency',       'FCFA',                  'Devise'),
  ('max_grade',      '20',                    'Note maximale'),
  ('passing_grade',  '10',                    'Note de passage');

-- ============================================================
--  TABLE : activity_logs
-- ============================================================
CREATE TABLE activity_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED DEFAULT NULL,
  action      VARCHAR(100) NOT NULL,
  description TEXT         DEFAULT NULL,
  ip_address  VARCHAR(45)  DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user   (user_id),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  TABLE : report_cards
-- ============================================================
CREATE TABLE report_cards (
  id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  student_id      INT UNSIGNED      NOT NULL,
  term_id         SMALLINT UNSIGNED NOT NULL,
  average         DECIMAL(5,2)      DEFAULT NULL,
  rank            SMALLINT UNSIGNED DEFAULT NULL,
  class_average   DECIMAL(5,2)      DEFAULT NULL,
  conduct         ENUM('Excellent','Tres bien','Bien','Passable','A ameliorer') DEFAULT NULL,
  teacher_comment TEXT              DEFAULT NULL,
  admin_comment   TEXT              DEFAULT NULL,
  is_published    TINYINT(1)        NOT NULL DEFAULT 0,
  generated_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_report (student_id, term_id),
  CONSTRAINT fk_rc_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_rc_term    FOREIGN KEY (term_id)    REFERENCES terms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  CORRECTION MOT DE PASSE
--  Le hash ci-dessus correspond au mot de passe : password
--  Apres import, ouvre test.php et clique "Corriger le mot de passe"
--  pour le definir a : SmartSchool2025!
-- ============================================================
