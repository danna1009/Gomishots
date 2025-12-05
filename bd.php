<?php
// Obtener las variables de entorno
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');

// Crear la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$username password=$password");

// Verificar la conexión
if (!$conn) {
    die("Conexión fallida: " . pg_last_error());
}
?>
