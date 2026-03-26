<?php
    require_once '../db.php';
    require_once '../includes/notification.php';
    session_start();

    // Fetch sensor data for testing
    $stmt = $conn->prepare('SELECT s.*, sen.sensorName, f.farmName 
                            FROM sensordata s 
                            JOIN sensorinfo sen 
                            ON s.SoilSensorID = sen.SoilSensorID 
                            JOIN farmlocation f 
                            ON s.locationID = f.locationID 
                            ORDER BY s.DateTime 
                            DESC LIMIT 99'
                            );
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();


    // fetch water level for testing
    $tankQuery = "
        SELECT 
            s.liquidsensorID, 
            s.liquidtankname, 
            t.currentliquidlevel,
            t.dateandtime
        FROM liquidsensorinfo s
        LEFT JOIN liquidlevelsensor t ON s.liquidsensorID = t.liquidsensorID
        WHERE t.liquidsensorreadID = (
            SELECT MAX(liquidsensorreadID)
            FROM liquidlevelsensor
            WHERE liquidsensorID = s.liquidsensorID
        )
    ";
    
    $tankStmt = $conn->prepare($tankQuery);
    $tankStmt->execute(); // Fixed typo here
    $tankResult = $tankStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $tankStmt->close();

    // Tank Dimensions
    $diameter = 0.48;
    $radius = $diameter / 2; // 0.24m
    $tankHeightM = 0.90;

    foreach ($tankResult as $key => $tank) {
        if ($tank['currentliquidlevel'] !== null) {
            
            $emptySpace = $tank['currentliquidlevel'] / 100;
            $liquidHeightMeters = $tankHeightM - $emptySpace;

            if ($liquidHeightMeters < 0) {
                $liquidHeightMeters = 0;
            }
            
            $volume = pi() * pow($radius, 2) * $liquidHeightMeters;
            $currentLiters = $volume * 1000;

            $tankResult[$key]['liters'] = round($currentLiters);
            
        } else {
            $tankResult[$key]['liters'] = 0;
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>Testing Page</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .page-container {
            max-width: 900px;
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
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        .welcome-section p {
            font-size: 1.1rem;
            color: #666;
            font-weight: 400;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
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


        /* // tanks css */

        .tank-container {
            margin: 0 auto;
        }

        .tanks-list {
            width: 100%;
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .tank-card {
            background: #ffffff;
            border-radius: 15px;
            flex: 1; 
            min-width: 200px;
            height: 180px;
            text-align: center;
            padding: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            overflow: hidden;
        }

        .tank-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .tank-card h1 {
            text-align: left;
            font-size: 0.9rem;
            color: #495057;
            margin: 0;
            padding: 10px 10px; 
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .tank-card h2 {
            font-size: 1.5rem;
            color: #007bff;
            margin: auto;
            font-weight: 700;
            padding: 10px;
        }

        /* actuator css */
        .actuators {
            margin-top: 40px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .actuator-header h1 {
            text-align: left;
            font-size: 1.2rem;
            color: #495057;
            margin: 0;
            padding: 10px 10px; 
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .btn-primary {
            background-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0069d9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .liquid-amount-field {
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .liquid-amount-field label {
            font-weight: 500;
            color: #495057;
        }

        .liquid-amount-field input {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1rem;
        }

        .pump-motors {
            background: #ffffff;
            border-radius: 15px;
            flex: 1; 
            min-width: 200px;
            height: 600px;
            padding: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            overflow: hidden;
        }

        .pump-motors:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .mixers {
            background: #ffffff;
            border-radius: 15px;
            flex: 1; 
            min-width: 200px;
            height: 600px;
            padding: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            overflow: hidden;
        }

        .mixers:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .solenoids {
            background: #ffffff;
            border-radius: 15px;
            flex: 1; 
            min-width: 200px;
            height: 600px;
            padding: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            overflow: hidden;
        }

        .solenoids:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

    </style>
</head>
<body>
    <div class="page-container">
        
        <div class="page-header">
            <div class="header-content">
                <div class="welcome-section">
                    <h1>Developer Test Page</h1>
                    <p>This page is used for testing purposes.</p>
                </div>
                <div class="header-actions">
                    <a href="../pages/dashboard.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Exit
                    </a>
                </div>
            </div>
        </div>

        <section class="tanks">
            <div class="tank-container">
                <div class="tanks-list">
                    
                    <?php foreach ($tankResult as $tank): ?>
                        <div class="tank-card" data-liquidsensor-id="<?= $tank['liquidsensorID'] ?? '' ?>">
                            <h1><?php echo htmlspecialchars($tank['liquidtankname'] ?? 'Unknown Tank'); ?></h1>
                            
                            <h2 class="liquid-level"><?php echo htmlspecialchars($tank['currentliquidlevel']); ?>cm | <?php echo htmlspecialchars($tank['liters']); ?>L</h2>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
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
            <div class="pump-motors">
                <div class="actuator-header">
                    <h1><i class="fas fa-tint"></i> Pump Motors</h1>
                </div>
                <div class="button-group">
                    <h3>Tank 1</h3>
                    <button class="btn btn-primary" id="pump-tank-1"><i class="fas fa-tint"></i> Pump</button>
                </div>
                <div class="button-group">
                    <h3>Tank 2</h3>
                    <button class="btn btn-primary" id="pump-tank-2"><i class="fas fa-tint"></i> Pump</button>
                </div>
                <div class="button-group">
                    <h3>Tank 3</h3>
                    <button class="btn btn-primary" id="pump-tank-3"><i class="fas fa-tint"></i> Pump</button>
                </div>
            </div>
            <div class="mixers">
                <div class="actuator-header">
                    <h1><i class="fas fa-cogs"></i> Mixers</h1>
                </div>
                <div class="button-group">
                    <h3>Mixer 1</h3>
                    <button class="btn btn-secondary" id="mixer-tank-1"><i class="fas fa-cogs"></i> Mix</button>
                </div>
                <div class="button-group">
                    <h3>Mixer 2</h3>
                    <button class="btn btn-secondary" id="mixer-tank-2"><i class="fas fa-cogs"></i> Mix</button>
                </div>
            </div>
            <div class="solenoids">
                <div class="actuator-header">
                    <h1><i class="fas fa-shower"></i> Solenoids</h1>
                </div>
                <div class="liquid-amount-field">
                    <label for="liquidAmount">Liquid Amount (mL)</label>
                    <input type="number" id="liquidAmount" name="liquidAmount" step="0.01" min="0">
                </div>
                <div class="button-group">
                    <h3>Solenoid 1</h3>
                    <button class="btn btn-success" id="valve-1"><i class="fas fa-shower"></i> Open/Close</button>
                </div>
                <div class="button-group">
                    <h3>Solenoid 2</h3>
                    <button class="btn btn-success" id="valve-2"><i class="fas fa-shower"></i> Open/Close</button>
                </div>
                <div class="button-group">
                    <h3>Solenoid 3</h3>
                    <button class="btn btn-success" id="valve-3"><i class="fas fa-shower"></i> Open/Close</button>
                </div>
            </div>
        </section>
    </div>
</body>
<script src="test.js"></script>
</html>