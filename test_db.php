<?php
require 'db_config.php';

echo "<h2>Prueba de conexión a la base de datos</h2>";

if ($conn->connect_error) {
    echo "<p style='color: red;'>Error de conexión: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>¡Conexión exitosa a la base de datos!</p>";
    
    // Verificar tablas
    $tables = ['users', 'files', 'file_likes', 'shared_files', 'comments'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p>Tabla <strong>$table</strong> existe ✔️</p>";
        } else {
            echo "<p style='color: orange;'>Tabla <strong>$table</strong> no existe ❌</p>";
        }
    }
}

$conn->close();
?>
