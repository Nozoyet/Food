<?php
require_once '../connection/connection.php';

header('Content-Type: application/json');

$ci_e = trim($_POST['ci'] ?? '');

if (empty($ci_e)) {
    echo json_encode(['success' => false, 'message' => 'Falta CI del encargado']);
    exit;
}

$stmt = $pdo->prepare("SELECT id_restaurante FROM templeados WHERE CI_E = ? AND id_rol = 1 AND estado = 1");
$stmt->execute([$ci_e]);
$encargado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$encargado || !$encargado['id_restaurante']) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado o sin restaurante']);
    exit;
}

$id_restaurante = $encargado['id_restaurante'];

if ($_POST['items'] ?? '') {
    $items = json_decode($_POST['items'], true);
    $total = (float)($_POST['total'] ?? 0);
    $metodo_pago = $_POST['metodo_pago'] ?? 'QR_SIMULADO';

    if (empty($items) || $total <= 0) {
        echo json_encode(['success' => false, 'message' => 'Carrito vacío o total inválido']);
        exit;
    }

    $pdo->beginTransaction();

    try {
        $stmt_venta = $pdo->prepare("
            INSERT INTO tventas (
                razon_social, fecha, hora, estado, total, metodo_Pago,
                id_cliente, id_empleado, id_restaurante, usuarioA, fechaA, estadoA
            ) VALUES (
                'Venta rápida en local', CURDATE(), CURTIME(), 'R', ?, ?,
                NULL, ?, ?, 'sistema', NOW(), 1
            )
        ");
        $stmt_venta->execute([$total, $metodo_pago, $ci_e, $id_restaurante]);
        $id_venta = $pdo->lastInsertId();

        $stmt_detalle = $pdo->prepare("
            INSERT INTO tdetalleVenta (cantidad, subtotal, id_venta, id_producto)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $stmt_detalle->execute([
                $item['cantidad'],
                $item['subtotal'],
                $id_venta,
                $item['id_producto']
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'id_venta' => $id_venta,
            'message' => 'Venta registrada exitosamente'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al registrar: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos']);
}