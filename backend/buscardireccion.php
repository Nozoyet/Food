<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['q']) || trim($_GET['q']) === '') {
  echo json_encode([]);
  exit;
}

$direccion = urlencode($_GET['q'] . ', La Paz, Bolivia');

$url = "https://nominatim.openstreetmap.org/search?" .
       "format=json&limit=1&countrycodes=bo&" .
       "viewbox=-68.192,-16.456,-68.056,-16.579&bounded=1&" .
       "q={$direccion}";

$opts = [
  "http" => [
    "header" => "User-Agent: FoodCourt-App\r\n"
  ]
];

$context = stream_context_create($opts);
$response = @file_get_contents($url, false, $context);

echo $response ?: json_encode([]);
