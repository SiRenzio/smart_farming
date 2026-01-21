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
        sd.*,
        s.soilSensorID,
        s.sensorName,
        fl.locationID,
        COUNT(sd.SensorDataID) AS data_count,
        fl.farmName
    FROM sensordata sd
    LEFT JOIN sensorinfo s ON s.soilSensorID = sd.SoilSensorID
    LEFT JOIN farmlocation fl ON fl.locationID = sd.locationID
    GROUP BY sd.soilSensorID
    ORDER BY sd.soilSensorID
');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $soilSensorID = (int)trim($_POST['soilSensorID'] ?? '');
    $locationID = (int)trim($_POST['locationID'] ?? '');
    $dateTimeRaw = trim($_POST['dateTime'] ?? '');
    
    $processInput = function($val, $type) {
        if ($val === '') return null;
        return ($type === 'float') ? (float)$val : (int)$val;
    };

    $soilN = $processInput($_POST['soilN'] ?? '', 'int');
    $soilP = $processInput($_POST['soilP'] ?? '', 'int');
    $soilK = $processInput($_POST['soilK'] ?? '', 'int');
    $soilEC = $processInput($_POST['soilEC'] ?? '', 'int');
    $soilPH = $processInput($_POST['soilPH'] ?? '', 'float');
    $soilT = $processInput($_POST['soilT'] ?? '', 'float');
    $soilMois = $processInput($_POST['soilMois'] ?? '', 'float');
    $liquidVolume = $processInput($_POST['liquidVolume'] ?? '', 'float');

    if ($dateTimeRaw) {
        $dateTime = date('Y-m-d H:i:s', strtotime($dateTimeRaw));
    } else {
        $errors[] = 'Date and time is required.';
    }

    if (!$soilSensorID) $errors[] = 'Sensor ID is required.';
    if (!$locationID) $errors[] = 'Location ID is required.';

    if ($soilPH !== null && ($soilPH < 0 || $soilPH > 14)) $errors[] = 'Soil pH must be 0-14.';
    if ($soilMois !== null && ($soilMois < 0 || $soilMois > 100)) $errors[] = 'Soil moisture must be 0-100%.';

    if (!$errors) {
        $checkStmt = $conn->prepare("SELECT * FROM sensordata WHERE SoilSensorID = ? ORDER BY DateTime DESC LIMIT 1");
        $checkStmt->bind_param("i", $soilSensorID);
        $checkStmt->execute();
        $lastRow = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        $action = 'INSERT';
        $updateData = []; 
        $fieldsToCheck = [
            'SoilN' => $soilN,
            'SoilP' => $soilP,
            'SoilK' => $soilK,
            'SoilEC' => $soilEC,
            'SoilPH' => $soilPH,
            'SoilT' => $soilT,
            'SoilMois' => $soilMois,
            'liquidVolume' => $liquidVolume
        ];

        if ($lastRow) {
            $canUpdate = true;
            $hasNewData = false;

            foreach ($fieldsToCheck as $colName => $userValue) {
                if ($userValue !== null) {
                    $dbValue = $lastRow[$colName];

                    if (is_null($dbValue)) {
                        $hasNewData = true;
                        $updateData[$colName] = $userValue;
                    } else {
                        $canUpdate = false; 
                        break; 
                    }
                }
            }

            if ($canUpdate && $hasNewData) {
                $action = 'UPDATE';
            }
        }

        if ($action === 'UPDATE') {
            $sqlSet = [];
            $types = "";
            $params = [];

            foreach ($updateData as $col => $val) {
                $sqlSet[] = "$col = ?";
                $types .= (in_array($col, ['SoilPH', 'SoilT', 'SoilMois', 'liquidVolume'])) ? "d" : "i";
                $params[] = $val;
            }

            $types .= "i";
            $params[] = $lastRow['SensorDataID'];

            $sql = "UPDATE sensordata SET " . implode(", ", $sqlSet) . " WHERE SensorDataID = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $success = "Sensor data updated successfully (filled empty slots)!";
            } else {
                $errors[] = "Update failed: " . $stmt->error;
            }
            $stmt->close();

        } else {
            $stmt = $conn->prepare('INSERT INTO sensordata (SoilSensorID, locationID, SoilN, SoilP, SoilK, SoilEC, SoilPH, SoilT, SoilMois, liquidVolume, DateTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            
            $bindN = $soilN ?? null;
            $bindP = $soilP ?? null;
            $bindK = $soilK ?? null;
            $bindEC = $soilEC ?? null;
            $bindPH = $soilPH ?? null;
            $bindT = $soilT ?? null;
            $bindMois = $soilMois ?? null;
            $bindVol = $liquidVolume ?? null;

            $stmt->bind_param('iiiiiidddds', 
                $soilSensorID, $locationID, $bindN, $bindP, $bindK, $bindEC, 
                $bindPH, $bindT, $bindMois, $bindVol, $dateTime
            );

            if ($stmt->execute()) {
                $success = 'New sensor data entry added successfully!';
            } else {
                $errors[] = 'Insert failed: ' . $stmt->error;
            }
            $stmt->close();
        }
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
                            <option value="<?php echo $sensor['soilSensorID']; ?>" data-location="<?php echo $sensor['locationID']; ?>" <?php echo ($_POST['soilSensorID'] ?? '') == $sensor['soilSensorID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sensor['farmName']); ?> - <?php echo htmlspecialchars($sensor['sensorName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="locationID" id="locationID">
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
<script>
    const sensorSelect = document.getElementById('soilSensorID');
    const locationInput = document.getElementById('locationID');

    function setLocationID() {
        const selected = sensorSelect.options[sensorSelect.selectedIndex];
        locationInput.value = selected?.dataset.location || '';
    }

    sensorSelect.addEventListener('change', setLocationID);
    setLocationID();
</script>
</body>
</html>
