<?php
session_start();
require_once 'db.php';
require_once 'sending.php';

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
$mapResult = $conn->query("SELECT locationID, farmName FROM farmlocation");

while ($row = $mapResult->fetch_assoc()) {
    $locations[] = $row;
}

// Register Senor Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sensorName']) && isset($_POST['modalSensorID'])) {
    $sensorName = trim($_POST['sensorName'] ?? '');
    $sensorID = trim($_POST['modalSensorID']);

    if (empty($sensorName)) {
        $error = "Sensor name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE sensorinfo SET sensorName = ?, isRegistered = 1, dateAdded = NOW() WHERE soilSensorID = ?");
        $stmt->bind_param("si", $sensorName, $sensorID);

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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .page-container { max-width: 1350px; margin: 0 auto; padding: 2rem; }
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
        .sensors-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); width: 100%; max-width: 1350px; margin: 0 auto; gap: 1.5rem; }
        .offline-text {
            position: absolute;
            bottom: 4.5rem;
            left: 1.5rem;
            right: 1.5rem;
            background: #ffebee;
            color: #c62828;
            padding: 0.8rem;
            border-radius: 12px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.1);
        }

        .online-text, .configured-text {
            position: absolute;
            bottom: 7rem;
            left: 1.5rem;
            right: 1.5rem;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 0.8rem;
            border-radius: 12px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(0, 255, 0, 0.1);
        }

        .unregistered-text {
            position: absolute;
            bottom: 5.7rem;
            left: 1.5rem;
            right: 1.5rem;
            background: #fff3e0;
            color: #ef6c00;
            padding: 0.8rem;
            border-radius: 12px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(255, 165, 0, 0.1);
        }

        .unregistered {
            color: #ef6c00;
            font-style: italic;
        }

        .sensor-box {
            padding: 1.8rem;
            border: 1px solid #ccc;
            width: 100%;
            min-height: 280px;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 20px;
            background: #f9f9f9;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .sensor-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #2196F3, #1976D2);
        }
        
        .sensor-box input[type="checkbox"] { margin: 0.2rem; position: absolute; right: 1.5rem; top: 1.8rem; transform: scale(1.5); cursor: pointer;  width: 1.5rem; height: 1.5rem; }
        .location-select { 
            display: none;
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            text-align: center;
        }

        .location-select select { 
            width: 100%; 
            padding: 0.8rem; 
            border-radius: 12px; 
            border: 2px solid #e0e0e0; 
            background: #fafafa; 
            font-size: 1rem;
            cursor: pointer;
            transition: border-color 0.3s;
            margin-top: 5rem;
        }

        .sensor-label {
            position: absolute; 
            top: 1.5rem; 
            left: 6rem; 
            font-size: 1.2rem;
            font-weight: 600; 
            cursor: pointer; 
            padding-bottom: 1rem; 
            width: auto; 
            max-width: calc(100% - 7rem);
        }

        .toggle-label {
            position: absolute; 
            top: 1.5rem; 
            left: 6rem; 
            font-size: 1.2rem;
            font-weight: 600; 
            cursor: pointer; 
            padding-bottom: 1rem; 
            width: auto; 
            max-width: calc(100% - 7rem);
        }

        .send-button {
            display: block;
            width: 100%;
            margin-top: auto;
            padding: 0.85rem 2rem;
            background: #1976D2;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .send-button:hover:not(:disabled) {
            background: #1565C0;
            transform: translateY(-2px);
        }

        .send-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .sensor-box .icon { position: absolute; top: 1.5rem; left: 1.5rem; width: 60px; height: 60px; background: linear-gradient(135deg, #2196F3, #1976D2); border-radius: 15px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem; color: white; }
        .online { position: absolute; top: 3rem; left: 6rem; display: flex; align-items: center; gap: 0.4rem; font-weight: bold;}
        .indicator { width: 12px; height: 12px; background: #4CAF50; border-radius: 50%; box-shadow: 0 0 8px rgba(76, 175, 80, 0.6); }
        .empty-state { font-size: 1.2rem; color: #333; background: rgba(255, 255, 255, 0.8); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .location { position: absolute; top: 1.5rem; right: 2rem; font-size: 1.2rem; font-weight: bold; }
        
        /* Disconnect Button */
        .disconnect {
            width: 100%;
            margin-top: auto;
            padding: 0.85rem 2rem;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .disconnect:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Register Button */
        .register {
            width: 100%;
            margin-top: auto;
            padding: 0.85rem 2rem;
            background: #ff9800;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        /* Modal Backdrop */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        /* Add Sensor Modal */
        .add-sensor-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            width: 500px;
            max-width: 90%;
        }

        body.modal-open {
            overflow: hidden;
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-header h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            padding-left: 3.7rem;
            color: #333;
        }

        .add-sensor-modal .icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .modal-description p {
            color: #666;
            font-size: 1rem;
            line-height: 1.4;
            padding-top: 1rem;
        }

        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 0 0 1rem 0;
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            cursor: pointer;
            font-size: 1.5rem;
            color: #666;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .form-input {
            display: block;
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-input:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
            background: white;
        }

        .form-input::placeholder {
            color: #999;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
            margin-bottom: 1.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }

        .btn-primary {
            background: linear-gradient(135deg, #2196F3, #1976D2) !important;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3) !important;
        }

        .btn-primary:hover {
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4) !important;
        }

        #app-loading {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #app-loading .loader {
            text-align: center;
            color: white;
            font-size: 1.2rem;
        }

        #app-loading i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
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
                <p>No sensors found. <a href="add_sensor.php">Add your first sensor</a> to get started.</p>
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
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Location: <strong class="display-location-name"></strong></span>
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
    <script>
        // UI States
        const UI_STATES = {
            OFFLINE: 'offline',
            ONLINE_IDLE: 'online_idle',
            CONFIGURED: 'configured',
            UNREGISTERED: 'unregistered'
        };

        // Helper to get elements within a sensor box
        function getBoxEls(box) {
            return {
                checkbox: box.querySelector('.sensor-checkbox'),
                locationLabel: box.querySelector('.location'),
                locationSelect: box.querySelector('.location-select'),
                select: box.querySelector('select'),
                sendBtn: box.querySelector('.send-button'),
                disconnectBtn: box.querySelector('.disconnect'),
                statusText: box.querySelector('.status-text'),
                indicator: box.querySelector('.indicator'),
                offlineText: box.querySelector('.offline-text'),
                onlineText: box.querySelector('.online-text'),
                configuredText: box.querySelector('.configured-text'),
                displayName: box.querySelector('.display-location-name'),
                unregisteredText: box.querySelector('.unregistered-text'),
                registerBtn: box.querySelector('.register'),
                addSensorModal: box.querySelector('.add-sensor-modal'),
                formSensorName: box.querySelector('.form-input'),
                sensorNameLabel: box.querySelector('.sensor-name')
            };
        }

        function renderState(box, state) {
            const els = getBoxEls(box);

            switch (state) {
                case UI_STATES.CONFIGURED:
                    els.checkbox.style.display = 'none';
                    els.locationLabel.style.display = 'block';

                     els.locationSelect.style.display = 'none';
                     els.select.disabled = true;

                    els.sendBtn.style.display = 'none';
                    els.disconnectBtn.style.display = 'block';
                    els.configuredText.style.display = 'block';

                    els.statusText.textContent = 'Connected';
                    els.indicator.style.background = '#4CAF50';
                    els.offlineText.style.display = 'none';
                    els.onlineText.style.display = 'none';
                    els.unregisteredText.style.display = 'none';
                    els.registerBtn.style.display = 'none';
                    break;

                case UI_STATES.ONLINE_IDLE:
                    els.checkbox.style.display = 'inline-block';
                    els.checkbox.disabled = false;
                    els.checkbox.checked = false;

                    els.locationLabel.style.display = 'none';
                    els.locationSelect.style.display = 'none';
                    els.configuredText.style.display = 'none';
                    els.select.disabled = false;
                    els.select.value = '';

                    els.sendBtn.style.display = 'block';
                    els.sendBtn.disabled = true;
                    els.disconnectBtn.style.display = 'none';

                    els.statusText.textContent = 'Online';
                    els.indicator.style.background = '#4CAF50';
                    els.offlineText.style.display = 'none';
                    els.onlineText.style.display = 'block';
                    els.unregisteredText.style.display = 'none';
                    els.registerBtn.style.display = 'none';
                    break;

                case UI_STATES.UNREGISTERED:
                    els.checkbox.style.display = 'none';
                    els.checkbox.disabled = true;
                    els.checkbox.checked = false;

                    els.locationLabel.style.display = 'none';
                    els.locationSelect.style.display = 'none';
                    els.configuredText.style.display = 'none';

                    els.sendBtn.style.display = 'none';
                    els.disconnectBtn.style.display = 'none';

                    els.statusText.textContent = 'Unregistered';
                    els.indicator.style.background = '#ff9800';
                    els.offlineText.style.display = 'none';
                    els.onlineText.style.display = 'none';
                    els.unregisteredText.style.display = 'block';
                    els.registerBtn.style.display = 'block';
                    break;  

                case UI_STATES.OFFLINE:
                default:
                    els.checkbox.style.display = 'none';
                    els.checkbox.disabled = true;
                    els.checkbox.checked = false;

                    els.locationLabel.style.display = 'none';
                    els.locationSelect.style.display = 'none';
                    els.configuredText.style.display = 'none';

                    els.sendBtn.style.display = 'none';
                    els.disconnectBtn.style.display = 'none';

                    els.statusText.textContent = 'Offline';
                    els.indicator.style.background = '#f44336';
                    els.offlineText.style.display = 'block';
                    els.onlineText.style.display = 'none';
                    els.unregisteredText.style.display = 'none';
                    els.registerBtn.style.display = 'none';
                    break;
            }
        }

        function toggleLocation(cb) {
            const box = cb.closest('.sensor-box');
            const { locationSelect, select, sendBtn, onlineText } = getBoxEls(box);

            box.dataset.userInteracting = cb.checked ? '1' : '0';
            onlineText.style.display = 'none';

            locationSelect.style.display = cb.checked ? 'block' : 'none';
            select.required = cb.checked;
            if (!cb.checked) {
                select.value = '';
                sendBtn.disabled = true;
            }
        }

        document.querySelectorAll('.location-select select').forEach(select => {
            select.addEventListener('change', () => {
                const box = select.closest('.sensor-box');
                const { checkbox, sendBtn } = getBoxEls(box);
                sendBtn.disabled = !(checkbox.checked && select.value);
            });
        });

        let firstLoadDone = false;

        function updateSensors() {
            fetch('fetch_sensor.php')
                .then(res => res.json())
                .then(data => {
                    const container = document.querySelector('.sensors-container');
                    const emptyState = document.querySelector('.empty-state');

                    if (!firstLoadDone) {
                        document.getElementById('app-loading').style.display = 'none';
                        firstLoadDone = true;
                    }

                    // Handle empty state
                    emptyState.style.display = data.length === 0 ? 'block' : 'none';

                    // Collect sensor IDs from backend
                    const serverIDs = new Set(
                        data.map(s => String(s.soilSensorID))
                    );

                    //  Remove DOM sensors not in DB anymore
                    container.querySelectorAll('.sensor-box').forEach(box => {
                        const id = box.dataset.sensorId;
                        if (!serverIDs.has(id)) {
                            box.remove();
                        }
                    });

                    // Update existing sensors
                    data.forEach(sensor => {
                        const box = document.querySelector(
                            `.sensor-box[data-sensor-id="${sensor.soilSensorID}"]`
                        );

                        // If brand-new sensor appears
                        if (!box) {
                            location.reload();
                            return;
                        }

                        const els = getBoxEls(box);

                        // Update location name if configured
                        if (sensor.sensorStatus == 1 && sensor.isConnected == 1) {
                            if (els.displayName) {
                                els.displayName.textContent = sensor.farmName || 'Unknown';
                            }
                        }

                        if (box.dataset.userInteracting === '1') return;

                        // Render correct UI state
                        if (sensor.isRegistered == 0) {
                            renderState(box, UI_STATES.UNREGISTERED);
                            els.sensorNameLabel.textContent = 'Unknown';
                        } 
                        else if (sensor.sensorStatus == 1 && sensor.isConnected == 1) {
                            renderState(box, UI_STATES.CONFIGURED);
                        } 
                        else if (sensor.sensorStatus == 1) {
                            renderState(box, UI_STATES.ONLINE_IDLE);
                            els.sensorNameLabel.textContent = sensor.sensorName || 'Unknown';
                        } 
                        else {
                            renderState(box, UI_STATES.OFFLINE);
                        }
                    });
                })
                .catch(err => console.error('AJAX error:', err));
        }

        function registerSensor(sensorID) {
            const box = document.querySelector(`.sensor-box[data-sensor-id="${sensorID}"]`);
            const { addSensorModal, formSensorName } = getBoxEls(box);

            formSensorName.value = '';
            addSensorModal.style.display = 'block';
            document.getElementById('modal-backdrop').style.display = 'block';
        }

        function closeRegisterModal() {
            document.querySelectorAll('.add-sensor-modal').forEach(modal => {
                modal.style.display = 'none';
            });
            document.getElementById('modal-backdrop').style.display = 'none';
        }

        function connectSensor  (btn) {
            const box = btn.closest('.sensor-box');
            const { select, displayName } = getBoxEls(box);

            btn.disabled = true;
            const selectLocationName = select.options[select.selectedIndex].text;

            fetch('connect_sensor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sensor_id: box.dataset.sensorId,
                    location_id: select.value,
                })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                if(displayName) displayName.textContent = selectLocationName;
                box.dataset.userInteracting = '0';
                renderState(box, UI_STATES.CONFIGURED);
            })
            .catch(err => {
                alert(err.message || 'Server error');
                btn.disabled = false;
            });
        }

        function disconnectSensor(btn) {
            const box = btn.closest('.sensor-box');

            btn.disabled = true;

            fetch('disconnect_sensor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ sensor_id: btn.dataset.id })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                box.dataset.userInteracting = '0';
                renderState(box, UI_STATES.ONLINE_IDLE);
            })
            .catch(err => alert(err.message || 'Server error'))
            .finally(() => btn.disabled = false);
        }

        // Poll every 3 seconds
        setInterval(updateSensors, 1000);
    </script>
</body>
<div id="app-loading">
    <div class="loader">
        <i class="fas fa-circle-notch fa-spin"></i>
        <p>Loading sensors…</p>
    </div>
</div>
</html>