<?php
require_once __DIR__ . '/../modelo/Conexion.php';

class AppController {
    public static function handle() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $accion = $_POST['accion'] ?? '';
        switch ($accion) {
            case 'registro_alumno': self::registroAlumno(); break;
            case 'login_alumno': self::loginAlumno(); break;
            case 'logout': self::logout(); break;
            case 'guardar_evaluacion': self::guardarEvaluacion(); break;
            case 'toggle_evaluacion': self::toggleEvaluacion(); break;
            case 'eliminar_carreras_todas': self::eliminarCarrerasTodas(); break;
            case 'actualizar_seccion': self::actualizarSeccion(); break;
            case 'actualizar_perfil_alumno': self::actualizarPerfilAlumno(); break;
            case 'login_sa': self::loginSa(); break;
            case 'logout_sa': self::logoutSa(); break;
            case 'crear_dpa': self::crearDpa(); break;
            case 'eliminar_dpa': self::eliminarDpa(); break;
            case 'registrar_docente': self::registrarDocente(); break;
            case 'agregar_asignaciones_docente': self::agregarAsignacionesDocente(); break;
            case 'crear_carrera': self::crearCarrera(); break;
            case 'eliminar_carrera': self::eliminarCarrera(); break;
            case 'renombrar_carrera': self::renombrarCarrera(); break;
            case 'login_docente': self::loginDocente(); break;
            case 'login_dpa': self::loginDpa(); break;
            case 'logout_dpa': self::logoutDpa(); break;
            case 'editar_docente': self::editarDocente(); break;
            case 'eliminar_docente': self::eliminarDocente(); break;
            case 'eliminar_docente_total': self::eliminarDocenteTotal(); break;
        }
    }

    private static function crearCarrera() {
        session_start();
        if (empty($_SESSION['sa_ok'])) { $_SESSION['flash_err'] = 'Acceso SA requerido.'; header('Location: index.php?seccion=sa'); exit; }
        $conn = self::db();
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            $_SESSION['flash_err'] = 'Ingresa el nombre de la carrera.';
            header('Location: index.php?seccion=sa');
            exit;
        }
        try {
            $st = $conn->prepare('INSERT INTO carreras (nombre) VALUES (?)');
            $st->bind_param('s', $nombre);
            $st->execute();
            $_SESSION['flash_ok'] = 'Carrera creada.';
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = 'No se pudo crear. ¿Duplicado?';
        }
        header('Location: index.php?seccion=sa');
        exit;
    }

    private static function eliminarCarrera() {
        session_start();
        if (empty($_SESSION['sa_ok'])) { $_SESSION['flash_err'] = 'Acceso SA requerido.'; header('Location: index.php?seccion=sa'); exit; }
        $conn = self::db();
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            $_SESSION['flash_err'] = 'Carrera inválida.';
            header('Location: index.php?seccion=sa');
            exit;
        }
        try {
            $ex = $conn->prepare('SELECT 1 FROM carreras WHERE nombre=?');
            $ex->bind_param('s', $nombre);
            $ex->execute();
            $r = $ex->get_result()->fetch_assoc();
            if (!$r) {
                $_SESSION['flash_err'] = 'No existe la carrera indicada.';
                header('Location: index.php?seccion=sa');
                exit;
            }

            $chkA = $conn->prepare('SELECT COUNT(*) AS cnt FROM alumnos WHERE carrera=?');
            $chkA->bind_param('s', $nombre);
            $chkA->execute();
            $ra = $chkA->get_result()->fetch_assoc();
            $alumCnt = (int)($ra['cnt'] ?? 0);
            if ($alumCnt > 0) {
                $_SESSION['flash_err'] = 'No se puede eliminar: hay alumnos en esta carrera. Cambia su carrera o elimínalos.';
                header('Location: index.php?seccion=sa');
                exit;
            }
            $conn->begin_transaction();
            $delRG = $conn->prepare('DELETE FROM registros_grupo WHERE carrera=?');
            $delRG->bind_param('s', $nombre);
            $delRG->execute();
            $delEA = $conn->prepare('DELETE FROM encuestas_alumno WHERE carrera=?');
            $delEA->bind_param('s', $nombre);
            $delEA->execute();
            $st = $conn->prepare('DELETE FROM carreras WHERE nombre=?');
            $st->bind_param('s', $nombre);
            $st->execute();
            $conn->commit();
            if ($st->affected_rows > 0) {
                $_SESSION['flash_ok'] = 'Carrera eliminada y asignaciones asociadas.';
            } else {
                $_SESSION['flash_err'] = 'No existe la carrera indicada.';
            }
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ex) {}
            $_SESSION['flash_err'] = 'No se puede eliminar. Verifica relaciones activas.';
        }
        header('Location: index.php?seccion=sa');
        exit;
    }

    private static function renombrarCarrera() {
        session_start();
        if (empty($_SESSION['sa_ok'])) { $_SESSION['flash_err'] = 'Acceso SA requerido.'; header('Location: index.php?seccion=sa'); exit; }
        $conn = self::db();
        $old = trim($_POST['old'] ?? '');
        $nuevo = trim($_POST['nuevo'] ?? '');
        if ($old === '' || $nuevo === '') {
            $_SESSION['flash_err'] = 'Indica el nombre actual y el nuevo nombre.';
            header('Location: index.php?seccion=sa');
            exit;
        }
        if ($old === $nuevo) {
            $_SESSION['flash_err'] = 'El nuevo nombre es igual al actual.';
            header('Location: index.php?seccion=sa');
            exit;
        }
        try {
            $chk = $conn->prepare('SELECT 1 FROM carreras WHERE nombre=?');
            $chk->bind_param('s', $nuevo);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            if ($exists) {
                $_SESSION['flash_err'] = 'Ya existe una carrera con ese nombre.';
                header('Location: index.php?seccion=sa');
                exit;
            }
        } catch (Throwable $e) { }

        try {
            $exOld = $conn->prepare('SELECT 1 FROM carreras WHERE nombre=?');
            $exOld->bind_param('s', $old);
            $exOld->execute();
            $oldExists = $exOld->get_result()->fetch_assoc();
            if (!$oldExists) {
                $_SESSION['flash_err'] = 'No existe la carrera indicada.';
                header('Location: index.php?seccion=sa');
                exit;
            }
            $up = $conn->prepare('UPDATE carreras SET nombre=? WHERE nombre=?');
            $up->bind_param('ss', $nuevo, $old);
            $up->execute();
            if ($up->affected_rows > 0) {
                $_SESSION['flash_ok'] = 'Carrera renombrada.';
                header('Location: index.php?seccion=sa');
                exit;
            } else {
                $_SESSION['flash_err'] = 'No se pudo renombrar. Verifica el nombre actual.';
                header('Location: index.php?seccion=sa');
                exit;
            }
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = 'Error al renombrar la carrera.';
            header('Location: index.php?seccion=sa');
            exit;
        }
        header('Location: index.php?seccion=sa');
        exit;
    }

    private static function registrarDocente() {
        session_start();
        $conn = self::db();
        $num = trim($_POST['numeroEmpleado'] ?? '');
        $nom = trim($_POST['nombreDocente'] ?? '');
        $pass = $_POST['password'] ?? '';
        $materias = (array)($_POST['materia'] ?? []);
        $semestres = (array)($_POST['semestre'] ?? []);
        $grupos = array_map(function($g){ return strtoupper(trim((string)$g)); }, (array)($_POST['grupo'] ?? []));
        $carrs = (array)($_POST['carrera'] ?? []);
        $materias = array_map('trim', $materias);
        $semestres = array_map('trim', $semestres);
        $carrs = array_map('trim', $carrs);
        if ($num === '' || $nom === '' || strlen($pass) < 4 || empty($materias) || empty($semestres) || empty($grupos) || empty($carrs)) {
            $_SESSION['flash_err'] = 'Completa todos los campos.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        if (!preg_match('/^\d+$/', $num)) {
            $_SESSION['flash_err'] = 'El número de empleado debe contener solo números.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        $now = time() * 1000;
        try {
            $sd = $conn->prepare('INSERT INTO docentes (numeroEmpleado, nombre, password, createdAt) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), password=VALUES(password)');
            $sd->bind_param('sssi', $num, $nom, $pass, $now);
            $sd->execute();
        } catch (Throwable $e) { }
        $n = min(count($materias), count($semestres), count($grupos), count($carrs));
        if ($n <= 0) {
            $_SESSION['flash_err'] = 'Agrega al menos una asignación.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        $ins = $conn->prepare('INSERT INTO registros_grupo (numeroEmpleado, nombreDocente, carrera, materia, semestre, grupo, password) VALUES (?,?,?,?,?,?,?)');
        $okCount = 0; $dupCount = 0; $err = false;
        for ($i = 0; $i < $n; $i++) {
            $mat = $materias[$i];
            $sem = $semestres[$i];
            $gru = $grupos[$i];
            $car = $carrs[$i] ?? '';
            if ($mat === '' || $sem === '' || $gru === '' || $car === '') { continue; }
            try {
                $ins->bind_param('sssssss', $num, $nom, $car, $mat, $sem, $gru, $pass);
                $ins->execute();
                $okCount++;
            } catch (Throwable $e) {
                $dupCount++;
            }
        }
        if ($okCount > 0) {
            $_SESSION['flash_ok'] = "Docente registrado: $okCount asignaciones" . ($dupCount > 0 ? ", $dupCount duplicadas" : "");
        } else {
            $_SESSION['flash_err'] = 'No se pudo registrar. Verifique duplicados o datos.';
        }
        header('Location: index.php?seccion=dpa');
        exit;
    }

    private static function agregarAsignacionesDocente() {
        session_start();
        $conn = self::db();
        $num = trim($_POST['numeroEmpleado'] ?? '');
        $nom = trim($_POST['nombreDocente'] ?? '');
        $pass = $_POST['password'] ?? '';
        $materias = (array)($_POST['materia'] ?? []);
        $semestres = (array)($_POST['semestre'] ?? []);
        $grupos = array_map(function($g){ return strtoupper(trim((string)$g)); }, (array)($_POST['grupo'] ?? []));
        $carrs = (array)($_POST['carrera'] ?? []);
        $materias = array_map('trim', $materias);
        $semestres = array_map('trim', $semestres);
        $carrs = array_map('trim', $carrs);
        if ($num === '' || !preg_match('/^\d+$/', $num) || $nom === '' || empty($materias) || empty($semestres) || empty($grupos) || empty($carrs)) {
            $_SESSION['flash_err'] = 'Completa los datos y al menos una asignación.';
            header('Location: index.php?seccion=dpa&add_num=' . urlencode($num) . '&add_nom=' . urlencode($nom));
            exit;
        }
        $dbName = '';
        try {
            $resDb = $conn->query('SELECT DATABASE() AS db');
            if ($resDb) { $rdb = $resDb->fetch_assoc(); $dbName = (string)($rdb['db'] ?? ''); }
        } catch (Throwable $e) { }
        $hasDocentes = false;
        $hasPasswordRG = false;
        if ($dbName !== '') {
            try {
                $std = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME="docentes"');
                $std->bind_param('s', $dbName);
                $std->execute();
                $rd = $std->get_result()->fetch_assoc();
                $hasDocentes = ((int)($rd['cnt'] ?? 0)) > 0;
            } catch (Throwable $e) { }
            try {
                $stp = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME="registros_grupo" AND COLUMN_NAME="password"');
                $stp->bind_param('s', $dbName);
                $stp->execute();
                $rp = $stp->get_result()->fetch_assoc();
                $hasPasswordRG = ((int)($rp['cnt'] ?? 0)) > 0;
            } catch (Throwable $e) { }
        }
        if ($hasDocentes) {
            $now = time() * 1000;
            try {
                $sd = $conn->prepare('INSERT INTO docentes (numeroEmpleado, nombre, password, createdAt) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), password=IF(VALUES(password)="", password, VALUES(password))');
                $sd->bind_param('sssi', $num, $nom, $pass, $now);
                $sd->execute();
            } catch (Throwable $e) { }
        }
        if (!$hasDocentes && $hasPasswordRG && ($pass === '' || $pass === null)) {
            try {
                $sp = $conn->prepare('SELECT password FROM registros_grupo WHERE numeroEmpleado=? LIMIT 1');
                $sp->bind_param('s', $num);
                $sp->execute();
                $rp = $sp->get_result()->fetch_assoc();
                $pass = (string)($rp['password'] ?? '');
            } catch (Throwable $e) { }
        }
        if (!$hasDocentes && $hasPasswordRG && $pass === '') {
            $_SESSION['flash_err'] = 'Ingresa la contraseña del docente.';
            header('Location: index.php?seccion=dpa&add_num=' . urlencode($num) . '&add_nom=' . urlencode($nom));
            exit;
        }
        $n = min(count($materias), count($semestres), count($grupos), count($carrs));
        if ($n <= 0) {
            $_SESSION['flash_err'] = 'Agrega al menos una asignación.';
            header('Location: index.php?seccion=dpa&add_num=' . urlencode($num) . '&add_nom=' . urlencode($nom));
            exit;
        }
        $ins = $conn->prepare('INSERT INTO registros_grupo (numeroEmpleado, nombreDocente, carrera, materia, semestre, grupo, password) VALUES (?,?,?,?,?,?,?)');
        $okCount = 0; $dupCount = 0;
        for ($i = 0; $i < $n; $i++) {
            $mat = $materias[$i];
            $sem = $semestres[$i];
            $gru = $grupos[$i];
            $car = $carrs[$i] ?? '';
            if ($mat === '' || $sem === '' || $gru === '' || $car === '') { continue; }
            try {
                $ins->bind_param('sssssss', $num, $nom, $car, $mat, $sem, $gru, $pass);
                $ins->execute();
                $okCount++;
            } catch (Throwable $e) {
                $dupCount++;
            }
        }
        if ($okCount > 0) {
            $_SESSION['flash_ok'] = "Asignaciones agregadas: $okCount" . ($dupCount > 0 ? ", $dupCount duplicadas" : "");
            header('Location: index.php?seccion=dpa');
        } else {
            $_SESSION['flash_err'] = 'No se pudieron agregar asignaciones.';
            header('Location: index.php?seccion=dpa&add_num=' . urlencode($num) . '&add_nom=' . urlencode($nom));
        }
        exit;
    }

    private static function loginDocente() {
        session_start();
        $conn = self::db();
        $num = trim($_POST['numeroEmpleado'] ?? '');
        $pass = $_POST['password'] ?? '';
        if ($num === '' || $pass === '') {
            $_SESSION['flash_err'] = 'Ingresa tu número de empleado y contraseña.';
            header('Location: index.php?seccion=docente');
            exit;
        }
        if (!preg_match('/^\d+$/', $num)) {
            $_SESSION['flash_err'] = 'El número de empleado debe contener solo números.';
            header('Location: index.php?seccion=docente');
            exit;
        }
        $dbName = '';
        try {
            $resDb = $conn->query('SELECT DATABASE() AS db');
            if ($resDb) { $rdb = $resDb->fetch_assoc(); $dbName = (string)($rdb['db'] ?? ''); }
        } catch (Throwable $e) { }
        $hasDocentes = false;
        $hasPasswordRG = false;
        if ($dbName !== '') {
            try {
                $std = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME="docentes"');
                $std->bind_param('s', $dbName);
                $std->execute();
                $rd = $std->get_result()->fetch_assoc();
                $hasDocentes = ((int)($rd['cnt'] ?? 0)) > 0;
            } catch (Throwable $e) { }
            try {
                $stp = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME="registros_grupo" AND COLUMN_NAME="password"');
                $stp->bind_param('s', $dbName);
                $stp->execute();
                $rp = $stp->get_result()->fetch_assoc();
                $hasPasswordRG = ((int)($rp['cnt'] ?? 0)) > 0;
            } catch (Throwable $e) { }
        }
        if ($hasDocentes) {
            $stmt = $conn->prepare('SELECT numeroEmpleado, nombre FROM docentes WHERE numeroEmpleado = ? AND password = ? LIMIT 1');
            $stmt->bind_param('ss', $num, $pass);
        } else if ($hasPasswordRG) {
            $stmt = $conn->prepare('SELECT numeroEmpleado, nombreDocente FROM registros_grupo WHERE numeroEmpleado = ? AND password = ? LIMIT 1');
            $stmt->bind_param('ss', $num, $pass);
        } else {
            $stmt = $conn->prepare('SELECT numeroEmpleado, nombreDocente FROM registros_grupo WHERE numeroEmpleado = ? LIMIT 1');
            $stmt->bind_param('s', $num);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) {
            $_SESSION['flash_err'] = 'Número de empleado o contraseña incorrectos.';
            header('Location: index.php?seccion=docente');
            exit;
        }
        $nombre = $row['nombreDocente'] ?? ($row['nombre'] ?? '');
        $_SESSION['docente'] = ['numeroEmpleado' => $row['numeroEmpleado'], 'nombreDocente' => $nombre];
        $_SESSION['flash_ok'] = 'Sesión iniciada.';
        header('Location: index.php?seccion=docente');
        exit;
    }

    private static function editarDocente() {
        session_start();
        $conn = self::db();
        $id = (int)($_POST['id'] ?? 0);
        $num = trim($_POST['numeroEmpleado'] ?? '');
        $nom = trim($_POST['nombreDocente'] ?? '');
        $car = trim($_POST['carrera'] ?? '');
        $mat = trim($_POST['materia'] ?? '');
        $sem = trim($_POST['semestre'] ?? '');
        $gru = trim(strtoupper($_POST['grupo'] ?? ''));
        if ($id <= 0 || $num === '' || $nom === '' || $car === '' || $mat === '' || $sem === '' || $gru === '') {
            $_SESSION['flash_err'] = 'Datos inválidos para edición.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        if (!preg_match('/^\d+$/', $num)) {
            $_SESSION['flash_err'] = 'El número de empleado debe contener solo números.';
            header('Location: index.php?seccion=dpa&edit=' . $id);
            exit;
        }
        // Evitar duplicados al editar (mismo número en otro registro)
        $chk2 = $conn->prepare('SELECT id FROM registros_grupo WHERE numeroEmpleado = ? AND id <> ? LIMIT 1');
        $chk2->bind_param('si', $num, $id);
        $chk2->execute();
        $chk2->store_result();
        if ($chk2->num_rows > 0) {
            $_SESSION['flash_err'] = 'El número de empleado ya está registrado en otro docente.';
            header('Location: index.php?seccion=dpa&edit=' . $id);
            exit;
        }
        $stmt = $conn->prepare('UPDATE registros_grupo SET numeroEmpleado=?, nombreDocente=?, carrera=?, materia=?, semestre=?, grupo=? WHERE id=?');
        try {
            $stmt->bind_param('ssssssi', $num, $nom, $car, $mat, $sem, $gru, $id);
            $stmt->execute();
            $_SESSION['flash_ok'] = 'Docente actualizado correctamente.';
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = 'No se pudo actualizar. ¿Duplicado o error?';
        }
        header('Location: index.php?seccion=dpa');
        exit;
    }

    private static function eliminarDocente() {
        session_start();
        $conn = self::db();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_err'] = 'ID inválido para eliminación.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM registros_grupo WHERE id=?');
        try {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $_SESSION['flash_ok'] = 'Docente eliminado correctamente.';
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = 'No se pudo eliminar. Error en la operación.';
        }
        header('Location: index.php?seccion=dpa');
        exit;
    }

    private static function eliminarDocenteTotal() {
        session_start();
        $conn = self::db();
        $num = trim($_POST['numeroEmpleado'] ?? '');
        if ($num === '' || !preg_match('/^\d+$/', $num)) {
            $_SESSION['flash_err'] = 'Número de empleado inválido.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        try {
            $conn->begin_transaction();
            $delRegs = $conn->prepare('DELETE FROM registros_grupo WHERE numeroEmpleado=?');
            $delRegs->bind_param('s', $num);
            $delRegs->execute();
            $delDoc = $conn->prepare('DELETE FROM docentes WHERE numeroEmpleado=?');
            $delDoc->bind_param('s', $num);
            $delDoc->execute();
            $conn->commit();
            $_SESSION['flash_ok'] = 'Docente eliminado por completo.';
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ex) {}
            $_SESSION['flash_err'] = 'No se pudo eliminar al docente.';
        }
        header('Location: index.php?seccion=dpa');
        exit;
    }

    private static function db() {
        $conn = (new Conexion())->conectar();
        self::ensureSchema($conn);
        return $conn;
    }

    private static function ensureSchema(mysqli $conn) {
        $conn->query("CREATE TABLE IF NOT EXISTS carreras (
            nombre VARCHAR(128) PRIMARY KEY
        )");
        $conn->query("CREATE TABLE IF NOT EXISTS docentes (
            numeroEmpleado VARCHAR(32) PRIMARY KEY,
            nombre VARCHAR(128) NOT NULL,
            password VARCHAR(128) NOT NULL,
            createdAt BIGINT NOT NULL
        )");
        $conn->query("CREATE TABLE IF NOT EXISTS alumnos (
            matricula VARCHAR(32) PRIMARY KEY,
            nombre VARCHAR(128) NOT NULL,
            carrera VARCHAR(128) NOT NULL,
            semestre VARCHAR(32) NOT NULL,
            grupo VARCHAR(32) NOT NULL,
            password VARCHAR(128) NOT NULL,
            createdAt BIGINT NOT NULL
        )");
        try { $conn->query("ALTER TABLE alumnos ADD COLUMN carrera VARCHAR(128) NOT NULL"); } catch (Throwable $e) { /* ya existe */ }
        try { $conn->query("ALTER TABLE alumnos ADD INDEX idx_alumnos_sem_gru (semestre, grupo)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE alumnos ADD CONSTRAINT fk_alumnos_carrera FOREIGN KEY (carrera) REFERENCES carreras(nombre) ON UPDATE CASCADE ON DELETE RESTRICT"); } catch (Throwable $e) { }
        $conn->query("CREATE TABLE IF NOT EXISTS encuestas_alumno (
            id INT AUTO_INCREMENT PRIMARY KEY,
            matricula VARCHAR(32) NOT NULL,
            carrera VARCHAR(128) NOT NULL,
            semestre VARCHAR(32) NOT NULL,
            grupo VARCHAR(32) NOT NULL,
            timestamp BIGINT NOT NULL
        )");
        // Intento añadir columna carrera si la tabla ya existía sin ella
        try { $conn->query("ALTER TABLE encuestas_alumno ADD COLUMN carrera VARCHAR(128) NOT NULL"); } catch (Throwable $e) { /* ya existe */ }
        try { $conn->query("ALTER TABLE encuestas_alumno ADD UNIQUE KEY uniq_encuesta_alumno (matricula, semestre, grupo)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE encuestas_alumno ADD CONSTRAINT fk_encuesta_alumno_matricula FOREIGN KEY (matricula) REFERENCES alumnos(matricula) ON DELETE CASCADE ON UPDATE CASCADE"); } catch (Throwable $e) { }
        $conn->query("CREATE TABLE IF NOT EXISTS registros_grupo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numeroEmpleado VARCHAR(32) NOT NULL,
            nombreDocente VARCHAR(128) NOT NULL,
            carrera VARCHAR(128) NOT NULL,
            materia VARCHAR(128) NOT NULL,
            semestre VARCHAR(32) NOT NULL,
            grupo VARCHAR(32) NOT NULL,
            password VARCHAR(128) NOT NULL
        )");
        try { $conn->query("ALTER TABLE registros_grupo MODIFY nombreDocente VARCHAR(128) NULL"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE registros_grupo MODIFY password VARCHAR(128) NULL"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE registros_grupo ADD COLUMN carrera VARCHAR(128) NOT NULL"); } catch (Throwable $e) { /* ya existe */ }
        try { $conn->query("ALTER TABLE registros_grupo ADD COLUMN password VARCHAR(128) NOT NULL"); } catch (Throwable $e) { /* ya existe */ }
        try { $conn->query("ALTER TABLE registros_grupo ADD UNIQUE KEY uniq_docente_sem_gru (numeroEmpleado, semestre, grupo)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE registros_grupo ADD INDEX idx_registro_docente (numeroEmpleado)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE registros_grupo ADD INDEX idx_registro_sem_gru (semestre, grupo)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE registros_grupo ADD CONSTRAINT fk_registros_carrera FOREIGN KEY (carrera) REFERENCES carreras(nombre) ON UPDATE CASCADE ON DELETE RESTRICT"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE registros_grupo ADD CONSTRAINT fk_registros_docente FOREIGN KEY (numeroEmpleado) REFERENCES docentes(numeroEmpleado) ON UPDATE CASCADE ON DELETE RESTRICT"); } catch (Throwable $e) { }
        $conn->query("CREATE TABLE IF NOT EXISTS respuestas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numeroEmpleado VARCHAR(32) NOT NULL,
            semestre VARCHAR(32) NOT NULL,
            grupo VARCHAR(32) NOT NULL,
            preguntaIndex INT NOT NULL,
            rating INT NOT NULL,
            timestamp BIGINT NOT NULL
        )");
        try { $conn->query("ALTER TABLE respuestas ADD INDEX idx_resp_docente (numeroEmpleado)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE respuestas ADD INDEX idx_resp_sem_gru (semestre, grupo)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE respuestas ADD INDEX idx_resp_docente_sem_gru (numeroEmpleado, semestre, grupo)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE respuestas ADD INDEX idx_resp_pregunta (preguntaIndex)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE respuestas ADD CONSTRAINT fk_respuestas_registro FOREIGN KEY (numeroEmpleado, semestre, grupo) REFERENCES registros_grupo(numeroEmpleado, semestre, grupo) ON DELETE CASCADE ON UPDATE CASCADE"); } catch (Throwable $e) { }
        $conn->query("CREATE TABLE IF NOT EXISTS opiniones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numeroEmpleado VARCHAR(32) NOT NULL,
            semestre VARCHAR(32) NOT NULL,
            grupo VARCHAR(32) NOT NULL,
            semestreGrupo VARCHAR(32) NOT NULL,
            comentario TEXT NOT NULL,
            timestamp BIGINT NOT NULL
        )");
        try { $conn->query("ALTER TABLE opiniones ADD COLUMN semestre VARCHAR(32) NOT NULL"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE opiniones ADD COLUMN grupo VARCHAR(32) NOT NULL"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE opiniones ADD INDEX idx_op_docente (numeroEmpleado)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE opiniones ADD INDEX idx_op_sem_gru (semestreGrupo)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE opiniones ADD INDEX idx_op_semestre_grupo (semestre, grupo)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE opiniones ADD CONSTRAINT fk_opiniones_registro FOREIGN KEY (numeroEmpleado, semestre, grupo) REFERENCES registros_grupo(numeroEmpleado, semestre, grupo) ON DELETE CASCADE ON UPDATE CASCADE"); } catch (Throwable $e) { }

        // Asignaciones normalizadas (docente + cuatrimestre + grupo + carrera)
        $conn->query("CREATE TABLE IF NOT EXISTS asignaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numeroEmpleado VARCHAR(32) NOT NULL,
            semestre VARCHAR(32) NOT NULL,
            grupo VARCHAR(32) NOT NULL,
            carrera VARCHAR(128) NOT NULL,
            UNIQUE KEY uniq_asignacion (numeroEmpleado, semestre, grupo, carrera),
            CONSTRAINT fk_asig_docente FOREIGN KEY (numeroEmpleado) REFERENCES docentes(numeroEmpleado) ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT fk_asig_carrera FOREIGN KEY (carrera) REFERENCES carreras(nombre) ON UPDATE CASCADE ON DELETE RESTRICT
        )");
        // Poblar asignaciones desde registros_grupo si faltan
        try {
            $conn->query("INSERT INTO asignaciones (numeroEmpleado, semestre, grupo, carrera)
                SELECT rg.numeroEmpleado, rg.semestre, rg.grupo, rg.carrera
                FROM registros_grupo rg
                LEFT JOIN asignaciones a ON a.numeroEmpleado=rg.numeroEmpleado AND a.semestre=rg.semestre AND a.grupo=rg.grupo AND a.carrera=rg.carrera
                WHERE a.id IS NULL
                GROUP BY rg.numeroEmpleado, rg.semestre, rg.grupo, rg.carrera");
        } catch (Throwable $e) { }
        // Añadir columna de referencia en respuestas/opiniones
        try { $conn->query("ALTER TABLE respuestas ADD COLUMN asignacion_id INT NULL"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE respuestas ADD INDEX idx_resp_asignacion (asignacion_id)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE respuestas ADD CONSTRAINT fk_respuestas_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones(id) ON DELETE CASCADE ON UPDATE CASCADE"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE opiniones ADD COLUMN asignacion_id INT NULL"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE opiniones ADD INDEX idx_op_asignacion (asignacion_id)"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE opiniones ADD CONSTRAINT fk_opiniones_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones(id) ON DELETE CASCADE ON UPDATE CASCADE"); } catch (Throwable $e) { }

        // Usuarios DPA (administración) y SA (Secretario Académico)
        $conn->query("CREATE TABLE IF NOT EXISTS dpa_usuarios (
            usuario VARCHAR(64) PRIMARY KEY,
            password VARCHAR(128) NOT NULL,
            carrera VARCHAR(128) NULL
        )");
        try { $conn->query("ALTER TABLE dpa_Usuarios ADD COLUMN carrera VARCHAR(128) NULL"); } catch (Throwable $e) { }
        try { $conn->query("ALTER TABLE dpa_usuarios ADD CONSTRAINT fk_dpa_carrera FOREIGN KEY (carrera) REFERENCES carreras(nombre) ON UPDATE CASCADE ON DELETE SET NULL"); } catch (Throwable $e) { }
        $conn->query("INSERT IGNORE INTO dpa_usuarios (usuario, password) VALUES ('dpa', 'dpa123')");
        $conn->query("CREATE TABLE IF NOT EXISTS sa_usuarios (
            usuario VARCHAR(64) PRIMARY KEY,
            password VARCHAR(128) NOT NULL
        )");
        $conn->query("INSERT IGNORE INTO sa_usuarios (usuario, password) VALUES ('sa', 'sa123')");
    }

    private static function loginDpa() {
        session_start();
        $conn = self::db();
        $usuario = trim($_POST['usuario'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($usuario === '' || $password === '') {
            $_SESSION['flash_err'] = 'Ingresa usuario y contraseña.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        $stmt = $conn->prepare('SELECT usuario, carrera FROM dpa_usuarios WHERE usuario = ? AND password = ? LIMIT 1');
        $stmt->bind_param('ss', $usuario, $password);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) {
            $_SESSION['flash_err'] = 'Usuario o contraseña incorrectos.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        $_SESSION['dpa_ok'] = true;
        $_SESSION['dpa_user'] = $usuario;
        $_SESSION['dpa_carrera'] = $row['carrera'] ?? null;
        $_SESSION['flash_ok'] = 'Sesión DPA iniciada.';
        header('Location: index.php?seccion=dpa');
        exit;
    }

    private static function loginSa() {
        session_start();
        $conn = self::db();
        $usuario = trim($_POST['usuario'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($usuario === '' || $password === '') {
            $_SESSION['flash_err'] = 'Ingresa usuario y contraseña.';
            header('Location: index.php?seccion=sa');
            exit;
        }
        $stmt = $conn->prepare('SELECT usuario FROM sa_usuarios WHERE usuario = ? AND password = ? LIMIT 1');
        $stmt->bind_param('ss', $usuario, $password);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) {
            $_SESSION['flash_err'] = 'Usuario o contraseña incorrectos.';
            header('Location: index.php?seccion=sa');
            exit;
        }
        $_SESSION['sa_ok'] = true;
        $_SESSION['sa_user'] = $usuario;
        $_SESSION['flash_ok'] = 'Sesión SA iniciada.';
        header('Location: index.php?seccion=sa');
        exit;
    }

    private static function logoutSa() {
        session_start();
        unset($_SESSION['sa_ok'], $_SESSION['sa_user']);
        $_SESSION['flash_ok'] = 'Sesión SA cerrada.';
        header('Location: index.php?seccion=sa');
        exit;
    }

    private static function crearDpa() {
        session_start();
        if (empty($_SESSION['sa_ok'])) { $_SESSION['flash_err'] = 'Acceso SA requerido.'; header('Location: index.php?seccion=sa'); exit; }
        $conn = self::db();
        $usuario = trim($_POST['usuario'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $carrera = trim($_POST['carrera'] ?? '');
        if ($usuario === '' || $password === '' || $carrera === '') {
            $_SESSION['flash_err'] = 'Completa usuario, contraseña y carrera.';
            header('Location: index.php?seccion=sa');
            exit;
        }
        try {
            $chk = $conn->prepare('SELECT 1 FROM carreras WHERE nombre=?');
            $chk->bind_param('s', $carrera);
            $chk->execute();
            $ex = $chk->get_result()->fetch_assoc();
            if (!$ex) { $_SESSION['flash_err'] = 'Carrera no válida.'; header('Location: index.php?seccion=sa'); exit; }
        } catch (Throwable $e) { }
        try {
            $st = $conn->prepare('INSERT INTO dpa_usuarios (usuario, password, carrera) VALUES (?,?,?) ON DUPLICATE KEY UPDATE password=VALUES(password), carrera=VALUES(carrera)');
            $st->bind_param('sss', $usuario, $password, $carrera);
            $st->execute();
            $_SESSION['flash_ok'] = 'DPA creado/actualizado.';
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = 'No se pudo crear el DPA.';
        }
        header('Location: index.php?seccion=sa');
        exit;
    }

    private static function eliminarDpa() {
        session_start();
        if (empty($_SESSION['sa_ok'])) { $_SESSION['flash_err'] = 'Acceso SA requerido.'; header('Location: index.php?seccion=sa'); exit; }
        $conn = self::db();
        $usuario = trim($_POST['usuario'] ?? '');
        if ($usuario === '') { $_SESSION['flash_err'] = 'Usuario inválido.'; header('Location: index.php?seccion=sa'); exit; }
        try {
            $st = $conn->prepare('DELETE FROM dpa_usuarios WHERE usuario=?');
            $st->bind_param('s', $usuario);
            $st->execute();
            $_SESSION['flash_ok'] = 'DPA eliminado.';
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = 'No se pudo eliminar el DPA.';
        }
        header('Location: index.php?seccion=sa');
        exit;
    }

    private static function logoutDpa() {
        session_start();
        unset($_SESSION['dpa_ok']);
        unset($_SESSION['dpa_user']);
        $_SESSION['flash_ok'] = 'Sesión DPA cerrada.';
        header('Location: index.php?seccion=dpa');
        exit;
    }

    private static function registroAlumno() {
        session_start();
        $conn = self::db();
        $mat = trim(strtoupper($_POST['matricula'] ?? ''));
        $nombre = trim($_POST['nombre'] ?? '');
        $carrera = trim($_POST['carrera'] ?? '');
        $sem = trim($_POST['semestre'] ?? '');
        $gru = trim(strtoupper($_POST['grupo'] ?? ''));
        $pass = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if ($mat === '' || $nombre === '' || $carrera === '' || $sem === '' || $gru === '' || strlen($pass) < 4 || $pass !== $pass2) {
            $_SESSION['flash_err'] = 'Datos inválidos o incompletos.';
            header('Location: index.php?seccion=registro_alumno');
            exit;
        }
        $stmt = $conn->prepare('SELECT matricula FROM alumnos WHERE matricula = ?');
        $stmt->bind_param('s', $mat);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['flash_err'] = 'La matrícula ya está registrada.';
            header('Location: index.php?seccion=registro_alumno');
            exit;
        }
        $stmt = $conn->prepare('INSERT INTO alumnos (matricula, nombre, carrera, semestre, grupo, password, createdAt) VALUES (?,?,?,?,?,?,?)');
        $createdAt = time() * 1000;
        $stmt->bind_param('ssssssi', $mat, $nombre, $carrera, $sem, $gru, $pass, $createdAt);
        $stmt->execute();
        $_SESSION['flash_ok'] = 'Cuenta creada correctamente. Ahora inicia sesión.';
        header('Location: index.php?seccion=login');
        exit;
    }

    private static function loginAlumno() {
        session_start();
        $conn = self::db();
        $mat = trim(strtoupper($_POST['matricula'] ?? ''));
        $pass = $_POST['password'] ?? '';
        $sem = trim($_POST['semestre'] ?? '');
        $gru = trim(strtoupper($_POST['grupo'] ?? ''));
        $stmt = $conn->prepare('SELECT matricula, nombre, carrera, semestre, grupo FROM alumnos WHERE matricula = ? AND password = ?');
        $stmt->bind_param('ss', $mat, $pass);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) {
            $_SESSION['flash_err'] = 'Matrícula o contraseña incorrectos.';
            header('Location: index.php?seccion=login');
            exit;
        }
        if ($sem === '' || $gru === '') {
            $_SESSION['flash_err'] = 'Ingresa cuatrimestre y grupo para continuar.';
            header('Location: index.php?seccion=login');
            exit;
        }
        $semDb = trim((string)$row['semestre']);
        $gruDb = trim(strtoupper((string)$row['grupo']));
        if ($sem !== $semDb || $gru !== $gruDb) {
            $_SESSION['flash_err'] = 'Cuatrimestre o grupo no coincide con tu perfil. Actualiza tu perfil o usa los datos actuales.';
            header('Location: index.php?seccion=login');
            exit;
        }
        $_SESSION['alumno'] = [
            'matricula' => $row['matricula'],
            'nombre' => $row['nombre'],
            'carrera' => $row['carrera'],
            'semestre' => $semDb,
            'grupo' => $gruDb,
        ];
        header('Location: index.php?seccion=evaluacion');
        exit;
    }

    private static function guardarEvaluacion() {
        session_start();
        if (empty($_SESSION['alumno'])) {
            header('Location: index.php?seccion=login');
            exit;
        }
        $conn = self::db();
        $al = $_SESSION['alumno'];
        $sem = $al['semestre'];
        $gru = $al['grupo'];
        $now = time() * 1000;
        try { $conn->query('CREATE TABLE IF NOT EXISTS config_app (clave VARCHAR(64) PRIMARY KEY, valor VARCHAR(256) NOT NULL)'); } catch (Throwable $e) { }
        $stCfg = $conn->prepare('SELECT valor FROM config_app WHERE clave="estado_evaluacion" LIMIT 1');
        $stCfg->execute();
        $rCfg = $stCfg->get_result()->fetch_assoc();
        $habilitada = !$rCfg ? true : ((string)$rCfg['valor'] === 'on');
        if (!$habilitada) {
            $_SESSION['flash_err'] = 'La evaluación está desactivada por DPA.';
            header('Location: index.php?seccion=evaluacion');
            exit;
        }
        $chk2 = $conn->prepare('SELECT id FROM encuestas_alumno WHERE matricula=? AND semestre=? AND grupo=? LIMIT 1');
        $chk2->bind_param('sss', $al['matricula'], $sem, $gru);
        $chk2->execute();
        $ya = $chk2->get_result()->fetch_assoc();
        if ($ya) {
            $_SESSION['flash_err'] = 'Ya has realizado esta evaluación.';
            header('Location: index.php?seccion=evaluacion');
            exit;
        }
        $ratings = $_POST['ratings'] ?? [];
        if (empty($ratings) || !is_array($ratings)) {
            $_SESSION['flash_err'] = 'Responde las preguntas para al menos un docente.';
            header('Location: index.php?seccion=evaluacion');
            exit;
        }
        $timestamp = $now;
        $stmt = $conn->prepare('INSERT INTO respuestas (asignacion_id, numeroEmpleado, semestre, grupo, preguntaIndex, rating, timestamp) VALUES (?,?,?,?,?,?,?)');
        $selAsig = $conn->prepare('SELECT id FROM asignaciones WHERE numeroEmpleado=? AND semestre=? AND grupo=? AND carrera=? LIMIT 1');
        $insAsig = $conn->prepare('INSERT INTO asignaciones (numeroEmpleado, semestre, grupo, carrera) VALUES (?,?,?,?)');
        foreach ($ratings as $numEmpleado => $porPregunta) {
            $numEmpleado = trim((string)$numEmpleado);
            if ($numEmpleado === '' || !is_array($porPregunta)) continue;
            $selAsig->bind_param('ssss', $numEmpleado, $sem, $gru, $al['carrera']);
            $selAsig->execute();
            $rowA = $selAsig->get_result()->fetch_assoc();
            $asigId = $rowA ? (int)$rowA['id'] : 0;
            if ($asigId === 0) {
                try {
                    $insAsig->bind_param('ssss', $numEmpleado, $sem, $gru, $al['carrera']);
                    $insAsig->execute();
                    $asigId = $conn->insert_id;
                } catch (Throwable $e) { /* si falla, continuamos sin asignacion_id */ }
            }
            foreach ($porPregunta as $pi => $r) {
                $ri = max(1, min(5, (int)$r));
                $pi = (int)$pi;
                $stmt->bind_param('isssiii', $asigId, $numEmpleado, $sem, $gru, $pi, $ri, $timestamp);
                $stmt->execute();
            }
        }
        $comentarios = $_POST['comentarios'] ?? [];
        if (!empty($comentarios) && is_array($comentarios)) {
            $semGrupo = $sem . ' ' . $gru;
            $stmtOp = $conn->prepare('INSERT INTO opiniones (asignacion_id, numeroEmpleado, semestre, grupo, semestreGrupo, comentario, timestamp) VALUES (?,?,?,?,?,?,?)');
            foreach ($comentarios as $numEmpleado => $texto) {
                $numEmpleado = trim((string)$numEmpleado);
                $texto = trim((string)$texto);
                if ($numEmpleado === '' || $texto === '') continue;
                $asigId = 0;
                $selAsig->bind_param('ssss', $numEmpleado, $sem, $gru, $al['carrera']);
                $selAsig->execute();
                $rowA = $selAsig->get_result()->fetch_assoc();
                $asigId = $rowA ? (int)$rowA['id'] : 0;
                if ($asigId === 0) {
                    try {
                        $insAsig->bind_param('ssss', $numEmpleado, $sem, $gru, $al['carrera']);
                        $insAsig->execute();
                        $asigId = $conn->insert_id;
                    } catch (Throwable $e) { }
                }
                $stmtOp->bind_param('isssssi', $asigId, $numEmpleado, $sem, $gru, $semGrupo, $texto, $timestamp);
                try { $stmtOp->execute(); } catch (Throwable $e) { /* ignore individual failures */ }
            }
        }
        $stmt3 = $conn->prepare('INSERT INTO encuestas_alumno (matricula, carrera, semestre, grupo, timestamp) VALUES (?,?,?,?,?)');
        $stmt3->bind_param('ssssi', $al['matricula'], $al['carrera'], $sem, $gru, $timestamp);
        $stmt3->execute();
        $_SESSION['flash_ok'] = '¡Gracias! Tus respuestas se han guardado.';
        header('Location: index.php?seccion=finalizacion');
        exit;
    }

    private static function toggleEvaluacion() {
        session_start();
        if (empty($_SESSION['dpa_ok'])) {
            $_SESSION['flash_err'] = 'Acceso DPA requerido.';
            header('Location: index.php?seccion=dpa');
            exit;
        }
        $conn = self::db();
        $valor = isset($_POST['valor']) && $_POST['valor'] === 'off' ? 'off' : 'on';
        try { $conn->query('CREATE TABLE IF NOT EXISTS config_app (clave VARCHAR(64) PRIMARY KEY, valor VARCHAR(256) NOT NULL)'); } catch (Throwable $e) { }
        $stmt = $conn->prepare('INSERT INTO config_app (clave, valor) VALUES ("estado_evaluacion", ?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
        $stmt->bind_param('s', $valor);
        $stmt->execute();
        if ($valor === 'off') {
            try { $conn->query('DELETE FROM respuestas'); } catch (Throwable $e) { }
            try { $conn->query('DELETE FROM opiniones'); } catch (Throwable $e) { }
        }
        $_SESSION['flash_ok'] = 'Estado de evaluación actualizado: ' . ($valor === 'on' ? 'activada' : 'desactivada') . ($valor === 'off' ? ' (promedios reiniciados)' : '');
        header('Location: index.php?seccion=dpa');
        exit;
    }

    private static function eliminarCarrerasTodas() {
        session_start();
        if (empty($_SESSION['sa_ok'])) { $_SESSION['flash_err'] = 'Acceso SA requerido.'; header('Location: index.php?seccion=sa'); exit; }
        $conn = self::db();
        try {
            $ca = $conn->query('SELECT COUNT(*) AS cnt FROM alumnos');
            $ra = $ca ? $ca->fetch_assoc() : ['cnt' => 0];
            if (((int)($ra['cnt'] ?? 0)) > 0) {
                $_SESSION['flash_err'] = 'No se pueden borrar todas las carreras: hay alumnos registrados. Elimina o reasigna primero.';
                header('Location: index.php?seccion=sa');
                exit;
            }
            $conn->begin_transaction();
            $conn->query('DELETE FROM registros_grupo');
            $conn->query('DELETE FROM encuestas_alumno');
            $conn->query('DELETE FROM carreras');
            $conn->commit();
            $_SESSION['flash_ok'] = 'Todas las carreras han sido borradas.';
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ex) {}
            $_SESSION['flash_err'] = 'No se pudieron borrar todas las carreras.';
        }
        header('Location: index.php?seccion=sa');
        exit;
    }

    private static function actualizarSeccion() {
        session_start();
        if (empty($_SESSION['alumno'])) {
            header('Location: index.php?seccion=login');
            exit;
        }
        $conn = self::db();
        $sem = trim($_POST['semestre'] ?? '');
        if ($sem === '') {
            $_SESSION['flash_err'] = 'El cuatrimestre no puede estar vacío.';
            header('Location: index.php?seccion=perfil');
            exit;
        }
        $mat = $_SESSION['alumno']['matricula'];
        $stmt = $conn->prepare('UPDATE alumnos SET semestre=? WHERE matricula=?');
        try {
            $stmt->bind_param('ss', $sem, $mat);
            $stmt->execute();
            $_SESSION['alumno']['semestre'] = $sem;
            $_SESSION['flash_ok'] = 'Cuatrimestre actualizado correctamente.';
        } catch (Throwable $e) {
            $_SESSION['flash_err'] = 'No se pudo actualizar el cuatrimestre.';
        }
        header('Location: index.php?seccion=perfil');
        exit;
    }

    private static function actualizarPerfilAlumno() {
        session_start();
        if (empty($_SESSION['alumno'])) {
            header('Location: index.php?seccion=login');
            exit;
        }
        $conn = self::db();
        $mat = $_SESSION['alumno']['matricula'];
        $nombre = trim($_POST['nombre'] ?? '');
        $carrera = trim($_POST['carrera'] ?? '');
        $sem = trim($_POST['semestre'] ?? '');
        $gru = trim(strtoupper($_POST['grupo'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password2'] ?? '');
        if ($nombre === '' || $carrera === '' || $sem === '' || $gru === '') {
            $_SESSION['flash_err'] = 'Completa nombre, carrera, cuatrimestre y grupo.';
            header('Location: index.php?seccion=perfil');
            exit;
        }
        try {
            $chk = $conn->prepare('SELECT 1 FROM carreras WHERE nombre=?');
            $chk->bind_param('s', $carrera);
            $chk->execute();
            $ex = $chk->get_result()->fetch_assoc();
            if (!$ex) {
                $_SESSION['flash_err'] = 'Carrera no válida.';
                header('Location: index.php?seccion=perfil');
                exit;
            }
        } catch (Throwable $e) {}
        $updated = false;
        try {
            if ($pass !== '') {
                if (strlen($pass) < 4 || $pass !== $pass2) {
                    $_SESSION['flash_err'] = 'Contraseña inválida o no coincide.';
                    header('Location: index.php?seccion=perfil');
                    exit;
                }
                $st = $conn->prepare('UPDATE alumnos SET nombre=?, carrera=?, semestre=?, grupo=?, password=? WHERE matricula=?');
                $st->bind_param('ssssss', $nombre, $carrera, $sem, $gru, $pass, $mat);
                $st->execute();
                $updated = $st->affected_rows > 0;
            } else {
                $st = $conn->prepare('UPDATE alumnos SET nombre=?, carrera=?, semestre=?, grupo=? WHERE matricula=?');
                $st->bind_param('sssss', $nombre, $carrera, $sem, $gru, $mat);
                $st->execute();
                $updated = $st->affected_rows > 0;
            }
        } catch (Throwable $e) {}
        $_SESSION['alumno']['nombre'] = $nombre;
        $_SESSION['alumno']['carrera'] = $carrera;
        $_SESSION['alumno']['semestre'] = $sem;
        $_SESSION['alumno']['grupo'] = $gru;
        $_SESSION['flash_ok'] = $updated ? 'Perfil actualizado.' : 'No hubo cambios.';
        header('Location: index.php?seccion=perfil');
        exit;
    }

    private static function logout() {
        session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

// Procesa acciones POST al inicio de la petición
AppController::handle();
?>
