<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';
$sensorLocationID = 0;
$sensorLocation = '';

// Fetch all sensors
$sensors = [];
$stmt = $conn->prepare("SELECT * FROM sensorinfo");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}
$stmt->close();

// Fetch all locations
$locations = [];
$locstmt = $conn->prepare("SELECT * FROM farmlocation");
$locstmt->execute();
$locresult = $locstmt->get_result();
while ($locrow = $locresult->fetch_assoc()) {
    $locations[] = $locrow;
}
$locstmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $soilSensorID = trim($_POST['soilSensorID'] ?? '');

    if (!empty($_POST['sensorLocation'])) {
        $sensorLocation = trim($_POST['sensorLocation'] ?? '');
    } else if (!empty($_POST['sensorLocID'])) {
        $sensorLocation = trim($_POST['sensorLocID'] ?? '');
    }

    // Validate
    if (!$sensorLocation) {
        $errors[] = 'Sensor location is required.';
    }
    if (!$soilSensorID) {
        $errors[] = 'Sensor ID is required';
    }

    if (!$errors) {
        // Insert sensor location to farmlocation table
        $sensorlocstmt= $conn->prepare('INSERT INTO farmlocation (farmName, dateAdded) VALUES (?, NOW())');
        $sensorlocstmt->bind_param('s', $sensorLocation);
        if ($sensorlocstmt->execute()) {
            $sensorLocationID = $conn->insert_id;
            $success = 'Sensor location addedd successfully.';
        } else {
            $errors[] = 'Failed to add sensor: ' . $conn->error . ' (Error Code: ' . $conn->errno . ')';
        }
        $sensorlocstmt->close();

        // Insert sensor and sensor location to sensordata table
        $stmt = $conn->prepare('INSERT INTO sensordata (soilSensorID, locationID, DateTime) VALUES (?, ?, NOW())');
        $stmt->bind_param('ii', $soilSensorID, $sensorLocationID);
        if ($stmt->execute()) {
            $sensorID = $conn->insert_id; // Get the auto-generated ID
            $success = 'Sensor #' . $sensorID . ' added successfully! <a href="sensors.php">View all sensors</a> or <a href="add_sensor_data.php">add sensor data</a>.';
        } else {
            $errors[] = 'Failed to add sensor location: ' . $conn->error . ' (Error Code: ' . $conn->errno . ')';
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
    <title>Add Sensor Location - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .page-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .page-header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2196F3, #1976D2);
        }

        .error-message {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .success-message {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .success-message a {
            color: white;
            text-decoration: underline;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-input {
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

        .form-group select {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-group select:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
            background: white;
        }

        .separator {
            text-align: center;
            margin-bottom: 1rem;
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

        .nav-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .nav-links a {
            display: inline-block;
            margin: 0 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .nav-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }
            
            .page-header, .form-card {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
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
                <div class="separator">OR</div>
                <div class="form-group">
                    <select name="sensorLocID" id="sensorLocID">
                        <option value="">Select an existing location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location['locationID']; ?>" <?php echo ($_POST['locationID'] ?? '') == $location['locationID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location['farmName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="soilSensorID">Sensor *</label>
                    <select name="soilSensorID" id="soilSensorID">
                        <option value="">Select a sensor</option>
                        <?php foreach ($sensors as $sensor): ?>
                            <option value="<?php echo $sensor['soilSensorID']; ?>" <?php echo ($_POST['soilSensorID'] ?? '') == $sensor['soilSensorID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sensor['sensorName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i> Add Sensor Location
                </button>
            </form>

            <div class="nav-links">
                <a href="dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="sensors.php">
                    <i class="fas fa-list"></i> View All Sensors
                </a>
            </div>
        </div>
    </div>
</body>
</html>