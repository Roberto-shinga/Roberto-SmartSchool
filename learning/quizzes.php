<?php
require_once 'C:/xampp/htdocs/SmartSchool/bootstrap.php';
requireStudent();

$pageTitle   = 'Quiz';
$pageSection = 'quizzes';
$user        = currentUser();
$studentId   = getCurrentStudentId();
$student     = dbFetchOne("SELECT * FROM students WHERE id=?", [$studentId]);

$quizId   = (int)($_GET['quiz_id'] ?? 0);
$resultId = (int)($_GET['result'] ?? 0);

// ── Soumission d'un quiz ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postQuizId = (int)($_POST['quiz_id'] ?? 0);
    $quiz = dbFetchOne(
        "SELECT q.*, c.level_id FROM quizzes q JOIN courses c ON q.course_id=c.id
         WHERE q.id=? AND q.is_published=1 AND c.is_published=1", [$postQuizId]
    );
    $accessible = $quiz && (!$quiz['level_id'] || (int)$quiz['level_id'] === (int)$student['level_id']);
    $attemptsUsed = $quiz ? dbCount('quiz_attempts', "quiz_id=? AND student_id=?", [$postQuizId, $studentId]) : 0;

    if (!$accessible) {
        redirectWith($_SERVER['PHP_SELF'], 'danger', 'Quiz introuvable ou non accessible.');
    } elseif ($quiz['max_attempts'] > 0 && $attemptsUsed >= $quiz['max_attempts']) {
        redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$postQuizId", 'danger', 'Nombre maximum de tentatives atteint pour ce quiz.');
    } else {
        $questions = dbFetchAll("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY order_index", [$postQuizId]);
        $answers = $_POST['answer'] ?? [];
        $score = 0; $totalPoints = 0; $reviewData = [];

        foreach ($questions as $q) {
            $totalPoints += (int)$q['points'];
            $given = trim((string)($answers[$q['id']] ?? ''));
            $correct = trim((string)$q['correct']);
            $isCorrect = ($given !== '' && mb_strtolower($given) === mb_strtolower($correct));
            if ($isCorrect) $score += (int)$q['points'];
            $reviewData[$q['id']] = $given;
        }

        $passed = $totalPoints > 0 && (($score / $totalPoints) * 100) >= (int)$quiz['pass_score'];

        dbExecute(
            "INSERT INTO quiz_attempts (quiz_id, student_id, score, total_points, answers, passed, finished_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$postQuizId, $studentId, $score, $totalPoints, json_encode($reviewData), $passed ? 1 : 0]
        );
        $attemptId = dbLastId();

        $newBadges = checkLearningBadges($studentId);
        logActivity('quiz_attempted', "Quiz #$postQuizId : $score/$totalPoints" . ($passed ? ' (reussi)' : ' (echoue)'));

        if (!empty($newBadges)) {
            $names = implode(', ', array_column($newBadges, 'name'));
            redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$postQuizId&result=$attemptId", 'success', "Quiz termine ! Nouveau badge debloque : $names 🎉");
        } else {
            redirectWith($_SERVER['PHP_SELF'] . "?quiz_id=$postQuizId&result=$attemptId", $passed ? 'success' : 'warning',
                $passed ? 'Quiz reussi !' : 'Quiz termine, mais le score minimum n\'est pas atteint.');
        }
    }
}

