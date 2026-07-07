<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">UPGOP</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="index.php?seccion=manual">Manual</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="index.php?seccion=evaluacion">Evaluación</a>
        </li>
      </ul>
      <ul class="navbar-nav">
        <?php if (!empty($_SESSION['alumno'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="index.php?seccion=perfil">Hola, <?php $n = trim($_SESSION['alumno']['nombre']); $p = $n !== '' ? preg_split('/\s+/', $n)[0] : ''; echo htmlspecialchars($p); ?></a>
          </li>
          <li class="nav-item">
            <a href="index.php?seccion=logout" class="nav-link">Salir</a>
          </li>
        <?php elseif (!empty($_SESSION['dpa_ok'])): ?>
          <li class="nav-item">
            <span class="nav-link">DPA: <?php echo htmlspecialchars($_SESSION['dpa_user'] ?? ''); ?><?php echo !empty($_SESSION['dpa_carrera']) ? ' (' . htmlspecialchars($_SESSION['dpa_carrera']) . ')' : ''; ?></span>
          </li>
          <li class="nav-item">
            <form method="post" action="index.php" class="d-inline">
              <input type="hidden" name="accion" value="logout_dpa" />
              <button class="nav-link btn btn-link p-0" type="submit">Salir</button>
            </form>
          </li>
        <?php elseif (!empty($_SESSION['sa_ok'])): ?>
          <li class="nav-item">
            <span class="nav-link">SA: <?php echo htmlspecialchars($_SESSION['sa_user'] ?? ''); ?></span>
          </li>
          <li class="nav-item">
            <form method="post" action="index.php" class="d-inline">
              <input type="hidden" name="accion" value="logout_sa" />
              <button class="nav-link btn btn-link p-0" type="submit">Salir</button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a href="index.php?seccion=login" class="nav-link">Login</a>
          </li>
          <li class="nav-item">
            <a href="index.php?seccion=registro_alumno" class="nav-link">Registrar</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container py-3">
  <div class="row">
    <div class="col">
      <img src="public/images/logo.png" alt="Logo UPGOP" style="height:60px;">
    </div>
  </div>

