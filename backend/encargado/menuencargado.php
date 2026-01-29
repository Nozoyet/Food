<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../connection/connection.php';  

header('Content-Type: application/json');

$ci_e = trim($_POST['ci'] ?? '');

if (empty($ci_e)) {
    echo json_encode(['success' => false, 'message' => 'Cédula requerida']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT r.nombre AS nombre_restaurante
        FROM templeados e
        LEFT JOIN trestaurante r ON e.id_restaurante = r.id_restaurante
        WHERE e.CI_E = ?
          AND e.estado = 1
        LIMIT 1
    ");
    $stmt->execute([$ci_e]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['nombre_restaurante']) {
        echo json_encode([
            'success' => true,
            'nombre_restaurante' => $row['nombre_restaurante']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró restaurante asociado (verifica id_restaurante en templeados)'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en consulta: ' . $e->getMessage()
    ]);
}