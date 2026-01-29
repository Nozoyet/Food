<?php
require_once '../connection/connection.php';
header('Content-Type: application/json');

$ci = $_GET['ci'] ?? null;
$action = $_GET['action'] ?? '';


function asignarPedido($pdo) {
    $pdo->beginTransaction();

    $rep = $pdo->query("
        SELECT CI_R FROM trepartidor 
        WHERE disponibilidad = 1 
        LIMIT 1 FOR UPDATE
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$rep) {
        $pdo->rollBack();
        return;
    }

    $pedido = $pdo->query("
        SELECT id_pedido FROM tpedido
        WHERE estado = 'C'
          AND id_repartidor IS NULL
        ORDER BY fecha, hora_P
        LIMIT 1 FOR UPDATE
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        $pdo->rollBack();
        return;
    }

    $stmt = $pdo->prepare("UPDATE tpedido SET id_repartidor=? WHERE id_pedido=?");
    $stmt->execute([$rep['CI_R'], $pedido['id_pedido']]);

    $stmt = $pdo->prepare("UPDATE trepartidor SET disponibilidad=0 WHERE CI_R=?");
    $stmt->execute([$rep['CI_R']]);

    $pdo->commit();
}

if ($action === 'nombre') {
    $stmt = $pdo->prepare("SELECT nom1, nom2, ap1, ap2 FROM trepartidor WHERE CI_R=?");
    $stmt->execute([$ci]);
    echo json_encode(['success'=>true,'repartidor'=>$stmt->fetch()]);
    exit;
}

if ($action === 'estado') {

    $libre = $pdo->prepare("SELECT disponibilidad FROM trepartidor WHERE CI_R=?");
    $libre->execute([$ci]);
    if ($libre->fetchColumn() == 1) {
        asignarPedido($pdo);
    }

    $stmt = $pdo->prepare("
        SELECT p.id_pedido, p.estado, p.ubicacion, p.total, p.metodo_Pago,
            p.lat_destino, p.lng_destino, r.nombre AS nombre_restaurante
        FROM tpedido p
        LEFT JOIN trestaurante r ON p.id_restaurante = r.id_restaurante
        WHERE p.id_repartidor = ?
        AND p.estado IN ('C')
        LIMIT 1
    ");

    $stmt->execute([$ci]);

    echo json_encode([
        'success'=>true,
        'pedido'=>$stmt->fetch(PDO::FETCH_ASSOC) ?: null
    ]);
    exit;
}

if ($action === 'recogido') {
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("
        UPDATE tpedido 
        SET estado='F' 
        WHERE id_pedido=? AND id_repartidor=? AND estado='C'
    ");
    $stmt->execute([$data['id_pedido'], $ci]);
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'entregado') {
    $data = json_decode(file_get_contents("php://input"), true);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
            UPDATE tpedido 
            SET estado='F', hora_E = CURTIME() 
            WHERE id_pedido=? AND id_repartidor=?
    ");
    $stmt->execute([$data['id_pedido'], $ci]);

    $stmt = $pdo->prepare("UPDATE trepartidor SET disponibilidad=1 WHERE CI_R=?");
    $stmt->execute([$ci]);

    $pdo->commit();

    echo json_encode(['success'=>true]);
    exit;
}
