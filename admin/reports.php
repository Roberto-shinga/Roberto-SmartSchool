<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_SUPER_ADMIN, ROLE_ADMIN);

$pageTitle   = 'Bulletins';
$pageSection = 'reports';

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;

$classId = (int)($_GET['class_id'] ?? 0);
$termId  = (int)($_GET['term_id']  ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Calculer / recalculer les bulletins de la classe ───────
    if ($action === 'generate') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $termId  = (int)($_POST['term_id']  ?? 0);

        $students = dbFetchAll("SELECT id FROM students WHERE class_id=? AND status='actif'", [$classId]);
        $averages = [];

        foreach ($students as $st) {
            $row = dbFetchOne(
                "SELECT SUM((g.score / g.max_score) * 20 * g.coefficient) / NULLIF(SUM(g.coefficient), 0) AS avg
                 FROM grades g JOIN teacher_assignments ta ON g.assignment_id = ta.id
                 WHERE g.student_id = ? AND g.term_id = ? AND ta.class_id = ?",
                [$st['id'], $termId, $classId]
            );
            if ($row && $row['avg'] !== null) $averages[$st['id']] = round((float)$row['avg'], 2);
        }

        if (empty($averages)) {
            redirectWith($_SERVER['PHP_SELF'] . "?class_id=$classId&term_id=$termId", 'danger',
                'Aucune note trouvee pour cette classe et ce trimestre. Impossible de calculer les bulletins.');
        } else {
            arsort($averages);
            $classAverage = round(array_sum($averages) / count($averages), 2);
            $total        = count($averages);
            $rank         = 0;

            foreach ($averages as $studentId => $avg) {
                $rank++;
                dbExecute(
                    "INSERT INTO report_cards (student_id, term_id, average, `rank`, class_average, total_students)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE average=VALUES(average), `rank`=VALUES(`rank`),
                                             class_average=VALUES(class_average), total_students=VALUES(total_students)",
                    [$studentId, $termId, $avg, $rank, $classAverage, $total]
                );
            }
            logActivity('report_cards_generated', "Classe #$classId, trimestre #$termId : $total bulletin(s)");
            redirectWith($_SERVER['PHP_SELF'] . "?class_id=$classId&term_id=$termId", 'success',
                "$total bulletin(s) calcule(s) avec succes.");
        }
    }

    // ── Mettre a jour conduite / commentaire admin ─────────────
    if ($action === 'update') {
        $id      = (int)($_POST['id'] ?? 0);
        $conduct = in_array($_POST['conduct'] ?? '', ['Excellent','Tres bien','Bien','Passable','A ameliorer']) ? $_POST['conduct'] : null;
        $comment = sanitizeString($_POST['admin_comment'] ?? '');
        dbExecute("UPDATE report_cards SET conduct=?, admin_comment=? WHERE id=?", [$conduct, $comment ?: null, $id]);
        logActivity('report_card_updated', "Bulletin #$id modifie");
        redirectWith($_SERVER['PHP_SELF'] . "?class_id=$classId&term_id=$termId", 'success', 'Bulletin mis a jour.');
    }

    // ── Publier / depublier ─────────────────────────────────────
    if ($action === 'toggle_publish') {
        $id = (int)($_POST['id'] ?? 0);
        $rc = dbFetchOne("SELECT * FROM report_cards WHERE id=?", [$id]);
        if ($rc) {
            dbExecute("UPDATE report_cards SET is_published=? WHERE id=?", [$rc['is_published'] ? 0 : 1, $id]);
            logActivity('report_card_publish_toggled', "Bulletin #$id -> " . ($rc['is_published'] ? 'depublie' : 'publie'));
            redirectWith($_SERVER['PHP_SELF'] . "?class_id=$classId&term_id=$termId", 'success', 'Statut de publication mis a jour.');
        }
    }
}

$classesList = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id=? ORDER BY grade_year, section", [$yearId]);
$termsList   = dbFetchAll("SELECT * FROM terms WHERE academic_year_id=? ORDER BY start_date", [$yearId]);

