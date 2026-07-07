<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../modelo/Conexion.php';
$conn = (new Conexion())->conectar();
$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
$carreras = [];
try {
  $rs = $conn->query('SELECT nombre FROM carreras ORDER BY nombre');
  if ($rs) { while ($row = $rs->fetch_assoc()) { $carreras[] = (string)$row['nombre']; } }
} catch (Throwable $e) { }
?>
<div class="row justify-content-center">
  <div class="col-md-7">
    <h3 class="mt-3 mb-3">Crear cuenta de alumno</h3>
    <?php if ($err): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div>
    <?php endif; ?>
    <form method="post" action="index.php">
      <input type="hidden" name="accion" value="registro_alumno" />
      <div class="mb-3">
        <label class="form-label">Nombre completo</label>
        <input name="nombre" class="form-control" value="<?php echo htmlspecialchars($_GET['nombre'] ?? ''); ?>" required />
      </div>
      <div class="mb-3">
        <label class="form-label">Matrícula</label>
        <input name="matricula" class="form-control" value="<?php echo htmlspecialchars($_GET['matricula'] ?? ''); ?>" required />
      </div>
      <div class="mb-3">
        <label class="form-label">Carrera</label>
        <?php if (empty($carreras)): ?>
          <div class="alert alert-warning">No hay carreras disponibles. Pide al DPA crear carreras antes de registrarte.</div>
          <select class="form-select" disabled><option value="">Sin carreras</option></select>
        <?php else: ?>
          <select name="carrera" class="form-select" required>
            <option value="">Selecciona una carrera</option>
            <?php foreach ($carreras as $car): ?>
              <option value="<?php echo htmlspecialchars($car); ?>" <?php echo (isset($_GET['carrera']) && (string)$_GET['carrera'] === (string)$car) ? 'selected' : ''; ?>><?php echo htmlspecialchars($car); ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
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
      <div class="mb-3">
        <label class="form-label">Contraseña</label>
        <input name="password" type="password" class="form-control" minlength="4" required />
      </div>
      <div class="mb-3">
        <label class="form-label">Confirmar contraseña</label>
        <input name="password2" type="password" class="form-control" minlength="4" required />
      </div>
      <button type="submit" class="btn btn-success w-100">Registrarme</button>
    </form>
    <div class="mt-2">
      <a href="index.php?seccion=login">Volver</a>
    </div>
  </div>
</div>
