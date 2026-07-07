<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['alumno'])) { header('Location: index.php?seccion=login'); exit; }
require_once __DIR__ . '/../modelo/Conexion.php';
$conn = (new Conexion())->conectar();
$al = $_SESSION['alumno'];
$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
$carreras = [];
try { $rs = $conn->query('SELECT nombre FROM carreras ORDER BY nombre'); if ($rs) { while ($row = $rs->fetch_assoc()) { $carreras[] = (string)$row['nombre']; } } } catch (Throwable $e) {}
?>
<div class="row">
  <div class="col-lg-8">
    <h3 class="mt-3 mb-3">Perfil</h3>
    <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">Datos del alumno</h5>
        <form method="post" action="index.php" class="row g-3">
          <input type="hidden" name="accion" value="actualizar_perfil_alumno" />
          <div class="col-md-6">
            <label class="form-label">Nombre</label>
            <input name="nombre" class="form-control" value="<?php echo htmlspecialchars($al['nombre']); ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Carrera</label>
            <?php if (empty($carreras)): ?>
              <input class="form-control" value="<?php echo htmlspecialchars($al['carrera']); ?>" disabled />
            <?php else: ?>
              <select name="carrera" class="form-select" required>
                <option value="">Selecciona carrera</option>
                <?php foreach ($carreras as $car): ?>
                  <option value="<?php echo htmlspecialchars($car); ?>" <?php echo ($al['carrera'] === $car) ? 'selected' : ''; ?>><?php echo htmlspecialchars($car); ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label">Grado (grupo)</label>
            <input name="grupo" class="form-control" value="<?php echo htmlspecialchars($al['grupo']); ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Cuatrimestre</label>
            <input name="semestre" class="form-control" value="<?php echo htmlspecialchars($al['semestre']); ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Nueva contraseña</label>
            <input name="password" type="password" class="form-control" minlength="4" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirmar contraseña</label>
            <input name="password2" type="password" class="form-control" minlength="4" />
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit">Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>

    <a class="btn btn-outline-secondary" href="index.php?seccion=evaluacion">Volver a evaluación</a>
  </div>
</div>
