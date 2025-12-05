<?php
session_start();

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['usuario'])) {
    header("Location: ingresar.html");
    exit();
}

// Verificar que se haya enviado el formulario
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Método inválido";
    header("Location: ingreso.php");
    exit();
}

// Obtener los datos del formulario
$codigo = trim($_POST['codigo'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$cantidad = $_POST['cantidad'] ?? '';
$fecha = trim($_POST['fecha'] ?? '');

// Validaciones
$errores = [];

if (empty($codigo) || !preg_match('/^[A-Z0-9]{3,10}$/', $codigo)) {
    $errores[] = "Código inválido";
}

$productos_permitidos = ['Jagger', 'Whisky Sour', 'Algarrobina', 'Apple Drunk', 'Cuba Libre'];
if (empty($nombre) || !in_array($nombre, $productos_permitidos)) {
    $errores[] = "Producto inválido";
}

if (!is_numeric($cantidad) || (int)$cantidad <= 0 || (int)$cantidad > 10000) {
    $errores[] = "Cantidad debe ser positiva (1-10000)";
}

// Validar la fecha
$fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha) {
    $errores[] = "Fecha inválida";
}

// Si hay errores, redirigir con los mensajes de error
if (!empty($errores)) {
    $_SESSION['error'] = implode(". ", $errores);
    header("Location: ingreso.php");
    exit();
}

// Asegurarse de que la cantidad sea un número positivo
$cantidad = abs((int)$cantidad);
if ($cantidad <= 0) {
    $_SESSION['error'] = "Cantidad inválida";
    header("Location: ingreso.php");
    exit();
}

// Conexión a la base de datos
include('bd.php');

// Verificar si el producto ya existe en la base de datos
$sql_check = "SELECT id_producto FROM productos WHERE codigo = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $codigo);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows == 0) {
    // Si el producto no existe, insertarlo en la tabla productos
    $sql_insert_producto = "INSERT INTO productos (codigo, nombre, estado) VALUES (?, ?, 'activo')";
    $stmt_insert = $conn->prepare($sql_insert_producto);
    $stmt_insert->bind_param("ss", $codigo, $nombre);
    $stmt_insert->execute();
}

// Obtener el id_producto recién insertado o existente
$sql_get_id_producto = "SELECT id_producto FROM productos WHERE codigo = ?";
$stmt_get_id_producto = $conn->prepare($sql_get_id_producto);
$stmt_get_id_producto->bind_param("s", $codigo);
$stmt_get_id_producto->execute();
$result_get_id_producto = $stmt_get_id_producto->get_result();
$row = $result_get_id_producto->fetch_assoc();
$id_producto = $row['id_producto'];  // Obtener el id_producto

// Insertar movimiento de ingreso en la tabla movimientos_inventario
$sql_insert_movimiento = "INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, fecha_movimiento) 
                          VALUES (?, 'INGRESO', ?, ?)";
$stmt_insert_movimiento = $conn->prepare($sql_insert_movimiento);
$stmt_insert_movimiento->bind_param("iis", $id_producto, $cantidad, $fecha);
$stmt_insert_movimiento->execute();

// Verificar si la inserción fue exitosa
if ($stmt_insert_movimiento->affected_rows > 0) {
    $_SESSION['mensaje'] = "Producto ingresado correctamente: $nombre - $cantidad unidades";
} else {
    $_SESSION['error'] = "Error al registrar el producto";
}

// Redirigir a la página de ingreso
header("Location: ingreso.php");
exit();
?>