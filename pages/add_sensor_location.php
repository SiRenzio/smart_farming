<?php
session_start();
require_once '../db.php';
require_once '../includes/notification.php';
require_once '../includes/sensor_data_includes.php';

// For testing purposes
require_once '../includes/test_page_button.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sensorLocation = trim($_POST['sensorLocation'] ?? '');
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';

    // Validate
    if (!$sensorLocation) {
        $errors[] = 'Sensor location name is required.';
    }
    if ($latitude === '' || $longitude === '') {
        $errors[] = 'Please pin and confirm a location on the map.';
    }

    if (!$errors) {
        // Insert sensor location to farmlocation table with coordinates
        $sensorlocstmt = $conn->prepare('INSERT INTO farmlocation (userID, farmName, latitude, longitude, dateAdded) VALUES (?, ?, ?, ?, NOW())');
        $sensorlocstmt->bind_param('isdd', $_SESSION['userID'], $sensorLocation, $latitude, $longitude);
        
        if ($sensorlocstmt->execute()) {
            $success = 'Sensor location and coordinates added successfully.';
        } else {
            $errors[] = 'Failed to add location: ' . $conn->error . ' (Error Code: ' . $conn->errno . ')';
        }
        $sensorlocstmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sensor Location - Smart Farming</title>
    
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <link href="../assets/css/add_sensor_location.css" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h1>Add New Sensor Location</h1>
            <p>Indicate the location of your new sensor</p>
        </div>

        <?php if ($errors): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="post" action="add_sensor_location.php" id="locationForm">
                
                <div class="form-group">
                    <label for="sensorLocation">Sensor Location Name *</label>
                    <input type="text" 
                           id="sensorLocation"
                           name="sensorLocation" 
                           class="form-input"
                           placeholder="Enter sensor location (e.g., Field 1, Plot 1)" 
                           value="<?php echo htmlspecialchars($_POST['sensorLocation'] ?? ''); ?>"
                           required>
                </div>

                <input type="hidden" id="latitude" name="latitude" value="">
                <input type="hidden" id="longitude" name="longitude" value="">

                <div class="form-group">
                    <label>Pin Location *</label>
                    
                    <div class="map-container">
                        
                        <div class="map-header search-pin-row">
                            <div class="search-container">
                                <input type="text" id="map-search" class="search-input" placeholder="Search for a place (e.g., Iloilo)..." autocomplete="off">
                                <div id="suggestions" class="suggestions-list"></div>
                            </div>
                            <button type="button" id="btn-pin-me" class="btn-pin-me">
                                <i class="fas fa-location-arrow"></i> Pin my location
                            </button>
                        </div>

                        <div id="map"></div>
                        
                        <div class="map-footer">
                            <div class="coord-display" id="coord-display">Lat: -, Lon: -</div>
                            <button type="button" id="btn-confirm" class="btn-confirm">
                                Confirm Location
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submit-btn" disabled>
                    <i class="fas fa-arrow-right-to-bracket"></i> Submit
                </button>
            </form>

            <div class="nav-links">
                <a href="manage_sensors.php">
                    <i class="fas fa-arrow-left"></i> Back to Sensors
                </a>
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../assets/js/add_farm_location.js"></script>
</body>
</html>