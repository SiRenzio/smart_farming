<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$sensorID = isset($_GET['sensor_id']) ? (int)$_GET['sensor_id'] : 0;
if (!$sensorID) {
    header('Location: sensors.php');
    exit;
}

// Fetch sensor information
$stmt = $conn->prepare('SELECT * FROM sensorinfo WHERE soilSensorID = ?');
if (!$stmt) {
    die('Failed to prepare sensor query: ' . $conn->error);
}
$stmt->bind_param('i', $sensorID);
if (!$stmt->execute()) {
    die('Failed to execute sensor query: ' . $stmt->error);
}
$resultSet = $stmt->get_result();
if (!$resultSet) {
    die('Failed to get sensor result: ' . $stmt->error);
}
$result = $resultSet->fetch_assoc();
$stmt->close();

if (!$result) {
    header('Location: sensors.php');
    exit;
}

// Fetch farm location
$stmt = $conn->prepare('SELECT * FROM farmLocation WHERE locationID = ?');
if (!$stmt) {
    die('Failed to prepare sensor query: ' . $conn->error);
}
$stmt->bind_param('i', $sensorID);
if (!$stmt->execute()) {
    die('Failed to execute sensor query: ' . $stmt->error);
}
$resultSet = $stmt->get_result();
if (!$resultSet) {
    die('Failed to get sensor result: ' . $stmt->error);
}
$farm = $resultSet->fetch_assoc();
$stmt->close();

if (!$result) {
    header('Location: sensors.php');
    exit;
}



// Fetch sensor data for this specific sensor
$data = [];
$stmt = $conn->prepare('SELECT * FROM sensordata WHERE SoilSensorID = ? ORDER BY DateTime DESC');
if (!$stmt) {
    die('Failed to prepare data query: ' . $conn->error);
}
$stmt->bind_param('i', $sensorID);
if (!$stmt->execute()) {
    die('Failed to execute data query: ' . $stmt->error);
}
$resultData = $stmt->get_result();
if (!$resultData) {
    die('Failed to get data result: ' . $stmt->error);
}
while ($row = $resultData->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

if(isset($_POST['delete_data']) && isset($_POST['data_id'])) {
    $sensorID = (int)$_POST['data_id'];

    $deletestmt = $conn->prepare("DELETE FROM sensordata WHERE SensorDataID = ?");
    $deletestmt->bind_param("i", $sensorID);
    if($deletestmt->execute()){
        $success = "Sensor data deleted successfully!";
    }
    else {
         $error = "Failed to delete sensor: " . $conn->error . " (Error Code: " . $conn->errno . ")";
    }
    $deletestmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensor #<?php echo $sensorID; ?> Data - Smart Farming</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .sensor-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .sensor-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2196F3, #1976D2);
        }

        .sensor-header .icon {
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

        .sensor-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .sensor-header p {
            color: #666;
            font-size: 1.1rem;
            font-weight: 500;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .data-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th {
            background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(25, 118, 210, 0.1));
            color: #333;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid rgba(33, 150, 243, 0.2);
            font-size: 0.9rem;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 0.9rem;
        }

        .data-table tr:hover {
            background: rgba(33, 150, 243, 0.05);
        }

        .numeric-value {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-weight: 500;
            color: #555;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 60px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }

        .btn-edit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        .btn-delete:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
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
            
            .sensor-header {
                padding: 1.5rem;
            }
            
            .sensor-header h1 {
                font-size: 1.8rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .data-container {
                padding: 1rem;
                overflow-x: auto;
            }
            
            .data-table {
                min-width: 800px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .action-btn {
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Sensor Header -->
        <div class="sensor-header">
            <div class="icon">
                <i class="fas fa-satellite-dish"></i>
            </div>
            <h1>Sensor #<?php echo htmlspecialchars($result['soilSensorID']); ?></h1>
            <p><i class="fas fa-tag"></i> <?php echo htmlspecialchars($result['sensorName']); ?></p>
        </div>

        <!-- Navigation Links -->
        <div class="nav-links">
            <a href="add_sensor_data.php">
                <i class="fas fa-plus"></i> Add New Data
            </a>
            <a href="edit_sensor.php?id=<?php echo $sensorID; ?>">
                <i class="fas fa-edit"></i> Edit Sensor
            </a>
            <a href="sensors.php">
                <i class="fas fa-arrow-left"></i> Back to Sensors
            </a>
        </div>

        <?php if (empty($data)): ?>
            <div class="empty-state">
                <div class="icon">
                    <i class="fas fa-database"></i>
                </div>
                <h3>No Data Yet</h3>
                <p>This sensor hasn't recorded any readings yet. Add your first data point to get started.</p>
                <a href="add_sensor_data.php">
                    <i class="fas fa-plus"></i> Add Your First Reading
                </a>
            </div>
        <?php else: ?>
            <!-- Statistics Summary -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($data); ?></div>
                    <div class="stat-label">Total Readings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo date('M j', strtotime($data[0]['DateTime'])); ?></div>
                    <div class="stat-label">Latest Reading</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo date('M j', strtotime(end($data)['DateTime'])); ?></div>
                    <div class="stat-label">First Reading</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo date('M j', strtotime($data[0]['DateTime'])) . ' - ' . date('M j', strtotime(end($data)['DateTime'])); ?></div>
                    <div class="stat-label">Date Range</div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="data-container">
                <table class="data-table">
                    <thead>
                        <tr>
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
                                <td><strong><?php echo date('M j, Y g:i A', strtotime($row['DateTime'])); ?></strong></td>
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
                                        <a href="edit_sensor_data.php?id=<?php echo $row['SensorDataID']; ?>" class="action-btn btn-edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this sensor data? This action cannot be undone.');">
                                            <input type="hidden" name="data_id" value="<?php echo $row['SensorDataID']; ?>">
                                            <button type="submit" name="delete_data" class="action-btn btn-delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
