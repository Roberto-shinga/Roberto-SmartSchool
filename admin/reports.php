<?php
// ============================================================
//  SmartSchool — Bulletins scolaires
//  Emplacement : admin/reports.php
// ============================================================

require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireRole(ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_TEACHER);

$pageTitle   = 'Bulletins scolaires';
$pageSection = 'reports';
$user        = currentUser();

$currentYear = dbFetchOne("SELECT id, name FROM academic_years WHERE is_current = 1");
$yearId      = $currentYear['id'] ?? 1;
$terms       = dbFetchAll("SELECT id, name FROM terms WHERE academic_year_id = ? ORDER BY start_date", [$yearId]);
$currentTerm = dbFetchOne("SELECT id, name FROM terms WHERE is_current = 1");
$classes     = dbFetchAll("SELECT id, name FROM classes WHERE academic_year_id = ? ORDER BY name", [$yearId]);

// ── Traitement POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Generer les bulletins pour une classe + trimestre
    if ($action === 'generate') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $termId  = (int)($_POST['term_id']  ?? 0);

        if (!$classId || !$termId) {
            redirectWith(BASE_URL . '/admin/reports.php', 'danger', 'Classe et trimestre obligatoires.');
        }

        $students = dbFetchAll(
            "SELECT s.id FROM students s WHERE s.class_id = ? AND s.status = 'actif'",
            [$classId]
        );

        $generated = 0;
        foreach ($students as $s) {
            // Calculer la moyenne de cet eleve pour ce trimestre
            $avg = dbFetchOne(
                "SELECT ROUND(
                    SUM((g.score/g.max_score*20) * g.coefficient) / NULLIF(SUM(g.coefficient),0)
                ,2) AS avg
                FROM grades g
                JOIN class_subjects cs ON g.class_subject_id = cs.id
                WHERE g.student_id = ? AND g.term_id = ?",
                [$s['id'], $termId]
            )['avg'] ?? null;

            // Calculer le rang dans la classe
            $rank = null;
            if ($avg !== null) {
                $betterCount = dbFetchOne(
                    "SELECT COUNT(DISTINCT g2.student_id) c
                     FROM grades g2
                     JOIN class_subjects cs2 ON g2.class_subject_id = cs2.id
                     JOIN students s2 ON g2.student_id = s2.id
                     WHERE s2.class_id = ? AND g2.term_id = ?
                     GROUP BY g2.student_id
                     HAVING ROUND(SUM((g2.score/g2.max_score*20)*g2.coefficient)/NULLIF(SUM(g2.coefficient),0),2) > ?",
                    [$classId, $termId, $avg]
                );
                $rank = ($betterCount['c'] ?? 0) + 1;
            }

            // Moyenne de la classe
            $classAvg = dbFetchOne(
                "SELECT ROUND(AVG(sub_avg),2) AS avg FROM (
                    SELECT g.student_id,
                           SUM((g.score/g.max_score*20)*g.coefficient)/NULLIF(SUM(g.coefficient),0) AS sub_avg
                    FROM grades g
                    JOIN class_subjects cs ON g.class_subject_id = cs.id
                    JOIN students s3 ON g.student_id = s3.id
                    WHERE s3.class_id = ? AND g.term_id = ?
                    GROUP BY g.student_id
                ) t",
                [$classId, $termId]
            )['avg'] ?? null;

            // Appreciation automatique
            $conduct = null;
            if ($avg !== null) {
                if ($avg >= 16)     $conduct = 'Excellent';
                elseif ($avg >= 14) $conduct = 'Tres bien';
                elseif ($avg >= 12) $conduct = 'Bien';
                elseif ($avg >= 10) $conduct = 'Passable';
                else                $conduct = 'A ameliorer';
            }

            // Upsert bulletin
            $existing = dbFetchOne(
                "SELECT id FROM report_cards WHERE student_id = ? AND term_id = ?",
                [$s['id'], $termId]
            );

            if ($existing) {
                dbExecute(
                    "UPDATE report_cards SET average=?, `rank`=?, class_average=?, conduct=? WHERE id=?",
                    [$avg, $rank, $classAvg, $conduct, $existing['id']]
                );
            } else {
                dbExecute(
                    "INSERT INTO report_cards (student_id, term_id, average, `rank`, class_average, conduct)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$s['id'], $termId, $avg, $rank, $classAvg, $conduct]
                );
            }
            $generated++;
        }

        logActivity('generate_reports', "Bulletins generes : $generated eleves");
        redirectWith(BASE_URL . '/admin/reports.php?class_id='.$classId.'&term_id='.$termId,
            'success', "$generated bulletin(s) genere(s) avec succes !");
    }

    // Publier / depublier
    if ($action === 'publish') {
        $reportId = (int)($_POST['report_id'] ?? 0);
        $current  = dbFetchOne("SELECT is_published FROM report_cards WHERE id = ?", [$reportId]);
        if ($current) {
            $newStatus = $current['is_published'] ? 0 : 1;
            dbExecute("UPDATE report_cards SET is_published = ? WHERE id = ?", [$newStatus, $reportId]);
            $msg = $newStatus ? 'Bulletin publie.' : 'Bulletin depublie.';
            redirectWith(BASE_URL . '/admin/reports.php', 'success', $msg);
        }
    }

    // Ajouter commentaire
    if ($action === 'comment') {
        $reportId      = (int)($_POST['report_id']      ?? 0);
        $teacherComment= trim($_POST['teacher_comment'] ?? '');
        $adminComment  = trim($_POST['admin_comment']   ?? '');
        dbExecute(
            "UPDATE report_cards SET teacher_comment=?, admin_comment=? WHERE id=?",
            [$teacherComment, $adminComment, $reportId]
        );
        redirectWith(BASE_URL . '/admin/reports.php', 'success', 'Commentaires enregistres.');
    }
}

