<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$plantID = $_GET['plantID'] ?? '';
$plantName = '';
$nutritionData = [];

// Validate plantID and get plant info
if (!$plantID) {
    header('Location: plants.php');
    exit;
}

$stmt = $conn->prepare('SELECT plantName FROM plantinfo WHERE plantID = ?');
$stmt->bind_param('i', $plantID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: plants.php');
    exit;
}
$plant = $result->fetch_assoc();
$plantName = $plant['plantName'];
$stmt->close();

// Get nutrition data for this plant
$stmt = $conn->prepare('SELECT * FROM plantnutrionneed WHERE plantID = ? ORDER BY nutritionSetName');
$stmt->bind_param('i', $plantID);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $nutritionData[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Nutrition - Smart Farming</title>
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

        .page-header {
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

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .page-header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4CAF50, #1976D2);
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
            background: linear-gradient(135deg, #4CAF50, #1976D2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .plant-info {
            background: linear-gradient(135deg, rgb(76, 175, 79), rgb(25, 118, 210));
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .plant-info strong {
            color: #ffffff;
            font-weight: 600;
        }

        .plant-info span {
            color: #fdf6f6;
            font-weight: 500;
            font-size: 1.1rem;
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

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049) !important;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3) !important;
        }

        .btn-primary:hover {
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4) !important;
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

        .nutrition-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .nutrition-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .nutrition-table th {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(25, 118, 210, 0.1));
            color: #333;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid rgba(76, 175, 80, 0.2);
            font-size: 0.9rem;
        }

        .nutrition-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 0.9rem;
        }

        .nutrition-table tr:hover {
            background: rgba(76, 175, 80, 0.05);
        }

        .nutrition-set-name {
            background: linear-gradient(135deg, #4CAF50, #45a049) !important;
            color: white !important;
            font-weight: 600 !important;
        }

        .nutrition-set-name td {
            text-align: center !important;
            font-size: 1rem !important;
            padding: 1.2rem 1rem !important;
        }

        .nutrition-values td:first-child {
            font-weight: 600;
            color: #1976D2;
        }

        .nutrition-values td {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-weight: 500;
            color: #555;
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
            
            .nutrition-container {
                padding: 1rem;
                overflow-x: auto;
            }
            
            .nutrition-table {
                min-width: 800px;
            }
            
            .nav-links a {
                display: block;
                margin: 0.5rem auto;
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-leaf"></i>
            </div>
            <h1>Plant Nutrition Details</h1>
            <p>Comprehensive nutrition requirements and soil conditions</p>
        </div>

        <!-- Plant Info -->
        <div class="plant-info">
            <strong><i class="fas fa-seedling"></i> Plant:</strong> <span><?php echo htmlspecialchars($plantName); ?></span>
        </div>

        <!-- Navigation Links -->
        <div class="nav-links">
            <a href="plants.php">
                <i class="fas fa-arrow-left"></i> Back to Plants
            </a>
            <a href="add_nutrition.php?plantID=<?php echo $plantID; ?>" class="btn-primary">
                <i class="fas fa-plus"></i> Add New Nutrition Set
            </a>
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>

        <?php if (empty($nutritionData)): ?>
            <div class="empty-state">
                <div class="icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h3>No Nutrition Data</h3>
                <p>This plant doesn't have any nutrition requirements defined yet. Add nutrition needs to get started.</p>
                <a href="add_nutrition.php?plantID=<?php echo $plantID; ?>" class="btn-primary">
                    <i class="fas fa-plus"></i> Add Nutrition Needs
                </a>
            </div>
        <?php else: ?>
            <!-- Nutrition Data Table -->
            <div class="nutrition-container">
                <table class="nutrition-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-layer-group"></i> Nutrition Set</th>
                            <th><i class="fas fa-leaf"></i> Nitrogen (N)</th>
                            <th><i class="fas fa-seedling"></i> Phosphorus (P)</th>
                            <th><i class="fas fa-tree"></i> Potassium (K)</th>
                            <th><i class="fas fa-bolt"></i> Electrical Conductivity</th>
                            <th><i class="fas fa-tint"></i> pH</th>
                            <th><i class="fas fa-thermometer-half"></i> Temperature (°C)</th>
                            <th><i class="fas fa-tint"></i> Moisture (%)</th>
                            <th><i class="fas fa-water"></i> Flow Rate (L/min)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nutritionData as $nutrition): ?>
                            <tr class="nutrition-set-name">
                                <td colspan="9">
                                    <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($nutrition['nutritionSetName']); ?>
                                </td>
                            </tr>
                            <tr class="nutrition-values">
                                <td><strong>Values</strong></td>
                                <td><?php echo $nutrition['soilN'] !== null ? htmlspecialchars($nutrition['soilN']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilP'] !== null ? htmlspecialchars($nutrition['soilP']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilK'] !== null ? htmlspecialchars($nutrition['soilK']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilEC'] !== null ? htmlspecialchars($nutrition['soilEC']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilPH'] !== null ? htmlspecialchars($nutrition['soilPH']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilT'] !== null ? htmlspecialchars($nutrition['soilT']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilM'] !== null ? htmlspecialchars($nutrition['soilM']) : '-'; ?></td>
                                <td><?php echo $nutrition['flowRate'] !== null ? htmlspecialchars($nutrition['flowRate']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 