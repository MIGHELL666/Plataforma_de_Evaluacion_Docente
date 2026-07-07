<?php
require_once __DIR__ . '/../modelo/Conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$conn = (new Conexion())->conectar();
$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
$carreras = [];
try { $rs = $conn->query('SELECT nombre FROM carreras ORDER BY nombre'); if ($rs) { while ($row = $rs->fetch_assoc()) { $carreras[] = (string)$row['nombre']; } } } catch (Throwable $e) {}
?>
<div class="row">
  <div class="col-lg-8">
    <h3 class="mt-3 mb-3">Secretario Académico (SA)</h3>
    <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>

    <?php if (empty($_SESSION['sa_ok'])): ?>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Acceder como SA</h5>
          <form method="post" action="index.php" class="row g-2">
            <input type="hidden" name="accion" value="login_sa" />
            <div class="col-md-4">
              <label class="form-label">Usuario</label>
              <input name="usuario" class="form-control" required />
            </div>
            <div class="col-md-4">
              <label class="form-label">Contraseña</label>
              <input name="password" type="password" class="form-control" required />
            </div>
            <div class="col-md-4 align-self-end">
              <button class="btn btn-primary" type="submit">Entrar</button>
            </div>
          </form>
        </div>
      </div>
    <?php else: ?>

      <div class="d-flex justify-content-between mb-3">
        <div><strong>Sesión SA:</strong> <?php echo htmlspecialchars($_SESSION['sa_user'] ?? ''); ?></div>
        <form method="post" action="index.php">
          <input type="hidden" name="accion" value="logout_sa" />
          <button class="btn btn-sm btn-outline-secondary" type="submit">Cerrar sesión SA</button>
        </form>
      </div>

      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Crear/gestionar DPA por carrera</h5>
          <form method="post" action="index.php" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="accion" value="crear_dpa" />
            <div class="col-md-3">
              <label class="form-label">Usuario DPA</label>
              <input name="usuario" class="form-control" required />
            </div>
            <div class="col-md-3">
              <label class="form-label">Contraseña</label>
              <input name="password" type="password" class="form-control" required />
            </div>
            <div class="col-md-4">
              <label class="form-label">Carrera</label>
              <select name="carrera" class="form-select" required>
                <option value="">Seleccione carrera...</option>
                <?php foreach ($carreras as $car): ?>
                  <option value="<?php echo htmlspecialchars($car); ?>"><?php echo htmlspecialchars($car); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <button class="btn btn-success w-100" type="submit">Guardar</button>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr><th>Usuario DPA</th><th>Carrera</th><th style="width:1%">Acciones</th></tr>
              </thead>
              <tbody>
                <?php
                  $lista = $conn->query('SELECT usuario, carrera FROM dpa_usuarios ORDER BY carrera, usuario');
                  if ($lista) while ($row = $lista->fetch_assoc()):
                ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['usuario']); ?></td>
                    <td><?php echo htmlspecialchars($row['carrera'] ?? ''); ?></td>
                    <td class="text-nowrap">
                      <form method="post" action="index.php" class="d-inline" onsubmit="return confirm('¿Eliminar este DPA?');">
                        <input type="hidden" name="accion" value="eliminar_dpa" />
                        <input type="hidden" name="usuario" value="<?php echo htmlspecialchars($row['usuario']); ?>" />
                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Administrar carreras</h5>
          <form method="post" action="index.php" class="mb-3" onsubmit="return confirm('¿Borrar TODAS las carreras? Esta acción elimina también asignaciones y encuestas.');">
            <input type="hidden" name="accion" value="eliminar_carreras_todas" />
            <button class="btn btn-outline-danger" type="submit">Eliminar todas las carreras</button>
          </form>
          <form method="post" action="index.php" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="accion" value="crear_carrera" />
            <div class="col-md-6">
              <label class="form-label">Nombre de la carrera</label>
              <input name="nombre" class="form-control" required />
            </div>
            <div class="col-md-2">
              <button class="btn btn-success" type="submit">Crear carrera</button>
            </div>
          </form>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Carrera</th>
                  <th style="width: 1%">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($carreras as $car): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($car); ?></td>
                    <td class="text-nowrap">
                      <form method="post" action="index.php" class="d-inline-flex align-items-center gap-2" onsubmit="return confirm('¿Renombrar esta carrera?');" style="display:inline-flex">
                        <input type="hidden" name="accion" value="renombrar_carrera" />
                        <input type="hidden" name="old" value="<?php echo htmlspecialchars($car); ?>" />
                        <input type="text" name="nuevo" class="form-control form-control-sm w-auto" style="min-width:160px" placeholder="Nuevo nombre" required />
                        <button class="btn btn-sm btn-outline-primary" type="submit">Renombrar</button>
                      </form>
                      <form method="post" action="index.php" onsubmit="return confirm('¿Eliminar esta carrera?');" class="d-inline-block ms-2" style="display:inline-block">
                        <input type="hidden" name="accion" value="eliminar_carrera" />
                        <input type="hidden" name="nombre" value="<?php echo htmlspecialchars($car); ?>" />
                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="form-text">Nota: No puedes eliminar carreras que estén en uso por alumnos o asignaciones.</div>
        </div>
      </div>

    <?php endif; ?>
  </div>
</div>
