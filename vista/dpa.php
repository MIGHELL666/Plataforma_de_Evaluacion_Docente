<?php
require_once __DIR__ . '/../modelo/Conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$conn = (new Conexion())->conectar();

$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);

// Opciones de carrera (mismas que ve el alumno en registro)

// Carrera seleccionada para filtrar
$selectedCarrera = isset($_GET['filtro_carrera']) ? trim($_GET['filtro_carrera']) : '';
if (!empty($_SESSION['dpa_carrera'])) { $selectedCarrera = $_SESSION['dpa_carrera']; }

// Detectar si existe la columna carrera en registros_grupo
$dbName = '';
try {
  $resDb = $conn->query('SELECT DATABASE() AS db');
  if ($resDb) { $rdb = $resDb->fetch_assoc(); $dbName = (string)($rdb['db'] ?? ''); }
} catch (Throwable $e) { /* ignore */ }
$hasCarreraRG = false;
if ($dbName !== '') {
  $stc = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME="registros_grupo" AND COLUMN_NAME="carrera"');
  $stc->bind_param('s', $dbName);
  $stc->execute();
  $rc = $stc->get_result()->fetch_assoc();
  $hasCarreraRG = ((int)($rc['cnt'] ?? 0)) > 0;
}

// Detectar si existe la columna password en registros_grupo
$hasPasswordRG = false;
if ($dbName !== '') {
  $stp = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME="registros_grupo" AND COLUMN_NAME="password"');
  $stp->bind_param('s', $dbName);
  $stp->execute();
  $rp = $stp->get_result()->fetch_assoc();
  $hasPasswordRG = ((int)($rp['cnt'] ?? 0)) > 0;
}

// Lista de carreras desde BD (se hace seed si está vacío)
$carreras = [];
try {
  $rsCarr = $conn->query('SELECT nombre FROM carreras ORDER BY nombre');
  if ($rsCarr) {
    while ($row = $rsCarr->fetch_assoc()) { $carreras[] = (string)$row['nombre']; }
  }
} catch (Throwable $e) { }

// Registro en edición (si aplica)
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
  $sqlEdit = $hasCarreraRG
    ? 'SELECT id, numeroEmpleado, nombreDocente, materia, semestre, grupo, carrera FROM registros_grupo WHERE id=?'
    : 'SELECT id, numeroEmpleado, nombreDocente, materia, semestre, grupo FROM registros_grupo WHERE id=?';
  $st = $conn->prepare($sqlEdit);
  $st->bind_param('i', $editId);
  $st->execute();
  $editRow = $st->get_result()->fetch_assoc();
}

$addNum = isset($_GET['add_num']) ? trim($_GET['add_num']) : '';
$addNom = isset($_GET['add_nom']) ? trim($_GET['add_nom']) : '';

// Lista docentes registrados en asignaciones (filtrados por carrera si aplica)

