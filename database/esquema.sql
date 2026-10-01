-- KGI Soluciones: esquema completo (MySQL / MariaDB) + registro de migraciones.
-- Alternativa a 'php spark migrate' en una base vacía. Después carga database/datos_iniciales.sql.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

CREATE TABLE `abonos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cuota_id` int(11) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `referencia` varchar(60) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cuota_id` (`cuota_id`),
  CONSTRAINT `abonos_cuota_id_foreign` FOREIGN KEY (`cuota_id`) REFERENCES `cuotas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `actividades` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `proyecto_id` int(11) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `fecha` date DEFAULT NULL,
  `estado` varchar(12) NOT NULL DEFAULT 'En proceso',
  `porcentaje` int(3) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `actividades_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `adjuntos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cuota_id` int(11) unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'otro',
  `nombre` varchar(150) NOT NULL,
  `archivo` varchar(80) NOT NULL,
  `mime` varchar(80) DEFAULT NULL,
  `tamano` int(11) unsigned NOT NULL DEFAULT 0,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cuota_id` (`cuota_id`),
  CONSTRAINT `adjuntos_cuota_id_foreign` FOREIGN KEY (`cuota_id`) REFERENCES `cuotas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `auditoria` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `usuario_nombre` varchar(120) DEFAULT NULL,
  `accion` varchar(20) NOT NULL,
  `entidad` varchar(30) NOT NULL,
  `registro_id` int(11) unsigned DEFAULT NULL,
  `resumen` varchar(255) NOT NULL,
  `cambios` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entidad_registro_id` (`entidad`,`registro_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `clientes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `configuraciones` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(80) NOT NULL,
  `valor` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `cuotas` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `proyecto_id` int(11) unsigned NOT NULL,
  `orden` int(5) NOT NULL DEFAULT 0,
  `etiqueta` varchar(60) NOT NULL,
  `porcentaje` decimal(7,4) NOT NULL,
  `estado` varchar(12) NOT NULL DEFAULT 'pendiente',
  `factura` varchar(30) DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `actividad_id` int(11) unsigned DEFAULT NULL,
  `fecha_estimada` date DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `detr_fecha` date DEFAULT NULL,
  `detr_ref` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `cuotas_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `password_resets` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `password_resets_usuario_id_foreign` (`usuario_id`),
  KEY `token_hash` (`token_hash`),
  CONSTRAINT `password_resets_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `perfiles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `es_admin` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `permisos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `perfil_id` int(11) unsigned NOT NULL,
  `permiso` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perfil_id_permiso` (`perfil_id`,`permiso`),
  CONSTRAINT `permisos_perfil_id_foreign` FOREIGN KEY (`perfil_id`) REFERENCES `perfiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `procesos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(255) NOT NULL,
  `fecha` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `proyectos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) unsigned DEFAULT NULL,
  `departamento` varchar(60) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `moneda` varchar(3) DEFAULT NULL,
  `monto` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proyectos_cliente_id_foreign` (`cliente_id`),
  CONSTRAINT `proyectos_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `usuarios` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `perfil_id` int(11) unsigned NOT NULL,
  `nombres` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `usuarios_perfil_id_foreign` (`perfil_id`),
  CONSTRAINT `usuarios_perfil_id_foreign` FOREIGN KEY (`perfil_id`) REFERENCES `perfiles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES (1,'2025-01-01-000001','App\\Database\\Migrations\\CreateKgiTables','default','App',1790886811,1),
(2,'2025-01-02-000001','App\\Database\\Migrations\\CreateFacturacion','default','App',1790886811,1),
(3,'2025-01-03-000001','App\\Database\\Migrations\\SeguimientoPagos','default','App',1790886811,1),
(4,'2025-01-04-000001','App\\Database\\Migrations\\Adjuntos','default','App',1790886811,1),
(6,'2025-01-05-000001','App\\Database\\Migrations\\PermisosYAuditoria','default','App',1790887101,2);
SET FOREIGN_KEY_CHECKS=1;
