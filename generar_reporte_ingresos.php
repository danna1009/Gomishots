<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ingresar.html");
    exit();
}

$filename = __DIR__ . '/data/ingresos.csv';

if (file_exists($filename)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Reporte_Ingresos_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM para Excel
    readfile($filename);
    exit();
} else {
    $_SESSION['error'] = "No hay datos para generar reporte";
    header("Location: ingreso.php");
    exit();
}
?>