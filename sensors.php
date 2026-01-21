<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

// Handle data deletion
if (isset($_POST['delete_data']) && isset($_POST['data_id'])) {
    $dataID = (int)$_POST['data_id'];
    
    $deleteStmt = $conn->prepare('DELETE FROM sensordata WHERE SensorDataID = ?');
    $deleteStmt->bind_param('i', $dataID);
    if ($deleteStmt->execute()) {
        $success = "Sensor data deleted successfully!";
    } else {
        $error = "Failed to delete sensor data: " . $conn->error . " (Error Code: " . $conn->errno . ")";
    }
    $deleteStmt->close();
}

// fetch data base on filter button inputs

$filterSensor = $_GET['sensor'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo = $_GET['dateTo'] ?? '';

$sql = "SELECT sd.*, si.sensorName, fl.farmName, fl.locationID 
        FROM sensordata sd 
        LEFT JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID 
        LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID 
        WHERE 1=1"; 

$params = [];
$types = "";

if (!empty($filterSensor)) {
    $sql .= " AND sd.SoilSensorID = ?";
    $params[] = $filterSensor;
    $types .= "i";
}

if (!empty($filterLocation)) {
    $sql .= " AND sd.locationID = ?";
    $params[] = $filterLocation;
    $types .= "i";
}

if (!empty($filterDateFrom)) {
    $sql .= " AND sd.DateTime >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}

if (!empty($filterDateTo)) {
    $sql .= " AND sd.DateTime <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}

$sql .= " ORDER BY sd.DateTime DESC";


$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();


// Fetch sensors for dropdown
$sensorsList = [];
$sensorQuery = $conn->query("SELECT * FROM sensorinfo ORDER BY sensorName");
if($sensorQuery) {
    while($row = $sensorQuery->fetch_assoc()) {
        $sensorsList[] = $row;
    }
}

// Fetch locations for dropdown
$locationsList = [];
$locQuery = $conn->query("SELECT * FROM farmlocation ORDER BY farmName");
if($locQuery) {
    while($row = $locQuery->fetch_assoc()) {
        $locationsList[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensors - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* [Styles kept exactly as before] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .page-container { max-width: 1500px; margin: 0 auto; padding: 2rem; }
        .page-header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); text-align: center; }
        .page-header .icon { width: 80px; height: 80px; background: linear-gradient(135deg, #2196F3, #1976D2); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem; color: white; }
        .page-header h1 { font-size: 2.2rem; font-weight: 700; background: linear-gradient(135deg, #2196F3, #1976D2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 0.5rem; }
        .page-header p { color: #666; font-size: 1.1rem; }
        .message-container { margin-bottom: 2rem; }
        .error-message { background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: white; padding: 1rem; border-radius: 12px; margin-bottom: 1rem; text-align: center; font-weight: 500; box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3); }
        .success-message { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 1rem; border-radius: 12px; margin-bottom: 1rem; text-align: center; font-weight: 500; box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); }
        .nav-links { text-align: center; margin-bottom: 2rem; }
        .nav-links a { display: inline-block; margin: 0 0.5rem; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 25px; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
        .nav-links a:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); text-decoration: none; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 1em; }
        .data-table th, .data-table td { padding: 0.75em; text-align: center; border-bottom: 1px solid #dee2e6; }
        .data-table th { background: #f8f9fa; font-weight: bold; }
        .data-table tr:hover { background: #f8f9fa; }
        .btn { padding: 0.5em 1em; border: none; border-radius: 4px; text-decoration: none; font-size: 0.9em; cursor: pointer; }
        .btn-edit { background: #ffc107; color: #212529; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-clear { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        .error { color: #b30000; background: #ffe5e5; padding: 0.5em; border-radius: 4px; margin-bottom: 1em; }
        .success { color: #155724; background: #d4edda; padding: 0.5em; border-radius: 4px; margin-bottom: 1em; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .empty-state { text-align: center; color: #6c757d; padding: 2em; }
        .sensor-info { background: #e3f2fd; padding: 0.5em; border-radius: 4px; margin-bottom: 0.5em; }
        .numeric-value { font-family: monospace; }
        .actions { display: flex; gap: 0.5em; }
        .filters-container { display: flex; flex-wrap: wrap; gap: 2rem; margin-bottom: 2rem; justify-content: center; align-items: flex-end; }
        .filter { display: flex; flex-direction: column; align-items: flex-start; }
        .filter label { font-weight: 500; margin-bottom: 0.3rem; color: #333; }
        .filter select, .filter input { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #ccc; background: #fff; cursor: pointer; transition: all 0.3s ease; min-width: 180px; }
        .filter select:hover, .filter input:hover { border-color: #667eea; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); }
 
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-microchip"></i>
            </div>
            <h1>Soil Sensors</h1>
            <p>Monitor and manage your deployed sensors</p>
        </div>

        <div class="message-container">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="nav-links">
            <a href="add_sensor.php">
                <i class="fas fa-plus"></i> Add New Sensor
            </a>
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="page-header">
            <form method="GET" action="">
                <div class="filters-container">
                    <div class="filter">
                        <label for="sensor">Sensor:</label>
                        <select id="sensor" name="sensor" onchange="this.form.submit()">
                            <option value="">All Sensors</option>
                            <?php foreach ($sensorsList as $s): ?>
                                <option value="<?php echo $s['soilSensorID']; ?>" <?php echo ($filterSensor == $s['soilSensorID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['sensorName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter">
                        <label for="location">Location:</label>
                        <select id="location" name="location" onchange="this.form.submit()">
                            <option value="">All Locations</option>
                            <?php foreach ($locationsList as $l): ?>
                                <option value="<?php echo $l['locationID']; ?>" <?php echo ($filterLocation == $l['locationID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($l['farmName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter">
                        <label for="dateFrom">Date & Time (From)</label>
                        <input type="datetime-local" name="dateFrom" id="dateFrom" value="<?php echo htmlspecialchars($filterDateFrom); ?>" onchange="this.form.submit()">
                    </div>

                    <div class="filter">
                        <label for="dateTo">Date & Time (To)</label>
                        <input type="datetime-local" name="dateTo" id="dateTo" value="<?php echo htmlspecialchars($filterDateTo); ?>" onchange="this.form.submit()">
                    </div>

                    <div class="filter">
                        <label>&nbsp;</label>
                        <a href="sensors.php" class="btn btn-clear">
                            <i class="fa-solid fa-rotate-left"></i> Clear
                        </a>
                    </div>

                </div>
            </form>

            <?php if (empty($data)): ?>
            <div class="empty-state">
                <p>No sensor data found matching your criteria.</p>
                <?php if(!empty($filterSensor) || !empty($filterLocation) || !empty($filterDateFrom)): ?>
                    <p><a href="sensors.php">Clear Filters</a></p>
                <?php else: ?>
                    <p><a href="add_sensor_data.php">Add your first sensor reading</a> to get started.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-satellite-dish"></i> Sensor</th>
                            <th><i class="fas fa-calendar"></i> Date & Time</th>
                            <th><i class="fas fa-leaf"></i> N</th>
                            <th><i class="fas fa-seedling"></i> P</th>
                            <th><i class="fas fa-tree"></i> K</th>
                            <th><i class="fas fa-bolt"></i> EC</th>
                            <th><i class="fas fa-tint"></i> pH</th>
                            <th><i class="fas fa-thermometer-half"></i> Temp (°C)</th>
                            <th><i class="fas fa-tint"></i> Moisture (%)</th>
                            <th><i class="fas fa-water"></i> Liquid Volume</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td>
                                    <div class="sensor-info">
                                        <strong><?php echo htmlspecialchars($row['sensorName']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['farmName'] ?? 'Unknown Location'); ?></small>
                                    </div>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['DateTime'])); ?></td>
                                <td class="numeric-value"><?php echo $row['SoilN'] !== null ? htmlspecialchars($row['SoilN']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilP'] !== null ? htmlspecialchars($row['SoilP']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilK'] !== null ? htmlspecialchars($row['SoilK']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilEC'] !== null ? htmlspecialchars($row['SoilEC']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilPH'] !== null ? htmlspecialchars($row['SoilPH']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilT'] !== null ? htmlspecialchars($row['SoilT']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilMois'] !== null ? htmlspecialchars($row['SoilMois']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['liquidVolume'] !== null ? htmlspecialchars($row['liquidVolume']) : '-'; ?></td>
                                <td>
                                    <div class="actions">
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this sensor data? This action cannot be undone.');">
                                            <input type="hidden" name="data_id" value="<?php echo $row['SensorDataID']; ?>">
                                            <button type="submit" name="delete_data" class="btn btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>