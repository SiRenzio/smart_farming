<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Fetch available sensors for dropdown
$sensors = [];
$stmt = $conn->prepare('
    SELECT 
        s.soilSensorID,
        s.sensorName,
        COUNT(sd.SensorDataID) AS data_count,
        MAX(fl.farmName) AS farmName
    FROM sensorinfo s
    LEFT JOIN sensordata sd ON s.soilSensorID = sd.SoilSensorID
    LEFT JOIN farmlocation fl ON fl.locationID = sd.locationID
    GROUP BY s.soilSensorID, s.sensorName
    ORDER BY s.soilSensorID
');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $soilSensorID = (int)trim($_POST['soilSensorID'] ?? '');
    $soilN = trim($_POST['soilN'] ?? '');
    $soilP = trim($_POST['soilP'] ?? '');
    $soilK = trim($_POST['soilK'] ?? '');
    $soilEC = trim($_POST['soilEC'] ?? '');
    $soilPH = trim($_POST['soilPH'] ?? '');
    $soilT = trim($_POST['soilT'] ?? '');
    $soilMois = trim($_POST['soilMois'] ?? '');
    $liquidVolume = trim($_POST['liquidVolume'] ?? '');
    $liquidVolume = trim($_POST['liquidVolume'] ?? '');
    $dateTime = trim($_POST['dateTime'] ?? '');
    
    // Convert HTML datetime-local format to MySQL format
    if ($dateTime) {
        $dateTime = date('Y-m-d H:i:s', strtotime($dateTime));
    }

    // Validate
    if (!$soilSensorID) {
        $errors[] = 'Sensor ID is required.';
    }
    
    if (!$dateTime) {
        $errors[] = 'Date and time is required.';
    }

    // Convert empty strings to NULL for numeric fields
    $soilN = ($soilN === '') ? null : (int)$soilN;
    $soilP = ($soilP === '') ? null : (int)$soilP;
    $soilK = ($soilK === '') ? null : (int)$soilK;
    $soilEC = ($soilEC === '') ? null : (int)$soilEC;
    $soilPH = ($soilPH === '') ? null : (float)$soilPH;
    $soilT = ($soilT === '') ? null : (float)$soilT;
    $soilMois = ($soilMois === '') ? null : (float)$soilMois;
    $liquidVolume = ($liquidVolume === '') ? null : (float)$liquidVolume;
    $liquidVolume = ($liquidVolume === '') ? null : (float)$liquidVolume;
    
    // Validate numeric fields
    $numericFields = ['soilN', 'soilP', 'soilK', 'soilEC', 'soilPH', 'soilT', 'soilMois', 'liquidVolume'];
    $numericFields = ['soilN', 'soilP', 'soilK', 'soilEC', 'soilPH', 'soilT', 'soilMois', 'liquidVolume'];
    foreach ($numericFields as $field) {
        if ($$field !== null && !is_numeric($$field)) {
            $errors[] = ucfirst($field) . ' must be a valid number. Received: "' . $$field . '"';
        }
    }
    
    // Validate specific ranges
    if ($soilPH !== null && ($soilPH < 0 || $soilPH > 14)) {
        $errors[] = 'Soil pH must be between 0 and 14. Received: ' . $soilPH;
    }
    
    if ($soilMois !== null && ($soilMois < 0 || $soilMois > 100)) {
        $errors[] = 'Soil moisture must be between 0% and 100%. Received: ' . $soilMois . '%';
    }

    if (!$errors) {
        $stmt = $conn->prepare('INSERT INTO sensordata (SoilSensorID, SoilN, SoilP, SoilK, SoilEC, SoilPH, SoilT, SoilMois, liquidVolume, DateTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt = $conn->prepare('INSERT INTO sensordata (SoilSensorID, SoilN, SoilP, SoilK, SoilEC, SoilPH, SoilT, SoilMois, liquidVolume, DateTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        // Handle NULL values properly for bind_param
        $bindN = $soilN ?? 0;
        $bindP = $soilP ?? 0;
        $bindK = $soilK ?? 0;
        $bindEC = $soilEC ?? 0;
        $bindPH = $soilPH ?? 0.0;
        $bindT = $soilT ?? 0.0;
        $bindMois = $soilMois ?? 0.0;
        $bindVol = $liquidVolume ?? 0.0;
        
        // Verify the exact count and validate all variables
        $typeString = 'iiiiidddds';
        // Debug: Show each character and its position
        /* for ($i = 0; $i < strlen($typeString); $i++) {
            $char = $typeString[$i];
            $ord = ord($char);
            die("Position $i: '$char' (ASCII: $ord)");
        }
        if (strlen($typeString) !== 10) {
            die('Type string length mismatch: ' . strlen($typeString) . ' (expected 10)');
        } */
        
        // Validate all variables are defined
        $allVars = [$soilSensorID, $bindN, $bindP, $bindK, $bindEC, $bindPH, $bindT, $bindMois, $bindVol, $dateTime];
        foreach ($allVars as $i => $var) {
            if (!isset($var)) {
                die('Variable ' . $i . ' is not set');
            }
        }
        
        $stmt->bind_param($typeString, 
            $soilSensorID, 
            $bindN, 
            $bindP, 
            $bindK, 
            $bindEC, 
            $bindPH, 
            $bindT, 
            $bindMois, 
            $bindVol, 
            $dateTime
        );
        // Try to execute and capture any errors
        $executeResult = $stmt->execute();
        if ($executeResult) {
            $success = 'Sensor data added successfully! <a href="view_sensor_data.php?sensor_id=' . $soilSensorID . '">View sensor data</a> or <a href="sensor_data.php">view all data</a>.';
        } else {
            $errors[] = 'Failed to add sensor data: ' . $conn->error . ' (Error Code: ' . $conn->errno . ')';
            $errors[] = 'Statement Error: ' . $stmt->error . ' (Statement Error Code: ' . $stmt->errno . ')';
            $errors[] = 'SQL Query: INSERT INTO sensordata (SoilSensorID, SoilN, SoilP, SoilK, SoilEC, SoilPH, SoilT, SoilMois, liquidVolume, DateTime) VALUES (' . 
                       $soilSensorID . ', ' . ($soilN ?? 'NULL') . ', ' . ($soilP ?? 'NULL') . ', ' . ($soilK ?? 'NULL') . ', ' . ($soilEC ?? 'NULL') . ', ' . ($soilPH ?? 'NULL') . ', ' . ($soilT ?? 'NULL') . ', ' . ($soilMois ?? 'NULL') . ', ' . ($liquidVolume ?? 'NULL') . ', "' . $dateTime . '")';
            $errors[] = 'SQL Query: INSERT INTO sensordata (SoilSensorID, SoilN, SoilP, SoilK, SoilEC, SoilPH, SoilT, SoilMois, liquidVolume, DateTime) VALUES (' . 
                       $soilSensorID . ', ' . ($soilN ?? 'NULL') . ', ' . ($soilP ?? 'NULL') . ', ' . ($soilK ?? 'NULL') . ', ' . ($soilEC ?? 'NULL') . ', ' . ($soilPH ?? 'NULL') . ', ' . ($soilT ?? 'NULL') . ', ' . ($soilMois ?? 'NULL') . ', ' . ($liquidVolume ?? 'NULL') . ', "' . $dateTime . '")';
            $errors[] = 'Bound Values: SensorID=' . $soilSensorID . ', N=' . $bindN . ', P=' . $bindP . ', K=' . $bindK . ', EC=' . $bindEC . ', pH=' . $bindPH . ', T=' . $bindT . ', Moisture=' . $bindMois . ', Flow=' . $bindFlow . ', DateTime=' . $dateTime;
            $errors[] = 'Original DateTime: ' . ($_POST['dateTime'] ?? 'NOT SET');
            $errors[] = 'Converted DateTime: ' . $dateTime;
            $errors[] = 'Data Types: SensorID=' . gettype($soilSensorID) . ', N=' . gettype($bindN) . ', P=' . gettype($bindP) . ', K=' . gettype($bindK) . ', EC=' . gettype($bindEC) . ', pH=' . gettype($bindPH) . ', T=' . gettype($bindT) . ', Moisture=' . gettype($bindMois) . ', Flow=' . gettype($bindVol) . ', DateTime=' . gettype($dateTime);
            
            // Additional debugging for constraint issues
            $errors[] = 'Sensor ID Type: ' . gettype($soilSensorID) . ' (Value: ' . $soilSensorID . ')';
            $errors[] = 'DateTime Type: ' . gettype($dateTime) . ' (Value: ' . $dateTime . ')';
            
            // Check if sensor exists
            $checkStmt = $conn->prepare('SELECT soilSensorID FROM sensorinfo WHERE soilSensorID = ?');
            $checkStmt->bind_param('i', $soilSensorID);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $errors[] = 'Sensor exists check: ' . ($checkResult->num_rows > 0 ? 'YES' : 'NO') . ' (Rows found: ' . $checkResult->num_rows . ')';
            $checkStmt->close();
        }
        $stmt->close();
    }
}

// Set default date time to current time
$defaultDateTime = date('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sensor Data - Smart Farming</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 600px; margin: 60px auto; background: #fff; padding: 2em; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { text-align: center; }
        form { display: flex; flex-direction: column; gap: 1em; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1em; }
        input[type=text], input[type=number], input[type=datetime-local], select { padding: 0.75em; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #28a745; color: #fff; border: none; padding: 0.75em; border-radius: 4px; font-weight: bold; cursor: pointer; }
        button:hover { background: #218838; }
        .nav-links { text-align: center; margin-top: 1em; }
        .error { color: #b30000; background: #ffe5e5; padding: 0.5em; border-radius: 4px; margin-bottom: 1em; }
        .success { color: #155724; background: #d4edda; padding: 0.5em; border-radius: 4px; margin-bottom: 1em; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .field-group { margin-bottom: 1em; }
        .field-group label { display: block; margin-bottom: 0.5em; font-weight: bold; }
        .optional { color: #6c757d; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Sensor Data</h2>
        
        <?php if ($errors): ?>
            <div class="error">
                <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
            </div>
            <div class="debug-info" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 1em; border-radius: 4px; margin-bottom: 1em; font-family: monospace; font-size: 0.9em;">
                <strong>Debug Info:</strong><br>
                Sensor ID: <?php echo htmlspecialchars($_POST['soilSensorID'] ?? 'NOT SET'); ?><br>
                Date/Time: <?php echo htmlspecialchars($_POST['dateTime'] ?? 'NOT SET'); ?><br>
                N: <?php echo htmlspecialchars($_POST['soilN'] ?? 'NOT SET'); ?><br>
                P: <?php echo htmlspecialchars($_POST['soilP'] ?? 'NOT SET'); ?><br>
                K: <?php echo htmlspecialchars($_POST['soilK'] ?? 'NOT SET'); ?><br>
                EC: <?php echo htmlspecialchars($_POST['soilEC'] ?? 'NOT SET'); ?><br>
                pH: <?php echo htmlspecialchars($_POST['soilPH'] ?? 'NOT SET'); ?><br>
                Temperature: <?php echo htmlspecialchars($_POST['soilT'] ?? 'NOT SET'); ?><br>
                Moisture: <?php echo htmlspecialchars($_POST['soilMois'] ?? 'NOT SET'); ?><br>
                Liquid Volume: <?php echo htmlspecialchars($_POST['liquidVolume'] ?? 'NOT SET'); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (empty($sensors)): ?>
            <div class="error">
                No sensors available. Please <a href="add_sensor.php">add a sensor first</a>.
            </div>
        <?php else: ?>
            <form method="post" action="add_sensor_data.php">
                <div class="field-group">
                    <label for="soilSensorID">Sensor *</label>
                    <select name="soilSensorID" id="soilSensorID" required>
                        <option value="">Select a sensor</option>
                        <?php foreach ($sensors as $sensor): ?>
                            <option value="<?php echo $sensor['soilSensorID']; ?>" <?php echo ($_POST['soilSensorID'] ?? '') == $sensor['soilSensorID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sensor['farmName']); ?> - <?php echo htmlspecialchars($sensor['sensorName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="field-group">
                    <label for="dateTime">Date & Time *</label>
                    <input type="datetime-local" name="dateTime" id="dateTime" required value="<?php echo htmlspecialchars($_POST['dateTime'] ?? $defaultDateTime); ?>">
                </div>
                
                <div class="form-row">
                    <div class="field-group">
                        <label for="soilN">Soil N (Nitrogen)</label>
                        <input type="number" name="soilN" id="soilN" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($_POST['soilN'] ?? ''); ?>">
                        <div class="optional">Optional</div>
                    </div>
                    <div class="field-group">
                        <label for="soilP">Soil P (Phosphorus)</label>
                        <input type="number" name="soilP" id="soilP" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($_POST['soilP'] ?? ''); ?>">
                        <div class="optional">Optional</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="field-group">
                        <label for="soilK">Soil K (Potassium)</label>
                        <input type="number" name="soilK" id="soilK" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($_POST['soilK'] ?? ''); ?>">
                        <div class="optional">Optional</div>
                    </div>
                    <div class="field-group">
                        <label for="soilEC">Soil EC (Electrical Conductivity)</label>
                        <input type="number" name="soilEC" id="soilEC" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($_POST['soilEC'] ?? ''); ?>">
                        <div class="optional">Optional</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="field-group">
                        <label for="soilPH">Soil pH</label>
                        <input type="number" name="soilPH" id="soilPH" step="0.1" min="0" max="14" placeholder="7.0" value="<?php echo htmlspecialchars($_POST['soilPH'] ?? ''); ?>">
                        <div class="optional">Optional (0-14)</div>
                    </div>
                    <div class="field-group">
                        <label for="soilT">Soil Temperature (°C)</label>
                        <input type="number" name="soilT" id="soilT" step="0.1" placeholder="25.0" value="<?php echo htmlspecialchars($_POST['soilT'] ?? ''); ?>">
                        <div class="optional">Optional</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="field-group">
                        <label for="soilMois">Soil Moisture (%)</label>
                        <input type="number" name="soilMois" id="soilMois" step="0.1" min="0" max="100" placeholder="50.0" value="<?php echo htmlspecialchars($_POST['soilMois'] ?? ''); ?>">
                        <div class="optional">Optional (0-100%)</div>
                    </div>
                    <div class="field-group">
                        <label for="liquidVolume">Liquid Volume</label>
                        <input type="number" name="liquidVolume" id="flowRate" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($_POST['liquidVolume'] ?? ''); ?>">
                        <div class="optional">Optional</div>
                    </div>
                </div>
                
                <button type="submit">Add Sensor Data</button>
            </form>
        <?php endif; ?>
        
        <div class="nav-links">
            <a href="dashboard.php">← Back to Dashboard</a> | 
            <a href="sensor_data.php">View All Sensor Data</a>
        </div>
    </div>
</body>
</html>
