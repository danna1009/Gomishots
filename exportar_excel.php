<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ingresar.html");
    exit();
}

$ingresos_file = __DIR__ . '/data/ingresos.csv';
$salidas_file = __DIR__ . '/data/salidas.csv';

// Función para calcular stock
function calcularStock($ingresos_file, $salidas_file) {
    $stock = [];
    
    if (file_exists($ingresos_file)) {
        $fp = fopen($ingresos_file, 'r');
        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) >= 3) {
                $nombre = $row[1];
                $cantidad = (int)$row[2];
                if (!isset($stock[$nombre])) {
                    $stock[$nombre] = ['ingresos' => 0, 'salidas' => 0, 'disponible' => 0];
                }
                $stock[$nombre]['ingresos'] += $cantidad;
                $stock[$nombre]['disponible'] += $cantidad;
            }
        }
        fclose($fp);
    }
    
    if (file_exists($salidas_file)) {
        $fp = fopen($salidas_file, 'r');
        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) >= 3) {
                $nombre = $row[1];
                $cantidad = (int)$row[2];
                if (!isset($stock[$nombre])) {
                    $stock[$nombre] = ['ingresos' => 0, 'salidas' => 0, 'disponible' => 0];
                }
                $stock[$nombre]['salidas'] += $cantidad;
                $stock[$nombre]['disponible'] -= $cantidad;
            }
        }
        fclose($fp);
    }
    
    return $stock;
}

$stock = calcularStock($ingresos_file, $salidas_file);

// Configurar headers para Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Reporte_Stock_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM para UTF-8
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid black; padding: 8px; text-align: center; }
        th { background-color: #ffa500; font-weight: bold; }
        .header { background-color: #000; color: #ffa500; padding: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>GOMISHOTS - REPORTE DE STOCK</h1>
        <p>Generado: <?php echo date('d/m/Y H:i:s'); ?></p>
        <p>Usuario: <?php echo htmlspecialchars($_SESSION['usuario']); ?></p>
    </div>

    <h2>Resumen General</h2>
    <table>
        <tr>
            <th>Total Ingresos</th>
            <th>Total Salidas</th>
            <th>Stock Disponible</th>
            <th>Productos</th>
        </tr>
        <tr>
            <td><?php echo number_format(array_sum(array_column($stock, 'ingresos'))); ?></td>
            <td><?php echo number_format(array_sum(array_column($stock, 'salidas'))); ?></td>
            <td><?php echo number_format(array_sum(array_column($stock, 'disponible'))); ?></td>
            <td><?php echo count($stock); ?></td>
        </tr>
    </table>

    <h2>Detalle por Producto</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Total Ingresos</th>
                <th>Total Salidas</th>
                <th>Stock Disponible</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stock as $nombre => $datos): ?>
                <?php
                if ($datos['disponible'] <= 0) {
                    $estado = 'SIN STOCK';
                } elseif ($datos['disponible'] <= 5) {
                    $estado = 'STOCK BAJO';
                } else {
                    $estado = 'OK';
                }
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($nombre); ?></strong></td>
                    <td><?php echo number_format($datos['ingresos']); ?></td>
                    <td><?php echo number_format($datos['salidas']); ?></td>
                    <td><?php echo number_format($datos['disponible']); ?></td>
                    <td><?php echo $estado; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top: 30px; text-align: center; color: #666;">
        <small>© <?php echo date('Y'); ?> GomiShots - Todos los derechos reservados</small>
    </p>
</body>
</html>