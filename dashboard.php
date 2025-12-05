<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ingresar.html");
    exit();
}

include('bd.php'); // Incluir la conexión a la base de datos

// Obtener filtros de fecha
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// Función para calcular stock desde la base de datos
function calcularStock($conn) {
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

    return $stock;
}

// Función para obtener movimientos por mes desde la base de datos
function movimientosPorMes($conn, $fecha_inicio, $fecha_fin) {
    $meses = [];

    $sql = "SELECT DATE_FORMAT(m.fecha_movimiento, '%Y-%m') AS mes,
                    SUM(CASE WHEN m.tipo = 'INGRESO' THEN m.cantidad ELSE 0 END) AS ingresos,
                    SUM(CASE WHEN m.tipo = 'SALIDA' THEN m.cantidad ELSE 0 END) AS salidas
            FROM movimientos_inventario m
            WHERE m.fecha_movimiento BETWEEN ? AND ?
            GROUP BY mes
            ORDER BY mes";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $meses[$row['mes']] = [
            'ingresos' => $row['ingresos'],
            'salidas' => $row['salidas']
        ];
    }

    return $meses;
}

// Función para obtener los top productos más vendidos
function topProductosVendidos($conn) {
    $productos_vendidos = [];

    $sql = "SELECT p.nombre, SUM(CASE WHEN m.tipo = 'SALIDA' THEN m.cantidad ELSE 0 END) AS salidas
            FROM productos p
            LEFT JOIN movimientos_inventario m ON p.id_producto = m.id_producto
            GROUP BY p.nombre
            ORDER BY salidas DESC
            LIMIT 5";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $productos_vendidos[$row['nombre']] = $row['salidas'];
        }
    }

    return $productos_vendidos;
}

// Obtener stock
$stock = calcularStock($conn);

// Obtener movimientos por mes
$movimientos = movimientosPorMes($conn, $fecha_inicio, $fecha_fin);

// Calcular totales
$total_ingresos = array_sum(array_column($stock, 'ingresos'));
$total_salidas = array_sum(array_column($stock, 'salidas'));
$total_disponible = array_sum(array_column($stock, 'disponible'));

// Alertas de stock bajo
$alertas = [];
foreach ($stock as $nombre => $datos) {
    if ($datos['disponible'] <= 5 && $datos['disponible'] > 0) {
        $alertas[] = ['nombre' => $nombre, 'cantidad' => $datos['disponible'], 'tipo' => 'warning'];
    } elseif ($datos['disponible'] <= 0) {
        $alertas[] = ['nombre' => $nombre, 'cantidad' => $datos['disponible'], 'tipo' => 'danger'];
    }
}

