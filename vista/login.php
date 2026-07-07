<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <h3 class="mt-3 mb-3">Accede y califica a tus profesores</h3>
    <?php if ($err): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div>
    <?php endif; ?>
    <form method="post" action="index.php">
      <input type="hidden" name="accion" value="login_alumno" />
      <div class="mb-3">
        <label class="form-label">Matrícula</label>
        <input name="matricula" class="form-control" value="<?php echo htmlspecialchars($_GET['matricula'] ?? ''); ?>" required />
      </div>
      <div class="mb-3">
        <label class="form-label">Contraseña</label>
        <input name="password" type="password" class="form-control" required />
      </div>
      <div class="row g-2 mb-3">
        <div class="col">
          <label class="form-label">Cuatrimestre (ej. 4to)</label>
          <input name="semestre" class="form-control" value="<?php echo htmlspecialchars($_GET['semestre'] ?? ''); ?>" required />
        </div>
        <div class="col">
          <label class="form-label">Grupo (ej. A)</label>
          <input name="grupo" class="form-control" value="<?php echo htmlspecialchars($_GET['grupo'] ?? ''); ?>" required />
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100">Acceder y evaluar</button>
    </form>
    <div class="mt-2">
      <a href="index.php?seccion=registro_alumno">Crear cuenta</a>
    </div>
      <div class="mt-3">
        <div class="d-flex gap-2">
          <a href="index.php?seccion=docente" class="btn btn-outline-secondary w-50">Acceso Docente</a>
          <a href="index.php?seccion=dpa" class="btn btn-outline-secondary w-50">Acceso DPA</a>
          <a href="index.php?seccion=sa" class="btn btn-outline-secondary w-50">Acceso SA</a>
        </div>
      </div>
  </div>
</div>
