<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

// Fetch sensors
$sensors = $conn->query("SELECT s.*, sd.*, f.* FROM sensorinfo s
    LEFT JOIN sensordata sd ON s.soilSensorID = sd.SoilSensorID
    LEFT JOIN farmlocation f ON sd.locationID = f.locationID
    GROUP BY s.soilSensorID
    ORDER BY s.soilSensorID ASC");

// Fetch sensor-location mapping
$mapQuery = "
    SELECT DISTINCT sl.soilSensorID, l.locationID, l.farmName
    FROM sensordata sl
    JOIN farmlocation l ON sl.locationID = l.locationID
";
$mapResult = $conn->query($mapQuery);

// Build mapping array
$sensorLocations = [];
while ($row = $mapResult->fetch_assoc()) {
    $sensorLocations[$row['soilSensorID']][] = $row;
}

// Handle submission
$selected = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['sensor'] ?? [] as $sensorID) {
        $locationID = $_POST['location'][$sensorID] ?? null;
        if ($locationID) {
            $selected[] = [
                'sensor_id' => $sensorID,
                'location_id' => $locationID
            ];
        }
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
        .pagination-container { display: flex; justify-content: center; align-item: center; margin-top: 2rem; gap: 5px}
        .pagination-link { display: flex; align-item: center; justify-content: center; min-width: 40px; height: 40px; background: rgba(255, 255, 255, 0.9); padding: 0.5rem 0.5rem; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; color: #555; font-weight: 600; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .pagination-link { font-size: 1.1rem;}
        .pagination-link:hover { background: white; color: #667eea; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-decoration: none; }
        .pagination-link.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-color: transparent; box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3); }
        .pagination-link.disabled { background: rgba(255, 255, 255, 0.5); color: #aaa; cursor: not-allowed; pointer-events: none; }
        .pagination-info { text-align: center; margin-top: 1rem; color: rgba(14, 0, 0, 0.9); font-size: 0.9rem; }
        .sensors-container { display: flex; justify-content: center; max-width: 1200px; margin: 0 auto; gap: 1.5rem; }
        .sensor-box { margin-bottom: 15px; padding: 3rem; border: 1px solid #ccc; width: 500px; position: relative; display: flex; border-radius: 10px; background: #f9f9f9; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .sensor-box label { margin: 0 auto; font-size: 1.2rem; font-weight: 600; cursor: pointer; }
        .sensor-box input[type="checkbox"] { margin-right: 1rem; transform: scale(1.5); cursor: pointer; }
        .location-select { margin: 0 auto; display: none; }
        .location-select select { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #ccc; background: #fff; cursor: pointer; transition: all 0.3s ease; min-width: 200px; }
        .send-button { display: block; margin: 2rem auto; padding: 0.75rem 2rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 25px; font-size: 1.1rem; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); transition: all 0.3s ease; }
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

        <div class="sensors-container">
            <form method="POST">
                <?php while ($sensor = $sensors->fetch_assoc()): ?>
                    <div class="sensor-box">
                        <label>
                            <input type="checkbox"
                                name="sensor[]"
                                value="<?= $sensor['soilSensorID'] ?>"
                                onchange="toggleLocation(this)">
                            <?= htmlspecialchars($sensor['sensorName']) ?>
                        </label>

                        <div class="location-select">
                            <select name="location[<?= $sensor['soilSensorID'] ?>]">
                                <option value="">Select location</option>
                                <?php foreach ($sensorLocations[$sensor['soilSensorID']] ?? [] as $loc): ?>
                                    <option value="<?= $loc['locationID'] ?>">
                                        <?= htmlspecialchars($loc['farmName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endwhile; ?>

                <button type="submit" class="send-button">Send to ESP32</button>
            </form>

                <script>
                function toggleLocation(cb) {
                    const locDiv = cb.closest('.sensor-box').querySelector('.location-select');
                    locDiv.style.display = cb.checked ? 'block' : 'none';
                }
                </script>

                <?php if (!empty($selected)): ?>
                <h3>Config Sent to ESP32</h3>
                <pre><?= json_encode($selected, JSON_PRETTY_PRINT); ?></pre>
                <?php endif; ?>
        </div>
    </div>
</body>
</html>