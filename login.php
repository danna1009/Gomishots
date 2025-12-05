<?php
session_start();

// Incluir la conexión a la base de datos
include('bd.php');

// Obtener datos del formulario
$usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$contrasena = isset($_POST['contrasena']) ? trim($_POST['contrasena']) : '';

// Validar campos vacíos
if (empty($usuario) || empty($contrasena)) {
    header("Location: ingresar.html?error=campos_vacios");
    exit();
}

// Verificar si el usuario es admin o user y crear los usuarios si no existen
$usuarios_default = [
    'admin' => ['1234', 'admin'],
    'user' => ['2025', 'usuario']
];

// Comprobar si el usuario es uno de los predeterminados (admin o user)
if (array_key_exists($usuario, $usuarios_default)) {
    $contrasena = $usuarios_default[$usuario][0];
    $rol = $usuarios_default[$usuario][1];
    
    // Verificar si el usuario ya existe en la base de datos
    $sql = "SELECT id_usuario FROM usuarios WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    // Si no existe, insertamos el usuario con la contraseña predeterminada
    if ($result->num_rows == 0) {
        $password_hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $insert_sql = "INSERT INTO usuarios (username, password_hash, rol) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $usuario, $password_hash, $rol);
        $insert_stmt->execute();
    }
}

// Consultar en la base de datos si el usuario existe
$sql = "SELECT id_usuario, username, password_hash, rol FROM usuarios WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

// Verificar si el usuario existe
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Verificar la contraseña utilizando password_verify
    if (password_verify($contrasena, $row['password_hash'])) {
        // Login exitoso
        session_regenerate_id(true);

        $_SESSION['usuario'] = $row['username'];
        $_SESSION['rol'] = $row['rol']; // Almacenar el rol (admin o usuario)
        $_SESSION['login_time'] = time();
        $_SESSION['ultimo_acceso'] = time();

        // Registrar login en los logs
        $log_dir = __DIR__ . '/logs/';
        if (file_exists($log_dir)) {
            $log = date('Y-m-d H:i:s') . " - Login exitoso: $usuario\n";
            @file_put_contents($log_dir . 'sistema.log', $log, FILE_APPEND);
        }

        header("Location: inventario.php");
        exit();
    } else {
        // Contraseña incorrecta
        $log_dir = __DIR__ . '/logs/';
        if (file_exists($log_dir)) {
            $log = date('Y-m-d H:i:s') . " - Login fallido: $usuario\n";
            @file_put_contents($log_dir . 'sistema.log', $log, FILE_APPEND);
        }

        header("Location: ingresar.html?error=credenciales_incorrectas");
        exit();
    }
} else {
    // Usuario no encontrado
    $log_dir = __DIR__ . '/logs/';
    if (file_exists($log_dir)) {
        $log = date('Y-m-d H:i:s') . " - Login fallido: $usuario\n";
        @file_put_contents($log_dir . 'sistema.log', $log, FILE_APPEND);
    }

    header("Location: ingresar.html?error=credenciales_incorrectas");
    exit();
}
?>