<?php
ob_start();
session_start();

require_once '../connection/connection.php';  // verifica que el path sea correcto

header('Content-Type: application/json; charset=utf-8');

// Verificación estricta (solo clientes)
if (!isset($_SESSION['ci_cliente']) || !is_numeric($_SESSION['ci_cliente'])) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado: solo clientes autenticados'
    ]);
    exit;
}

$id_cliente = (int) $_SESSION['ci_cliente'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            id_pedido,
            razon_social,
            fecha,
            hora_P               AS hora,
            total,
            estado,
            metodo_Pago,
            ubicacion,
            id_restaurante,
            id_repartidor
        FROM tpedido
        WHERE id_cliente = ?
          AND estado != 'E'          -- excluimos entregados
          AND estadoA = 1
        ORDER BY fecha DESC, hora_P DESC
        LIMIT 15
    ");
    $stmt->execute([$id_cliente]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Opcional: enriquecer con texto legible del estado
    $mapEstados = [
        'R' => 'Registrado',
        'P' => 'En preparación',
        'L' => 'Listo para entrega',
        'C' => 'En camino',
        'F' => 'Finalizado'   // si usas F en algunos casos
    ];

    foreach ($pedidos as &$pedido) {
        $pedido['estado_texto'] = $mapEstados[$pedido['estado']] ?? $pedido['estado'];
    }
    unset($pedido);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos,
        'count'   => count($pedidos),
        'debug_id_cliente' => $id_cliente   // ← temporal, para depuración
    ]);

} catch (Exception $e) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}