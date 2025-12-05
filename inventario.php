<?php
/**
 * GOMISHOT 2.0 - Panel de Inventario
 */

session_start();

// Verificar login
if (!isset($_SESSION['usuario'])) {
    header("Location: ingresar.html");
    exit();
}

// Verificar timeout (1 hora)
if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_inactivo = time() - $_SESSION['ultimo_acceso'];
    if ($tiempo_inactivo > 3600) {
        session_destroy();
        header("Location: ingresar.html");
        exit();
    }
}

$_SESSION['ultimo_acceso'] = time();
$usuario = htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Inventario - GomiShots</title>
    <link rel="stylesheet" href="styles/inventario.css">
</head>
<body>
    <div class="top-bar">
        <div class="logo-text">Gomi<span class="highlight">Shots</span></div>
        <a href="logout.php" class="logout-link">Cerrar Sesión</a>
    </div>
    
    <header class="header">
        <img src="img/disco.png" alt="Disco" class="disco">
        <h1>Bienvenido, <?php echo $usuario; ?>!</h1>
    </header>
    
    <main class="main-container">
        <div class="panel-buttons">
            <a href="ingreso.php" class="boton">📥 Ingreso</a>
            <a href="salidas.php" class="boton">📤 Salidas</a>
            <a href="almacen.php" class="boton">📦 Almacén</a>
            <a href="dashboard.php" class="boton">Dashboard</a>
        </div>
        <img src="img/oso.png" alt="Osito" class="osito-central">
    </main>
</body>
</html>