// ── Filtres ──────────────────────────────────────────────
$filterClass = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$filterTerm  = (int)($_GET['term_id']  ?? ($currentTerm['id'] ?? 0));

// Bulletins de la classe + trimestre
$reports = [];
if ($filterClass && $filterTerm) {
    $reports = dbFetchAll(
        "SELECT rc.id, rc.average, rc.rank, rc.class_average, rc.conduct,
                rc.teacher_comment, rc.admin_comment, rc.is_published, rc.generated_at,
                u.first_name, u.last_name, s.student_number,
                t.name AS term_name
         FROM report_cards rc
         JOIN students s ON rc.student_id = s.id
         JOIN users u    ON s.user_id     = u.id
         JOIN terms t    ON rc.term_id    = t.id
         WHERE s.class_id = ? AND rc.term_id = ?
         ORDER BY rc.rank ASC, u.last_name ASC",
        [$filterClass, $filterTerm]
    );
}

// Classe et trimestre selectionnes
$selectedClass = '';
foreach ($classes as $c) { if ($c['id'] == $filterClass) { $selectedClass = $c['name']; break; } }
$selectedTerm  = '';
foreach ($terms as $t) { if ($t['id'] == $filterTerm) { $selectedTerm = $t['name']; break; } }

$schoolName = getSetting('school_name', APP_NAME);

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
      <i class="bx bx-file" style="color:var(--primary)"></i>
      Bulletins scolaires
    </h1>
    <p>Generez et publiez les bulletins trimestriels</p>
  </div>
</div>

