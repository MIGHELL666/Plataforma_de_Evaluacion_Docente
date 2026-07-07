<?php
require_once __DIR__ . '/../modelo/Conexion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$conn = (new Conexion())->conectar();

$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);

$doc = $_SESSION['docente'] ?? null;
$num = $doc['numeroEmpleado'] ?? (isset($_GET['numeroEmpleado']) ? trim($_GET['numeroEmpleado']) : '');
$docNombre = $doc['nombreDocente'] ?? null;
if (!$doc && $num !== '') {
    $st = $conn->prepare('SELECT nombreDocente FROM registros_grupo WHERE numeroEmpleado=? LIMIT 1');
    $st->bind_param('s', $num);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $docNombre = $r ? $r['nombreDocente'] : null;
}
$promedio = null;
if ($num !== '') {
    $res = $conn->prepare('SELECT preguntaIndex, rating FROM respuestas WHERE numeroEmpleado=? ORDER BY preguntaIndex');
    $res->bind_param('s', $num);
    $res->execute();
    $rs = $res->get_result();
    $ratings = [];
    while ($row = $rs->fetch_assoc()) { $ratings[] = (int)$row['rating'] * 2.0; }
    $promedio = empty($ratings) ? 0.0 : array_sum($ratings) / count($ratings);
}
$opiniones = [];
if ($num !== '') {
    $so = $conn->prepare('SELECT id, comentario, semestre, grupo, timestamp FROM opiniones WHERE numeroEmpleado=? ORDER BY timestamp DESC');
    $so->bind_param('s', $num);
    $so->execute();
    $ro = $so->get_result();
    while ($row = $ro->fetch_assoc()) { $opiniones[] = $row; }
}
$printMode = isset($_GET['imprimir']);
$limitComments = isset($_GET['limit']) ? (string)$_GET['limit'] : 'all';
if (!empty($opiniones) && $limitComments === '10') { $opiniones = array_slice($opiniones, 0, 10); }
$selectMode = isset($_GET['select']) && $_GET['select'] === '10';
// Filtrar por IDs seleccionados desde DPA
$opIdsParam = isset($_GET['op_ids']) ? trim((string)$_GET['op_ids']) : '';
if ($printMode && $opIdsParam !== '') {
    $ids = array_values(array_filter(array_map(function($x){ return (int)trim($x); }, explode(',', $opIdsParam)), function($v){ return $v > 0; }));
    if (!empty($ids)) {
        $opiniones = array_values(array_filter($opiniones, function($op) use ($ids) {
            return in_array((int)$op['id'], $ids, true);
        }));
    }
}
//
$promedioPorMateria = [];
$promedioPorCarrera = [];
if ($num !== '') {
    $stm = $conn->prepare('SELECT rg.materia AS m, AVG(r.rating) * 2.0 AS avg10 FROM respuestas r INNER JOIN registros_grupo rg ON rg.numeroEmpleado = r.numeroEmpleado AND rg.semestre = r.semestre AND rg.grupo = r.grupo WHERE r.numeroEmpleado=? GROUP BY rg.materia ORDER BY rg.materia');
    $stm->bind_param('s', $num);
    $stm->execute();
    $rm = $stm->get_result();
    while ($row = $rm->fetch_assoc()) { $promedioPorMateria[] = $row; }

    $stc = $conn->prepare('SELECT rg.carrera AS c, AVG(r.rating) * 2.0 AS avg10 FROM respuestas r INNER JOIN registros_grupo rg ON rg.numeroEmpleado = r.numeroEmpleado AND rg.semestre = r.semestre AND rg.grupo = r.grupo WHERE r.numeroEmpleado=? GROUP BY rg.carrera ORDER BY rg.carrera');
    $stc->bind_param('s', $num);
    $stc->execute();
    $rc = $stc->get_result();
    while ($row = $rc->fetch_assoc()) { $promedioPorCarrera[] = $row; }
}
$labelsMaterias = [];
$dataMaterias = [];
foreach ($promedioPorMateria as $row) { $labelsMaterias[] = (string)$row['m']; $dataMaterias[] = round((float)$row['avg10'], 2); }
$labelsCarreras = [];
$dataCarreras = [];
foreach ($promedioPorCarrera as $row) { $labelsCarreras[] = (string)$row['c']; $dataCarreras[] = round((float)$row['avg10'], 2); }
?>
<div class="row">
  <div class="col-lg-12">
    <h3 class="mt-3 mb-3 no-print">Docente</h3>
    <?php if ($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php endif; ?>

    <?php if (!$doc && $num === ''): ?>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Iniciar sesión</h5>
          <form method="post" action="index.php" class="row g-2">
            <input type="hidden" name="accion" value="login_docente" />
            <div class="col-auto">
              <label class="form-label">Número de empleado</label>
              <input name="numeroEmpleado" class="form-control" required inputmode="numeric" pattern="[0-9]+" title="Solo números" oninput="this.value=this.value.replace(/\D/g,'')" />
            </div>
            <div class="col-auto">
              <label class="form-label">Contraseña</label>
              <input name="password" type="password" class="form-control" required />
            </div>
            <div class="col-auto align-self-end">
              <button class="btn btn-primary" type="submit">Entrar</button>
            </div>
          </form>
        </div>
      </div>
    <?php else: ?>
      <?php if ($doc): ?>
        <p class="no-print"><strong>Sesión:</strong> <?php echo htmlspecialchars($doc['nombreDocente']); ?> (<?php echo htmlspecialchars($doc['numeroEmpleado']); ?>)</p>
        <a class="btn btn-sm btn-outline-secondary mb-3 no-print" href="index.php?seccion=logout">Salir</a>
      <?php else: ?>
        <p class="no-print"><strong>Docente:</strong> <?php echo htmlspecialchars($docNombre ?? ''); ?> (<?php echo htmlspecialchars($num); ?>)</p>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($num !== '' && $printMode && !$selectMode): ?>
    <div class="card mb-3 no-print">
      <div class="card-body d-flex align-items-end gap-2">
        <form method="get" action="index.php" class="d-flex align-items-end gap-2">
          <input type="hidden" name="seccion" value="docente" />
          <input type="hidden" name="numeroEmpleado" value="<?php echo htmlspecialchars($num); ?>" />
          <input type="hidden" name="imprimir" value="1" />
          <div>
            <label class="form-label">Comentarios a imprimir</label>
            <select name="limit" class="form-select">
              <option value="all" <?php echo ($limitComments==='all')?'selected':''; ?>>Todos</option>
              <option value="10" <?php echo ($limitComments==='10')?'selected':''; ?>>Solo 10 más recientes</option>
            </select>
          </div>
          <button class="btn btn-outline-primary" type="submit">Aplicar</button>
        </form>
        <button class="btn btn-primary" type="button" onclick="window.print()">Imprimir</button>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($num !== '' && $printMode && $selectMode): ?>
    <div class="card mb-3 no-print">
      <div class="card-body">
        <h5 class="card-title">Selecciona hasta 10 comentarios para imprimir</h5>
        <?php if (!empty($opiniones)): ?>
          <div class="list-group" id="op-list">
            <?php foreach ($opiniones as $op): ?>
              <label class="list-group-item d-flex align-items-start gap-2">
                <input type="checkbox" class="form-check-input mt-1 op-check" data-op-id="<?php echo (int)$op['id']; ?>" />
                <div>
                  <div class="small text-muted">Semestre: <?php echo htmlspecialchars($op['semestre']); ?>, Grupo: <?php echo htmlspecialchars($op['grupo']); ?></div>
                  <div class="op-text" data-op-id="<?php echo (int)$op['id']; ?>"><?php echo nl2br(htmlspecialchars($op['comentario'])); ?></div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <div>Seleccionados: <span id="selCount">0</span> / 10</div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary" onclick="marcarPrimerosDiez()">Marcar 10 primeros</button>
              <button type="button" class="btn btn-primary" onclick="imprimirSeleccion()">Imprimir selección</button>
            </div>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0">Sin comentarios.</p>
        <?php endif; ?>
      </div>
    </div>
    <script>
    (function(){
      const checks = document.querySelectorAll('.op-check');
      const selCountEl = document.getElementById('selCount');
      function updateCount(){
        const count = Array.from(checks).filter(c => c.checked).length;
        selCountEl.textContent = count;
      }
      checks.forEach(ch => {
        ch.addEventListener('change', function(){
          const cks = Array.from(checks);
          const selected = cks.filter(c => c.checked);
          if (selected.length > 10) {
            this.checked = false;
            return;
          }
          updateCount();
        });
      });
      window.marcarPrimerosDiez = function(){
        let n = 0;
        checks.forEach(ch => { ch.checked = false; });
        for (const ch of checks) {
          if (n >= 10) break;
          ch.checked = true; n++;
        }
        updateCount();
      };
      window.imprimirSeleccion = function(){
        const selectedIds = Array.from(checks).filter(c => c.checked).map(c => c.getAttribute('data-op-id'));
        const items = document.querySelectorAll('#op-list .list-group-item');
        const toRestore = [];
        items.forEach(item => {
          const cbox = item.querySelector('.op-check');
          if (!cbox || !cbox.checked) {
            if (item.style.display !== 'none') { toRestore.push(item); }
            item.style.display = 'none';
          }
        });
        window.print();
        setTimeout(() => { toRestore.forEach(el => { el.style.display = ''; }); }, 100);
      };
    })();
    </script>
    <?php endif; ?>

    <div class="card no-print">
      <div class="card-body">
        <h5 class="card-title">Promedio general</h5>
        <?php if ($num !== ''): ?>
          <p class="card-text fs-4"><strong><?php echo number_format($promedio, 2); ?></strong> / 10</p>
          <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#comentariosModal">COMENTARIOS (<?php echo count($opiniones); ?>)</button>
        <?php else: ?>
          <p class="text-muted">Inicia sesión para ver tus resultados.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="row mt-3" id="printCharts">
      <div class="col-lg-6 col-md-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Promedio por materia</h5>
            <?php if ($num !== ''): ?>
              <?php if (!empty($promedioPorMateria)): ?>
                <div class="table-responsive">
                  <table class="table table-sm">
                    <thead>
                      <tr>
                        <th>Materia</th>
                        <th>Promedio</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($promedioPorMateria as $row): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($row['m']); ?></td>
                          <td><?php echo number_format((float)$row['avg10'], 2); ?> / 10</td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="mt-3">
                  <canvas id="chartMaterias" class="w-100" style="height:300px"></canvas>
                </div>
              <?php else: ?>
                <p class="text-muted">Sin registros de evaluación por materia.</p>
              <?php endif; ?>
            <?php else: ?>
              <p class="text-muted">Inicia sesión para ver tus resultados.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Promedio por carrera</h5>
            <?php if ($num !== ''): ?>
              <?php if (!empty($promedioPorCarrera)): ?>
                <div class="table-responsive">
                  <table class="table table-sm">
                    <thead>
                      <tr>
                        <th>Carrera</th>
                        <th>Promedio</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($promedioPorCarrera as $row): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($row['c']); ?></td>
                          <td><?php echo number_format((float)$row['avg10'], 2); ?> / 10</td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="mt-3">
                  <canvas id="chartCarreras" class="w-100" style="height:300px"></canvas>
                </div>
              <?php else: ?>
                <p class="text-muted">Sin registros de evaluación por carrera.</p>
              <?php endif; ?>
            <?php else: ?>
              <p class="text-muted">Inicia sesión para ver tus resultados.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if ($printMode): ?>
<style>
@page { size: A4 portrait; margin: 10mm; }
@media print {
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .navbar, .no-print { display: none !important; }
  body { font-size: 12px; }
  #printCharts { page-break-inside: avoid; }
  #printCharts .col-lg-6, #printCharts .col-md-12 { width: 50%; display: inline-block; vertical-align: top; }
  .card { margin: 6px 0 !important; break-inside: avoid; page-break-inside: avoid; }
  .card-body { padding: 8px !important; }
  h5.card-title { font-size: 16px; margin-bottom: 6px; }
  table.table { margin-bottom: 6px; }
  table.table th, table.table td { padding: 4px 6px; font-size: 11px; }
  canvas { height: 180px !important; }
  #printComentarios { page-break-inside: avoid; }
  #printComentarios .list-group { display: block; margin: 0; padding: 0; }
  #printComentarios .list-group-item { break-inside: avoid; display: inline; border: 0; padding: 0; margin: 0; }
  #printComentarios .list-group-item > div { display: inline; margin: 0; padding: 0; }
  #printComentarios .list-group-item::after { content: ', '; }
  #printComentarios .list-group-item:last-child::after { content: ''; }
  #printComentarios br { display: none; }
}
</style>
<?php endif; ?>
<script>
<?php if (!empty($promedioPorMateria)): ?>
const materiasLabels = <?php echo json_encode($labelsMaterias); ?>;
const materiasData = <?php echo json_encode($dataMaterias); ?>;
const ctxM = document.getElementById('chartMaterias');
<?php if ($printMode): ?>
ctxM.width = 600;
ctxM.height = 180;
<?php endif; ?>
const chM = new Chart(ctxM, {
  type: 'bar',
  data: {
    labels: materiasLabels,
    datasets: [{
      label: 'Promedio / 10',
      data: materiasData,
      backgroundColor: '#0d6efd'
    }]
  },
  options: {
    animation: { duration: <?php echo $printMode ? '0' : '400'; ?> },
    responsive: <?php echo $printMode ? 'false' : 'true'; ?>,
    maintainAspectRatio: false,
    scales: { y: { beginAtZero: true, suggestedMax: 10 } }
  }
});
<?php endif; ?>
<?php if (!empty($promedioPorCarrera)): ?>
const carrerasLabels = <?php echo json_encode($labelsCarreras); ?>;
const carrerasData = <?php echo json_encode($dataCarreras); ?>;
const ctxC = document.getElementById('chartCarreras');
<?php if ($printMode): ?>
ctxC.width = 600;
ctxC.height = 180;
<?php endif; ?>
const chC = new Chart(ctxC, {
  type: 'bar',
  data: {
    labels: carrerasLabels,
    datasets: [{
      label: 'Promedio / 10',
      data: carrerasData,
      backgroundColor: '#198754'
    }]
  },
  options: {
    animation: { duration: <?php echo $printMode ? '0' : '400'; ?> },
    responsive: <?php echo $printMode ? 'false' : 'true'; ?>,
    maintainAspectRatio: false,
    scales: { y: { beginAtZero: true, suggestedMax: 10 } }
  }
});
<?php endif; ?>
</script>
<?php if ($printMode): ?>
<script>
(function(){
  setTimeout(function(){
    try {
      var canvases = [document.getElementById('chartMaterias'), document.getElementById('chartCarreras')];
      var charts = [];
      if (typeof Chart !== 'undefined') {
        if (canvases[0]) charts.push(Chart.getChart(canvases[0]) || chM);
        if (canvases[1]) charts.push(Chart.getChart(canvases[1]) || chC);
      }
      charts.forEach(function(chart, i){
        if (!chart) return;
        var img = new Image();
        img.src = chart.toBase64Image();
        img.style.width = '100%';
        img.style.height = '180px';
        var cv = canvases[i];
        if (cv && cv.parentNode) cv.parentNode.replaceChild(img, cv);
      });
    } catch(e) {}
    window.__chartsReady = true;
    try { parent.postMessage({ type: 'charts-ready' }, '*'); } catch(e){}
  }, 300);
})();
</script>
<?php endif; ?>

<!-- Modal comentarios -->
<?php if (!$printMode): ?>
<div class="modal fade" id="comentariosModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Comentarios de alumnos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
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
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
  </div>
<?php endif; ?>

<?php if ($printMode): ?>
<div class="row mt-3" id="printComentarios">
  <div class="col-lg-12">
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
<?php endif; ?>
 
