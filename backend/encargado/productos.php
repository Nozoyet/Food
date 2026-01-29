<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../connection/connection.php';  

header('Content-Type: application/json');


$action = $_POST['action'] ?? '';
$ci_e = trim($_POST['ci'] ?? '');

if (empty($ci_e)) {
    echo json_encode(['success' => false, 'message' => 'Cédula del encargado requerida']);
    exit;
}

$stmt = $pdo->prepare("SELECT id_restaurante, id_rol FROM templeados WHERE CI_E = ? AND estado = 1");
$stmt->execute([$ci_e]);
$encargado = $stmt->fetch();

if (!$encargado || $encargado['id_rol'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado o encargado no encontrado']);
    exit;
}

$id_restaurante = $encargado['id_restaurante'];

switch ($action) {
    case 'listar':
        $stmt_prod = $pdo->prepare("
            SELECT p.id_producto, p.nombre, p.precio_u, p.descripcion, p.tiempo_Pre, p.foto, p.disponible, p.id_categoria
            FROM tproducto p
            WHERE p.id_restaurante = ? AND p.estado = 1
        ");
        $stmt_prod->execute([$id_restaurante]);
        $productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

        $stmt_cat = $pdo->prepare("SELECT id_categoria, nombre FROM tcategorias WHERE estado = 1");
        $stmt_cat->execute();
        $categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'productos' => $productos,
            'categorias' => $categorias
        ]);
        break;

    case 'toggle_disponible':
    $id_producto = (int)($_POST['id_producto'] ?? 0);
    $nuevo_valor = (int)($_POST['disponible'] ?? 0);

    if ($id_producto <= 0 || !in_array($nuevo_valor, [0,1])) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    $stmt_check = $pdo->prepare("
        SELECT id_producto 
        FROM tproducto 
        WHERE id_producto = ? 
          AND id_restaurante = ?
    ");
    $stmt_check->execute([$id_producto, $id_restaurante]);
    
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no pertenece a este restaurante']);
        exit;
    }

    $stmt_update = $pdo->prepare("
        UPDATE tproducto 
        SET disponible = ?, 
            usuarioA = ?, 
            fechaA = NOW()
        WHERE id_producto = ?
    ");
    $stmt_update->execute([$nuevo_valor, 'encargado_' . $ci_e, $id_producto]);

    if ($stmt_update->rowCount() === 1) {
        echo json_encode([
            'success' => true,
            'nuevo_valor' => $nuevo_valor
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo actualizar (rowCount = 0)'
        ]);
    }
    break;

    case 'editar':
        $id_producto = $_POST['id_producto'] ?? 0;
        $nombre = trim($_POST['nombre'] ?? '');
        $precio_u = $_POST['precio_u'] ?? 0;
        $descripcion = trim($_POST['descripcion'] ?? '');
        $tiempo_Pre = trim($_POST['tiempo_Pre'] ?? '');
        $foto = trim($_POST['foto'] ?? '');

        if (empty($nombre) || $precio_u <= 0 || empty($descripcion) || empty($tiempo_Pre) || empty($foto)) {
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
            exit;
        }

        $stmt_check = $pdo->prepare("SELECT id_producto FROM tproducto WHERE id_producto = ? AND id_restaurante = ?");
        $stmt_check->execute([$id_producto, $id_restaurante]);
        if (!$stmt_check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            exit;
        }

        $stmt_update = $pdo->prepare("
            UPDATE tproducto SET 
                nombre = ?, 
                precio_u = ?, 
                descripcion = ?, 
                tiempo_Pre = ?, 
                foto = ?,
                usuarioA = ?, 
                fechaA = NOW()
            WHERE id_producto = ?
        ");
        $stmt_update->execute([$nombre, $precio_u, $descripcion, $tiempo_Pre, $foto, 'encargado_' . $ci_e, $id_producto]);

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción inválida']);
        break;
        case 'crear':
    $nombre       = trim($_POST['nombre'] ?? '');
    $id_categoria = (int)($_POST['id_categoria'] ?? 0);
    $precio_u     = (float)($_POST['precio_u'] ?? 0);
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $tiempo_Pre   = trim($_POST['tiempo_Pre'] ?? '');
    $foto         = trim($_POST['foto'] ?? '');
    $disponible   = (int)($_POST['disponible'] ?? 1);

    if (empty($nombre) || $id_categoria <= 0 || $precio_u <= 0 || 
        empty($descripcion) || empty($tiempo_Pre) || empty($foto)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
        exit;
    }

     $stmt_cat = $pdo->prepare("SELECT id_categoria FROM tcategorias WHERE id_categoria = ?");
    $stmt_cat->execute([$id_categoria]);
    if (!$stmt_cat->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Categoría no válida']);
        exit;
    }

    $stmt_insert = $pdo->prepare("
        INSERT INTO tproducto (
            id_restaurante, id_categoria, nombre, precio_u, disponible, 
            descripcion, tiempo_Pre, foto, usuarioA, fechaA, estado
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1
        )
    ");

    $stmt_insert->execute([
        $id_restaurante,
        $id_categoria,
        $nombre,
        $precio_u,
        $disponible,
        $descripcion,
        $tiempo_Pre,
        $foto,
        'encargado_' . $ci_e
    ]);

    echo json_encode(['success' => true]);
    break;
}