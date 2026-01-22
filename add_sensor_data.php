<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Fetch available SENSORS for the first dropdown
$sensors = [];
$sensorQuery = $conn->query("SELECT * FROM sensorinfo ORDER BY sensorName ASC");
if ($sensorQuery) {
    while ($row = $sensorQuery->fetch_assoc()) {
        $sensors[] = $row;
    }
}

// Fetch available LOCATIONS for the second dropdown
$locations = [];
$locationQuery = $conn->query("SELECT * FROM farmlocation ORDER BY farmName ASC");
if ($locationQuery) {
    while ($row = $locationQuery->fetch_assoc()) {
        $locations[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get Sensor ID and Location ID separately from POST
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

    if (!$soilSensorID) $errors[] = 'Sensor is required.';
    if (!$locationID) $errors[] = 'Location is required.';

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

            if ($lastRow['locationID'] != $locationID) {
                $canUpdate = false;
            }

            if ($canUpdate) {
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
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" action="add_sensor_data.php">
            
            <div class="form-row">
                <div class="field-group">
                    <label for="soilSensorID">Sensor *</label>
                    <select name="soilSensorID" id="soilSensorID" required>
                        <option value="">Select a Sensor</option>
                        <?php foreach ($sensors as $sensor): ?>
                            <option value="<?php echo $sensor['soilSensorID']; ?>" <?php echo ($_POST['soilSensorID'] ?? '') == $sensor['soilSensorID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sensor['sensorName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-group">
                    <label for="locationID">Location *</label>
                    <select name="locationID" id="locationID" required>
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo $loc['locationID']; ?>" <?php echo ($_POST['locationID'] ?? '') == $loc['locationID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['farmName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                    <label for="soilEC">Soil EC (Conductivity)</label>
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
                    <label for="soilT">Soil Temp (°C)</label>
                    <input type="number" name="soilT" id="soilT" step="0.1" placeholder="25.0" value="<?php echo htmlspecialchars($_POST['soilT'] ?? ''); ?>">
                    <div class="optional">Optional</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="field-group">
                    <label for="soilMois">Moisture (%)</label>
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
        
        <div class="nav-links">
            <a href="dashboard.php">← Back to Dashboard</a> | 
            <a href="sensors.php">View All Sensor Data</a>
        </div>
    </div>
</body>
</html>