<?php
/**
 * Script de instalación de la base de datos para mYpuB
 * 
 * Este script creará la base de datos, usuario y todas las tablas necesarias.
 * Solo debe ejecutarse UNA VEZ durante la instalación inicial.
 */

// Evitar acceso directo desde navegador
if (php_sapi_name() !== 'cli' && !isset($_GET['install_token'])) {
    die("<h1>Acceso no autorizado</h1><p>Este script solo puede ejecutarse con un token de instalación válido.</p>");
}

// Configuración (ajusta según tus necesidades)
$db_host = 'localhost';
$db_root_user = 'root'; // Usuario con permisos para crear bases de datos
$db_root_pass = ''; // Contraseña del usuario root (dejar vacío si no tiene)
$db_name = 'mypub_db';
$db_user = 'mypub_user';
$db_pass = 'Enzema0097@&!'; // La contraseña que especificaste

// Token de instalación (cámbialo por uno único y seguro)
$install_token = 'INSTALL_TOKEN_' . bin2hex(random_bytes(16));

// Verificar si se está ejecutando con el token correcto
if (isset($_GET['install_token']) && $_GET['install_token'] !== $install_token) {
    die("<h1>Token de instalación incorrecto</h1>");
}

// Mostrar mensaje de advertencia
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Instalación de mYpuB</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Instalación de la base de datos mYpuB</h1>";

try {
    // Conexión al servidor MySQL
    $conn = new mysqli($db_host, $db_root_user, $db_root_pass);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión al servidor MySQL: " . $conn->connect_error);
    }
    
    echo "<p>Conexión al servidor MySQL establecida correctamente.</p>";
    
    // 1. Crear la base de datos
    $sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === TRUE) {
        echo "<p class='success'>Base de datos <strong>$db_name</strong> creada correctamente.</p>";
    } else {
        throw new Exception("Error al crear la base de datos: " . $conn->error);
    }
    
    // 2. Crear usuario y asignar privilegios
    $sql = "CREATE USER IF NOT EXISTS '$db_user'@'$db_host' IDENTIFIED BY '$db_pass'";
    if ($conn->query($sql) {
        echo "<p class='success'>Usuario <strong>$db_user</strong> creado correctamente.</p>";
    } else {
        throw new Exception("Error al crear el usuario: " . $conn->error);
    }
    
    $sql = "GRANT ALL PRIVILEGES ON `$db_name`.* TO '$db_user'@'$db_host'";
    if ($conn->query($sql)) {
        echo "<p class='success'>Privilegios asignados correctamente al usuario <strong>$db_user</strong>.</p>";
    } else {
        throw new Exception("Error al asignar privilegios: " . $conn->error);
    }
    
    $conn->query("FLUSH PRIVILEGES");
    echo "<p class='success'>Privilegios actualizados.</p>";
    
    // 3. Crear tablas en la base de datos
    $conn->select_db($db_name);
    
    // Tabla de usuarios
    $sql = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `fullname` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `gender` ENUM('Hombre', 'Mujer', 'Otros') NOT NULL,
        `country` VARCHAR(50) NOT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `is_active` BOOLEAN DEFAULT TRUE,
        `is_blocked` BOOLEAN DEFAULT FALSE,
        `last_login` DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>Tabla <strong>users</strong> creada correctamente.</p>";
    } else {
        throw new Exception("Error al crear tabla users: " . $conn->error);
    }
    
    // Tabla de archivos
    $sql = "CREATE TABLE IF NOT EXISTS `files` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `filename` VARCHAR(255) NOT NULL,
        `filepath` VARCHAR(255) NOT NULL,
        `filetype` ENUM('image', 'video') NOT NULL,
        `title` VARCHAR(100),
        `description` TEXT,
        `is_public` BOOLEAN DEFAULT FALSE,
        `likes` INT DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>Tabla <strong>files</strong> creada correctamente.</p>";
    } else {
        throw new Exception("Error al crear tabla files: " . $conn->error);
    }
    
    // Tabla de likes
    $sql = "CREATE TABLE IF NOT EXISTS `file_likes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `file_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_like` (`file_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>Tabla <strong>file_likes</strong> creada correctamente.</p>";
    } else {
        throw new Exception("Error al crear tabla file_likes: " . $conn->error);
    }
    
    // Tabla de compartidos
    $sql = "CREATE TABLE IF NOT EXISTS `shared_files` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `file_id` INT NOT NULL,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>Tabla <strong>shared_files</strong> creada correctamente.</p>";
    } else {
        throw new Exception("Error al crear tabla shared_files: " . $conn->error);
    }
    
    // Tabla de comentarios
    $sql = "CREATE TABLE IF NOT EXISTS `comments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `file_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `comment` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>Tabla <strong>comments</strong> creada correctamente.</p>";
    } else {
        throw new Exception("Error al crear tabla comments: " . $conn->error);
    }
    
    // Crear archivo db_config.php si no existe
    $config_content = "<?php
// Configuración de la base de datos para mYpuB
define('DB_SERVER', '$db_host');
define('DB_USERNAME', '$db_user');
define('DB_PASSWORD', '$db_pass');
define('DB_NAME', '$db_name');

// Crear conexión
\$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if (\$conn->connect_error) {
    die(\"Error de conexión: \" . \$conn->connect_error);
}

// Establecer el conjunto de caracteres
\$conn->set_charset(\"utf8mb4\");
?>";
    
    if (!file_exists('db_config.php')) {
        if (file_put_contents('db_config.php', $config_content)) {
            echo "<p class='success'>Archivo <strong>db_config.php</strong> creado correctamente.</p>";
        } else {
            echo "<p class='warning'>No se pudo crear el archivo db_config.php. Por favor, créalo manualmente con el siguiente contenido:</p>";
            echo "<pre>" . htmlspecialchars($config_content) . "</pre>";
        }
    } else {
        echo "<p class='warning'>El archivo db_config.php ya existe. No se ha modificado.</p>";
    }
    
    echo "<h2 class='success'>¡Instalación completada con éxito!</h2>";
    echo "<p>La base de datos y todas las tablas necesarias han sido creadas.</p>";
    echo "<p><strong>IMPORTANTE:</strong> Por seguridad, elimina o renombra este archivo (install_db.php) después de la instalación.</p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>Error durante la instalación</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
    echo "<p>Por favor, verifica los errores y vuelve a intentarlo.</p>";
}

echo "</body></html>";
?>
