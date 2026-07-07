-- Base de datos para UPGOP (web)
-- Motor: MySQL/MariaDB (Laragon)

-- Crea la BD si no existe y usa UTF8MB4
CREATE DATABASE IF NOT EXISTS `UPGOP`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `UPGOP`;

DROP TABLE IF EXISTS `carreras`;
CREATE TABLE `carreras` (
  `nombre` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `docentes`;
CREATE TABLE `docentes` (
  `numeroEmpleado` VARCHAR(32) NOT NULL,
  `nombre` VARCHAR(128) NOT NULL,
  `password` VARCHAR(128) NOT NULL,
  `createdAt` BIGINT NOT NULL,
  PRIMARY KEY (`numeroEmpleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabla: alumnos
-- =====================
DROP TABLE IF EXISTS `alumnos`;
CREATE TABLE `alumnos` (
  `matricula` VARCHAR(32) NOT NULL,
  `nombre` VARCHAR(128) NOT NULL,
  `carrera` VARCHAR(128) NOT NULL,
  `semestre` VARCHAR(32) NOT NULL,
  `grupo` VARCHAR(32) NOT NULL,
  `password` VARCHAR(128) NOT NULL,
  `createdAt` BIGINT NOT NULL,
  PRIMARY KEY (`matricula`),
  KEY `idx_alumnos_sem_gru` (`semestre`,`grupo`),
  CONSTRAINT `fk_alumnos_carrera`
    FOREIGN KEY (`carrera`) REFERENCES `carreras`(`nombre`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabla: encuestas_alumno (bandera de una sola encuesta por alumno/semestre/grupo)
-- =====================
DROP TABLE IF EXISTS `encuestas_alumno`;
CREATE TABLE `encuestas_alumno` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `matricula` VARCHAR(32) NOT NULL,
  `carrera` VARCHAR(128) NOT NULL,
  `semestre` VARCHAR(32) NOT NULL,
  `grupo` VARCHAR(32) NOT NULL,
  `timestamp` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_encuesta_alumno` (`matricula`,`semestre`,`grupo`),
  CONSTRAINT `fk_encuesta_alumno_matricula`
    FOREIGN KEY (`matricula`) REFERENCES `alumnos`(`matricula`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabla: registros_grupo (asignación de docente a grupo)
-- =====================
DROP TABLE IF EXISTS `registros_grupo`;
CREATE TABLE `registros_grupo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numeroEmpleado` VARCHAR(32) NOT NULL,
  `nombreDocente` VARCHAR(128) NOT NULL,
  `materia` VARCHAR(128) NOT NULL,
  `carrera` VARCHAR(128) NOT NULL,
  `semestre` VARCHAR(32) NOT NULL,
  `grupo` VARCHAR(32) NOT NULL,
  `password` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_docente_sem_gru` (`numeroEmpleado`,`semestre`,`grupo`),
  KEY `idx_registro_docente` (`numeroEmpleado`),
  KEY `idx_registro_sem_gru` (`semestre`,`grupo`),
  CONSTRAINT `fk_registros_carrera`
    FOREIGN KEY (`carrera`) REFERENCES `carreras`(`nombre`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_registros_docente`
    FOREIGN KEY (`numeroEmpleado`) REFERENCES `docentes`(`numeroEmpleado`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `asignaciones`;
CREATE TABLE `asignaciones` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numeroEmpleado` VARCHAR(32) NOT NULL,
  `semestre` VARCHAR(32) NOT NULL,
  `grupo` VARCHAR(32) NOT NULL,
  `carrera` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_asignacion` (`numeroEmpleado`,`semestre`,`grupo`,`carrera`),
  CONSTRAINT `fk_asig_docente`
    FOREIGN KEY (`numeroEmpleado`) REFERENCES `docentes`(`numeroEmpleado`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_asig_carrera`
    FOREIGN KEY (`carrera`) REFERENCES `carreras`(`nombre`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabla: respuestas (ratings 1..5 por pregunta)
-- =====================
DROP TABLE IF EXISTS `respuestas`;
CREATE TABLE `respuestas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `asignacion_id` INT NULL,
  `numeroEmpleado` VARCHAR(32) NOT NULL,
  `semestre` VARCHAR(32) NOT NULL,
  `grupo` VARCHAR(32) NOT NULL,
  `preguntaIndex` INT NOT NULL,
  `rating` INT NOT NULL,
  `timestamp` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_resp_docente` (`numeroEmpleado`),
  KEY `idx_resp_sem_gru` (`semestre`,`grupo`),
  KEY `idx_resp_docente_sem_gru` (`numeroEmpleado`,`semestre`,`grupo`),
  KEY `idx_resp_pregunta` (`preguntaIndex`),
  KEY `idx_resp_asignacion` (`asignacion_id`),
  CONSTRAINT `fk_respuestas_registro`
    FOREIGN KEY (`numeroEmpleado`,`semestre`,`grupo`) REFERENCES `registros_grupo`(`numeroEmpleado`,`semestre`,`grupo`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_respuestas_asignacion`
    FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================
-- Tabla: opiniones (comentarios libres por docente y grupo)
-- =====================
DROP TABLE IF EXISTS `opiniones`;
CREATE TABLE `opiniones` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `asignacion_id` INT NULL,
  `numeroEmpleado` VARCHAR(32) NOT NULL,
  `semestre` VARCHAR(32) NOT NULL,
  `grupo` VARCHAR(32) NOT NULL,
  `semestreGrupo` VARCHAR(32) NOT NULL,
  `comentario` TEXT NOT NULL,
  `timestamp` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_op_docente` (`numeroEmpleado`),
  KEY `idx_op_sem_gru` (`semestreGrupo`),
  KEY `idx_op_semestre_grupo` (`semestre`,`grupo`),
  KEY `idx_op_asignacion` (`asignacion_id`),
  CONSTRAINT `fk_opiniones_registro`
    FOREIGN KEY (`numeroEmpleado`,`semestre`,`grupo`) REFERENCES `registros_grupo`(`numeroEmpleado`,`semestre`,`grupo`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_opiniones_asignacion`
    FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `dpa_usuarios`;
CREATE TABLE `dpa_usuarios` (
  `usuario` VARCHAR(64) NOT NULL,
  `password` VARCHAR(128) NOT NULL,
  `carrera` VARCHAR(128) NULL,
  PRIMARY KEY (`usuario`),
  CONSTRAINT `fk_dpa_carrera`
    FOREIGN KEY (`carrera`) REFERENCES `carreras`(`nombre`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sa_usuarios`;
CREATE TABLE `sa_usuarios` (
  `usuario` VARCHAR(64) NOT NULL,
  `password` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `config_app`;
CREATE TABLE `config_app` (
  `clave` VARCHAR(64) NOT NULL,
  `valor` VARCHAR(256) NOT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
