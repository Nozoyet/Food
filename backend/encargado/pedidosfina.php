<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../connection/connection.php';  

header('Content-Type: application/json');

$ci_e = trim($_POST['ci'] ?? '');

if (empty($ci_e)) {
    echo json_encode(['success' => false, 'message' => 'Falta CI del encargado']);
    exit;
}

$stmt = $pdo->prepare("SELECT id_restaurante, id_rol FROM templeados WHERE CI_E = ? AND estado = 1");
$stmt->execute([$ci_e]);
$encargado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$encargado || $encargado['id_rol'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No eres encargado o no estás activo']);
    exit;
}

$id_restaurante = $encargado['id_restaurante'];

if (!$id_restaurante) {
    echo json_encode([
        'success' => false,
        'message' => 'El encargado no tiene restaurante asignado',
        'debug_ci' => $ci_e,
        'debug_id_restaurante' => 'NULL'
    ]);
    exit;
}

$action = $_POST['action'] ?? 'listar';
$tipo   = trim($_POST['tipo'] ?? 'local');

if ($action === 'detalle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0 || !in_array($tipo, ['local', 'delivery'])) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    if ($tipo === 'local') {
        $sql = "SELECT dv.cantidad, dv.subtotal, p.nombre AS nombre_producto
                FROM tdetalleVenta dv
                JOIN tproducto p ON dv.id_producto = p.id_producto
                JOIN tventas v ON dv.id_venta = v.id_Venta
                WHERE v.id_Venta = ? AND v.id_restaurante = ?";
    } else {
        $sql = "SELECT dp.cantidad, dp.subtotal, p.nombre AS nombre_producto
                FROM tdetallePedido dp
                JOIN tproducto p ON dp.id_producto = p.id_producto
                JOIN tpedido ped ON dp.id_pedido = ped.id_pedido
                WHERE ped.id_pedido = ? AND ped.id_restaurante = ?";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $id_restaurante]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'detalles' => $detalles]);
    exit;
}

if ($tipo === 'local') {
    $sql = "
        SELECT 
            v.id_Venta AS id,
            COALESCE(v.razon_social, 'Venta en local') AS razon_social,
            CONCAT(COALESCE(c.nom1,''), ' ', COALESCE(c.ap1,'')) AS nombre_cliente,
            DATE_FORMAT(v.fecha, '%Y-%m-%d') AS fecha,
            v.total,
            v.estado,
            (SELECT COUNT(*) FROM tdetalleVenta dv WHERE dv.id_venta = v.id_Venta) AS cantidad_productos
        FROM tventas v
        LEFT JOIN tcliente c ON v.id_cliente = c.CI_C
        WHERE v.id_restaurante = ?
          AND v.estado = 'E'
          AND v.estadoA = 1
        ORDER BY v.fecha DESC, v.hora DESC
    ";
    $debug_tipo = 'local (Entregado)';
} else {
    $sql = "
        SELECT 
            p.id_pedido AS id,
            COALESCE(p.razon_social, 'Delivery') AS razon_social,
            CONCAT(COALESCE(c.nom1,''), ' ', COALESCE(c.ap1,'')) AS nombre_cliente,
            DATE_FORMAT(p.fecha, '%Y-%m-%d') AS fecha,
            p.total,
            p.estado,
            (SELECT COUNT(*) FROM tdetallePedido dp WHERE dp.id_pedido = p.id_pedido) AS cantidad_productos
        FROM tpedido p
        LEFT JOIN tcliente c ON p.id_cliente = c.CI_C
        WHERE p.id_restaurante = ?
          AND p.estado IN ('C', 'F')
          AND p.estadoA = 1
        ORDER BY p.fecha DESC, p.hora_P DESC
    ";
    $debug_tipo = 'delivery (En camino y Finalizado)';
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_restaurante]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'items' => $items,
    'debug' => [
        'tipo_ejecutado' => $debug_tipo,
        'id_restaurante_usado' => $id_restaurante,
        'filas_encontradas' => count($items)
    ]
]);