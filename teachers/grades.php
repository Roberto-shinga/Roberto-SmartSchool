<?php
// ============================================================
//  SmartSchool — Notes (Enseignant)
//  Emplacement : teachers/grades.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_TEACHER);

$pageTitle   = 'Mes notes';
$pageSection = 'grades';
$user        = currentUser();

$teacher  = dbFetchOne("SELECT id FROM teachers WHERE user_id = ?", [$user['id']]);
$teacherId= $teacher['id'] ?? 0;

$currentYear = dbFetchOne("SELECT id FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;
$terms       = dbFetchAll("SELECT id, name FROM terms WHERE academic_year_id=? ORDER BY start_date", [$yearId]);
$currentTerm = dbFetchOne("SELECT id FROM terms WHERE is_current = 1");

// Mes associations classe-matiere
$myCS = dbFetchAll(
    "SELECT cs.id, cs.class_id, cs.subject_id,
            c.name AS class_name, sub.name AS subject_name, sub.color
     FROM class_subjects cs
     JOIN classes c ON cs.class_id = c.id
     JOIN subjects sub ON cs.subject_id = sub.id
     WHERE cs.teacher_id = ? AND cs.academic_year_id = ?
     ORDER BY c.name, sub.name",
    [$teacherId, $yearId]
);

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $studentId = (int)($_POST['student_id']       ?? 0);
        $csId      = (int)($_POST['class_subject_id'] ?? 0);
        $termId    = (int)($_POST['term_id']          ?? 0);
        $type      = trim($_POST['grade_type']         ?? 'devoir');
        $title     = trim($_POST['title']             ?? '');
        $score     = (float)($_POST['score']          ?? 0);
        $maxScore  = (float)($_POST['max_score']      ?? 20);
        $coeff     = (float)($_POST['coefficient']    ?? 1);
        $date      = trim($_POST['date']              ?? date('Y-m-d'));
        $comment   = trim($_POST['comment']           ?? '');

        // Verifier que ce cs appartient a cet enseignant
        $allowed = array_column($myCS, 'id');
        if (!in_array($csId, $allowed) || $score > $maxScore) {
            redirectWith(BASE_URL . '/teachers/grades.php', 'danger', 'Donnees invalides.');
        }

        dbExecute(
            "INSERT INTO grades (student_id, class_subject_id, term_id, grade_type, title, score, max_score, coefficient, date, comment, graded_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$studentId, $csId, $termId, $type, $title, $score, $maxScore, $coeff, $date, $comment, $user['id']]
        );
        logActivity('add_grade', "Note ajoutee par enseignant $teacherId");
        redirectWith(BASE_URL . '/teachers/grades.php', 'success', 'Note enregistree !');
    }

    if ($action === 'delete') {
        $gradeId = (int)($_POST['grade_id'] ?? 0);
        // Verifier que la note appartient a cet enseignant
        $g = dbFetchOne(
            "SELECT g.id FROM grades g
             JOIN class_subjects cs ON g.class_subject_id = cs.id
             WHERE g.id = ? AND cs.teacher_id = ?",
            [$gradeId, $teacherId]
        );
        if ($g) {
            dbExecute("DELETE FROM grades WHERE id = ?", [$gradeId]);
            redirectWith(BASE_URL . '/teachers/grades.php', 'success', 'Note supprimee.');
        }
    }
}

// Filtres
$filterCS   = (int)($_GET['cs']   ?? 0);
$filterTerm = (int)($_GET['term'] ?? ($currentTerm['id'] ?? 0));
$page       = max(1, (int)($_GET['page'] ?? 1));

$where  = ['cs.teacher_id = ' . $teacherId];
$params = [];
if ($filterCS)   { $where[] = "g.class_subject_id = $filterCS"; }
if ($filterTerm) { $where[] = "g.term_id = $filterTerm"; }

$whereStr = 'WHERE ' . implode(' AND ', $where);
$total    = dbFetchOne("SELECT COUNT(*) c FROM grades g JOIN class_subjects cs ON g.class_subject_id=cs.id $whereStr", $params)['c'] ?? 0;
$pag      = paginate($total, $page);