// ── Vue resultat d'une tentative ────────────────────────────────
if ($quizId && $resultId) {
    $attempt = dbFetchOne("SELECT * FROM quiz_attempts WHERE id=? AND student_id=? AND quiz_id=?", [$resultId, $studentId, $quizId]);
    if (!$attempt) redirectWith($_SERVER['PHP_SELF'], 'danger', 'Resultat introuvable.');

    $quiz = dbFetchOne("SELECT * FROM quizzes WHERE id=?", [$quizId]);
    $questions = dbFetchAll("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY order_index", [$quizId]);
    $given = json_decode($attempt['answers'], true) ?: [];
    $percent = $attempt['total_points'] > 0 ? round(($attempt['score'] / $attempt['total_points']) * 100) : 0;

    require INCLUDES_PATH . '/header.php';
    require INCLUDES_PATH . '/sidebar.php';
    ?>
    <div class="main-content">
      <div class="page-wrapper">
        <?= showFlash() ?>
        <div class="page-header">
          <div>
            <a href="<?= BASE_URL ?>/learning/quizzes.php" class="text-sm text-muted" style="display:inline-block;margin-bottom:6px"><i class="bx bx-arrow-back"></i> Retour aux quiz</a>
            <h1><?= clean($quiz['title']) ?> — Resultat</h1>
          </div>
        </div>

        <div class="grid-3 mb-6">
          <div class="stat-card <?= $attempt['passed'] ? 'c-success' : 'c-danger' ?>">
            <div class="stat-icon <?= $attempt['passed'] ? 'c-success' : 'c-danger' ?>"><i class="bx <?= $attempt['passed'] ? 'bx-check-circle' : 'bx-x-circle' ?>"></i></div>
            <div><div class="stat-value"><?= $percent ?>%</div><div class="stat-label"><?= $attempt['passed'] ? 'Reussi' : 'Non reussi' ?></div></div>
          </div>
          <div class="stat-card c-primary"><div class="stat-icon c-primary"><i class="bx bx-star"></i></div><div><div class="stat-value"><?= $attempt['score'] ?>/<?= $attempt['total_points'] ?></div><div class="stat-label">Points</div></div></div>
          <div class="stat-card c-cyan"><div class="stat-icon c-cyan"><i class="bx bx-target-lock"></i></div><div><div class="stat-value"><?= (int)$quiz['pass_score'] ?>%</div><div class="stat-label">Seuil de reussite</div></div></div>
        </div>

        <div class="card">
          <div class="card-header"><h3><i class="bx bx-list-check"></i> Correction</h3></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <?php foreach ($questions as $q):
              $yourAnswer = $given[$q['id']] ?? '';
              $isCorrect = mb_strtolower(trim($yourAnswer)) === mb_strtolower(trim((string)$q['correct']));
            ?>
            <div style="border-left:3px solid <?= $isCorrect ? 'var(--success)' : 'var(--danger)' ?>;padding:10px 14px;background:var(--bg-hover);border-radius:8px">
              <div class="text-sm font-semibold" style="margin-bottom:6px"><?= clean($q['question']) ?></div>
              <div class="text-sm">Ta reponse : <span style="color:<?= $isCorrect ? 'var(--success)' : 'var(--danger)' ?>"><?= clean($yourAnswer ?: '(aucune reponse)') ?></span></div>
              <?php if (!$isCorrect): ?><div class="text-sm text-muted">Bonne reponse : <?= clean($q['correct']) ?></div><?php endif; ?>
              <?php if ($q['explanation']): ?><div class="text-xs text-muted" style="margin-top:4px"><i class="bx bx-info-circle"></i> <?= clean($q['explanation']) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php require INCLUDES_PATH . '/footer.php'; ?>
    <?php exit; ?>
<?php
}

// ── Vue passage d'un quiz ───────────────────────────────────────
if ($quizId) {
    $quiz = dbFetchOne(
        "SELECT q.*, c.level_id, c.title AS course_title FROM quizzes q JOIN courses c ON q.course_id=c.id
         WHERE q.id=? AND q.is_published=1 AND c.is_published=1", [$quizId]
    );
    $accessible = $quiz && (!$quiz['level_id'] || (int)$quiz['level_id'] === (int)$student['level_id']);
    if (!$accessible) redirectWith(BASE_URL . '/learning/quizzes.php', 'danger', "Ce quiz n'est pas accessible.");

    $attemptsUsed = dbCount('quiz_attempts', "quiz_id=? AND student_id=?", [$quizId, $studentId]);
    $exhausted = $quiz['max_attempts'] > 0 && $attemptsUsed >= $quiz['max_attempts'];

    $questions = dbFetchAll("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY order_index", [$quizId]);

    require INCLUDES_PATH . '/header.php';
    require INCLUDES_PATH . '/sidebar.php';
    ?>
    <div class="main-content">
      <div class="page-wrapper">
        <?= showFlash() ?>
        <div class="page-header">
          <div>
            <a href="<?= BASE_URL ?>/learning/quizzes.php" class="text-sm text-muted" style="display:inline-block;margin-bottom:6px"><i class="bx bx-arrow-back"></i> Retour aux quiz</a>
            <h1><?= clean($quiz['title']) ?></h1>
            <p><?= clean($quiz['course_title']) ?><?php if ($quiz['duration_min']): ?> · <?= (int)$quiz['duration_min'] ?> min<?php endif; ?></p>
          </div>
        </div>

        <?php if ($exhausted): ?>
          <div class="alert alert-danger"><i class="bx bx-error-circle"></i> Tu as atteint le nombre maximum de tentatives (<?= (int)$quiz['max_attempts'] ?>) pour ce quiz.</div>
        <?php elseif (empty($questions)): ?>
          <div class="empty-state"><div class="empty-state-icon"><i class="bx bx-brain"></i></div><h3>Ce quiz n'a pas encore de questions</h3></div>
        <?php else: ?>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
          <?php if ($quiz['description']): ?><div class="alert alert-info" style="margin-bottom:20px"><i class="bx bx-info-circle"></i> <?= clean($quiz['description']) ?></div><?php endif; ?>

          <?php foreach ($questions as $i => $q): $options = json_decode($q['options'] ?? '[]', true) ?: []; ?>
          <div class="card" style="margin-bottom:16px">
            <div class="card-body">
              <div class="text-sm font-semibold" style="margin-bottom:12px"><?= $i + 1 ?>. <?= clean($q['question']) ?> <span class="text-xs text-muted">(<?= (int)$q['points'] ?> pt<?= $q['points']>1?'s':'' ?>)</span></div>

              <?php if ($q['type'] === 'qcm'): ?>
                <?php foreach ($options as $opt): ?>
                  <label class="form-check" style="display:block;margin-bottom:8px">
                    <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= clean($opt) ?>" required>
                    <span class="form-check-label"><?= clean($opt) ?></span>
                  </label>
                <?php endforeach; ?>
              <?php elseif ($q['type'] === 'vrai_faux'): ?>
                <label class="form-check" style="display:block;margin-bottom:8px"><input type="radio" name="answer[<?= $q['id'] ?>]" value="Vrai" required><span class="form-check-label">Vrai</span></label>
                <label class="form-check" style="display:block"><input type="radio" name="answer[<?= $q['id'] ?>]" value="Faux" required><span class="form-check-label">Faux</span></label>
              <?php else: ?>
                <input type="text" name="answer[<?= $q['id'] ?>]" class="form-control" required>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Soumettre le quiz</button>
          <span class="text-xs text-muted" style="margin-left:10px">Tentative <?= $attemptsUsed + 1 ?><?= $quiz['max_attempts'] > 0 ? ' / ' . (int)$quiz['max_attempts'] : '' ?></span>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php require INCLUDES_PATH . '/footer.php'; ?>
    <?php exit; ?>
<?php
}

