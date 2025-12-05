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

// Leer productos desde la base de datos para obtener los códigos únicos
$sql = "SELECT codigo, nombre FROM productos WHERE estado = 'activo'";
$result = $conn->query($sql);
$productos = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
}

// Obtener ingresos desde la base de datos
$ingresos = [];
$sql_ingresos = "SELECT m.id_producto, p.nombre, m.cantidad, m.fecha_movimiento 
                 FROM movimientos_inventario m
                 JOIN productos p ON m.id_producto = p.id_producto
                 WHERE m.tipo = 'INGRESO'";
$result_ingresos = $conn->query($sql_ingresos);

if ($result_ingresos->num_rows > 0) {
    while ($row = $result_ingresos->fetch_assoc()) {
        $ingresos[] = $row;
    }
}

// Obtener códigos únicos de los productos
$codigos_unicos = [];
foreach ($ingresos as $ingreso) {
    if (!empty($ingreso['id_producto']) && !in_array($ingreso['id_producto'], $codigos_unicos)) {
        $codigos_unicos[] = $ingreso['id_producto'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso de Productos</title>
    <link rel="stylesheet" href="styles/ingreso.css">
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
            <h2>Registrar Producto</h2>
            <form method="POST" action="procesar_ingreso.php" id="formIngreso">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <input type="text" name="codigo" placeholder="Código (ej: JAG001)" 
                       pattern="[A-Z0-9]{3,10}" title="3-10 caracteres: A-Z y 0-9" 
                       maxlength="10" required>
                
                <select name="nombre" required>
                    <option value="">Seleccione el producto</option>
                    <?php foreach ($productos as $producto): ?>
                        <option value="<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="number" name="cantidad" id="cantidadInput"
                       placeholder="Cantidad (solo positivos)" min="1" max="10000" step="1" required>
                
                <input type="date" name="fecha" max="<?php echo date('Y-m-d'); ?>" required>
                
                <div class="boton-group">
                    <button class="boton" type="submit">Registrar</button>
                    <a href="generar_reporte_ingresos.php" class="boton">Generar Reporte</a>
                </div>
            </form>

            <h2>Modificar Ingreso</h2>
            <form method="POST" action="modificar_i.php" id="formModificar">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <select name="codigo" required>
                    <option value="">Seleccione el código</option>
                    <?php foreach ($codigos_unicos as $codigo): ?>
                        <option value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="number" name="cantidad_modificada" id="cantidadMod"
                       placeholder="Nueva cantidad" min="1" max="10000" required>
                
                <input type="text" name="justificacion" placeholder="Justificación" maxlength="200" required>
                
                <button class="boton" type="submit">Modificar</button>
            </form>
        </div>

        <div class="tabla">
            <h3>Buscar Producto</h3>
            <input type="text" id="buscar" class="buscar" placeholder="Buscar...">
            
            <table id="tablaProductos">
                <thead>
                    <tr><th>Código</th><th>Nombre</th><th>Cantidad</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($ingresos)): ?>
                        <tr><td colspan="4">No hay ingresos</td></tr>
                    <?php else: ?>
                        <?php foreach ($ingresos as $ing): ?>
                            <?php if (count($ing) >= 4): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ing['id_producto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($ing['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($ing['cantidad'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($ing['fecha_movimiento'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Validación de cantidad
        function validarCantidad(input) {
            const valor = parseFloat(input.value);
            if (valor <= 0 || isNaN(valor)) {
                input.value = '';
                alert('❌ La cantidad debe ser mayor a 0');
                return false;
            }
            if (valor % 1 !== 0) input.value = Math.floor(valor);
            if (valor > 10000) input.value = 10000;
            return true;
        }
        
        // Prevenir números negativos
        function prevenirNegativos(e) {
            if (e.key === '-' || e.key === 'e' || e.key === '+' || e.key === '.') {
                e.preventDefault();
            }
        }
        
        // Aplicar validaciones
        ['cantidadInput', 'cantidadMod'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('keydown', prevenirNegativos);
                input.addEventListener('change', function() { validarCantidad(this); });
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
        
        // Búsqueda en tiempo real
        document.getElementById("buscar").addEventListener("keyup", function() {
            const valor = this.value.toLowerCase();
            document.querySelectorAll("#tablaProductos tbody tr").forEach(fila => {
                fila.style.display = fila.textContent.toLowerCase().includes(valor) ? "" : "none";
            });
        });
    </script>
</body>
</html>