$reports = [];
if ($classId && $termId) {
    $reports = dbFetchAll(
        "SELECT rc.*, s.student_number, COALESCE(s.first_name, u.first_name) AS fn, COALESCE(s.last_name, u.last_name) AS ln
         FROM report_cards rc
         JOIN students s ON rc.student_id = s.id
         LEFT JOIN users u ON s.user_id = u.id
         WHERE s.class_id = ? AND rc.term_id = ?
         ORDER BY rc.`rank`",
        [$classId, $termId]
    );
}

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Bulletins</h1><p>Moyennes ponderees, classement et publication</p></div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <form method="GET" class="grid-3" style="align-items:end">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Classe</label>
            <select name="class_id" class="form-control" onchange="this.form.submit()">
              <option value="">Selectionner...</option>
              <?php foreach ($classesList as $c): ?><option value="<?= $c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Trimestre</label>
            <select name="term_id" class="form-control" onchange="this.form.submit()">
              <option value="">Selectionner...</option>
              <?php foreach ($termsList as $t): ?><option value="<?= $t['id'] ?>" <?= $termId===$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <?php if ($classId && $termId): ?>
          <div>
            <form method="POST" data-confirm="Recalculer les moyennes et le classement pour cette classe ?" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="generate">
              <input type="hidden" name="class_id" value="<?= $classId ?>">
              <input type="hidden" name="term_id" value="<?= $termId ?>">
              <button type="submit" class="btn btn-primary"><i class="bx bx-calculator"></i> Calculer les bulletins</button>
            </form>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php if (!$classId || !$termId): ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-file"></i></div><h3>Selectionne une classe et un trimestre</h3></div>
    <?php elseif (empty($reports)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-calculator"></i></div>
        <h3>Aucun bulletin calcule</h3>
        <p>Clique sur "Calculer les bulletins" pour generer les moyennes a partir des notes existantes.</p>
      </div>
    <?php else: ?>

    <div class="card">
      <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
        <table class="table">
          <thead><tr><th>Rang</th><th>Eleve</th><th>Moyenne</th><th>Moy. classe</th><th>Conduite</th><th>Publie</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($reports as $r): $mention = getMention((float)$r['average']); ?>
            <tr data-report='<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>'>
              <td class="font-semibold">#<?= $r['rank'] ?> / <?= $r['total_students'] ?></td>
              <td class="text-sm"><?= clean($r['fn'] . ' ' . $r['ln']) ?><div class="text-xs text-muted"><?= clean($r['student_number']) ?></div></td>
              <td>
                <span class="font-semibold" style="color:<?= $mention['color'] ?>"><?= number_format((float)$r['average'], 2) ?>/20</span>
                <div class="text-xs text-muted"><?= $mention['label'] ?></div>
              </td>
              <td class="text-sm text-muted"><?= number_format((float)$r['class_average'], 2) ?>/20</td>
              <td class="text-sm"><?= clean($r['conduct'] ?: '—') ?></td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_publish">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button type="submit" class="badge badge-<?= $r['is_published'] ? 'success' : 'gray' ?>" style="border:none;cursor:pointer">
                    <?= $r['is_published'] ? 'Publie' : 'Brouillon' ?>
                  </button>
                </form>
              </td>
              <td class="td-actions">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" onclick='openReportModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="bx bx-edit"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; ?>

  </div>
</div>

<!-- ══ Modal : conduite + commentaire administration ══ -->
<div class="modal-overlay" id="reportModal">
  <div class="modal">
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="rc_id">
      <div class="modal-header">
        <h3><i class="bx bx-edit"></i> Bulletin — <span id="rc_name"></span></h3>
        <button type="button" class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Conduite</label>
          <select name="conduct" id="rc_conduct" class="form-control">
            <option value="">Non evaluee</option>
            <option>Excellent</option><option>Tres bien</option><option>Bien</option><option>Passable</option><option>A ameliorer</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Commentaire de l'administration</label>
          <textarea name="admin_comment" id="rc_comment" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReportModal(r) {
  document.getElementById('rc_id').value = r.id;
  document.getElementById('rc_conduct').value = r.conduct || '';
  document.getElementById('rc_comment').value = r.admin_comment || '';
  document.getElementById('rc_name').textContent = r.fn + ' ' + r.ln;
  SS.openModal('reportModal');
}
</script>

<?php require INCLUDES_PATH . '/footer.php'; ?>
