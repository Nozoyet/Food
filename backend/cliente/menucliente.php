<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../connection/connection.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_restaurantes') {
        $stmt = $pdo->prepare("
            SELECT id_restaurante, nombre, dias_A, horarios_A
            FROM trestaurante
            WHERE estado = 1
            ORDER BY nombre
        ");
        $stmt->execute();
        $restaurantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'restaurantes' => $restaurantes
        ]);
    }

    elseif ($action === 'get_recomendaciones') {
        $ids = [1, 12, 22, 31];
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id_producto, nombre, precio_u AS precio, foto, descripcion
            FROM tproducto
            WHERE id_producto IN ($placeholders)
              AND disponible = 1 
              AND estado = 1
            ORDER BY FIELD(id_producto, " . implode(',', $ids) . ")
        ");
        $stmt->execute($ids);
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'recomendaciones' => $datos
        ]);
    }

    elseif ($action === 'get_promociones') {
        $ids = [7, 18, 26, 35]; 
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id_producto, nombre, precio_u AS precio, foto, descripcion
            FROM tproducto
            WHERE id_producto IN ($placeholders)
              AND disponible = 1 
              AND estado = 1
            ORDER BY FIELD(id_producto, " . implode(',', $ids) . ")
        ");
        $stmt->execute($ids);
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'promociones' => $datos
        ]);
    }
    elseif ($action === 'get_productos_por_restaurante') {
    $id_rest = $_GET['id'] ?? 0;
    if (!$id_rest) {
        echo json_encode(['success' => false, 'message' => 'ID de restaurante requerido']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT p.id_producto, p.nombre, p.precio_u, p.foto, p.descripcion, p.tiempo_Pre,
               r.nombre AS nombre_restaurante,
               p.id_restaurante   -- ← ¡AGREGA ESTA LÍNEA!
        FROM tproducto p
        INNER JOIN trestaurante r ON p.id_restaurante = r.id_restaurante
        WHERE p.id_restaurante = ?
          AND p.disponible = 1
          AND p.estado = 1
        ORDER BY p.nombre
    ");
    $stmt->execute([$id_rest]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $nombre_rest = $productos[0]['nombre_restaurante'] ?? 'Desconocido';

    echo json_encode([
        'success' => true,
        'productos' => $productos,
        'nombre_restaurante' => $nombre_rest
    ]);

}
    else {
        echo json_encode([
            'success' => false,
            'message' => 'Acción no válida'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}