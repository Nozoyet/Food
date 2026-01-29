<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../connection/connection.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['productos']) || empty($data['razon_social']) || !isset($data['subtotal'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

if (!isset($_SESSION['ci_cliente']) || !is_numeric($_SESSION['ci_cliente'])) {
    echo json_encode(['success' => false, 'message' => 'Cliente no autenticado']);
    exit;
}

$id_cliente = (int) $_SESSION['ci_cliente'];

try {
    $pdo->beginTransaction();

    // Validar que todos los productos sean del mismo restaurante
    $restaurantes = array_unique(array_column($data['productos'], 'id_restaurante'));
    if (count($restaurantes) !== 1) {
        throw new Exception('Solo se permite pedir de un restaurante a la vez');
    }
    $id_restaurante = (int) $restaurantes[0];

    // Obtener ubicación del cliente
    $stmt = $pdo->prepare("SELECT latitud, longitud FROM tcliente WHERE CI_C = ? AND estado = 1");
    $stmt->execute([$id_cliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente || !$cliente['latitud'] || !$cliente['longitud']) {
        throw new Exception('Cliente sin ubicación registrada');
    }

    $lat = $cliente['latitud'];
    $lon = $cliente['longitud'];

    // Obtener zona y comisión
    $stmt = $pdo->prepare("
        SELECT c.id_comision, c.monto
        FROM tzonas z
        LEFT JOIN tcomisiones c ON c.id_zona = z.id_zona AND c.estado = 1
        WHERE z.estado = 1
          AND ? BETWEEN z.lat_min AND z.lat_max
          AND ? BETWEEN z.lon_min AND z.lon_max
        LIMIT 1
    ");
    $stmt->execute([$lat, $lon]);
    $zona = $stmt->fetch(PDO::FETCH_ASSOC);

    $comision = $zona['monto'] ?? 0.00;
    $id_comision = $zona['id_comision'] ?? null;

    $total_final = $data['subtotal'] + $comision;

    // Crear pedido
    $stmt = $pdo->prepare("
        INSERT INTO tpedido (
            razon_social, fecha, hora_P, estado, total, metodo_Pago,
            id_comision, ubicacion, id_cliente, id_restaurante,
            lat_destino, lng_destino, usuarioA, fechaA, estadoA
        ) VALUES (
            ?, CURDATE(), CURTIME(), 'R', ?, 'QR',
            ?, 'Ubicación cliente', ?, ?,
            ?, ?, 'cliente_web', NOW(), 1
        )
    ");
    $stmt->execute([
        $data['razon_social'],
        $total_final,
        $id_comision,
        $id_cliente,
        $id_restaurante,
        $lat,
        $lon
    ]);

    $id_pedido = $pdo->lastInsertId();

    // Detalle del pedido
    $stmt = $pdo->prepare("
        INSERT INTO tdetallepedido (cantidad, subtotal, id_pedido, id_producto, usuarioA, fechaA)
        VALUES (?, ?, ?, ?, 'cliente_web', NOW())
    ");

    foreach ($data['productos'] as $p) {
        $subtotal_item = $p['precio_u'] * $p['cantidad'];
        $stmt->execute([$p['cantidad'], $subtotal_item, $id_pedido, $p['id_producto']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id_pedido' => $id_pedido,
        'total' => $total_final,
        'comision' => $comision
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}