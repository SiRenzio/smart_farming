<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Fetch all sensors
$sensors = [];
$stmt = $conn->prepare("SELECT * FROM sensorinfo");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sensorName = trim($_POST['sensorName'] ?? '');

    // Validate
    if (!$sensorName) {
        $errors[] = 'Sensor name is required.';
    }

    if (!$errors) {
        // Insert sensor name to database
        $namestmt = $conn->prepare('INSERT INTO sensorinfo (sensorName, dateAdded) VALUES (?, NOW())');
        $namestmt->bind_param('s', $sensorName);
        if ($namestmt->execute()) {
            $sensorID = $conn->insert_id; // Get the auto-generated ID
            $success = 'Sensor added successfully! <a href="add_sensor_location.php">Next, add sensor location</a>';
            // Refresh sensor data
            $sensors = [];
            $stmt = $conn->prepare("SELECT * FROM sensorinfo");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $sensors[] = $row;
            }
            $stmt->close();
        } else {
            $errors[] = 'Failed to add sensor: ' . $conn->error . ' (Error Code: ' . $conn->errno . ')';
        }
        $namestmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sensor - Smart Farming</title>
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
                <i class="fas fa-microchip"></i>
            </div>
            <h1>Add New Sensor</h1>
            <p>Deploy a new soil monitoring sensor</p>
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

            <form method="post" action="add_sensor.php">
                <div class="form-group">
                    <label for="sensorName">Sensor Name *</label>
                    <input type="text" 
                           id="sensorName"
                           name="sensorName" 
                           class="form-input"
                           placeholder="Enter sensor name (e.g., Sensor A, Sensor B)" 
                           required 
                           value="<?php echo htmlspecialchars($_POST['sensorName'] ?? ''); ?>">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i> Add Sensor
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
