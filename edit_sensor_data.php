<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$dataID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$dataID) {
    header('Location: sensor_data.php');
    exit;
}

$errors = [];
$success = '';

// Fetch sensor data
$stmt = $conn->prepare('SELECT * FROM sensordata WHERE SensorDataID = ?');
$stmt->bind_param('i', $dataID);
$stmt->execute();
$result = $stmt->get_result();
$sensorData = $result->fetch_assoc();
$stmt->close();

if (!$sensorData) {
    header('Location: sensor_data.php');
    exit;
}

// Fetch available sensors for dropdown
$sensors = [];
$stmt = $conn->prepare('SELECT soilSensorID, sensorLocation FROM sensorinfo ORDER BY soilSensorID');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $soilSensorID = trim($_POST['soilSensorID'] ?? '');
    $soilN = trim($_POST['soilN'] ?? '');
    $soilP = trim($_POST['soilP'] ?? '');
    $soilK = trim($_POST['soilK'] ?? '');
    $soilEC = trim($_POST['soilEC'] ?? '');
    $soilPH = trim($_POST['soilPH'] ?? '');
    $soilT = trim($_POST['soilT'] ?? '');
    $soilMois = trim($_POST['soilMois'] ?? '');
    $liquidVolume = trim($_POST['liquidVolume'] ?? '');
    $dateTime = trim($_POST['dateTime'] ?? '');

    // Validate
    if (!$soilSensorID) {
        $errors[] = 'Sensor ID is required.';
    }
    
    if (!$dateTime) {
        $errors[] = 'Date and time is required.';
    }

    // Validate numeric fields
    $numericFields = ['soilN', 'soilP', 'soilK', 'soilEC', 'soilPH', 'soilT', 'soilMois', 'liquidVolume'];
    foreach ($numericFields as $field) {
        if ($$field !== '' && !is_numeric($$field)) {
            $errors[] = ucfirst($field) . ' must be a valid number. Received: "' . $$field . '"';
        }
    }
    
    // Validate specific ranges
    if ($soilPH !== '' && ($soilPH < 0 || $soilPH > 14)) {
        $errors[] = 'Soil pH must be between 0 and 14. Received: ' . $soilPH;
    }
    
    if ($soilMois !== '' && ($soilMois < 0 || $soilMois > 100)) {
        $errors[] = 'Soil moisture must be between 0% and 100%. Received: ' . $soilMois . '%';
    }

    if (!$errors) {
        $updateStmt = $conn->prepare('UPDATE sensordata SET SoilSensorID = ?, SoilN = ?, SoilP = ?, SoilK = ?, SoilEC = ?, SoilPH = ?, SoilT = ?, SoilMois = ?, liquidVolume = ?, DateTime = ? WHERE SensorDataID = ?');
        $updateStmt->bind_param('iiiiiddddsi', $soilSensorID, $soilN, $soilP, $soilK, $soilEC, $soilPH, $soilT, $soilMois, $liquidVolume, $dateTime, $dataID);
        if ($updateStmt->execute()) {
            $success = 'Sensor data updated successfully!';
            // Refresh sensor data
            $stmt = $conn->prepare('SELECT * FROM sensordata WHERE SensorDataID = ?');
            $stmt->bind_param('i', $dataID);
            $stmt->execute();
            $result = $stmt->get_result();
            $sensorData = $result->fetch_assoc();
            $stmt->close();
        } else {
            $errors[] = 'Failed to update sensor data: ' . $conn->error . ' (Error Code: ' . $conn->errno . ')';
        }
        $updateStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sensor Data - Smart Farming</title>
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
        .data-info { background: #f8f9fa; padding: 1em; border-radius: 4px; margin-bottom: 1em; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Sensor Data</h2>
        
        <div class="data-info">
            <strong>Data ID:</strong> <?php echo htmlspecialchars($sensorData['SensorDataID']); ?>
        </div>
        
        <?php if ($errors): ?>
            <div class="error">
                <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="post" action="edit_sensor_data.php?id=<?php echo $dataID; ?>">
            <div class="field-group">
                <label for="soilSensorID">Sensor *</label>
                <select name="soilSensorID" id="soilSensorID" required>
                    <option value="">Select a sensor</option>
                    <?php foreach ($sensors as $sensor): ?>
                        <option value="<?php echo $sensor['soilSensorID']; ?>" <?php echo ($sensorData['SoilSensorID'] == $sensor['soilSensorID']) ? 'selected' : ''; ?>>
                            Sensor #<?php echo htmlspecialchars($sensor['soilSensorID']); ?> - <?php echo htmlspecialchars($sensor['sensorLocation']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="field-group">
                <label for="dateTime">Date & Time *</label>
                <input type="datetime-local" name="dateTime" id="dateTime" required value="<?php echo date('Y-m-d\TH:i', strtotime($sensorData['DateTime'])); ?>">
            </div>
            
            <div class="form-row">
                <div class="field-group">
                    <label for="soilN">Soil N (Nitrogen)</label>
                    <input type="number" name="soilN" id="soilN" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($sensorData['SoilN'] ?? ''); ?>">
                    <div class="optional">Optional</div>
                </div>
                <div class="field-group">
                    <label for="soilP">Soil P (Phosphorus)</label>
                    <input type="number" name="soilP" id="soilP" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($sensorData['SoilP'] ?? ''); ?>">
                    <div class="optional">Optional</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="field-group">
                    <label for="soilK">Soil K (Potassium)</label>
                    <input type="number" name="soilK" id="soilK" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($sensorData['SoilK'] ?? ''); ?>">
                    <div class="optional">Optional</div>
                </div>
                <div class="field-group">
                    <label for="soilEC">Soil EC (Electrical Conductivity)</label>
                    <input type="number" name="soilEC" id="soilEC" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($sensorData['SoilEC'] ?? ''); ?>">
                    <div class="optional">Optional</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="field-group">
                    <label for="soilPH">Soil pH</label>
                    <input type="number" name="soilPH" id="soilPH" step="0.1" min="0" max="14" placeholder="7.0" value="<?php echo htmlspecialchars($sensorData['SoilPH'] ?? ''); ?>">
                    <div class="optional">Optional (0-14)</div>
                </div>
                <div class="field-group">
                    <label for="soilT">Soil Temperature (°C)</label>
                    <input type="number" name="soilT" id="soilT" step="0.1" placeholder="25.0" value="<?php echo htmlspecialchars($sensorData['SoilT'] ?? ''); ?>">
                    <div class="optional">Optional</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="field-group">
                    <label for="soilMois">Soil Moisture (%)</label>
                    <input type="number" name="soilMois" id="soilMois" step="0.1" min="0" max="100" placeholder="50.0" value="<?php echo htmlspecialchars($sensorData['SoilMois'] ?? ''); ?>">
                    <div class="optional">Optional (0-100%)</div>
                </div>
                <div class="field-group">
                    <label for="liquidVolume">Liquid Volume (L/min)</label>
                    <input type="number" name="liquidVolume" id="liquidVolume" step="0.1" placeholder="0" value="<?php echo htmlspecialchars($sensorData['liquidVolume'] ?? ''); ?>">
                    <div class="optional">Optional</div>
                </div>
            </div>
            
            <button type="submit">Update Sensor Data</button>
        </form>
        
        <div class="nav-links">
            <a href="sensor_data.php">← Back to Sensor Data</a> | 
            <a href="dashboard.php">Dashboard</a>
        </div>
    </div>
</body>
</html>
