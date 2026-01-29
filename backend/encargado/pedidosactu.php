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
$tipo   = $_POST['tipo'] ?? 'local';


if ($action === 'detalle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0 || !in_array($tipo, ['local', 'delivery'])) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    if ($tipo === 'local') {
        $stmt_cliente = $pdo->prepare("
            SELECT CONCAT(COALESCE(c.nom1,''), ' ', COALESCE(c.ap1,'')) AS nombre_cliente
            FROM tventas v
            LEFT JOIN tcliente c ON v.id_cliente = c.CI_C
            WHERE v.id_Venta = ? AND v.id_restaurante = ?
        ");
    } else {
        $stmt_cliente = $pdo->prepare("
            SELECT CONCAT(COALESCE(c.nom1,''), ' ', COALESCE(c.ap1,'')) AS nombre_cliente
            FROM tpedido p
            LEFT JOIN tcliente c ON p.id_cliente = c.CI_C
            WHERE p.id_pedido = ? AND p.id_restaurante = ?
        ");
    }
    $stmt_cliente->execute([$id, $id_restaurante]);
    $cliente = $stmt_cliente->fetchColumn() ?: 'Cliente no registrado';

    if ($tipo === 'local') {
        $sql = "
            SELECT 
                dv.cantidad, 
                dv.subtotal, 
                p.nombre AS nombre_producto,
                p.tiempo_Pre
            FROM tdetalleVenta dv
            JOIN tproducto p ON dv.id_producto = p.id_producto
            WHERE dv.id_venta = ?
        ";
    } else {
        $sql = "
            SELECT 
                dp.cantidad, 
                dp.subtotal, 
                p.nombre AS nombre_producto,
                p.tiempo_Pre
            FROM tdetallePedido dp
            JOIN tproducto p ON dp.id_producto = p.id_producto
            WHERE dp.id_pedido = ?
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'detalles' => $detalles,
        'nombre_cliente' => $cliente  
    ]);
    exit;
}

if ($action === 'cambiar_estado') {
    $id = (int)($_POST['id'] ?? 0);
    $nuevo_estado = trim($_POST['nuevo_estado'] ?? '');

    if ($id <= 0 || !in_array($tipo, ['local', 'delivery']) || empty($nuevo_estado)) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    $transiciones_validas = [
        'R' => 'P',
        'P' => 'L',
        'L' => ($tipo === 'local' ? 'E' : 'C')
    ];

    if ($tipo === 'local') {
        $stmt = $pdo->prepare("SELECT estado FROM tventas WHERE id_Venta = ? AND id_restaurante = ?");
        $stmt->execute([$id, $id_restaurante]);
        $tabla = 'tventas';
        $pk_col = 'id_Venta';
    } else {
        $stmt = $pdo->prepare("SELECT estado FROM tpedido WHERE id_pedido = ? AND id_restaurante = ?");
        $stmt->execute([$id, $id_restaurante]);
        $tabla = 'tpedido';
        $pk_col = 'id_pedido';
    }

    $actual = $stmt->fetchColumn();

    if (!$actual) {
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado o no pertenece al restaurante']);
        exit;
    }

    if (!isset($transiciones_validas[$actual]) || $transiciones_validas[$actual] !== $nuevo_estado) {
        echo json_encode([
            'success' => false,
            'message' => "Transición no permitida: de $actual a $nuevo_estado no es válida"
        ]);
        exit;
    }

    $stmt_update = $pdo->prepare("
        UPDATE $tabla 
        SET estado = ?, 
            fechaA = NOW(), 
            usuarioA = ? 
        WHERE $pk_col = ? 
          AND id_restaurante = ?
    ");
    $stmt_update->execute([$nuevo_estado, 'encargado_' . $ci_e, $id, $id_restaurante]);

    if ($stmt_update->rowCount() === 1) {
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar (posiblemente ya cambiado)']);
    }
    exit;
}
$dia_semana = date('N'); 

if ($tipo === 'local') {
    $sql = "
        SELECT 
            v.id_Venta AS id,
            COALESCE(v.razon_social, 'Venta en local') AS razon_social,
            CONCAT(COALESCE(c.nom1,''), ' ', COALESCE(c.ap1,'')) AS nombre_cliente,
            DATE_FORMAT(v.fecha, '%Y-%m-%d') AS fecha,
            v.fecha AS fecha_sql,  
            v.total,
            v.estado,
            (SELECT COUNT(*) FROM tdetalleVenta dv WHERE dv.id_venta = v.id_Venta) AS cantidad_productos
        FROM tventas v
        LEFT JOIN tcliente c ON v.id_cliente = c.CI_C
        WHERE v.id_restaurante = ?
          AND v.estado IN ('R','P','L')
          AND v.estadoA = 1
    ";
    $debug_tipo = 'local (tventas)';
} else {
    $sql = "
        SELECT 
            p.id_pedido AS id,
            COALESCE(p.razon_social, 'Delivery') AS razon_social,
            CONCAT(COALESCE(c.nom1,''), ' ', COALESCE(c.ap1,'')) AS nombre_cliente,
            DATE_FORMAT(p.fecha, '%Y-%m-%d') AS fecha,
            p.fecha AS fecha_sql,  
            p.total,
            p.estado,
            (SELECT COUNT(*) FROM tdetallePedido dp WHERE dp.id_pedido = p.id_pedido) AS cantidad_productos
        FROM tpedido p
        LEFT JOIN tcliente c ON p.id_cliente = c.CI_C
        WHERE p.id_restaurante = ?
          AND p.estado IN ('R','P','L')
          AND p.estadoA = 1
    ";
    $debug_tipo = 'delivery (tpedido)';
}

if (in_array($dia_semana, [5,6,7])) {  
    $sql .= " ORDER BY cantidad_productos ASC, fecha_sql ASC";
} else {  
    if ($tipo === 'local') {
        $sql .= " ORDER BY fecha_sql ASC, hora ASC";      
    } else {
        $sql .= " ORDER BY fecha_sql ASC, hora_P ASC";    
    }
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_restaurante]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'items' => $items,
    'debug' => [
        'dia_semana_actual' => $dia_semana,
        'orden_usado' => in_array($dia_semana, [5,6,7]) ? 'Por cantidad ascendente' : 'FIFO (fecha ascendente)',
        'tipo_ejecutado' => $debug_tipo,
        'id_restaurante_usado' => $id_restaurante,
        'filas_encontradas' => count($items)
    ]
]);