try {
  $conn->query('INSERT INTO docentes (numeroEmpleado, nombre, password)
    SELECT rg.numeroEmpleado, MAX(rg.nombreDocente), MAX(rg.password)
    FROM registros_grupo rg
    LEFT JOIN docentes d ON d.numeroEmpleado = rg.numeroEmpleado
    WHERE d.numeroEmpleado IS NULL
    GROUP BY rg.numeroEmpleado');
} catch (Throwable $e) { }
if ($hasCarreraRG) {
  $selFields = 'id, numeroEmpleado, nombreDocente, materia, semestre, grupo, carrera' . ($hasPasswordRG ? ', password' : '');
  if ($selectedCarrera !== '') {
    $st = $conn->prepare("SELECT $selFields FROM registros_grupo WHERE carrera=? ORDER BY nombreDocente, semestre, grupo");
    $st->bind_param('s', $selectedCarrera);
    $st->execute();
    $lista = $st->get_result();
  } else {
    $lista = $conn->query("SELECT $selFields FROM registros_grupo ORDER BY nombreDocente, semestre, grupo");
  }
} else {
  // Fallback sin columna carrera
  $selFields = 'id, numeroEmpleado, nombreDocente, materia, semestre, grupo' . ($hasPasswordRG ? ', password' : '');
  $lista = $conn->query("SELECT $selFields FROM registros_grupo ORDER BY nombreDocente, semestre, grupo");
}

$grouped = [];
if ($lista) {
  while ($row = $lista->fetch_assoc()) {
    $k = (string)$row['numeroEmpleado'];
    if (!isset($grouped[$k])) {
      $grouped[$k] = [
        'numeroEmpleado' => $k,
        'nombreDocente' => (string)$row['nombreDocente'],
        'password' => $row['password'] ?? null,
        'asigs' => []
      ];
    }
    $grouped[$k]['asigs'][] = $row;
  }
}

try {
  $docs = $conn->query('SELECT numeroEmpleado, nombre FROM docentes');
  $docNames = [];
  if ($docs) {
    while ($d = $docs->fetch_assoc()) { $docNames[(string)$d['numeroEmpleado']] = (string)$d['nombre']; }
    foreach ($grouped as $k => &$g) { if (isset($docNames[$k])) { $g['nombreDocente'] = $docNames[$k]; } }
    unset($g);
  }
} catch (Throwable $e) {}

function promedioDocente(mysqli $conn, string $num): float {
    $stmt = $conn->prepare('SELECT rating FROM respuestas WHERE numeroEmpleado=?');
    $stmt->bind_param('s', $num);
    $stmt->execute();
    $rs = $stmt->get_result();
    $ratings = [];
    while ($r = $rs->fetch_assoc()) { $ratings[] = (int)$r['rating'] * 2.0; }
    if (empty($ratings)) return 0.0;
    return array_sum($ratings) / count($ratings);
}
// Impresión masiva: página de solo impresión con gráficas y comentarios de todos los docentes (filtrados)
if (!empty($_SESSION['dpa_ok']) && isset($_GET['print_all'])) {
  ?>
  <style>
  @page { size: A4 portrait; margin: 10mm; }
  @media print {
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .navbar { display: none !important; }
    .container.py-3 > .row > .col > img[alt="Logo UPGOP"] { display: none !important; }
    .print-docente { page-break-inside: avoid; break-inside: avoid; page-break-after: always; }
    .print-docente:last-child { page-break-after: auto; }
    .card { margin: 6px 0 !important; break-inside: avoid; page-break-inside: avoid; }
    .card-body { padding: 8px !important; }
    h5.card-title { font-size: 16px; margin-bottom: 6px; }
    table.table th, table.table td { padding: 4px 6px; font-size: 11px; }
    canvas { height: 180px !important; }
    .print-comentarios .list-group { display: block; margin: 0; padding: 0; }
    .print-comentarios .list-group-item { display: inline; border: 0; padding: 0; margin: 0; }
    .print-comentarios .list-group-item > div { display: inline; }
    .print-comentarios .list-group-item::after { content: ', '; }
    .print-comentarios .list-group-item:last-child::after { content: ''; }
    .print-comentarios br { display: none; }
  }
  </style>
  <div class="container">
    <?php foreach ($grouped as $g): ?>
      <?php
        $num = (string)$g['numeroEmpleado'];
        $labelsMaterias = [];$dataMaterias = [];
        $stm = $conn->prepare('SELECT rg.materia AS m, AVG(r.rating) * 2.0 AS avg10 FROM respuestas r INNER JOIN registros_grupo rg ON rg.numeroEmpleado = r.numeroEmpleado AND rg.semestre = r.semestre AND rg.grupo = r.grupo WHERE r.numeroEmpleado=? GROUP BY rg.materia ORDER BY rg.materia');
        $stm->bind_param('s', $num);
        $stm->execute();
        $rm = $stm->get_result();
        while ($row = $rm->fetch_assoc()) { $labelsMaterias[] = (string)$row['m']; $dataMaterias[] = round((float)$row['avg10'], 2); }
        $labelsCarreras = [];$dataCarreras = [];
        if ($hasCarreraRG) {
          $stc = $conn->prepare('SELECT rg.carrera AS c, AVG(r.rating) * 2.0 AS avg10 FROM respuestas r INNER JOIN registros_grupo rg ON rg.numeroEmpleado = r.numeroEmpleado AND rg.semestre = r.semestre AND rg.grupo = r.grupo WHERE r.numeroEmpleado=? GROUP BY rg.carrera ORDER BY rg.carrera');
          $stc->bind_param('s', $num);
          $stc->execute();
          $rc = $stc->get_result();
          while ($row = $rc->fetch_assoc()) { $labelsCarreras[] = (string)$row['c']; $dataCarreras[] = round((float)$row['avg10'], 2); }
        }
        $opiniones = [];
        $so = $conn->prepare('SELECT id, comentario, semestre, grupo, timestamp FROM opiniones WHERE numeroEmpleado=? ORDER BY timestamp DESC');
        $so->bind_param('s', $num);
        $so->execute();
        $ro = $so->get_result();
        while ($row = $ro->fetch_assoc()) { $opiniones[] = $row; }
      ?>
      <div class="row mt-3 print-docente">
        <div class="col-12 mb-2"><img src="public/images/logo.png" alt="Logo UPGOP" style="height:60px;"></div>
        <div class="col-12">
          <h4><?php echo htmlspecialchars($g['nombreDocente']); ?> (<?php echo htmlspecialchars($num); ?>)</h4>
        </div>
        <div class="col-lg-6 col-md-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Promedio por materia</h5>
              <?php if (!empty($labelsMaterias)): ?>
                <div class="table-responsive">
                  <table class="table table-sm">
                    <thead><tr><th>Materia</th><th>Promedio</th></tr></thead>
                    <tbody>
                      <?php foreach ($labelsMaterias as $i => $lab): ?>
                        <tr><td><?php echo htmlspecialchars($lab); ?></td><td><?php echo number_format((float)$dataMaterias[$i], 2); ?> / 10</td></tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="mt-2"><canvas id="chartMaterias-<?php echo htmlspecialchars($num); ?>" data-print="1" style="height:300px" ></canvas></div>
              <?php else: ?>
                <p class="text-muted">Sin registros de evaluación por materia.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-6 col-md-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Promedio por carrera</h5>
              <?php if (!empty($labelsCarreras)): ?>
                <div class="table-responsive">
                  <table class="table table-sm">
                    <thead><tr><th>Carrera</th><th>Promedio</th></tr></thead>
                    <tbody>
                      <?php foreach ($labelsCarreras as $i => $lab): ?>
                        <tr><td><?php echo htmlspecialchars($lab); ?></td><td><?php echo number_format((float)$dataCarreras[$i], 2); ?> / 10</td></tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="mt-2"><canvas id="chartCarreras-<?php echo htmlspecialchars($num); ?>" data-print="1" style="height:300px" ></canvas></div>
              <?php else: ?>
                <p class="text-muted">Sin registros de evaluación por carrera.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-12 mt-2 print-comentarios">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Comentarios de alumnos</h5>
              <?php if (!empty($opiniones)): ?>
                <div class="list-group">
                  <?php foreach ($opiniones as $op): ?>
                    <div class="list-group-item">
                      <div><?php echo nl2br(htmlspecialchars($op['comentario'])); ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted mb-0">Sin comentarios.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php
        // Preparar configuración para charts JS
        $cfgs[] = [ 'id' => 'chartMaterias-' . $num, 'labels' => $labelsMaterias, 'data' => $dataMaterias, 'color' => '#0d6efd' ];
        $cfgs[] = [ 'id' => 'chartCarreras-' . $num, 'labels' => $labelsCarreras, 'data' => $dataCarreras, 'color' => '#198754' ];
      ?>
    <?php endforeach; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
  (function(){
    var cfgs = <?php echo json_encode($cfgs ?? []); ?>;
    var charts = [];
    cfgs.forEach(function(cfg){
      if (!cfg.labels || cfg.labels.length === 0) return;
      var cv = document.getElementById(cfg.id);
      if (!cv) return;
      cv.width = 600; cv.height = 180;
      var ch = new Chart(cv, {
        type: 'bar',
        data: { labels: cfg.labels, datasets: [{ label: 'Promedio / 10', data: cfg.data, backgroundColor: cfg.color }] },
        options: { animation: { duration: 0 }, responsive: false, maintainAspectRatio: false, scales: { y: { beginAtZero: true, suggestedMax: 10 } } }
      });
      charts.push({ chart: ch, canvas: cv });
    });
    setTimeout(function(){
      charts.forEach(function(obj){
        try {
          var img = new Image();
          img.src = obj.chart.toBase64Image();
          img.style.width = '100%';
          img.style.height = '180px';
          if (obj.canvas && obj.canvas.parentNode) obj.canvas.parentNode.replaceChild(img, obj.canvas);
        } catch(e) {}
      });
      window.__chartsReady = true;
      try { parent.postMessage({ type: 'charts-ready' }, '*'); } catch(e){}
    }, 400);
  })();
  </script>
  <?php
  return;
}
?>
<div class="row">
  <div class="col-lg-10">
    <h3 class="mt-3 mb-3">DPA</h3>
    <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>

    <?php $isDpa = !empty($_SESSION['dpa_ok']); if (!$isDpa): ?>
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Iniciar sesión DPA</h5>
          <form method="post" action="index.php" class="row g-2">
            <input type="hidden" name="accion" value="login_dpa" />
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
      <div><strong>Sesión DPA:</strong> <?php echo htmlspecialchars($_SESSION['dpa_user'] ?? ''); ?></div>
      <form method="post" action="index.php">
        <input type="hidden" name="accion" value="logout_dpa" />
        <button class="btn btn-sm btn-outline-secondary" type="submit">Cerrar sesión DPA</button>
      </form>
    </div>

    <?php
      try { $conn->query('CREATE TABLE IF NOT EXISTS config_app (clave VARCHAR(64) PRIMARY KEY, valor VARCHAR(256) NOT NULL)'); } catch (Throwable $e) { }
      $stCfg = $conn->prepare('SELECT valor FROM config_app WHERE clave="estado_evaluacion" LIMIT 1');
      $stCfg->execute();
      $rCfg = $stCfg->get_result()->fetch_assoc();
      $habilitada = !$rCfg ? true : ((string)$rCfg['valor'] === 'on');
    ?>
    <div class="card mb-3">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <strong>Estado de evaluación:</strong> <?php echo $habilitada ? '<span class="text-success">ACTIVADA</span>' : '<span class="text-danger">DESACTIVADA</span>'; ?>
        </div>
        <div class="d-flex gap-2">
          <form method="post" action="index.php">
            <input type="hidden" name="accion" value="toggle_evaluacion" />
            <input type="hidden" name="valor" value="on" />
            <button class="btn btn-sm btn-success" type="submit">Activar</button>
          </form>
          <form method="post" action="index.php">
            <input type="hidden" name="accion" value="toggle_evaluacion" />
            <input type="hidden" name="valor" value="off" />
            <button class="btn btn-sm btn-outline-danger" type="submit">Desactivar</button>
          </form>
        </div>
      </div>
    </div>

    

    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Registrar docente</h5>
        <form method="post" action="index.php">
          <input type="hidden" name="accion" value="registrar_docente" />
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label">Núm. empleado</label>
              <input name="numeroEmpleado" class="form-control" required inputmode="numeric" pattern="[0-9]+" title="Solo números" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Nombre</label>
              <input name="nombreDocente" class="form-control" required />
            </div>
            <div class="col-md-3">
              <label class="form-label">Contraseña docente</label>
              <input name="password" type="password" class="form-control" minlength="4" required />
            </div>
          </div>
          <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Asignaciones (materia, semestre, grupo, carrera)</h6>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="addAsignacion()">Agregar asignación</button>
            </div>
            <div id="asignaciones">
              <div class="row g-2 asignacion-item">
                <div class="col-md-3">
                  <input name="materia[]" class="form-control" placeholder="Materia" required />
                </div>
                <div class="col-md-2">
                  <input name="semestre[]" class="form-control" placeholder="Semestre" required />
                </div>
                <div class="col-md-2">
                  <input name="grupo[]" class="form-control" placeholder="Grupo" required />
                </div>
                <?php if ($hasCarreraRG): ?>
                <div class="col-md-3">
                  <select name="carrera[]" class="form-select" required>
                    <option value="">Seleccione carrera...</option>
                    <?php foreach ($carreras as $car): ?>
                      <option value="<?php echo htmlspecialchars($car); ?>"><?php echo htmlspecialchars($car); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php else: ?>
                <div class="col-md-3">
                  <input class="form-control" placeholder="Carrera (no disponible)" disabled />
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                  <button type="button" class="btn btn-outline-danger w-100 text-nowrap" onclick="removeAsignacion(this)">Eliminar</button>
                </div>
              </div>
            </div>
            <?php if (!$hasCarreraRG): ?>
            <div class="alert alert-warning mt-2">La columna <code>carrera</code> no existe en <code>registros_grupo</code>. Agrega la columna para habilitar la selección de carrera.</div>
            <?php endif; ?>
          </div>
          <div class="mt-3">
            <button class="btn btn-success" type="submit">Registrar</button>
          </div>
        </form>
        <script>
        function addAsignacion() {
          const cont = document.getElementById('asignaciones');
          const row = document.createElement('div');
          row.className = 'row g-2 asignacion-item';
          row.innerHTML = `
            <div class="col-md-3">
              <input name="materia[]" class="form-control" placeholder="Materia" required />
            </div>
            <div class="col-md-2">
              <input name="semestre[]" class="form-control" placeholder="Semestre" required />
            </div>
            <div class="col-md-2">
              <input name="grupo[]" class="form-control" placeholder="Grupo" required />
            </div>
            <?php if ($hasCarreraRG): ?>
            <div class="col-md-3">
              <select name="carrera[]" class="form-select" required>
                <option value="">Seleccione carrera...</option>
                <?php foreach ($carreras as $car): ?>
                  <option value="<?php echo htmlspecialchars($car); ?>"><?php echo htmlspecialchars($car); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php else: ?>
            <div class="col-md-3">
              <input class="form-control" placeholder="Carrera (no disponible)" disabled />
            </div>
            <?php endif; ?>
            <div class="col-md-2">
              <button type="button" class="btn btn-outline-danger w-100 text-nowrap" onclick="removeAsignacion(this)">Eliminar</button>
            </div>
          `;
          cont.appendChild(row);
        }
        function removeAsignacion(btn) {
          const row = btn.closest('.asignacion-item');
          if (!row) return;
          const cont = document.getElementById('asignaciones');
          if (cont.children.length <= 1) return;
          cont.removeChild(row);
        }
        </script>
      </div>
    </div>

    <?php if ($addNum !== '' && $addNom !== ''): ?>
    <div class="card mb-4 border-success">
      <div class="card-body">
        <h5 class="card-title">Agregar asignaciones a <?php echo htmlspecialchars($addNom); ?> (<?php echo htmlspecialchars($addNum); ?>)</h5>
        <form method="post" action="index.php">
          <input type="hidden" name="accion" value="agregar_asignaciones_docente" />
          <input type="hidden" name="numeroEmpleado" value="<?php echo htmlspecialchars($addNum); ?>" />
          <input type="hidden" name="nombreDocente" value="<?php echo htmlspecialchars($addNom); ?>" />
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Contraseña docente (opcional)</label>
              <input name="password" type="password" class="form-control" />
            </div>
          </div>
          <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Asignaciones (materia, semestre, grupo, carrera)</h6>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="addAsignacion2()">Agregar asignación</button>
            </div>
            <div id="asignaciones-add">
              <div class="row g-2 asignacion-item2">
                <div class="col-md-3">
                  <input name="materia[]" class="form-control" placeholder="Materia" required />
                </div>
                <div class="col-md-2">
                  <input name="semestre[]" class="form-control" placeholder="Semestre" required />
                </div>
                <div class="col-md-2">
                  <input name="grupo[]" class="form-control" placeholder="Grupo" required />
                </div>
                <?php if ($hasCarreraRG): ?>
                <div class="col-md-3">
                  <select name="carrera[]" class="form-select" required>
                    <option value="">Seleccione carrera...</option>
                    <?php foreach ($carreras as $car): ?>
                      <option value="<?php echo htmlspecialchars($car); ?>"><?php echo htmlspecialchars($car); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php else: ?>
                <div class="col-md-3">
                  <input class="form-control" placeholder="Carrera (no disponible)" disabled />
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                  <button type="button" class="btn btn-outline-danger w-100 text-nowrap" onclick="removeAsignacion2(this)">Eliminar</button>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-success" type="submit">Guardar asignaciones</button>
            <a class="btn btn-outline-secondary" href="index.php?seccion=dpa">Cancelar</a>
          </div>
        </form>
        <script>
        function addAsignacion2() {
          const cont = document.getElementById('asignaciones-add');
          const row = document.createElement('div');
          row.className = 'row g-2 asignacion-item2';
          row.innerHTML = `
            <div class="col-md-3">
              <input name="materia[]" class="form-control" placeholder="Materia" required />
            </div>
            <div class="col-md-2">
              <input name="semestre[]" class="form-control" placeholder="Semestre" required />
            </div>
            <div class="col-md-2">
              <input name="grupo[]" class="form-control" placeholder="Grupo" required />
            </div>
            <?php if ($hasCarreraRG): ?>
            <div class="col-md-3">
              <select name="carrera[]" class="form-select" required>
                <option value="">Seleccione carrera...</option>
                <?php foreach ($carreras as $car): ?>
                  <option value="<?php echo htmlspecialchars($car); ?>"><?php echo htmlspecialchars($car); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php else: ?>
            <div class="col-md-3">
              <input class="form-control" placeholder="Carrera (no disponible)" disabled />
            </div>
            <?php endif; ?>
            <div class="col-md-2">
              <button type="button" class="btn btn-outline-danger w-100 text-nowrap" onclick="removeAsignacion2(this)">Eliminar</button>
            </div>
          `;
          cont.appendChild(row);
        }
        function removeAsignacion2(btn) {
          const row = btn.closest('.asignacion-item2');
          if (!row) return;
          const cont = document.getElementById('asignaciones-add');
          if (cont.children.length <= 1) return;
          cont.removeChild(row);
        }
        </script>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($editRow): ?>
    <div class="card mb-4 border-warning">
      <div class="card-body">
        <h5 class="card-title">Editar docente</h5>
        <form method="post" action="index.php" class="row g-2">
          <input type="hidden" name="accion" value="editar_docente" />
          <input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>" />
          <div class="col-md-3">
            <label class="form-label">Núm. empleado</label>
            <input name="numeroEmpleado" class="form-control" value="<?php echo htmlspecialchars($editRow['numeroEmpleado']); ?>" required inputmode="numeric" pattern="[0-9]+" title="Solo números" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Nombre</label>
            <input name="nombreDocente" class="form-control" value="<?php echo htmlspecialchars($editRow['nombreDocente']); ?>" required />
          </div>
          <div class="col-md-3">
            <label class="form-label">Materia</label>
            <input name="materia" class="form-control" value="<?php echo htmlspecialchars($editRow['materia']); ?>" required />
          </div>
          <div class="col-md-1">
            <label class="form-label">Semestre</label>
            <input name="semestre" class="form-control" value="<?php echo htmlspecialchars($editRow['semestre']); ?>" required />
          </div>
          <div class="col-md-1">
            <label class="form-label">Grupo</label>
            <input name="grupo" class="form-control" value="<?php echo htmlspecialchars($editRow['grupo']); ?>" required />
          </div>
          <?php if ($hasCarreraRG): ?>
            <div class="col-md-3">
              <label class="form-label">Carrera</label>
              <select name="carrera" class="form-select" required>
                <option value="">Seleccione carrera...</option>
                <?php foreach ($carreras as $car): ?>
                  <option value="<?php echo htmlspecialchars($car); ?>" <?php echo ($editRow && ($editRow['carrera'] ?? '') === $car) ? 'selected' : ''; ?>><?php echo htmlspecialchars($car); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php else: ?>
            <div class="col-md-12">
              <div class="alert alert-warning">La columna <code>carrera</code> no existe en <code>registros_grupo</code>. Agrega la columna para habilitar la edición de carrera del docente.</div>
            </div>
          <?php endif; ?>
          <div class="col-md-12">
            <button class="btn btn-primary" type="submit">Guardar cambios</button>
            <a class="btn btn-outline-secondary" href="index.php?seccion=dpa">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <form method="get" action="index.php" class="row g-2 align-items-end">
          <input type="hidden" name="seccion" value="dpa" />
          <?php if ($hasCarreraRG): ?>
            <div class="col-md-4">
              <label class="form-label">Filtrar por carrera</label>
              <select name="filtro_carrera" class="form-select">
                <option value="">Todas las carreras</option>
                <?php foreach ($carreras as $car): ?>
                  <option value="<?php echo htmlspecialchars($car); ?>" <?php echo ($selectedCarrera === $car) ? 'selected' : ''; ?>><?php echo htmlspecialchars($car); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php else: ?>
            <div class="col-md-12">
              <div class="alert alert-info">El filtro por carrera estará disponible después de agregar la columna <code>carrera</code> en <code>registros_grupo</code>.</div>
            </div>
          <?php endif; ?>
          <div class="col-md-2">
            <button class="btn btn-outline-primary" type="submit">Aplicar filtro</button>
          </div>
          <div class="col-md-3">
            <button class="btn btn-success" type="button" onclick="printMasivo()">Imprimir masivo (gráficas y comentarios)</button>
          </div>
        </form>
      </div>
    </div>

    <table class="table table-striped">
      <thead>
        <tr>
          <th>Docente</th>
          <th>Núm. empleado</th>
          <?php if ($hasPasswordRG): ?><th>Contraseña</th><?php endif; ?>
          <th>Asignaciones</th>
          <th>Promedio</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($grouped as $g): ?>
          <tr>
            <td>
              <a href="index.php?seccion=docente&numeroEmpleado=<?php echo urlencode($g['numeroEmpleado']); ?>">
                <?php echo htmlspecialchars($g['nombreDocente']); ?>
              </a>
            </td>
            <td><?php echo htmlspecialchars($g['numeroEmpleado']); ?></td>
            <?php if ($hasPasswordRG): ?><td><?php echo htmlspecialchars($g['password'] ?? ''); ?></td><?php endif; ?>
            <td>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAsignaciones('asg-<?php echo htmlspecialchars($g['numeroEmpleado']); ?>')">Ver asignaciones (<?php echo count($g['asigs']); ?>)</button>
              <div id="asg-<?php echo htmlspecialchars($g['numeroEmpleado']); ?>" style="display:none" class="mt-2">
                <div class="table-responsive">
                  <table class="table table-sm">
                    <thead>
                      <tr>
                        <th>Materia</th>
                        <th>Semestre</th>
                        <th>Grupo</th>
                        <?php if ($hasCarreraRG): ?><th>Carrera</th><?php endif; ?>
                        <th>Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($g['asigs'] as $row): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($row['materia']); ?></td>
                          <td><?php echo htmlspecialchars($row['semestre']); ?></td>
                          <td><?php echo htmlspecialchars($row['grupo']); ?></td>
                          <?php if ($hasCarreraRG): ?><td><?php echo htmlspecialchars($row['carrera'] ?? ''); ?></td><?php endif; ?>
                          <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="index.php?seccion=dpa&edit=<?php echo (int)$row['id']; ?>">Editar</a>
                            <form method="post" action="index.php" style="display:inline-block" onsubmit="return confirm('¿Eliminar esta asignación?');">
                              <input type="hidden" name="accion" value="eliminar_docente" />
                              <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>" />
                              <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </td>
            <td><?php echo number_format(promedioDocente($conn, $g['numeroEmpleado']), 2); ?> / 10</td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-success" href="index.php?seccion=dpa&add_num=<?php echo urlencode($g['numeroEmpleado']); ?>&add_nom=<?php echo urlencode($g['nombreDocente']); ?>">Agregar</a>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="printComentarios('<?php echo htmlspecialchars($g['numeroEmpleado']); ?>','<?php echo htmlspecialchars($g['nombreDocente']); ?>')">Imprimir</button>
              <form method="post" action="index.php" style="display:inline-block" onsubmit="return confirm('¿Eliminar este docente y todas sus asignaciones?');">
                <input type="hidden" name="accion" value="eliminar_docente_total" />
                <input type="hidden" name="numeroEmpleado" value="<?php echo htmlspecialchars($g['numeroEmpleado']); ?>" />
                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <script>
    function toggleAsignaciones(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
    }
    function openPrintFrame(url) {
      const iframe = document.createElement('iframe');
      iframe.style.position = 'fixed';
      iframe.style.right = '0';
      iframe.style.bottom = '0';
      iframe.style.width = '0';
      iframe.style.height = '0';
      iframe.style.border = '0';
      iframe.src = url;
      function tryPrint(w) {
        if (!w) return;
        if (w.__chartsReady || w.document.readyState === 'complete') {
          try { w.focus(); w.print(); } catch (e) {}
          setTimeout(() => { if (iframe.parentNode) iframe.parentNode.removeChild(iframe); }, 1500);
        } else {
          setTimeout(() => tryPrint(w), 150);
        }
      }
      iframe.onload = function() {
        const w = iframe.contentWindow;
        tryPrint(w);
      };
      window.addEventListener('message', function(ev){ if (ev && ev.data && ev.data.type === 'charts-ready') { tryPrint(iframe.contentWindow); } });
      document.body.appendChild(iframe);
    }
    function printMasivo(){
      const sel = document.querySelector('select[name="filtro_carrera"]');
      const car = sel ? sel.value.trim() : '';
      const url = 'index.php?seccion=dpa&print_all=1' + (car ? ('&filtro_carrera=' + encodeURIComponent(car)) : '');
      openPrintFrame(url);
    }
    function printComentarios(num, nom) {
      Swal.fire({
        title: nom,
        html: '<div class="d-grid gap-2">'+
              '<button id="btnPrintAll" class="btn btn-primary">Imprimir con todos los comentarios</button>'+
              '<button id="btnChoose10" class="btn btn-outline-primary">Elegir 10 comentarios</button>'+
              '</div>',
        showConfirmButton: false,
        allowOutsideClick: true
      }).then(() => {});
      Swal.getHtmlContainer().querySelector('#btnPrintAll').onclick = function(){
        openPrintFrame('index.php?seccion=docente&numeroEmpleado=' + encodeURIComponent(num) + '&imprimir=1&limit=all');
      };
      Swal.getHtmlContainer().querySelector('#btnChoose10').onclick = async function(){
        const resp = await fetch('index.php?seccion=docente&numeroEmpleado=' + encodeURIComponent(num) + '&imprimir=1&select=10', { credentials: 'same-origin' });
        const html = await resp.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const list = doc.querySelector('#op-list');
        const items = list ? Array.from(list.querySelectorAll('.list-group-item')).map(el => ({ id: el.querySelector('.op-check')?.getAttribute('data-op-id') || '', text: el.querySelector('.op-text') ? el.querySelector('.op-text').innerHTML : el.textContent })) : [];
        const optionsHtml = items.map(i => '<label class="d-flex align-items-start gap-2 mb-1"><input type="checkbox" class="form-check-input sel-op" data-id="'+i.id+'"><div class="flex-grow-1">'+i.text+'</div></label>').join('');
        Swal.fire({
          title: 'Elegir 10 comentarios',
          html: '<div style="text-align:left; max-height:360px; overflow:auto">'+optionsHtml+'</div><div class="mt-2">Seleccionados: <span id="selCount">0</span> / 10</div>',
          showCancelButton: true,
          confirmButtonText: 'Imprimir selección',
          cancelButtonText: 'Cancelar',
          didOpen: () => {
            const checks = Swal.getHtmlContainer().querySelectorAll('.sel-op');
            const countEl = Swal.getHtmlContainer().querySelector('#selCount');
            checks.forEach(ch => ch.addEventListener('change', () => {
              let cnt = Array.from(checks).filter(c => c.checked).length;
              if (cnt > 10) { ch.checked = false; cnt = 10; }
              countEl.textContent = cnt;
            }));
          },
          preConfirm: () => {
            const checks = Swal.getHtmlContainer().querySelectorAll('.sel-op');
            const ids = Array.from(checks).filter(c => c.checked).map(c => c.getAttribute('data-id')).filter(Boolean);
            if (ids.length === 0) { Swal.showValidationMessage('Selecciona al menos 1 comentario'); return false; }
            if (ids.length > 10) { Swal.showValidationMessage('Máximo 10 comentarios'); return false; }
            return ids;
          }
        }).then(res => {
          if (res.isConfirmed) {
            const ids = res.value;
            openPrintFrame('index.php?seccion=docente&numeroEmpleado=' + encodeURIComponent(num) + '&imprimir=1&op_ids=' + encodeURIComponent(ids.join(',')));
          }
        });
      };
    }
    </script>
    <?php endif; ?>
  </div>
</div>
