<?php
session_start();
require_once '../db.php';
require_once '../includes/notification.php';
require_once '../includes/sensor_data_includes.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$plantID = $_GET['plantID'] ?? '';
$plantName = '';
$nutritionData = [];
$fertilizerData = [];

// Validate plantID and get plant info
if (!$plantID) {
    header('Location: plants.php');
    exit;
}

$stmt = $conn->prepare('SELECT plantName FROM plantinfo WHERE plantID = ? AND userID = ?');
$stmt->bind_param('ii', $plantID, $_SESSION['userID']);
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
$stmt = $conn->prepare('SELECT * FROM plantnutrionneed WHERE plantID = ? AND userID = ? ORDER BY nutritionSetName ASC, nutritionID ASC');
$stmt->bind_param('ii', $plantID, $_SESSION['userID']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $nutritionData[] = $row;
}
$stmt->close();

foreach ($nutritionData as $nutrition) {
    $stmt = $conn->prepare('SELECT fertilizerName, fertilizerAmount FROM fertilizer WHERE nutritionID = ?');
    $stmt->bind_param('i', $nutrition['nutritionID']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fertRow = $res->fetch_assoc()) {
        $fertilizerData[$nutrition['nutritionID']][] = $fertRow;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Nutrition - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/view_nutrition.css" rel="stylesheet">
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
                            <th><i class="fas fa-layer-group"></i> Soil Type</th>
                            <th><i class="fas fa-water"></i> Moisture Threshold</th>
                            <th><i class="fas fa-tree"></i> Growth Stage</th>
                            <th><i class="fas fa-leaf"></i> Nitrogen (N)</th>
                            <th><i class="fas fa-seedling"></i> Phosphorus (P)</th>
                            <th><i class="fas fa-tree"></i> Potassium (K)</th>
                            <th><i class="fas fa-bolt"></i> Electrical Conductivity</th>
                            <th><i class="fas fa-tint"></i> pH</th>
                            <th><i class="fas fa-water"></i> Liquid Volume (ml)</th>
                            <th><i class="fas fa-poo-storm"></i> Fertilizer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $nutritionSetStages = '';
                        foreach ($nutritionData as $nutrition):
                            if ($nutritionSetStages !== $nutrition['nutritionSetName']):
                                $nutritionSetStages = $nutrition['nutritionSetName'];
                        ?>
                            <tr class="nutrition-set-name">
                                <td colspan="12">
                                    <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($nutrition['nutritionSetName']); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr class="nutrition-values">
                                <td><strong>Values</strong></td>
                                <td><?php echo $nutrition['soilType'] !== null ? htmlspecialchars($nutrition['soilType']) : '-'; ?></td>
                                <td><?php echo $nutrition['meanMoistureThreshold'] !== null ? htmlspecialchars($nutrition['meanMoistureThreshold']) . " | " . htmlspecialchars($nutrition['meanMoistureThreshold'] + 5) . " | " . htmlspecialchars($nutrition['meanMoistureThreshold'] + 10) : '-'; ?></td>
                                <td><?php echo $nutrition['growthStage'] !== null ? htmlspecialchars($nutrition['growthStage']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilN'] !== null ? htmlspecialchars($nutrition['soilN']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilP'] !== null ? htmlspecialchars($nutrition['soilP']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilK'] !== null ? htmlspecialchars($nutrition['soilK']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilEC'] !== null ? htmlspecialchars($nutrition['soilEC']) : '-'; ?></td>
                                <td><?php echo $nutrition['soilPH'] !== null ? htmlspecialchars($nutrition['soilPH']) : '-'; ?></td>
                                <td><?php echo $nutrition['liquidVolume'] !== null ? htmlspecialchars($nutrition['liquidVolume']) : '-'; ?></td>
                                <td>
                                    <?php 
                                    if (isset($fertilizerData[$nutrition['nutritionID']])) {
                                        foreach ($fertilizerData[$nutrition['nutritionID']] as $fertilizer) {
                                            echo htmlspecialchars($fertilizer['fertilizerName']) . " (" . htmlspecialchars($fertilizer['fertilizerAmount']) . "g)<br>";
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
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