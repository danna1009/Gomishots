<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ingresar.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ingreso.php");
    exit();
}

$codigo = $_POST["codigo"] ?? '';
$nueva_cantidad = $_POST["cantidad_modificada"] ?? '';
$justificacion = trim($_POST["justificacion"] ?? '');

if (!is_numeric($nueva_cantidad) || (int)$nueva_cantidad <= 0 || (int)$nueva_cantidad > 10000) {
    $_SESSION['error'] = "Cantidad inválida";
    header("Location: ingreso.php");
    exit();
}

$archivo = __DIR__ . '/data/ingresos.csv';
if (!file_exists($archivo)) {
    $_SESSION['error'] = "Archivo no encontrado";
    header("Location: ingreso.php");
    exit();
}

$datos = [];
$fp = fopen($archivo, 'r');
while (($row = fgetcsv($fp)) !== false) {
    $datos[] = $row;
}
fclose($fp);

$modificado = false;
foreach ($datos as &$fila) {
    if ($fila[0] == $codigo) {
        $fila[2] = abs((int)$nueva_cantidad);
        $modificado = true;
        break;
    }
}

if ($modificado) {
    $fp = fopen($archivo, 'w');
    if (flock($fp, LOCK_EX)) {
        foreach ($datos as $fila) {
            fputcsv($fp, $fila);
        }
        flock($fp, LOCK_UN);
        $_SESSION['mensaje'] = "Ingreso modificado. Justificación: $justificacion";
    }
    fclose($fp);
} else {
    $_SESSION['error'] = "Código no encontrado";
}

header("Location: ingreso.php");
exit();
?>