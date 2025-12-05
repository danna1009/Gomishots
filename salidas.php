<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ingresar.html");
    exit();
}

include('bd.php'); // Incluir la conexión a la base de datos

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Obtener productos desde la base de datos
$sql = "SELECT p.codigo, p.nombre, 
               SUM(CASE WHEN m.tipo = 'INGRESO' THEN m.cantidad ELSE 0 END) - 
               SUM(CASE WHEN m.tipo = 'SALIDA' THEN m.cantidad ELSE 0 END) AS stock
        FROM productos p
        LEFT JOIN movimientos_inventario m ON p.id_producto = m.id_producto
        GROUP BY p.codigo, p.nombre";
$result = $conn->query($sql);

$productos = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $productos[] = [
            'codigo' => $row['codigo'],
            'nombre' => $row['nombre'],
            'stock' => $row['stock']
        ];
    }
}

// Registrar salida de producto
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["registrar"])) {
    $codigo = trim($_POST['codigo'] ?? '');
    $cantidad = $_POST['cantidad'] ?? '';
    $fecha = trim($_POST['fecha'] ?? '');

    // Verificar si el producto existe en la base de datos
    $producto = null;
    foreach ($productos as $prod) {
        if ($prod['codigo'] == $codigo) {
            $producto = $prod;
            break;
        }
    }

    // Validar existencia del producto y stock disponible
    if (!$producto) {
        $_SESSION['error'] = "Producto no encontrado.";
        header("Location: salidas.php");
        exit();
    }

    $stock_actual = $producto['stock'];

    if ($cantidad <= 0 || $cantidad > $stock_actual) {
        $_SESSION['error'] = "Cantidad inválida. Stock disponible: $stock_actual";
        header("Location: salidas.php");
        exit();
    }

    // Registrar la salida en la base de datos
    $sql_insert = "INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, fecha_movimiento)
                   SELECT id_producto, 'SALIDA', ?, ?, ? FROM productos WHERE codigo = ?";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("isis", $cantidad, $fecha, $codigo);
    $stmt->execute();

    $_SESSION['mensaje'] = "Salida registrada. Stock restante: " . ($stock_actual - $cantidad);

    header("Location: salidas.php");
    exit();
}

// Descargar reporte de salidas
if (isset($_GET['descargar']) && file_exists('data/salidas.csv')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Reporte_Salidas_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM para Excel
    readfile('data/salidas.csv');
    exit();
}

// Obtener salidas registradas
$salidas = [];
$sql_salidas = "SELECT m.id_producto, p.nombre, m.cantidad, m.fecha_movimiento 
                FROM movimientos_inventario m
                JOIN productos p ON m.id_producto = p.id_producto
                WHERE m.tipo = 'SALIDA'";
$result_salidas = $conn->query($sql_salidas);

if ($result_salidas->num_rows > 0) {
    while ($row = $result_salidas->fetch_assoc()) {
        $salidas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salidas de Producto</title>
    <link rel="stylesheet" href="styles/salida.css">
</head>
<body>
    <div class="top-bar">
        <div class="logo-text">Gomi<span class="highlight">Shots</span></div>
        <div>
            <a href="inventario.php" class="volver-link">Volver al Panel</a>
            <a href="logout.php" class="logout-link">Cerrar sesión</a>
        </div>
    </div>

    <?php if (isset($_SESSION['mensaje'])): ?>
        <div style="background:#004400; color:#00ff00; padding:15px; margin:20px; border-radius:5px; text-align:center;">
            ✅ <?php echo htmlspecialchars($_SESSION['mensaje'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['mensaje']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="background:#440000; color:#ff6666; padding:15px; margin:20px; border-radius:5px; text-align:center;">
            ❌ <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <div class="formulario">
            <h2>Registrar Salida</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <select name="codigo" id="selectProducto" required>
                    <option value="">Seleccione el producto</option>
                    <?php foreach ($productos as $producto): ?>
                        <option value="<?php echo htmlspecialchars($producto['codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-stock="<?php echo $producto['stock']; ?>">
                            <?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            (Stock: <?php echo $producto['stock']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="stockInfo" style="display:none; background:#1a1a1a; padding:10px; margin:10px 0; border-left:3px solid #ffa500;">
                    <strong>Stock disponible:</strong> <span id="stockDisponible">0</span> unidades
                </div>

                <input type="number" name="cantidad" id="cantidadSalida" placeholder="Cantidad" min="1" required>
                
                <input type="date" name="fecha" max="<?php echo date('Y-m-d'); ?>" required>
                
                <div class="boton-group">
                    <button type="submit" name="registrar" class="boton">Registrar</button>
                    <a class="boton" href="?descargar=1">Generar Reporte</a>
                </div>
            </form>

            <h2>Salidas Registradas</h2>
            <table>
                <thead>
                    <tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($salidas)): ?>
                        <tr><td colspan="4">No hay salidas registradas</td></tr>
                    <?php else: ?>
                        <?php foreach ($salidas as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id_producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['cantidad'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['fecha_movimiento'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('selectProducto').addEventListener('change', function() {
            const stockInfo = document.getElementById('stockInfo');
            const stockDisponible = document.getElementById('stockDisponible');
            const cantidadInput = document.getElementById('cantidadSalida');
            
            if (this.value) {
                const stock = this.options[this.selectedIndex].dataset.stock;
                stockDisponible.textContent = stock;
                stockInfo.style.display = 'block';
                cantidadInput.max = stock;
            } else {
                stockInfo.style.display = 'none';
                cantidadInput.removeAttribute('max');
            }
        });
        
        document.getElementById('cantidadSalida').addEventListener('input', function() {
            const max = parseInt(this.max);
            const valor = parseInt(this.value);
            
            if (max && valor > max) {
                this.value = max;
                alert('La cantidad no puede exceder el stock disponible (' + max + ')');
            }
        });
    </script>
</body>
</html>