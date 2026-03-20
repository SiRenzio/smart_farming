<?php
    require_once '../db.php';
    session_start();

    // Fetch sensor data for testing
    $stmt = $conn->prepare('SELECT s.*, sen.sensorName, f.farmName FROM sensordata s JOIN sensorinfo sen ON s.SoilSensorID = sen.SoilSensorID JOIN farmlocation f ON s.locationID = f.locationID ORDER BY s.DateTime DESC LIMIT 99');
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testing Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .page-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
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

        .sensors {
            margin-top: 2rem;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 25px;
        }

        .sensors-container {
            overflow-x: auto;
            max-height: 300px;
        }

        .sensors-table-header {
            text-align: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: #333;
        }

        .sensors-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 1em; 
        }

        .sensors-table th, .sensors-table td { 
            padding: 0.75em; 
            text-align: center; 
            border-bottom: 1px solid #dee2e6; 
        
        }
        .sensors-table th { 
            background: #f8f9fa; 
            font-weight: bold; 
        
        }
        .sensors-table tr:hover { 
            background: #f8f9fa; 
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <h1>Testing Page</h1>
            <p>This page is used for testing purposes.</p>
        </div>

        <div class="nav-links">
            <a href="../pages/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>

        <section class="tanks">

        </section>

        <section class="sensors">
            <h1 class="sensors-table-header">Sensor Data</h1>
            <div class="sensors-container">
                <table class="sensors-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-satellite-dish"></i> Sensor</th>
                            <th><i class="fas fa-calendar"></i> Date & Time</th>
                            <th><i class="fas fa-leaf"></i> N</th>
                            <th><i class="fas fa-seedling"></i> P</th>
                            <th><i class="fas fa-tree"></i> K</th>
                            <th><i class="fas fa-bolt"></i> EC</th>
                            <th><i class="fas fa-tint"></i> pH</th>
                            <th><i class="fas fa-thermometer-half"></i> Temp (°C)</th>
                            <th><i class="fas fa-tint"></i> Moisture (%)</th>
                        </tr>
                    </thead>
                    <tbody id="sensor-data-body">
                        <?php foreach ($result as $row): ?>
                            <tr>
                                <td>
                                    <div class="sensor-info">
                                        <strong><?php echo htmlspecialchars($row['sensorName']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['farmName'] ?? 'Unknown Location'); ?></small>
                                    </div>
                                </td>
                                <td><?php echo date('M j, Y g:i:s A', strtotime($row['DateTime'])); ?></td>
                                <td class="numeric-value"><?php echo $row['SoilN'] !== null ? htmlspecialchars($row['SoilN']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilP'] !== null ? htmlspecialchars($row['SoilP']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilK'] !== null ? htmlspecialchars($row['SoilK']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilEC'] !== null ? htmlspecialchars($row['SoilEC']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilPH'] !== null ? htmlspecialchars($row['SoilPH']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilT'] !== null ? htmlspecialchars($row['SoilT']) : '-'; ?></td>
                                <td class="numeric-value"><?php echo $row['SoilMois'] !== null ? htmlspecialchars($row['SoilMois']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="actuators">

        </section>


    </div>
</body>
</html>