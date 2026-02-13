<?php
session_start();
require_once '../db.php';
require_once '../includes/notification.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sensorLocation = trim($_POST['sensorLocation'] ?? '');

    // Validate
    if (!$sensorLocation) {
        $errors[] = 'Sensor location is required.';
    }

    if (!$errors) {
        // Insert sensor location to farmlocation table
        $sensorlocstmt= $conn->prepare('INSERT INTO farmlocation (farmName, dateAdded) VALUES (?, NOW())');
        $sensorlocstmt->bind_param('s', $sensorLocation);
        if ($sensorlocstmt->execute()) {
            $sensorLocationID = $conn->insert_id;
            $success = 'Sensor location added successfully.';
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/add_sensor_location.css" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <!-- Page Header -->
         <div class="page-header">
            <div class="icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h1>Add New Sensor Location</h1>
            <p>Indicate the location of your new sensor</p>
        </div>

        <!-- Form Card -->
        <div class="form-card">
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

            <form method="post" action="add_sensor_location.php">
                <div class="form-group">
                    <label for="sensorLocation">Sensor Location *</label>
                    <input type="text" 
                           id="sensorLocation"
                           name="sensorLocation" 
                           class="form-input"
                           placeholder="Enter sensor location (e.g., Field 1, Plot 1)" 
                           value="<?php echo htmlspecialchars($_POST['sensorLocation'] ?? ''); ?>">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i> Add Sensor Location
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
</body>
</html>