<?php
function sendToESP32($sensorIP, $sensorID, $locationID) {
    if (empty($sensorIP)) {
        return "No IP address defined";
    }

    $esp32_url = "http://{$sensorIP}/receive";

    $data = [
        "SoilSensorID" => (int)$sensorID,
        "locationID"  => (int)$locationID
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 5
        ]
    ];

    var_dump($esp32_url, $data);

    $context = stream_context_create($options);
    return @file_get_contents($esp32_url, false, $context) ?: "ESP32 not reachable";
}
?>