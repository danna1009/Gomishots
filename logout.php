<?php
/**
 * GOMISHOT 2.0 - Cerrar Sesión
 */

session_start();

// Registrar logout
$usuario = $_SESSION['usuario'] ?? 'Desconocido';
$log_dir = __DIR__ . '/logs/';
if (file_exists($log_dir)) {
    $log_message = date('Y-m-d H:i:s') . " - Logout: $usuario\n";
    @file_put_contents($log_dir . 'sistema.log', $log_message, FILE_APPEND);
}

// Destruir sesión
session_unset();
session_destroy();

// Destruir cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirigir
header("Location: ingresar.html");
exit();
?>