$grades = dbFetchAll(
    "SELECT g.id, g.score, g.max_score, g.coefficient, g.grade_type, g.title, g.date, g.comment,
            u.first_name, u.last_name, s.student_number,
            sub.name AS subject_name, sub.color,
            c.name AS class_name,
            t.name AS term_name
     FROM grades g
     JOIN class_subjects cs ON g.class_subject_id = cs.id
     JOIN students st ON g.student_id = st.id
     JOIN users u ON st.user_id = u.id
     JOIN subjects sub ON cs.subject_id = sub.id
     JOIN classes c ON cs.class_id = c.id
     JOIN terms t ON g.term_id = t.id
     $whereStr
     ORDER BY g.date DESC, g.created_at DESC
     LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
    $params
);

// Eleves pour mes classes
$myStudents = dbFetchAll(
    "SELECT DISTINCT st.id, u.first_name, u.last_name, st.student_number, st.class_id
     FROM class_subjects cs
     JOIN students st ON st.class_id = cs.class_id
     JOIN users u ON st.user_id = u.id
     WHERE cs.teacher_id = ? AND cs.academic_year_id = ? AND st.status='actif'
     ORDER BY u.last_name",
    [$teacherId, $yearId]
);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/sidebar.php';
?>

<div class="app-layout">
<div class="main-content" id="mainContent">
<div class="page-wrapper">

