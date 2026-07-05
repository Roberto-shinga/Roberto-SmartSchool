<?php
// ============================================================
//  SmartSchool — Footer commun
//  Emplacement : includes/footer.php
// ============================================================
?>
    </div><!-- /.main-content -->
  </div><!-- /.app-layout -->

  <!-- Toast container -->
  <div class="toast-container" id="toastContainer"></div>

  <!-- JavaScript principal -->
  <script src="<?= ASSETS_URL ?>/js/main.js"></script>

  <?php if (!empty($pageScript)): ?>
  <script><?= $pageScript ?></script>
  <?php endif; ?>

</body>
</html>
