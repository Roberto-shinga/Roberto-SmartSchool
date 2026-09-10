<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireStudent();

$pageTitle   = 'Mes cours';
$pageSection = 'learning';
$user        = currentUser();
$studentId   = getCurrentStudentId();

$currentYear = dbFetchOne("SELECT * FROM academic_years WHERE is_current=1 LIMIT 1");
$yearId      = $currentYear['id'] ?? 0;
$student     = dbFetchOne("SELECT * FROM students WHERE id=?", [$studentId]);

// ── Marquer une lecon comme terminee ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    $lesson   = dbFetchOne(
        "SELECT le.*, c.level_id, c.option_id, c.grade_year FROM lessons le JOIN courses c ON le.course_id=c.id
         WHERE le.id=? AND le.is_published=1 AND c.is_published=1", [$lessonId]
    );
    // GARDE-FOU : la lecon doit appartenir a un cours accessible a ce niveau
    $accessible = $lesson && (!$lesson['level_id'] || (int)$lesson['level_id'] === (int)$student['level_id']);
    if ($accessible) {
        dbExecute(
            "INSERT INTO lesson_progress (student_id, lesson_id, completed, completed_at) VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE completed=1, completed_at=NOW()",
            [$studentId, $lessonId]
        );
        $newBadges = checkLearningBadges($studentId);
        if (!empty($newBadges)) {
            $names = implode(', ', array_column($newBadges, 'name'));
            redirectWith($_SERVER['PHP_SELF'] . '?course_id=' . $lesson['course_id'], 'success', "Lecon terminee ! Nouveau badge debloque : $names 🎉");
        } else {
            redirectWith($_SERVER['PHP_SELF'] . '?course_id=' . $lesson['course_id'], 'success', 'Lecon marquee comme terminee.');
        }
    } else {
        redirectWith($_SERVER['PHP_SELF'], 'danger', 'Lecon introuvable ou non accessible.');
    }
}

$courseId = (int)($_GET['course_id'] ?? 0);

// ── Vue detail d'un cours ───────────────────────────────────────
if ($courseId) {
    $course = dbFetchOne(
        "SELECT c.*, s.name AS subject_name, s.color, tu.first_name AS t_fn, tu.last_name AS t_ln
         FROM courses c JOIN subjects s ON c.subject_id=s.id
         JOIN teachers te ON c.teacher_id=te.id JOIN users tu ON te.user_id=tu.id
         WHERE c.id=? AND c.is_published=1", [$courseId]
    );
    // GARDE-FOU : le cours doit correspondre au niveau de l'eleve (ou etre generique)
    $accessible = $course && (!$course['level_id'] || (int)$course['level_id'] === (int)$student['level_id']);
    if (!$accessible) {
        redirectWith($_SERVER['PHP_SELF'], 'danger', "Ce cours n'est pas accessible.");
    }

    $lessons = dbFetchAll(
        "SELECT le.*, lp.completed FROM lessons le
         LEFT JOIN lesson_progress lp ON lp.lesson_id=le.id AND lp.student_id=?
         WHERE le.course_id=? AND le.is_published=1 ORDER BY le.order_index",
        [$studentId, $courseId]
    );
    $resources = dbFetchAll("SELECT * FROM learning_resources WHERE course_id=? ORDER BY order_index", [$courseId]);
    $quizzes   = dbFetchAll("SELECT * FROM quizzes WHERE course_id=? AND is_published=1", [$courseId]);

    require INCLUDES_PATH . '/header.php';
    require INCLUDES_PATH . '/sidebar.php';
    ?>
    <div class="main-content">
      <div class="page-wrapper">
        <?= showFlash() ?>
        <div class="page-header">
          <div>
            <a href="<?= BASE_URL ?>/learning/index.php" class="text-sm text-muted" style="display:inline-block;margin-bottom:6px"><i class="bx bx-arrow-back"></i> Retour aux cours</a>
            <h1><?= clean($course['title']) ?></h1>
            <p><?= clean($course['subject_name']) ?> — <?= clean($course['t_fn'] . ' ' . $course['t_ln']) ?></p>
          </div>
        </div>

        <?php if ($course['description']): ?>
        <div class="card" style="margin-bottom:20px"><div class="card-body"><p class="text-sm"><?= nl2br(clean($course['description'])) ?></p></div></div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:20px">
          <div class="card-header"><h3><i class="bx bx-list-ul"></i> Lecons</h3></div>
          <?php if (empty($lessons)): ?>
            <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-book-open"></i></div><h3>Aucune lecon publiee pour l'instant</h3></div>
          <?php else: ?>
          <div class="table-wrap" style="border:none;box-shadow:none;border-radius:0">
            <table class="table">
              <tbody>
                <?php foreach ($lessons as $le): ?>
                <tr>
                  <td style="width:36px"><i class="bx <?= $le['completed'] ? 'bx-check-circle' : 'bx-circle' ?>" style="font-size:1.3rem;color:<?= $le['completed'] ? 'var(--success)' : 'var(--border)' ?>"></i></td>
                  <td>
                    <div class="text-sm font-semibold"><?= clean($le['title']) ?></div>
                    <?php if ($le['duration_min']): ?><div class="text-xs text-muted"><?= (int)$le['duration_min'] ?> min</div><?php endif; ?>
                  </td>
                  <td style="text-align:right">
                    <?php if (!$le['completed']): ?>
                    <form method="POST" style="display:inline">
                      <?= csrfField() ?>
                      <input type="hidden" name="lesson_id" value="<?= $le['id'] ?>">
                      <button type="submit" class="btn btn-secondary btn-sm">Marquer comme lu</button>
                    </form>
                    <?php else: ?>
                    <span class="badge badge-success">Termine</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if ($le['content']): ?>
                <tr><td></td><td colspan="2" class="text-sm text-muted" style="padding-top:0"><?= nl2br(clean(mb_substr(strip_tags($le['content']), 0, 400))) ?></td></tr>
                <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($resources)): ?>
        <div class="card" style="margin-bottom:20px">
          <div class="card-header"><h3><i class="bx bx-paperclip"></i> Ressources</h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($resources as $r): $url = $r['external_url'] ?: (UPLOADS_URL . '/' . $r['file_path']); ?>
              <a href="<?= clean($url) ?>" target="_blank" class="text-sm" style="display:flex;align-items:center;gap:8px">
                <i class="bx bx-file"></i> <?= clean($r['title']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($quizzes)): ?>
        <div class="card">
          <div class="card-header"><h3><i class="bx bx-brain"></i> Quiz lies a ce cours</h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($quizzes as $q): ?>
              <a href="<?= BASE_URL ?>/learning/quizzes.php?quiz_id=<?= $q['id'] ?>" class="btn btn-secondary btn-sm" style="width:fit-content"><i class="bx bx-brain"></i> <?= clean($q['title']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php require INCLUDES_PATH . '/footer.php'; ?>
    <?php exit; ?>
<?php
}