// ── Liste des quiz disponibles ──────────────────────────────────
$quizzes = dbFetchAll(
    "SELECT q.*, c.title AS course_title, s.name AS subject_name, s.color,
            (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=?) AS attempts_used,
            (SELECT MAX(score) FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=?) AS best_score,
            (SELECT MAX(passed) FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=?) AS has_passed,
            (SELECT id FROM quiz_attempts qa WHERE qa.quiz_id=q.id AND qa.student_id=? ORDER BY qa.finished_at DESC LIMIT 1) AS last_attempt_id
     FROM quizzes q JOIN courses c ON q.course_id=c.id JOIN subjects s ON c.subject_id=s.id
     WHERE q.is_published=1 AND c.is_published=1 AND c.academic_year_id=?
       AND (c.level_id IS NULL OR c.level_id=?)
     ORDER BY s.name, q.title",
    [$studentId, $studentId, $studentId, $studentId, ($student['academic_year_id'] ?? 0), $student['level_id']]
);

require INCLUDES_PATH . '/header.php';
require INCLUDES_PATH . '/sidebar.php';
?>

<div class="main-content">
  <div class="page-wrapper">

    <?= showFlash() ?>

    <div class="page-header">
      <div><h1>Quiz</h1><p>Teste tes connaissances</p></div>
    </div>

    <?php if (empty($quizzes)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bx bx-brain"></i></div>
        <h3>Aucun quiz disponible pour l'instant</h3>
      </div>
    <?php else: ?>
    <div class="grid-3">
      <?php foreach ($quizzes as $q):
        $exhausted = $q['max_attempts'] > 0 && $q['attempts_used'] >= $q['max_attempts'];
      ?>
      <div class="card">
        <div class="card-body">
          <div class="flex items-center gap-2" style="margin-bottom:10px">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= clean($q['color']) ?>"></span>
            <span class="text-xs text-muted"><?= clean($q['subject_name']) ?></span>
          </div>
          <div style="font-weight:700;font-size:15px;color:var(--text-primary);margin-bottom:6px"><?= clean($q['title']) ?></div>
          <div class="text-xs text-muted" style="margin-bottom:12px"><?= clean($q['course_title']) ?></div>

          <?php if ($q['has_passed']): ?>
            <span class="badge badge-success" style="margin-bottom:10px"><i class="bx bx-check"></i> Reussi</span>
          <?php elseif ($q['attempts_used'] > 0): ?>
            <span class="badge badge-warning" style="margin-bottom:10px">Meilleur score : <?= (int)$q['best_score'] ?></span>
          <?php endif; ?>

          <div class="text-xs text-muted" style="margin-bottom:12px">
            Tentatives : <?= (int)$q['attempts_used'] ?><?= $q['max_attempts'] > 0 ? ' / ' . (int)$q['max_attempts'] : ' (illimite)' ?>
          </div>

          <div style="display:flex;gap:8px">
            <?php if (!$exhausted): ?>
              <a href="<?= BASE_URL ?>/learning/quizzes.php?quiz_id=<?= $q['id'] ?>" class="btn btn-primary btn-sm" style="flex:1">
                <?= $q['attempts_used'] > 0 ? 'Retenter' : 'Commencer' ?>
              </a>
            <?php endif; ?>
            <?php if ($q['last_attempt_id']): ?>
              <a href="<?= BASE_URL ?>/learning/quizzes.php?quiz_id=<?= $q['id'] ?>&result=<?= $q['last_attempt_id'] ?>" class="btn btn-secondary btn-sm" style="flex:1">Voir resultat</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require INCLUDES_PATH . '/footer.php'; ?>
