<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$sensorID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$sensorID) {
    header('Location: sensors.php');
    exit;
}
$locID = isset($_GET['loc']) ? (int)$_GET['loc'] : 0;
if (!$locID) {
    header('Location: sensors.php');
    exit;
}

$errors = [];
$success = '';

// Fetch sensor data
$stmt = $conn->prepare('SELECT * FROM sensorinfo WHERE soilSensorID = ?');
$stmt->bind_param('i', $sensorID);
$stmt->execute();
$result = $stmt->get_result();
$sensor = $result->fetch_assoc();
$stmt->close();

// Fetch sensor location
$locstmt = $conn->prepare('SELECT * FROM farmlocation WHERE locationID = ?');
$locstmt->bind_param('i', $locID);
$locstmt->execute();
$result = $locstmt->get_result();
$location = $result->fetch_assoc();
$locstmt->close();

if (!$sensor) {
    header('Location: sensors.php');
    exit;
}
if (!$location) {
    header('Location: sensors.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sensorLocation = trim($_POST['sensorLocation'] ?? '');
    $sensorName = trim($_POST['sensorName'] ?? '');

    // Validate
    if (!$sensorLocation) {
        $errors[] = 'Sensor location is required.';
    }
    if (!$sensorName) {
        $errors[] = 'Sensor name is required';
    }

    if (!$errors) {
        $updateStmt = $conn->prepare('UPDATE sensorinfo SET sensorName = ? WHERE soilSensorID = ?');
        $updateStmt->bind_param('si', $sensorName, $sensorID);
        if ($updateStmt->execute()) {
            $success = 'Sensor updated successfully!';
            // Refresh sensor data
            $stmt = $conn->prepare('SELECT * FROM sensorinfo WHERE soilSensorID = ?');
            $stmt->bind_param('i', $sensorID);
            $stmt->execute();
            $result = $stmt->get_result();
            $sensor = $result->fetch_assoc();
            $stmt->close();
        } else {
            $errors[] = 'Failed to update sensor: ' . $conn->error . ' (Error Code: ' . $conn->errno . ')';
        }
        $updateStmt->close();

        $updatelocStmt = $conn->prepare('UPDATE farmlocation SET farmName = ? WHERE locationID = ?');
        $updatelocStmt->bind_param('si', $sensorLocation, $locID);
        if ($updatelocStmt->execute()) {
            $success = 'Sensor updated successfully!';
            // Refresh sensor data
            $locstmt = $conn->prepare('SELECT * FROM farmlocation WHERE locationID = ?');
            $locstmt->bind_param('i', $locID);
            $locstmt->execute();
            $result = $locstmt->get_result();
            $location = $result->fetch_assoc();
            $locstmt->close();
        } else {
            $errors[] = 'Failed to update sensor: ' . $conn->error . ' (Error Code: ' . $conn->errno . ')';
        }
        $updatelocStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sensor - Smart Farming</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2196F3, #1976D2);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header .icon {
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

        .form-header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: #666;
            font-size: 1rem;
        }

        .sensor-info {
            background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(25, 118, 210, 0.1));
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid rgba(33, 150, 243, 0.2);
        }

        .sensor-info strong {
            color: #1976D2;
            font-weight: 600;
        }

        .sensor-info span {
            color: #333;
            font-weight: 500;
        }

        .message-container {
            margin-bottom: 2rem;
        }

        .error-message {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .success-message {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-group input:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
            background: white;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            margin-bottom: 2rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .nav-links {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .nav-links a {
            display: inline-block;
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
            body {
                padding: 1rem;
            }
            
            .form-container {
                padding: 2rem;
            }
            
            .form-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <!-- Form Header -->
        <div class="form-header">
            <div class="icon">
                <i class="fas fa-edit"></i>
            </div>
            <h1>Edit Sensor</h1>
            <p>Update sensor location and settings</p>
        </div>

        <!-- Sensor Info -->
        <div class="sensor-info">
            <strong>Sensor ID:</strong> <span><?php echo htmlspecialchars($sensor['soilSensorID']); ?></span>
        </div>

        <!-- Messages -->
        <div class="message-container">
            <?php if ($errors): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Edit Form -->
        <form method="post" action="edit_sensor.php?id=<?php echo $sensorID; ?>&loc=<?php echo $locID; ?>">
            <div class="form-group">
                <label for="sensorName">
                    <i class="fas fa-tag"></i> Sensor Name
                </label>
                <input 
                    type="text" 
                    id="sensorName"
                    name="sensorName" 
                    placeholder="Enter sensor Name (e.g., pH Sensor, Moisture Sensor)" 
                    required 
                    value="<?php echo htmlspecialchars($sensor['sensorName']); ?>"
                >
            </div>
            <div class="form-group">
                <label for="sensorLocation">
                    <i class="fas fa-map-marker-alt"></i> Sensor Location
                </label>
                <input 
                    type="text" 
                    id="sensorLocation"
                    name="sensorLocation" 
                    placeholder="Enter sensor location (e.g., Field A, Greenhouse 1)" 
                    required 
                    value="<?php echo htmlspecialchars($location['farmName']); ?>"
                >
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i> Update Sensor
            </button>
        </form>

        <!-- Navigation Links -->
        <div class="nav-links">
            <a href="sensors.php">
                <i class="fas fa-arrow-left"></i> Back to Sensors
            </a>
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
    </div>
</body>
</html>