<!-- FILTRES + GENERATION -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label">Classe</label>
        <select name="class_id" class="form-control">
          <option value="">Selectionner</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= clean($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label">Trimestre</label>
        <select name="term_id" class="form-control">
          <option value="">Selectionner</option>
          <?php foreach ($terms as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $filterTerm==$t['id']?'selected':'' ?>><?= clean($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-secondary" style="margin-top:auto">
        <i class="bx bx-filter-alt"></i> Afficher
      </button>
    </form>
  </div>
</div>

<?php if ($filterClass && $filterTerm): ?>

<!-- Boutons actions -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action"   value="generate">
    <input type="hidden" name="class_id" value="<?= $filterClass ?>">
    <input type="hidden" name="term_id"  value="<?= $filterTerm ?>">
    <button type="submit" class="btn btn-primary">
      <i class="bx bx-refresh"></i>
      <?= empty($reports) ? 'Generer les bulletins' : 'Regenerer les bulletins' ?>
    </button>
  </form>
  <?php if (!empty($reports)): ?>
    <button class="btn btn-secondary" onclick="window.print()">
      <i class="bx bx-printer"></i> Imprimer tout
    </button>
  <?php endif; ?>
</div>

<?php if (empty($reports)): ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bx bx-file"></i></div>
    <h3>Aucun bulletin</h3>
    <p>
      Aucun bulletin genere pour <?= clean($selectedClass) ?> — <?= clean($selectedTerm) ?>.
      Cliquez sur "Generer les bulletins" pour creer les bulletins a partir des notes saisies.
    </p>
  </div>
</div>

<?php else: ?>

<!-- TABLEAU DES BULLETINS -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h3><i class="bx bx-list-ul"></i> <?= clean($selectedClass) ?> — <?= clean($selectedTerm) ?></h3>
    <span style="font-size:13px;color:var(--text-muted)"><?= count($reports) ?> eleve(s)</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Rang</th>
          <th>Eleve</th>
          <th>Moyenne</th>
          <th>Moy. classe</th>
          <th>Appreciation</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reports as $r):
          $mention = getMention($r['average'] ?? 0);
          $avg     = $r['average'] ?? null;
        ?>
        <tr>
          <td>
            <div style="width:30px;height:30px;border-radius:50%;background:<?= ($r['rank']??0)<=3?'var(--warning-bg)':'var(--bg-body)' ?>;color:<?= ($r['rank']??0)<=3?'var(--warning-dark)':'var(--text-muted)' ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px">
              <?= $r['rank'] ?? '—' ?>
            </div>
          </td>
          <td>
            <div class="td-user">
              <div class="avatar avatar-32" style="background:<?= $avg>=10?'var(--success)':'var(--danger)' ?>">
                <?= getInitials($r['first_name'], $r['last_name']) ?>
              </div>
              <div>
                <div class="td-name"><?= clean($r['first_name']) ?> <?= clean($r['last_name']) ?></div>
                <div class="td-sub"><?= clean($r['student_number']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span style="font-size:16px;font-weight:800;color:<?= ($avg??0)>=10?'var(--success)':'var(--danger)' ?>">
              <?= $avg !== null ? $avg.'/20' : '—' ?>
            </span>
          </td>
          <td style="color:var(--text-muted)"><?= $r['class_average'] ?? '—' ?>/20</td>
          <td>
            <?php if ($r['conduct']): ?>
              <span class="badge" style="background:<?= $mention['color'] ?>20;color:<?= $mention['color'] ?>">
                <?= clean($r['conduct']) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--text-muted)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($r['is_published']): ?>
              <span class="badge badge-success"><i class="bx bx-check"></i> Publie</span>
            <?php else: ?>
              <span class="badge badge-gray">Brouillon</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="td-actions">
              <button class="btn btn-sm btn-secondary btn-icon"
                      title="Voir / Imprimer"
                      onclick="printReport(<?= $r['id'] ?>)">
                <i class="bx bx-printer"></i>
              </button>
              <button class="btn btn-sm btn-secondary btn-icon"
                      title="Commentaire"
                      onclick='openComment(<?= json_encode([
                        "id"              => $r["id"],
                        "teacher_comment" => $r["teacher_comment"],
                        "admin_comment"   => $r["admin_comment"],
                        "name"            => $r["first_name"]." ".$r["last_name"],
                      ], JSON_HEX_QUOT|JSON_HEX_APOS) ?>)'>
                <i class="bx bx-comment-edit"></i>
              </button>
              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action"    value="publish">
                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-icon <?= $r['is_published']?'btn-warning':'btn-success' ?>"
                        title="<?= $r['is_published']?'Depublier':'Publier' ?>">
                  <i class="bx <?= $r['is_published']?'bx-hide':'bx-show' ?>"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- BULLETIN IMPRIMABLE (masque a l'ecran) -->
<div id="printArea" style="display:none">
  <?php foreach ($reports as $r):
    $avg     = $r['average'] ?? null;
    $mention = getMention($avg ?? 0);

    // Notes detaillees de cet eleve pour ce trimestre
    $studentId = dbFetchOne(
        "SELECT s.id FROM students s JOIN users u ON s.user_id=u.id
         WHERE CONCAT(u.first_name,' ',u.last_name) = ?",
        [$r['first_name'].' '.$r['last_name']]
    )['id'] ?? 0;

    $detailGrades = dbFetchAll(
        "SELECT sub.name AS subject_name, sub.coefficient,
                ROUND(AVG(g.score/g.max_score*20),2) AS avg,
                sub.color
         FROM grades g
         JOIN class_subjects cs ON g.class_subject_id = cs.id
         JOIN subjects sub ON cs.subject_id = sub.id
         WHERE g.term_id = ? AND g.student_id = (
             SELECT s.id FROM report_cards rc JOIN students s ON rc.student_id=s.id WHERE rc.id=?
         )
         GROUP BY sub.id, sub.name, sub.coefficient, sub.color
         ORDER BY sub.name",
        [$filterTerm, $r['id']]
    );
  ?>
  <div class="bulletin-page" style="page-break-after:always;padding:24px;font-family:Arial,sans-serif;max-width:750px;margin:0 auto">
    <!-- Entete bulletin -->
    <div style="text-align:center;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid #6366f1">
      <div style="font-size:22px;font-weight:800;color:#6366f1"><?= clean($schoolName) ?></div>
      <div style="font-size:14px;color:#64748b;margin-top:4px">BULLETIN SCOLAIRE</div>
      <div style="font-size:13px;color:#94a3b8"><?= clean($selectedClass) ?> — <?= clean($selectedTerm) ?></div>
    </div>

    <!-- Infos eleve -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;padding:12px;background:#f8faff;border-radius:8px">
      <div>
        <div style="font-size:11px;color:#94a3b8">Eleve</div>
        <div style="font-size:15px;font-weight:700;color:#0f172a"><?= clean($r['first_name']) ?> <?= clean($r['last_name']) ?></div>
        <div style="font-size:12px;color:#64748b"><?= clean($r['student_number']) ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:#94a3b8">Rang</div>
        <div style="font-size:22px;font-weight:800;color:#6366f1"><?= $r['rank'] ?? '—' ?></div>
        <div style="font-size:11px;color:#64748b">sur <?= count($reports) ?> eleves</div>
      </div>
    </div>

    <!-- Notes par matiere -->
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px">
      <thead>
        <tr style="background:#eef2ff">
          <th style="padding:8px;text-align:left;font-size:11px;color:#6366f1;text-transform:uppercase">Matiere</th>
          <th style="padding:8px;text-align:center;font-size:11px;color:#6366f1;text-transform:uppercase">Coeff</th>
          <th style="padding:8px;text-align:center;font-size:11px;color:#6366f1;text-transform:uppercase">Moyenne</th>
          <th style="padding:8px;text-align:left;font-size:11px;color:#6366f1;text-transform:uppercase">Appreciation</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($detailGrades as $dg):
          $gMention = getMention($dg['avg'] ?? 0);
        ?>
        <tr style="border-bottom:1px solid #e2e8f0">
          <td style="padding:8px;color:#0f172a;font-weight:500">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $dg['color'] ?>;margin-right:6px"></span>
            <?= clean($dg['subject_name']) ?>
          </td>
          <td style="padding:8px;text-align:center;color:#64748b"><?= $dg['coefficient'] ?></td>
          <td style="padding:8px;text-align:center;font-weight:700;color:<?= ($dg['avg']??0)>=10?'#059669':'#dc2626' ?>">
            <?= $dg['avg'] ?? '—' ?>/20
          </td>
          <td style="padding:8px;color:<?= $gMention['color'] ?>;font-size:12px"><?= $gMention['label'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Recapitulatif -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
      <div style="text-align:center;padding:12px;background:#f0fdf4;border-radius:8px">
        <div style="font-size:22px;font-weight:800;color:<?= ($avg??0)>=10?'#059669':'#dc2626' ?>"><?= $avg ?? '—' ?>/20</div>
        <div style="font-size:11px;color:#64748b">Moyenne generale</div>
      </div>
      <div style="text-align:center;padding:12px;background:#eef2ff;border-radius:8px">
        <div style="font-size:18px;font-weight:700;color:#6366f1"><?= $r['class_average'] ?? '—' ?>/20</div>
        <div style="font-size:11px;color:#64748b">Moyenne classe</div>
      </div>
      <div style="text-align:center;padding:12px;background:#fff7ed;border-radius:8px">
        <div style="font-size:15px;font-weight:700;color:<?= $mention['color'] ?>"><?= $r['conduct'] ?? '—' ?></div>
        <div style="font-size:11px;color:#64748b">Appreciation</div>
      </div>
    </div>

    <!-- Commentaires -->
    <?php if ($r['teacher_comment'] || $r['admin_comment']): ?>
    <div style="margin-bottom:12px">
      <?php if ($r['teacher_comment']): ?>
      <div style="padding:10px;background:#f8faff;border-left:3px solid #6366f1;border-radius:4px;margin-bottom:8px">
        <div style="font-size:11px;font-weight:700;color:#6366f1;margin-bottom:4px">APPRECIATION DE L'ENSEIGNANT</div>
        <div style="font-size:13px;color:#334155"><?= clean($r['teacher_comment']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($r['admin_comment']): ?>
      <div style="padding:10px;background:#f0fdf4;border-left:3px solid #10b981;border-radius:4px">
        <div style="font-size:11px;font-weight:700;color:#059669;margin-bottom:4px">MOT DU DIRECTEUR</div>
        <div style="font-size:13px;color:#334155"><?= clean($r['admin_comment']) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Signatures -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0">
      <div style="text-align:center">
        <div style="height:40px"></div>
        <div style="font-size:12px;color:#64748b;border-top:1px solid #94a3b8;padding-top:6px">Signature de l'enseignant</div>
      </div>
      <div style="text-align:center">
        <div style="height:40px"></div>
        <div style="font-size:12px;color:#64748b;border-top:1px solid #94a3b8;padding-top:6px">Signature du directeur</div>
      </div>
    </div>

    <div style="text-align:center;font-size:11px;color:#94a3b8;margin-top:16px">
      Genere le <?= date('d/m/Y H:i') ?> — <?= clean($schoolName) ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; // fin si reports ?>
<?php endif; // fin si filtres ?>

<!-- MODAL COMMENTAIRE -->
<div class="modal-overlay" id="modalComment">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="bx bx-comment-edit" style="color:var(--primary)"></i> Commentaires</h3>
      <button class="modal-close" data-modal-close><i class="bx bx-x"></i></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"    value="comment">
      <input type="hidden" name="report_id" id="cReportId">
      <div class="modal-body">
        <p id="cStudentName" style="font-size:13.5px;font-weight:600;color:var(--text-primary);margin-bottom:16px"></p>
        <div class="form-group">
          <label class="form-label">Appreciation de l'enseignant</label>
          <textarea name="teacher_comment" id="cTeacherComment" class="form-control" rows="3"
                    placeholder="Appreciation de l'enseignant..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Mot du directeur</label>
          <textarea name="admin_comment" id="cAdminComment" class="form-control" rows="3"
                    placeholder="Mot du directeur..."></textarea>
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

<style>
@media print {
  .sidebar, .topbar, .page-header, .card:not(.bulletin-print), form, .btn,
  .tabs, nav, .page-header-actions { display: none !important; }
  .main-content { margin: 0 !important; padding: 0 !important; }
  #printArea { display: block !important; }
  .bulletin-page { page-break-after: always; }
  body { background: white; }
}
</style>

<?php
$pageScript = "
function printReport(id) {
  document.getElementById('printArea').style.display = 'block';
  window.print();
  document.getElementById('printArea').style.display = 'none';
}

function openComment(d) {
  document.getElementById('cReportId').value        = d.id;
  document.getElementById('cStudentName').textContent = 'Eleve : ' + d.name;
  document.getElementById('cTeacherComment').value  = d.teacher_comment || '';
  document.getElementById('cAdminComment').value    = d.admin_comment || '';
  SS_Modal.open('modalComment');
}
";
require_once INCLUDES_PATH . '/footer.php';
?>