<?= showFlash() ?>

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px">
      <i class="bx bx-star" style="color:var(--primary)"></i> Mes notes
    </h1>
    <p><?= $total ?> note<?= $total>1?'s':'' ?> saisie<?= $total>1?'s':'' ?></p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" data-modal="modalAdd">
      <i class="bx bx-plus"></i> Ajouter une note
    </button>
  </div>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:200px">
        <label class="form-label">Classe / Matiere</label>
        <select name="cs" class="form-control">
          <option value="">Toutes</option>
          <?php foreach ($myCS as $cs): ?>
            <option value="<?= $cs['id'] ?>" <?= $filterCS==$cs['id']?'selected':'' ?>>
              <?= clean($cs['class_name']) ?> — <?= clean($cs['subject_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:160px">
        <label class="form-label">Trimestre</label>
        <select name="term" class="form-control">
          <option value="">Tous</option>
          <?php foreach ($terms as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $filterTerm==$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:auto">
        <i class="bx bx-filter-alt"></i> Filtrer
      </button>
    </form>
  </div>
</div>

<!-- Tableau -->
<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Eleve</th>
          <th>Matiere</th>
          <th>Classe</th>
          <th>Type</th>
          <th>Note</th>
          <th>/20</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($grades)): ?>
          <tr><td colspan="8">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="bx bx-star"></i></div>
              <h3>Aucune note</h3>
              <button class="btn btn-primary" data-modal="modalAdd"><i class="bx bx-plus"></i> Ajouter</button>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($grades as $i => $g):
            $sur20 = $g['max_score']>0 ? round($g['score']/$g['max_score']*20,2) : 0;
            $mention = getMention($sur20);
          ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="avatar avatar-32" style="background:var(--primary)"><?= getInitials($g['first_name'],$g['last_name']) ?></div>
                <div>
                  <div class="td-name"><?= clean($g['first_name']) ?> <?= clean($g['last_name']) ?></div>
                  <div class="td-sub"><?= clean($g['student_number']) ?></div>
                </div>
              </div>
            </td>
            <td><span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;border-radius:50%;background:<?= $g['color'] ?>"></span><?= clean($g['subject_name']) ?></span></td>
            <td style="color:var(--text-muted)"><?= clean($g['class_name']) ?></td>
            <td><span class="badge badge-primary" style="font-size:11px"><?= clean($g['grade_type']) ?></span></td>
            <td style="font-weight:700;color:<?= $sur20>=10?'var(--success)':'var(--danger)' ?>"><?= $g['score'] ?>/<?= $g['max_score'] ?></td>
            <td><span style="font-weight:800;color:<?= $sur20>=10?'var(--success)':'var(--danger)' ?>"><?= $sur20 ?>/20</span></td>
            <td style="font-size:12px;color:var(--text-muted)"><?= formatDate($g['date']) ?></td>
            <td>
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action"   value="delete">
                <input type="hidden" name="grade_id" value="<?= $g['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                        onclick="return confirm('Supprimer cette note ?')">
                  <i class="bx bx-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL AJOUTER -->
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
          <div class="form-group">
            <label class="form-label">Classe / Matiere <span class="form-required">*</span></label>
            <select name="class_subject_id" id="csSelect" class="form-control" required onchange="filterStudents(this.value)">
              <option value="">Selectionner</option>
              <?php foreach ($myCS as $cs): ?>
                <option value="<?= $cs['id'] ?>" data-class="<?= $cs['class_id'] ?>">
                  <?= clean($cs['class_name']) ?> — <?= clean($cs['subject_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Eleve <span class="form-required">*</span></label>
            <select name="student_id" id="studentSelect" class="form-control" required>
              <option value="">Selectionner d'abord une classe</option>
              <?php foreach ($myStudents as $s): ?>
                <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>" style="display:none">
                  <?= clean($s['first_name']) ?> <?= clean($s['last_name']) ?> (<?= clean($s['student_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Trimestre <span class="form-required">*</span></label>
            <select name="term_id" class="form-control" required>
              <?php foreach ($terms as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($currentTerm && $t['id']==$currentTerm['id'])?'selected':'' ?>><?= clean($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="grade_type" class="form-control">
              <option value="devoir">Devoir surveille</option>
              <option value="interrogation">Interrogation</option>
              <option value="examen">Examen</option>
              <option value="tp">TP</option>
              <option value="projet">Projet</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Note obtenue <span class="form-required">*</span></label>
            <input type="number" name="score" class="form-control" min="0" step="0.25" required oninput="previewScore()">
          </div>
          <div class="form-group">
            <label class="form-label">Note maximale</label>
            <input type="number" name="max_score" id="maxScore" class="form-control" value="20" min="1" oninput="previewScore()">
          </div>
          <div class="form-group">
            <label class="form-label">Intitule</label>
            <input type="text" name="title" class="form-control" placeholder="Devoir 1 — Chapitre 2">
          </div>
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div id="scorePreview" style="display:none;text-align:center;padding:10px;background:var(--bg-body);border-radius:var(--radius);margin-bottom:12px">
          <span style="font-size:13px;color:var(--text-muted)">Note /20 : </span>
          <span id="previewVal" style="font-size:22px;font-weight:800;color:var(--primary)">—</span>
        </div>
        <div class="form-group">
          <label class="form-label">Commentaire</label>
          <textarea name="comment" class="form-control" rows="2" placeholder="Appreciation..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

</div>
</div>
</div>

<?php
$pageScript = "
function filterStudents(csId) {
  const sel = document.getElementById('csSelect');
  const opt = sel.options[sel.selectedIndex];
  const classId = opt ? opt.dataset.class : '';
  const studentSel = document.getElementById('studentSelect');
  [...studentSel.options].forEach(o => {
    if (!o.value) return;
    o.style.display = (!classId || o.dataset.class == classId) ? '' : 'none';
  });
  studentSel.value = '';
}

function previewScore() {
  const score = parseFloat(document.querySelector('[name=score]').value) || 0;
  const max   = parseFloat(document.getElementById('maxScore').value) || 20;
  if (score <= 0) { document.getElementById('scorePreview').style.display='none'; return; }
  const sur20 = Math.round(score/max*20*100)/100;
  document.getElementById('previewVal').textContent = sur20+'/20';
  document.getElementById('previewVal').style.color = sur20>=10?'var(--success)':'var(--danger)';
  document.getElementById('scorePreview').style.display = 'block';
}
";
require_once INCLUDES_PATH . '/footer.php';
?>