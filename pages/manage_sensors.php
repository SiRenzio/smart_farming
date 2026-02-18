<?php
session_start();
require_once '../db.php';
require_once '../includes/notification.php';

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


// Fetch all farm locations
$locations = [];
$mapResult = $conn->prepare("SELECT locationID, farmName FROM farmlocation WHERE userID = ?");
$mapResult->bind_param("i", $_SESSION['userID']);
$mapResult->execute();
$result = $mapResult->get_result();

while ($row = $result->fetch_assoc()) {
    $locations[] = $row;
}

// Register Senor Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sensorName']) && isset($_POST['modalSensorID'])) {
    $sensorName = trim($_POST['sensorName'] ?? '');
    $sensorID = trim($_POST['modalSensorID']);

    if (empty($sensorName)) {
        $error = "Sensor name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE sensorinfo SET userID = ?, sensorName = ?, isRegistered = 1, dateAdded = NOW() WHERE soilSensorID = ?");
        $stmt->bind_param("isi", $_SESSION['userID'], $sensorName, $sensorID);

        if ($stmt->execute()) {
            $success = "Sensor registered successfully.";
            $_SESSION['success'] = $success;
            header("Location: manage_sensors.php");
        } else {
            $error = "Error registering sensor: " . $conn->error;
        }

        $stmt->close();
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
    <link href="../assets/css/manage_sensors.css" rel="stylesheet">
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
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
        </div>

        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <a href="sensors.php">
                <i class="fas fa-satellite-dish"></i> Sensor Overview
            </a>

            <a href="add_sensor_location.php" class="btn-primary">
                <i class="fas fa-plus"></i> Add New Location
            </a>
            
        </div>

        <div class="sensors-container">
            <div class="empty-state" style="display: none;">
                <p>No sensors found.</p>
            </div>
            <?php while ($sensor = $sensors->fetch_assoc()): ?>
                <div class="sensor-box" data-sensor-id="<?= $sensor['soilSensorID'] ?>">
                    <div class="icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="online">
                        <div class="indicator"></div>
                        <p class="status-text">Offline</p>
                    </div>
                    <label class="sensor-label">
                        <span class="sensor-name"><?= htmlspecialchars($sensor['sensorName']) ?></span>
                    </label>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" 
                            id="cb-<?= $sensor['soilSensorID'] ?>"
                            name="sensor[]" 
                            value="<?= $sensor['soilSensorID'] ?>" 
                            onchange="toggleLocation(this)" 
                            class="sensor-checkbox">
                        <label for="cb-<?= $sensor['soilSensorID'] ?>" class="toggle-label"></label>
                    </div>
                    <div class="online-text" style="display: none;">
                        <i class="fas fa-circle-check"></i>
                        <span>The sensor is online and ready for configuration.</span>
                    </div>
                    <div class="offline-text" style="display: none;">
                        <i class="fas fa-circle-exclamation"></i>
                        <span>The sensor is currently offline. Please ensure it is powered on and connected to the network.</span>
                    </div>
                    <div class="configured-text" style="display: none;">
                        <i class="fas fa-user"></i>
                        <span> Deployed by: <strong class="display-user"></strong></span><br><br>
                        <i class="fas fa-map-marker-alt"></i>
                        <span> Location: <strong class="display-location-name"></strong></span>
                    </div>
                    <div class="unregistered-text" style="display: none;">
                        <i class="fas fa-circle-question"></i>
                        <span>This sensor is unregistered. Please register the sensor before configuration.</span>
                    </div>
                    <span class="location" style="display: none;"></span>
                    <div class="location-select">
                        <select name="location[<?= $sensor['soilSensorID'] ?>]">
                            <option value="">Select Farm Location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['locationID'] ?>">
                                    <?= htmlspecialchars($loc['farmName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="send-button" onclick="connectSensor(this)" disabled>Connect</button>
                    <button class="disconnect" style="display: none;" data-id="<?= htmlspecialchars($sensor['soilSensorID'] ?? '') ?>" onclick="disconnectSensor(this)">Disconnect</button>
                    <button class="register" style="display: none;" onclick="registerSensor(<?= $sensor['soilSensorID'] ?>)">Register</button>
                    <div class="add-sensor-modal" style="display: none;">
                        <div class="modal-header">
                            <div class="icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <h2>Register Sensor</h2>
                            <div class="modal-description">
                                <p>Please provide a name for the sensor to register it in the system.</p>
                            </div>
                        </div>
                        <div class="divider"></div>
                        <div class="close">
                            <i class="fas fa-times" onclick="closeRegisterModal()"></i>
                        </div>
                        <form action="manage_sensors.php" method="post">
                            <div class="form-group">
                                <label for="sensorName" class="form-label">Sensor Name *</label>
                                <input type="text" 
                                    name="sensorName" 
                                    class="form-input"
                                    placeholder="Enter sensor name (e.g., Sensor A, Sensor B)" 
                                    value="<?php echo htmlspecialchars($_POST['sensorName'] ?? ''); ?>">
                            </div>
                            <input type="hidden" value="<?= $sensor['soilSensorID'] ?>" name="modalSensorID">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-plus"></i> Register Sensor
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
            <div id="modal-backdrop" class="modal-backdrop" style="display:none;"></div>
        </div>
    </div>
    <script src="../assets/js/manage_sensors.js" defer></script>
</body>
<div id="app-loading">
    <div class="loader">
        <i class="fas fa-circle-notch fa-spin"></i>
        <p>Loading sensors…</p>
    </div>
</div>
</html>