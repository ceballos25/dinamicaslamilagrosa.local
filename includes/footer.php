<script src="<?= ASSETS_URL ?>/libs/jquery/dist/jquery.min.js"></script>
  <script src="<?= ASSETS_URL ?>/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= ASSETS_URL ?>/js/sidebarmenu.js"></script>
  <script src="<?= ASSETS_URL ?>/js/app.min.js"></script>
  <script src="<?= ASSETS_URL ?>/libs/simplebar/dist/simplebar.js"></script>
  <script>
    window.APP_CSRF_TOKEN = window.APP_CSRF_TOKEN || <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
    <?php if (!empty($_SESSION['user_id']) && function_exists('adminCapsMap')): ?>
    window.ADMIN_CAPS = <?= json_encode(adminCapsMap(), JSON_UNESCAPED_UNICODE) ?>;
    <?php endif; ?>
  </script>
  <?php include_once "pagination_script.php"; ?>
  <?php include_once "preloader.php"; ?>
  <?php include_once "btn-share.php"; ?>
  <script src="<?= ASSETS_URL ?>/js/admin-brand.js?v=3"></script>
  <script src="<?= ASSETS_URL ?>/js/admin-fetch.js?v=5"></script>
  <?php if(isset($extra_js)) echo $extra_js; ?>

  <div class="modal fade" id="modalMiPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Cambiar mi contraseña</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Contraseña actual</label>
            <input type="password" class="form-control" id="miPassActual" autocomplete="current-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" class="form-control" id="miPassNueva" autocomplete="new-password">
          </div>
          <div class="mb-0">
            <label class="form-label">Confirmar nueva</label>
            <input type="password" class="form-control" id="miPassConfirm" autocomplete="new-password">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btnGuardarMiPassword">Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnGuardarMiPassword');
    if (!btn) return;
    btn.addEventListener('click', async function () {
      const fd = new FormData();
      fd.append('action', 'cambiar_mi_password');
      fd.append('current_password', document.getElementById('miPassActual').value);
      fd.append('new_password', document.getElementById('miPassNueva').value);
      fd.append('confirm_password', document.getElementById('miPassConfirm').value);
      try {
        const res = await adminFetchJson('/front/ajax/usuarios.ajax.php', {
          body: fd,
          withCsrf: true,
          writeActions: ['cambiar_mi_password'],
        });
        if (res.success) {
          bootstrap.Modal.getInstance(document.getElementById('modalMiPassword'))?.hide();
          alertify.success(res.message || 'Contraseña actualizada');
          document.getElementById('miPassActual').value = '';
          document.getElementById('miPassNueva').value = '';
          document.getElementById('miPassConfirm').value = '';
        } else {
          alertify.error(res.message || 'Error');
        }
      } catch (e) {
        adminNotifyError(e instanceof Error ? e.message : 'No se pudo cambiar la contraseña.');
      }
    });
  });
  </script>

  <footer class="admin-panel-footer text-center small text-muted py-3 border-top">
    Desarrollado por
    <a href="https://cristianceballos.com/" target="_blank" rel="noopener noreferrer" class="admin-panel-footer__link">Cristian Ceballos</a>
  </footer>

</body>
</html>