// ── Catalogue des cours ─────────────────────────────────────────
$courses = dbFetchAll(
    "SELECT c.*, s.name AS subject_name, s.color,
            (SELECT COUNT(*) FROM lessons WHERE course_id=c.id AND is_published=1) AS nb_lessons,
            (SELECT COUNT(*) FROM lessons le JOIN lesson_progress lp ON lp.lesson_id=le.id
             WHERE le.course_id=c.id AND lp.student_id=? AND lp.completed=1) AS nb_done
     FROM courses c JOIN subjects s ON c.subject_id=s.id
     WHERE c.is_published=1 AND c.academic_year_id=?
       AND (c.level_id IS NULL OR c.level_id=?)
       AND (c.option_id IS NULL OR c.option_id=?)
     ORDER BY s.name, c.title",
    [$studentId, $yearId, $student['level_id'], $student['option_id']]
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Mes cours</h1><p>SmartSchool Learning — <?= clean($currentYear['name'] ?? '—') ?></p></div>
    </div>

    <?php if (empty($courses)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-book-open"></i></div>
        <h3>Aucun cours disponible pour l'instant</h3>
        <p>Tes enseignants n'ont pas encore publie de cours pour ta classe.</p>
      </div>
    <?php else: ?>
    <div class="grid-3">
      <?php foreach ($courses as $c):
        $progress = $c['nb_lessons'] > 0 ? round(($c['nb_done'] / $c['nb_lessons']) * 100) : 0;
      ?>
      <a href="<?= BASE_URL ?>/learning/index.php?course_id=<?= $c['id'] ?>" class="card" style="text-decoration:none;display:block">
        <div class="card-body">
          <div class="flex items-center gap-2" style="margin-bottom:10px">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= clean($c['color']) ?>"></span>
            <span class="text-xs text-muted"><?= clean($c['subject_name']) ?></span>
          </div>
          <div style="font-weight:700;font-size:15px;color:var(--text-primary);margin-bottom:10px"><?= clean($c['title']) ?></div>
          <div class="text-xs text-muted" style="margin-bottom:6px"><?= (int)$c['nb_lessons'] ?> lecon(s)</div>
          <div class="progress"><div class="progress-bar success" data-value="<?= $progress ?>" style="width:0"></div></div>
          <div class="text-xs text-muted" style="margin-top:4px"><?= $progress ?>% termine</div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
