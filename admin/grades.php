<?php
// ============================================================
//  SmartSchool — Gestion des notes
//  Emplacement : admin/grades.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_TEACHER);

$pageTitle   = 'Gestion des notes';
$pageSection = 'grades';
$user        = currentUser();

// Donnees pour les selects
$currentYear = dbFetchOne("SELECT id FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;

$classes  = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id = ? ORDER BY name", [$yearId]);
$subjects = dbFetchAll("SELECT id, name, code, coefficient, color FROM subjects WHERE is_active = 1 ORDER BY name");
$terms    = dbFetchAll("SELECT id, name FROM terms WHERE academic_year_id = ? ORDER BY start_date", [$yearId]);
$currentTerm = dbFetchOne("SELECT id, name FROM terms WHERE is_current = 1");

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // AJOUTER NOTE
    if ($action === 'add') {
        $studentId      = (int)($_POST['student_id']      ?? 0);
        $classSubjectId = (int)($_POST['class_subject_id']?? 0);
        $termId         = (int)($_POST['term_id']         ?? 0);
        $gradeType      = trim($_POST['grade_type']        ?? 'devoir');
        $title          = trim($_POST['title']             ?? '');
        $score          = (float)($_POST['score']          ?? 0);
        $maxScore       = (float)($_POST['max_score']      ?? 20);
        $coeff          = (float)($_POST['coefficient']    ?? 1);
        $date           = trim($_POST['date']              ?? date('Y-m-d'));
        $comment        = trim($_POST['comment']           ?? '');

        if (!$studentId || !$classSubjectId || !$termId || $score < 0 || $score > $maxScore) {
            redirectWith(BASE_URL . '/admin/grades.php', 'danger',
                'Donnees invalides. Verifiez les champs obligatoires.');
        }

        try {
            dbExecute(
                "INSERT INTO grades
                 (student_id, class_subject_id, term_id, grade_type, title, score, max_score, coefficient, date, comment, graded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$studentId, $classSubjectId, $termId, $gradeType,
                 $title, $score, $maxScore, $coeff, $date, $comment, $user['id']]
            );
            logActivity('add_grade', "Note ajoutee : $score/$maxScore pour eleve $studentId");
            redirectWith(BASE_URL . '/admin/grades.php', 'success', 'Note enregistree avec succes !');
        } catch (Exception $e) {
            redirectWith(BASE_URL . '/admin/grades.php', 'danger', 'Erreur : ' . $e->getMessage());
        }
    }

    // MODIFIER NOTE
    if ($action === 'edit') {
        $gradeId  = (int)($_POST['grade_id'] ?? 0);
        $score    = (float)($_POST['score']   ?? 0);
        $maxScore = (float)($_POST['max_score'] ?? 20);
        $title    = trim($_POST['title']    ?? '');
        $comment  = trim($_POST['comment']  ?? '');
        $date     = trim($_POST['date']     ?? date('Y-m-d'));
        $coeff    = (float)($_POST['coefficient'] ?? 1);

        if ($score < 0 || $score > $maxScore) {
            redirectWith(BASE_URL . '/admin/grades.php', 'danger', 'Note invalide.');
        }

        dbExecute(
            "UPDATE grades SET score=?, max_score=?, title=?, comment=?, date=?, coefficient=? WHERE id=?",
            [$score, $maxScore, $title, $comment, $date, $coeff, $gradeId]
        );
        logActivity('edit_grade', "Note modifiee : ID $gradeId");
        redirectWith(BASE_URL . '/admin/grades.php', 'success', 'Note mise a jour.');
    }

    // SUPPRIMER NOTE
    if ($action === 'delete') {
        $gradeId = (int)($_POST['grade_id'] ?? 0);
        dbExecute("DELETE FROM grades WHERE id = ?", [$gradeId]);
        logActivity('delete_grade', "Note supprimee : ID $gradeId");
        redirectWith(BASE_URL . '/admin/grades.php', 'success', 'Note supprimee.');
    }
}

