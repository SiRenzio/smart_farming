<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

// Handle sensor deletion
if (isset($_POST['delete_sensor']) && isset($_POST['sensor_id'])) {
    $sensorID = (int)$_POST['sensor_id'];
    
    // Check if sensor has associated data
    $checkStmt = $conn->prepare('SELECT COUNT(*) as count FROM sensordata WHERE SoilSensorID = ?');
    $checkStmt->bind_param('i', $sensorID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($row['count'] > 0) {
        $error = "Cannot delete sensor. It has associated sensor data records.";
    } else {
        $deleteStmt = $conn->prepare('DELETE FROM sensorinfo WHERE soilSensorID = ?');
        $deleteStmt->bind_param('i', $sensorID);
        if ($deleteStmt->execute()) {
            $success = "Sensor deleted successfully!";
        } else {
            $error = "Failed to delete sensor: " . $conn->error . " (Error Code: " . $conn->errno . ")";
        }
        $deleteStmt->close();
    }
}

// Fetch all sensors
$sensors = [];
$stmt = $conn->prepare('
    SELECT s.*, 
           COUNT(sd.SensorDataID) as data_count
    FROM sensorinfo s 
    LEFT JOIN sensordata sd ON s.soilSensorID = sd.SoilSensorID 
    GROUP BY s.soilSensorID 
    ORDER BY s.soilSensorID
');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}
$stmt->close();

$locations = [];
$locstmt = $conn->prepare('
    SELECT f.*, 
           COUNT(sd.locationID) as data_count
    FROM farmlocation f 
    LEFT JOIN sensordata sd ON f.locationID = sd.locationID
    GROUP BY f.locationID
    ORDER BY f.locationID
');
$locstmt->execute();
$locresult = $locstmt->get_result();
while ($row = $locresult->fetch_assoc()) {
    $locations[] = $row;
}
$locstmt->close();

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
            max-width: 1000px;
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

        .nav-links {
            text-align: center;
            margin-bottom: 2rem;
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

        .sensors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .sensor-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .sensor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2196F3, #1976D2);
        }

        .sensor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .sensor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .sensor-info {
            display: flex;
            align-items: center;
        }

        .sensor-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
            color: white;
        }

        .sensor-details h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .data-count {
            background: linear-gradient(135deg, #FF9800, #F57C00);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
        }

        .sensor-location {
            color: #666;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .sensor-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .action-btn {
            flex: 1;
            min-width: 100px;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }

        .empty-state {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .empty-state .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e0e0e0, #bdbdbd);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: #666;
        }

        .empty-state h3 {
            color: #666;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .empty-state p {
            color: #888;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        .empty-state a {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }

        .empty-state a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .sensors-grid {
                grid-template-columns: 1fr;
            }
            
            .sensor-actions {
                flex-direction: column;
            }
            
            .action-btn {
                min-width: auto;
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
            <h1>Soil Sensors</h1>
            <p>Monitor and manage your deployed sensors</p>
        </div>

        <!-- Messages -->
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

        <!-- Navigation Links -->
        <div class="nav-links">
            <a href="add_sensor.php">
                <i class="fas fa-plus"></i> Add New Sensor
            </a>
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (empty($sensors)): ?>
            <div class="empty-state">
                <div class="icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <h3>No Sensors Yet</h3>
                <p>Start monitoring your soil by deploying your first sensor</p>
                <a href="add_sensor.php">
                    <i class="fas fa-plus"></i> Add Your First Sensor
                </a>
            </div>
        <?php else: ?>
            <div class="sensors-grid">
                <?php foreach ($sensors as $sensor): ?>
                        <div class="sensor-card">
                            <div class="sensor-header">
                                <div class="sensor-info">
                                    <div class="sensor-icon">
                                        <i class="fas fa-satellite-dish"></i>
                                    </div>
                                    <div class="sensor-details">
                                        <h3><?php echo htmlspecialchars($sensor['sensorName']); ?></h3>
                                    </div>
                                </div>
                                <div class="data-count">
                                    <i class="fas fa-database"></i> <?php echo $sensor['data_count']; ?> readings
                                </div>
                            </div>
                            <div class="sensor-location">
                                <i class="fas fa-map-marker-alt"></i> <strong>Location:</strong> <?php echo htmlspecialchars($sensor['sensorName']); ?>
                            </div>
                            
                            <div class="sensor-actions">
                                <a href="view_sensor_data.php?sensor_id=<?php echo $sensor['soilSensorID']; ?>" class="action-btn btn-view">
                                    <i class="fas fa-eye"></i> View Data
                                </a>
                                <a href="edit_sensor.php?id=<?php echo $sensor['soilSensorID']; ?>" class="action-btn btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this sensor? This action cannot be undone.');">
                                    <input type="hidden" name="sensor_id" value="<?php echo $sensor['soilSensorID']; ?>">
                                    <button type="submit" name="delete_sensor" class="action-btn btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
