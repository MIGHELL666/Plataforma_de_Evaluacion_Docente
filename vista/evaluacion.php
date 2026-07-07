<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['alumno'])) {
    header('Location: index.php?seccion=login');
    exit;
}
$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
$alumno = $_SESSION['alumno'];

require_once __DIR__ . '/../modelo/Conexion.php';
$conn = (new Conexion())->conectar();
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
$docentes = [];
if ($hasCarreraRG) {
  $stmt = $conn->prepare('SELECT numeroEmpleado, nombreDocente FROM registros_grupo WHERE semestre = ? AND grupo = ? AND carrera = ? ORDER BY nombreDocente');
  $stmt->bind_param('sss', $alumno['semestre'], $alumno['grupo'], $alumno['carrera']);
} else {
  $stmt = $conn->prepare('SELECT numeroEmpleado, nombreDocente FROM registros_grupo WHERE semestre = ? AND grupo = ? ORDER BY nombreDocente');
  $stmt->bind_param('ss', $alumno['semestre'], $alumno['grupo']);
}
$stmt->execute();
$rs = $stmt->get_result();
while ($row = $rs->fetch_assoc()) { $docentes[] = $row; }

try { $conn->query('CREATE TABLE IF NOT EXISTS config_app (clave VARCHAR(64) PRIMARY KEY, valor VARCHAR(256) NOT NULL)'); } catch (Throwable $e) { }
$stCfg = $conn->prepare('SELECT valor FROM config_app WHERE clave="estado_evaluacion" LIMIT 1');
$stCfg->execute();
$rCfg = $stCfg->get_result()->fetch_assoc();
$habilitada = !$rCfg ? true : ((string)$rCfg['valor'] === 'on');
$stOnce = $conn->prepare('SELECT id FROM encuestas_alumno WHERE matricula=? AND semestre=? AND grupo=? LIMIT 1');
$stOnce->bind_param('sss', $alumno['matricula'], $alumno['semestre'], $alumno['grupo']);
$stOnce->execute();
$rOnce = $stOnce->get_result()->fetch_assoc();

$preguntas = [
  '1.- El profesor propicia un ambiente favorable para la comunicacion en la clase',
  '2.- ¿El maestro responde claramente las preguntas que surgen en clase?',
  '3.- ¿El profesor utiliza material didactico y TIC\'s (tecnologias) en clase?',
  '4.- ¿El profesor utiliza la plataforma tecnologica moodle para los contenidos y evidencias de la materia?',
  '5.- ¿El profesor publica la calendarizacion de evidencias en plataforma moodle?',
  '6.- ¿El profesor publica el programa de la materia (temario) en plataforma moodle?',
  '7.- El profesor prepara clase',
  '8.- ¿El profesor te informa oportunamente las referencias bibliograficas y/o electronicas requeridas para la materia?',
  '9.- ¿El profesor se apega al programa de la materia?',
  '10.- ¿El profesor te señala como contribuye la asignatura para el perfil de la carrera?',
  '11.- El profesor asiste, inicia y termina puntualmente la clase.',
  '12.- El maestro explica el sistema de evaluacion y evalua de manera congruente segun los objetivos de la asignatura',
  '13.- El profesor te retroalimenta adecuada y oportunamente los resultados de las evidencias',
  '14.- Te gustaria tener otra clase con el mismo maestro.'
];
?>
<div class="row">
  <div class="col-lg-10">
    <h3 class="mt-3 mb-3">Evaluación docente</h3>
    <?php if ($err): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div>
    <?php endif; ?>
    <?php if (!$habilitada): ?>
      <div class="alert alert-info">La evaluación está desactivada por DPA.</div>
    <?php elseif ($rOnce): ?>
      <div class="alert alert-warning">Ya has realizado esta evaluación para tu cuatrimestre y grupo.</div>
    <?php elseif (empty($docentes)): ?>
      <div class="alert alert-warning">No hay docentes registrados para tu cuatrimestre y grupo<?php echo $hasCarreraRG ? ', y carrera' : ''; ?>.</div>
    <?php else: ?>
    <form method="post" action="index.php">
      <input type="hidden" name="accion" value="guardar_evaluacion" />

      <?php foreach ($preguntas as $idx => $texto): ?>
        <div class="card mb-3">
          <div class="card-header">
            <strong><?php echo htmlspecialchars($texto); ?></strong>
          </div>
          <ul class="list-group list-group-flush">
            <?php foreach ($docentes as $d): 
              $num = htmlspecialchars($d['numeroEmpleado']);
              $nom = htmlspecialchars($d['nombreDocente']);
            ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><?php echo $nom; ?></span>
                <div class="btn-group" role="group" aria-label="Calificación">
                  <?php for ($v = 1; $v <= 5; $v++):
                    $id = 'r_' . $num . '_' . $idx . '_' . $v;
                  ?>
                    <input type="radio" class="btn-check" name="ratings[<?php echo $num; ?>][<?php echo $idx; ?>]" id="<?php echo $id; ?>" autocomplete="off" value="<?php echo $v; ?>" <?php echo $v === 1 ? 'required' : ''; ?>>
                    <label class="btn btn-outline-secondary btn-sm" for="<?php echo $id; ?>"><?php echo $v; ?></label>
                  <?php endfor; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>

      <div class="card mb-3">
        <div class="card-header">
          <strong>Comentarios por profesor</strong>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">Opcional: deja un comentario específico para cada profesor.</p>
          <?php foreach ($docentes as $d): 
            $num = htmlspecialchars($d['numeroEmpleado']);
            $nom = htmlspecialchars($d['nombreDocente']);
          ?>
            <div class="mb-3">
              <label class="form-label"><?php echo $nom; ?></label>
              <textarea name="comentarios[<?php echo $num; ?>]" class="form-control" rows="3" placeholder="Escribe tu comentario para <?php echo $nom; ?> (opcional)"></textarea>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Enviar evaluación</button>
    </form>
    <?php endif; ?>
  </div>
</div>