// ── Filtres ──────────────────────────────────────────────
$filterClass   = (int)($_GET['class']   ?? 0);
$filterSubject = (int)($_GET['subject'] ?? 0);
$filterTerm    = (int)($_GET['term']    ?? ($currentTerm['id'] ?? 0));
$page          = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($filterClass) {
    $where[] = "cs.class_id = $filterClass";
}
if ($filterSubject) {
    $where[] = "cs.subject_id = $filterSubject";
}
if ($filterTerm) {
    $where[] = "g.term_id = $filterTerm";
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$total  = dbFetchOne(
    "SELECT COUNT(*) c FROM grades g
     JOIN class_subjects cs ON g.class_subject_id = cs.id
     $whereStr", $params
)['c'] ?? 0;

$pag    = paginate($total, $page);
$grades = dbFetchAll(
    "SELECT g.id, g.score, g.max_score, g.coefficient, g.grade_type,
            g.title, g.date, g.comment,
            u.first_name, u.last_name,
            s.student_number,
            sub.name AS subject_name, sub.color AS subject_color,
            c.name  AS class_name,
            t.name  AS term_name
     FROM grades g
     JOIN class_subjects cs ON g.class_subject_id = cs.id
     JOIN students s        ON g.student_id        = s.id
     JOIN users u           ON s.user_id           = u.id
     JOIN subjects sub      ON cs.subject_id       = sub.id
     JOIN classes c         ON cs.class_id         = c.id
     JOIN terms t           ON g.term_id           = t.id
     $whereStr
     ORDER BY g.date DESC, g.created_at DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);

// Stats notes
$statsGrades = dbFetchOne(
    "SELECT
        COUNT(*) nb,
        ROUND(AVG(score/max_score*20),2) avg_score,
        MIN(score/max_score*20) min_score,
        MAX(score/max_score*20) max_score
     FROM grades g
     JOIN class_subjects cs ON g.class_subject_id = cs.id
     " . ($filterTerm ? "WHERE g.term_id = $filterTerm" : "")
) ?: ['nb'=>0,'avg_score'=>0,'min_score'=>0,'max_score'=>0];

// Eleves pour le select d'ajout
$studentsForSelect = dbFetchAll(
    "SELECT s.id, s.student_number, u.first_name, u.last_name, s.class_id
     FROM students s JOIN users u ON s.user_id = u.id
     WHERE s.status = 'actif' ORDER BY u.last_name"
);

// Associations classe-matiere-enseignant
$classSubjects = dbFetchAll(
    "SELECT cs.id, cs.class_id, cs.subject_id,
            c.name AS class_name, sub.name AS subject_name
     FROM class_subjects cs
     JOIN classes c ON cs.class_id = cs.class_id
     JOIN subjects sub ON cs.subject_id = sub.id
     WHERE cs.academic_year_id = ?
     ORDER BY c.name, sub.name",
    [$yearId]
);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<!-- ENTETE -->
<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-star" style="color:var(--primary)"></i>
      Gestion des notes
    </h1>
    <p>
      <?= $total ?> note<?= $total > 1 ? 's' : '' ?> enregistree<?= $total > 1 ? 's' : '' ?>
      <?php if ($currentTerm): ?>
        — <?= clean($currentTerm['name']) ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-plus"></i> Ajouter une note
    </button>
  </div>
</div>

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:16px;margin-bottom:24px">
  <div class="stat-card c-primary">
    <div class="stat-icon c-primary"><i class="bx bx-list-ul"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $statsGrades['nb'] ?></div>
      <div class="stat-label">Notes saisies</div>
    </div>
  </div>
  <div class="stat-card c-success">
    <div class="stat-icon c-success"><i class="bx bx-trending-up"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($statsGrades['avg_score'] ?? 0, 2) ?></div>
      <div class="stat-label">Moyenne generale /20</div>
    </div>
  </div>
  <div class="stat-card c-cyan">
    <div class="stat-icon c-cyan"><i class="bx bx-up-arrow-alt"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($statsGrades['max_score'] ?? 0, 2) ?></div>
      <div class="stat-label">Note maximale /20</div>
    </div>
  </div>
  <div class="stat-card c-warning">
    <div class="stat-icon c-warning"><i class="bx bx-down-arrow-alt"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($statsGrades['min_score'] ?? 0, 2) ?></div>
      <div class="stat-label">Note minimale /20</div>
    </div>
  </div>
</div>

<!-- FILTRES -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:160px">
        <label class="form-label">Classe</label>
        <select name="class" class="form-control">
          <option value="">Toutes les classes</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>>
              <?= clean($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:160px">
        <label class="form-label">Matiere</label>
        <select name="subject" class="form-control">
          <option value="">Toutes les matieres</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $filterSubject == $s['id'] ? 'selected' : '' ?>>
              <?= clean($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:150px">
        <label class="form-label">Trimestre</label>
        <select name="term" class="form-control">
          <option value="">Tous les trimestres</option>
          <?php foreach ($terms as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $filterTerm == $t['id'] ? 'selected' : '' ?>>
              <?= clean($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:auto">
        <i class="bx bx-filter-alt"></i> Filtrer
      </button>
      <?php if ($filterClass || $filterSubject || $filterTerm): ?>
        <a href="<?= BASE_URL ?>/admin/grades.php" class="btn btn-secondary" style="margin-top:auto">
          <i class="bx bx-x"></i> Effacer
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- TABLEAU DES NOTES -->
<div class="card">
  <div class="card-header">
    <h3><i class="bx bx-table"></i> Liste des notes</h3>
    <span style="font-size:13px;color:var(--text-muted)"><?= $total ?> note<?= $total > 1 ? 's' : '' ?></span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Eleve</th>
          <th>Matiere</th>
          <th>Classe</th>
          <th>Type</th>
          <th>Titre</th>
          <th>Note</th>
          <th>Sur 20</th>
          <th>Mention</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($grades)): ?>
          <tr>
            <td colspan="11">
              <div class="empty-state">
                <div class="empty-state-icon"><i class="bx bx-star"></i></div>
                <h3>Aucune note</h3>
                <p>Aucune note enregistree pour ces filtres.</p>
                <button class="btn btn-primary" data-modal="modalAdd">
                  <i class="bx bx-plus"></i> Ajouter une note
                </button>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($grades as $i => $g):
            $sur20   = $g['max_score'] > 0 ? round($g['score'] / $g['max_score'] * 20, 2) : 0;
            $mention = getMention($sur20);
            $pct     = $g['max_score'] > 0 ? ($g['score'] / $g['max_score'] * 100) : 0;

            $typeLabels = [
              'devoir'       => ['label'=>'Devoir',        'badge'=>'primary'],
              'interrogation'=> ['label'=>'Interro',       'badge'=>'info'],
              'examen'       => ['label'=>'Examen',        'badge'=>'danger'],
              'tp'           => ['label'=>'TP',            'badge'=>'success'],
              'projet'       => ['label'=>'Projet',        'badge'=>'purple'],
            ];
            $tl = $typeLabels[$g['grade_type']] ?? ['label'=>$g['grade_type'],'badge'=>'gray'];
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:12px"><?= $pag['offset'] + $i + 1 ?></td>
            <td>
              <div class="td-user">
                <div class="avatar avatar-32" style="background:var(--primary)">
                  <?= getInitials($g['first_name'], $g['last_name']) ?>
                </div>
                <div>
                  <div class="td-name"><?= clean($g['first_name']) ?> <?= clean($g['last_name']) ?></div>
                  <div class="td-sub"><?= clean($g['student_number']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <span style="display:flex;align-items:center;gap:6px">
                <span style="width:10px;height:10px;border-radius:50%;background:<?= clean($g['subject_color']) ?>;flex-shrink:0"></span>
                <span style="font-size:13px;color:var(--text-secondary)"><?= clean($g['subject_name']) ?></span>
              </span>
            </td>
            <td style="color:var(--text-muted)"><?= clean($g['class_name']) ?></td>
            <td>
              <span class="badge badge-<?= $tl['badge'] ?>"><?= $tl['label'] ?></span>
            </td>
            <td style="color:var(--text-muted);font-size:12.5px">
              <?= clean($g['title'] ?: '—') ?>
            </td>
            <td>
              <!-- Barre de progression -->
              <div style="display:flex;align-items:center;gap:8px">
                <div style="flex:1;min-width:60px">
                  <div class="progress">
                    <div class="progress-bar <?= $sur20 >= 10 ? 'success' : 'danger' ?>"
                         style="width:<?= min(100,$pct) ?>%"></div>
                  </div>
                </div>
                <span style="font-size:14px;font-weight:700;color:<?= $sur20 >= 10 ? 'var(--success)' : 'var(--danger)' ?>;min-width:40px">
                  <?= $g['score'] ?>/<?= $g['max_score'] ?>
                </span>
              </div>
            </td>
            <td style="font-weight:700;font-size:15px;color:<?= $sur20 >= 10 ? 'var(--success)' : 'var(--danger)' ?>">
              <?= $sur20 ?>/20
            </td>
            <td>
              <span class="badge" style="background:<?= $mention['color'] ?>20;color:<?= $mention['color'] ?>">
                <?= $mention['label'] ?>
              </span>
            </td>
            <td style="color:var(--text-muted);font-size:12.5px"><?= formatDate($g['date']) ?></td>
            <td>
              <div class="td-actions">
                <button class="btn btn-sm btn-secondary btn-icon"
                        title="Modifier"
                        onclick='openEditGrade(<?= json_encode([
                          "id"          => $g["id"],
                          "title"       => $g["title"],
                          "score"       => $g["score"],
                          "max_score"   => $g["max_score"],
                          "coefficient" => $g["coefficient"],
                          "date"        => $g["date"],
                          "comment"     => $g["comment"],
                        ], JSON_HEX_QUOT | JSON_HEX_APOS) ?>)'>
                  <i class="bx bx-edit"></i>
                </button>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"   value="delete">
                  <input type="hidden" name="grade_id" value="<?= $g['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                          onclick="return confirm('Supprimer cette note ?')">
                    <i class="bx bx-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pag['total_pages'] > 1): ?>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <span style="font-size:13px;color:var(--text-muted)">
      <?= $pag['offset']+1 ?> – <?= min($pag['offset']+$pag['per_page'],$total) ?> sur <?= $total ?>
    </span>
    <nav class="pagination">
      <?php if ($pag['has_prev']): ?>
        <a href="?page=<?= $pag['prev_page'] ?>&class=<?= $filterClass ?>&subject=<?= $filterSubject ?>&term=<?= $filterTerm ?>">
          <i class="bx bx-chevron-left"></i>
        </a>
      <?php endif; ?>
      <?php for ($pg = max(1,$pag['current_page']-2); $pg <= min($pag['total_pages'],$pag['current_page']+2); $pg++): ?>
        <?php if ($pg === $pag['current_page']): ?>
          <span class="active"><?= $pg ?></span>
        <?php else: ?>
          <a href="?page=<?= $pg ?>&class=<?= $filterClass ?>&subject=<?= $filterSubject ?>&term=<?= $filterTerm ?>"><?= $pg ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($pag['has_next']): ?>
        <a href="?page=<?= $pag['next_page'] ?>&class=<?= $filterClass ?>&subject=<?= $filterSubject ?>&term=<?= $filterTerm ?>">
          <i class="bx bx-chevron-right"></i>
        </a>
      <?php endif; ?>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL AJOUTER NOTE -->
<div class="modal-overlay" id="modalAdd">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="bx bx-plus" style="color:var(--primary)"></i> Ajouter une note</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="grid-2">

          <!-- Classe (filtre eleves) -->
          <div class="form-group">
            <label class="form-label">Classe <span class="form-required">*</span></label>
            <select id="addClassFilter" class="form-control" onchange="filterStudentsByClass(this.value)">
              <option value="">Toutes les classes</option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Eleve -->
          <div class="form-group">
            <label class="form-label">Eleve <span class="form-required">*</span></label>
            <select name="student_id" id="studentSelect" class="form-control" required>
              <option value="">Selectionner un eleve</option>
              <?php foreach ($studentsForSelect as $s): ?>
                <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>">
                  <?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?>
                  (<?= clean($s['student_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Matiere + Classe-Sujet -->
          <div class="form-group">
            <label class="form-label">Matiere <span class="form-required">*</span></label>
            <select name="class_subject_id" id="csSelect" class="form-control" required>
              <option value="">Selectionner la matiere</option>
              <?php foreach ($classSubjects as $cs): ?>
                <option value="<?= $cs['id'] ?>" data-class="<?= $cs['class_id'] ?>">
                  <?= clean($cs['subject_name']) ?>
                  (<?= clean($cs['class_name']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Trimestre -->
          <div class="form-group">
            <label class="form-label">Trimestre <span class="form-required">*</span></label>
            <select name="term_id" class="form-control" required>
              <?php foreach ($terms as $t): ?>
                <option value="<?= $t['id'] ?>"
                        <?= ($currentTerm && $t['id'] == $currentTerm['id']) ? 'selected' : '' ?>>
                  <?= clean($t['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Type de note -->
          <div class="form-group">
            <label class="form-label">Type <span class="form-required">*</span></label>
            <select name="grade_type" class="form-control">
              <option value="devoir">Devoir surveille</option>
              <option value="interrogation">Interrogation</option>
              <option value="examen">Examen</option>
              <option value="tp">Travaux pratiques</option>
              <option value="projet">Projet</option>
            </select>
          </div>

          <!-- Titre -->
          <div class="form-group">
            <label class="form-label">Intitule de l'epreuve</label>
            <input type="text" name="title" class="form-control"
                   placeholder="Ex : Devoir 1 — Algebre">
          </div>

          <!-- Note obtenue -->
          <div class="form-group">
            <label class="form-label">Note obtenue <span class="form-required">*</span></label>
            <div class="input-wrap">
              <i class="bx bx-star input-icon"></i>
              <input type="number" name="score" id="scoreInput" class="form-control"
                     placeholder="0" min="0" step="0.25" required
                     oninput="updateScorePreview()">
            </div>
          </div>

          <!-- Note maximale -->
          <div class="form-group">
            <label class="form-label">Note maximale</label>
            <input type="number" name="max_score" id="maxScoreInput" class="form-control"
                   value="20" min="1" step="1" oninput="updateScorePreview()">
          </div>

          <!-- Coefficient -->
          <div class="form-group">
            <label class="form-label">Coefficient</label>
            <input type="number" name="coefficient" class="form-control"
                   value="1" min="0.5" step="0.5">
          </div>

          <!-- Date -->
          <div class="form-group">
            <label class="form-label">Date de l'epreuve</label>
            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <!-- Apercu note -->
        <div id="scorePreview" style="display:none;padding:14px;background:var(--bg-body);border-radius:var(--radius);margin-bottom:16px;text-align:center">
          <span style="font-size:13px;color:var(--text-muted)">Note sur 20 : </span>
          <span id="previewVal" style="font-size:22px;font-weight:800;color:var(--primary)">—</span>
          <span id="previewMention" style="font-size:12px;font-weight:600;margin-left:10px"></span>
        </div>

        <!-- Commentaire -->
        <div class="form-group">
          <label class="form-label">Commentaire</label>
          <textarea name="comment" class="form-control" rows="2"
                    placeholder="Appreciation de l'enseignant..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary">
          <i class="bx bx-save"></i> Enregistrer la note
        </button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL MODIFIER NOTE -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-edit" style="color:var(--primary)"></i> Modifier la note</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"   value="edit">
      <input type="hidden" name="grade_id" id="eGradeId">
      <div class="modal-body">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Intitule</label>
            <input type="text" name="title" id="eTitle" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="date" id="eDate" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Note obtenue <span class="form-required">*</span></label>
            <input type="number" name="score" id="eScore" class="form-control" min="0" step="0.25" required>
          </div>
          <div class="form-group">
            <label class="form-label">Note maximale</label>
            <input type="number" name="max_score" id="eMaxScore" class="form-control" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Coefficient</label>
            <input type="number" name="coefficient" id="eCoeff" class="form-control" min="0.5" step="0.5">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Commentaire</label>
          <textarea name="comment" id="eComment" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary">
          <i class="bx bx-save"></i> Mettre a jour
        </button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php
// Mentions pour JS
$mentionsJson = json_encode(GRADE_MENTIONS);

$pageScript = "
const MENTIONS = $mentionsJson;

// Apercu de la note sur 20
function updateScorePreview() {
  const score    = parseFloat(document.getElementById('scoreInput').value) || 0;
  const maxScore = parseFloat(document.getElementById('maxScoreInput').value) || 20;
  if (score <= 0 || maxScore <= 0) {
    document.getElementById('scorePreview').style.display = 'none';
    return;
  }
  const sur20 = Math.round((score / maxScore * 20) * 100) / 100;
  document.getElementById('previewVal').textContent = sur20 + '/20';
  document.getElementById('previewVal').style.color =
    sur20 >= 10 ? 'var(--success)' : 'var(--danger)';

  // Mention
  const mention = MENTIONS.find(m => sur20 >= m.min && sur20 <= m.max);
  const mEl = document.getElementById('previewMention');
  if (mention) {
    mEl.textContent  = mention.label;
    mEl.style.color  = mention.color;
  }

  document.getElementById('scorePreview').style.display = 'block';
}

// Filtrer eleves par classe
function filterStudentsByClass(classId) {
  const sel = document.getElementById('studentSelect');
  const csEl = document.getElementById('csSelect');
  [...sel.options].forEach(opt => {
    if (!opt.value) return;
    opt.style.display = (!classId || opt.dataset.class == classId) ? '' : 'none';
  });
  [...csEl.options].forEach(opt => {
    if (!opt.value) return;
    opt.style.display = (!classId || opt.dataset.class == classId) ? '' : 'none';
  });
  sel.value = '';
  csEl.value = '';
}

// Ouvrir modal modifier
function openEditGrade(d) {
  document.getElementById('eGradeId').value  = d.id;
  document.getElementById('eTitle').value    = d.title || '';
  document.getElementById('eScore').value    = d.score;
  document.getElementById('eMaxScore').value = d.max_score;
  document.getElementById('eCoeff').value    = d.coefficient;
  document.getElementById('eDate').value     = d.date;
  document.getElementById('eComment').value  = d.comment || '';
  SS_Modal.open('modalEdit');
}
";
require_once INCLUDES_PATH . '/footer.php';
?>
