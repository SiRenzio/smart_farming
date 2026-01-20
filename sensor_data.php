<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

// Handle data deletion
if (isset($_POST['delete_data']) && isset($_POST['data_id'])) {
    $dataID = (int)$_POST['data_id'];
    
    $deleteStmt = $conn->prepare('DELETE FROM sensordata WHERE SensorDataID = ?');
    $deleteStmt->bind_param('i', $dataID);
    if ($deleteStmt->execute()) {
        $success = "Sensor data deleted successfully!";
    } else {
        $error = "Failed to delete sensor data: " . $conn->error . " (Error Code: " . $conn->errno . ")";
    }
    $deleteStmt->close();
}

// Fetch all sensor data with sensor location information
$data = [];
$stmt = $conn->prepare('
    SELECT sd.*, si.sensorName, fl.farmName, fl.locationID 
    FROM sensordata sd 
    LEFT JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID 
    LEFT JOIN farmLocation fl ON sd.locationID = fl.locationID 
    ORDER BY sd.DateTime DESC
');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensor Data - Smart Farming</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 60px auto; background: #fff; padding: 2em; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { text-align: center; }
        .nav-links { text-align: center; margin-bottom: 2em; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 1em; }
        .data-table th, .data-table td { padding: 0.75em; text-align: left; border-bottom: 1px solid #dee2e6; }
        .data-table th { background: #f8f9fa; font-weight: bold; }
        .data-table tr:hover { background: #f8f9fa; }
        .btn { padding: 0.5em 1em; border: none; border-radius: 4px; text-decoration: none; font-size: 0.9em; cursor: pointer; }
        .btn-edit { background: #ffc107; color: #212529; }
        .btn-delete { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        .error { color: #b30000; background: #ffe5e5; padding: 0.5em; border-radius: 4px; margin-bottom: 1em; }
        .success { color: #155724; background: #d4edda; padding: 0.5em; border-radius: 4px; margin-bottom: 1em; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .empty-state { text-align: center; color: #6c757d; padding: 2em; }
        .sensor-info { background: #e3f2fd; padding: 0.5em; border-radius: 4px; margin-bottom: 0.5em; }
        .numeric-value { font-family: monospace; }
        .actions { display: flex; gap: 0.5em; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Sensor Data</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="nav-links">
            <a href="add_sensor_data.php" class="btn btn-edit">+ Add New Sensor Data</a> |
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
        
        <?php if (empty($data)): ?>
            <div class="empty-state">
                <p>No sensor data found. <a href="add_sensor_data.php">Add your first sensor reading</a> to get started.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sensor</th>
                        <th>Date & Time</th>
                        <th>N</th>
                        <th>P</th>
                        <th>K</th>
                        <th>EC</th>
                        <th>pH</th>
                        <th>Temp (°C)</th>
                        <th>Moisture (%)</th>
                        <th>Liquid Volume</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td>
                                <div class="sensor-info">
                                    <strong><?php echo htmlspecialchars($row['sensorName']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($row['farmName'] ?? 'Unknown Location'); ?></small>
                                </div>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($row['DateTime'])); ?></td>
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
                                    <a href="edit_sensor_data.php?id=<?php echo $row['SensorDataID']; ?>" class="btn btn-edit">Edit</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this sensor data? This action cannot be undone.');">
                                        <input type="hidden" name="data_id" value="<?php echo $row['SensorDataID']; ?>">
                                        <button type="submit" name="delete_data" class="btn btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
