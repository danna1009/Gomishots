<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ingresar.html");
    exit();
}

include('bd.php'); // Incluir la conexión a la base de datos

// Leer ingresos y salidas desde la base de datos
$stock = [];
$sql = "SELECT p.codigo, p.nombre, 
               SUM(CASE WHEN m.tipo = 'INGRESO' THEN m.cantidad ELSE 0 END) AS ingresos,
               SUM(CASE WHEN m.tipo = 'SALIDA' THEN m.cantidad ELSE 0 END) AS salidas
        FROM productos p
        LEFT JOIN movimientos_inventario m ON p.id_producto = m.id_producto
        GROUP BY p.codigo, p.nombre";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stock[$row['nombre']] = [
            'codigo' => $row['codigo'],
            'ingresos' => $row['ingresos'],
            'salidas' => $row['salidas'],
            'disponible' => $row['ingresos'] - $row['salidas']
        ];
    }
}

// Generar alertas
$alertas = [];
foreach ($stock as $nombre => $datos) {
    if ($datos['disponible'] <= 5 && $datos['disponible'] > 0) {
        $alertas[] = "⚠️ Stock bajo de <strong>$nombre</strong>: {$datos['disponible']} unidades";
    } elseif ($datos['disponible'] <= 0) {
        $alertas[] = "❌ <strong>$nombre</strong> sin stock";
    }
}

ksort($stock);

$total_ingresos = array_sum(array_column($stock, 'ingresos'));
$total_salidas = array_sum(array_column($stock, 'salidas'));
$total_disponible = array_sum(array_column($stock, 'disponible'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock de Almacén</title>
    <link rel="stylesheet" href="styles/ingreso.css">
    <style>
        .tabla-stock { max-width: 900px; margin: 80px auto 40px; padding: 0 20px; }
        .alertas { background: #2a2a00; border-left: 4px solid #ffa500; padding: 15px; margin-bottom: 20px; }
        .resumen { display: flex; justify-content: space-around; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .resumen-card { background: #1a1a1a; padding: 20px; border-radius: 8px; text-align: center; flex: 1; min-width: 150px; border: 2px solid #ffa500; }
        .resumen-card h3 { color: #ffa500; margin-bottom: 10px; font-size: 0.9rem; }
        .resumen-card .numero { font-size: 2rem; font-weight: bold; }
        .stock-verde { color: #00ff00; }
        .stock-amarillo { color: #ffff00; }
        .stock-rojo { color: #ff0000; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo-text">Gomi<span class="highlight">Shots</span></div>
        <div>
            <a href="inventario.php" class="volver-link">Volver al Panel</a>
            <a href="logout.php" class="logout-link">Cerrar Sesión</a>
        </div>
    </div>

    <main class="tabla-stock">
        <h2 style="color:#ffa500; text-align:center;">📦 Stock de Almacén</h2>
        
        <?php if (!empty($alertas)): ?>
            <div class="alertas">
                <strong>⚠️ Alertas:</strong>
                <?php foreach ($alertas as $alerta): ?>
                    <p><?php echo $alerta; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="resumen">
            <div class="resumen-card">
                <h3>Total Ingresos</h3>
                <div class="numero"><?php echo number_format($total_ingresos); ?></div>
            </div>
            <div class="resumen-card">
                <h3>Total Salidas</h3>
                <div class="numero"><?php echo number_format($total_salidas); ?></div>
            </div>
            <div class="resumen-card">
                <h3>Stock Disponible</h3>
                <div class="numero"><?php echo number_format($total_disponible); ?></div>
            </div>
        </div>
        
        <?php if (empty($stock)): ?>
            <div style="text-align:center; padding:40px; color:#999;">
                <p>📭 No hay productos en el almacén</p>
                <p style="margin-top:20px;"><a href="ingreso.php" class="boton">Registrar Producto</a></p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Código</th><th>Producto</th><th>Ingresos</th><th>Salidas</th><th>Disponible</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($stock as $nombre => $datos): ?>
                        <?php
                        if ($datos['disponible'] <= 0) {
                            $clase = 'stock-rojo'; $estado = '❌ Sin Stock';
                        } elseif ($datos['disponible'] <= 5) {
                            $clase = 'stock-amarillo'; $estado = '⚠️ Bajo';
                        } else {
                            $clase = 'stock-verde'; $estado = '✅ OK';
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($datos['codigo']); ?></td>
                            <td><strong><?php echo htmlspecialchars($nombre); ?></strong></td>
                            <td><?php echo number_format($datos['ingresos']); ?></td>
                            <td><?php echo number_format($datos['salidas']); ?></td>
                            <td class="<?php echo $clase; ?>"><strong><?php echo number_format($datos['disponible']); ?></strong></td>
                            <td><?php echo $estado; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>