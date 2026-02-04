<?php
$esp32_url = "http://172.18.0.10/receive";

$data = [
    "SoilSensorID"   => 14,
    "locationID" => 13
];

$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'timeout' => 5
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($esp32_url, false, $context);

echo $response;