// Top productos más vendidos
$productos_vendidos = topProductosVendidos($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GomiShots</title>
    <link rel="stylesheet" href="styles/ingreso.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .dashboard-container { max-width: 1400px; margin: 80px auto 40px; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #1a1a1a; padding: 20px; border-radius: 10px; border: 2px solid #ffa500; text-align: center; }
        .stat-card h3 { color: #ffa500; font-size: 0.9rem; margin-bottom: 10px; }
        .stat-card .number { font-size: 2.5rem; font-weight: bold; color: #fff; }
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .chart-card { background: #1a1a1a; padding: 20px; border-radius: 10px; border: 2px solid #ffa500; }
        .chart-card h3 { color: #ffa500; margin-bottom: 15px; }
        .alerts-section { background: #1a1a1a; padding: 20px; border-radius: 10px; border: 2px solid #ffa500; margin-bottom: 20px; }
        .alert-item { padding: 10px; margin: 5px 0; border-radius: 5px; }
        .alert-warning { background: #332200; color: #ffcc00; }
        .alert-danger { background: #330000; color: #ff6666; }
        .filters { background: #1a1a1a; padding: 15px; border-radius: 10px; border: 2px solid #ffa500; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filters input { padding: 8px; border: 1px solid #ffa500; border-radius: 5px; background: #000; color: #fff; }
        .export-buttons { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .btn-export { background: #ffa500; color: #000; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-export:hover { background: #cc8400; }
        @media (max-width: 768px) {
            .charts-grid { grid-template-columns: 1fr; }
            .dashboard-container { margin-top: 120px; }
        }
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

    <div class="dashboard-container">
        <h1 style="color:#ffa500; text-align:center; margin-bottom:30px;">📊 Dashboard de Análisis</h1>

        <!-- Filtros por Fecha -->
        <form method="GET" class="filters">
            <label style="color:#ffa500;">Filtrar por fecha:</label>
            <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
            <label style="color:#fff;">hasta</label>
            <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
            <button type="submit" class="btn-export">Filtrar</button>
            <a href="dashboard.php" class="btn-export" style="background:#666;">Limpiar</a>
        </form>

        <!-- Botones de Exportación -->
        <div class="export-buttons">
            <a href="exportar_pdf.php" class="btn-export">📥 Exportar PDF</a>
            <a href="exportar_excel.php" class="btn-export">📊 Exportar Excel</a>
            <button onclick="window.print()" class="btn-export">🖨️ Imprimir</button>
        </div>

        <!-- Tarjetas de Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Ingresos</h3>
                <div class="number"><?php echo number_format($total_ingresos); ?></div>
                <p style="color:#999; font-size:0.8rem;">Unidades ingresadas</p>
            </div>
            <div class="stat-card">
                <h3>Total Salidas</h3>
                <div class="number"><?php echo number_format($total_salidas); ?></div>
                <p style="color:#999; font-size:0.8rem;">Unidades vendidas</p>
            </div>
            <div class="stat-card">
                <h3>Stock Disponible</h3>
                <div class="number" style="color:#00ff00;"><?php echo number_format($total_disponible); ?></div>
                <p style="color:#999; font-size:0.8rem;">Unidades en almacén</p>
            </div>
            <div class="stat-card">
                <h3>Productos</h3>
                <div class="number"><?php echo count($stock); ?></div>
                <p style="color:#999; font-size:0.8rem;">Tipos diferentes</p>
            </div>
        </div>

        <!-- Alertas de Stock Bajo -->
        <?php if (!empty($alertas)): ?>
        <div class="alerts-section">
            <h3 style="color:#ffa500; margin-bottom:15px;">⚠️ Alertas de Stock</h3>
            <?php foreach ($alertas as $alerta): ?>
                <div class="alert-item alert-<?php echo $alerta['tipo']; ?>">
                    <?php if ($alerta['tipo'] == 'danger'): ?>
                        ❌ <strong><?php echo $alerta['nombre']; ?></strong>: Sin stock disponible
                    <?php else: ?>
                        ⚠️ <strong><?php echo $alerta['nombre']; ?></strong>: Stock bajo (<?php echo $alerta['cantidad']; ?> unidades)
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Gráficos -->
        <div class="charts-grid">
            <!-- Gráfico de Stock por Producto -->
            <div class="chart-card">
                <h3>📊 Stock Actual por Producto</h3>
                <canvas id="chartStock"></canvas>
            </div>

            <!-- Gráfico de Movimientos por Mes -->
            <div class="chart-card">
                <h3>📈 Movimientos por Mes</h3>
                <canvas id="chartMovimientos"></canvas>
            </div>

            <!-- Gráfico de Top Productos Vendidos -->
            <div class="chart-card">
                <h3>🏆 Top 5 Productos Más Vendidos</h3>
                <canvas id="chartTopVendidos"></canvas>
            </div>

            <!-- Gráfico de Comparación Ingresos vs Salidas -->
            <div class="chart-card">
                <h3>⚖️ Comparación Ingresos vs Salidas</h3>
                <canvas id="chartComparacion"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Configuración global de Chart.js
        Chart.defaults.color = '#fff';
        Chart.defaults.borderColor = '#333';

        // Datos de PHP a JavaScript
        const stockData = <?php echo json_encode($stock); ?>;
        const movimientosData = <?php echo json_encode($movimientos); ?>;
        const topVendidosData = <?php echo json_encode($productos_vendidos); ?>;

        // Gráfico 1: Stock por Producto
        const ctxStock = document.getElementById('chartStock').getContext('2d');
        new Chart(ctxStock, {
            type: 'bar',
            data: {
                labels: Object.keys(stockData),
                datasets: [{
                    label: 'Stock Disponible',
                    data: Object.values(stockData).map(d => d.disponible),
                    backgroundColor: '#ffa500',
                    borderColor: '#ff8800',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Gráfico 2: Movimientos por Mes
        const ctxMovimientos = document.getElementById('chartMovimientos').getContext('2d');
        new Chart(ctxMovimientos, {
            type: 'line',
            data: {
                labels: Object.keys(movimientosData),
                datasets: [
                    {
                        label: 'Ingresos',
                        data: Object.values(movimientosData).map(d => d.ingresos),
                        borderColor: '#00ff00',
                        backgroundColor: 'rgba(0, 255, 0, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Salidas',
                        data: Object.values(movimientosData).map(d => d.salidas),
                        borderColor: '#ff0000',
                        backgroundColor: 'rgba(255, 0, 0, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Gráfico 3: Top Vendidos
        const ctxTopVendidos = document.getElementById('chartTopVendidos').getContext('2d');
        new Chart(ctxTopVendidos, {
            type: 'pie',
            data: {
                labels: Object.keys(topVendidosData),
                datasets: [{
                    data: Object.values(topVendidosData),
                    backgroundColor: ['#ffa500', '#ff6600', '#ffcc00', '#ff9900', '#ff3300']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Gráfico 4: Comparación Total
        const ctxComparacion = document.getElementById('chartComparacion').getContext('2d');
        new Chart(ctxComparacion, {
            type: 'bar',
            data: {
                labels: Object.keys(stockData),
                datasets: [
                    {
                        label: 'Ingresos',
                        data: Object.values(stockData).map(d => d.ingresos),
                        backgroundColor: '#00ff00'
                    },
                    {
                        label: 'Salidas',
                        data: Object.values(stockData).map(d => d.salidas),
                        backgroundColor: '#ff0000'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>