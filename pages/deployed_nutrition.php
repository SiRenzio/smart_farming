<?php
session_start();
require_once '../db.php';
require_once '../includes/notification.php';
require_once '../includes/sensor_data_includes.php';

// For testing purposes
require_once '../includes/test_page_button.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$nutritionID = $_GET['nutritionID'] ?? '';
$plantName = '';
$nutritionData = [];
$fertilizerData = [];
$plantID = $_GET['plantID'] ?? '';

// Validate nutritionID and get plant info
if (!$nutritionID) {
    header('Location: plants.php');
    exit;
}

$stmt = $conn->prepare('SELECT p.plantName, p.plantVariety FROM plantinfo p JOIN plantnutrionneed n ON p.plantID = n.plantID WHERE n.nutritionID = ? AND p.userID = ?');
$stmt->bind_param('ii', $nutritionID, $_SESSION['userID']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: plants.php');
    exit;
}
$plant = $result->fetch_assoc();
$plantName = $plant['plantName'];
$plantVariety = $plant['plantVariety'];
$stmt->close();

// Get nutrition data for this plant
$stmt = $conn->prepare('SELECT * FROM plantnutrionneed WHERE nutritionID = ? AND userID = ? ORDER BY nutritionSetName');
$stmt->bind_param('ii', $nutritionID, $_SESSION['userID']);
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
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/view_nutrition.css" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-leaf"></i>
            </div>
            <h1>Deployed Plant Nutrition</h1>
            <p>Comprehensive nutrition requirements and soil conditions</p>
        </div>

        <!-- Plant Info -->
        <div class="plant-info">
            <strong><i class="fas fa-seedling"></i> Plant:</strong> 
            <span><?php echo htmlspecialchars($plantName); ?></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;    
            <strong><i class="fas fa-dna"></i> Variety:</strong> 
            <span><?php echo htmlspecialchars($plantVariety); ?></span>
        </div>

        <!-- Navigation Links -->
        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Dashboard
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
                            <th><i class="fas fa-layer-group"></i> Soil Type</th>
                            <th><i class="fas fa-water"></i> Moisture Threshold</th>
                            <th><i class="fas fa-tree"></i> Growth Stage</th>
                            <th><i class="fas fa-thermometer-half"></i> Temperature</th>
                            <th><i class="fas fa-leaf"></i> Nitrogen (N)</th>
                            <th><i class="fas fa-poo-storm"></i> Fertilizer</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
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
                                <td colspan="8">
                                    <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($nutrition['nutritionSetName']); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr class="nutrition-values">
                                <td><?php echo $nutrition['soilType'] !== null ? htmlspecialchars($nutrition['soilType']) : '-'; ?></td>
                                <td><?php echo $nutrition['meanMoistureThreshold'] !== null ? htmlspecialchars($nutrition['meanMoistureThreshold']) . " | " . htmlspecialchars($nutrition['meanMoistureThreshold'] + 5) . " | " . htmlspecialchars($nutrition['meanMoistureThreshold'] + 10) : '-'; ?></td>
                                <td><?php echo $nutrition['growthStage'] !== null ? htmlspecialchars($nutrition['growthStage']) : '-'; ?></td>
                                <td>30° | 31°-34° | 35°</td>
                                <td><?php echo $nutrition['soilN'] !== null ? htmlspecialchars($nutrition['soilN']) : '-'; ?></td>
                                <td>
                                    <?php 
                                        if (isset($fertilizerData[$nutrition['nutritionID']])) {
                                            foreach ($fertilizerData[$nutrition['nutritionID']] as $fertilizer) {
                                                echo '<span class="fert-badge">'
                                                    . htmlspecialchars($fertilizer['fertilizerName']) 
                                                    . ' (' . htmlspecialchars($fertilizer['fertilizerAmount']) . 'g)</span>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button class="details-btn"
                                            data-soil="<?php echo htmlspecialchars($nutrition['soilType']); ?>"
                                            data-moisture="<?php echo htmlspecialchars($nutrition['meanMoistureThreshold']); ?>"
                                            data-stage="<?php echo htmlspecialchars($nutrition['growthStage']); ?>"
                                            data-plants="<?php echo htmlspecialchars($nutrition['numberOfPlants']); ?>"
                                            data-n="<?php echo htmlspecialchars($nutrition['soilN']); ?>"
                                            data-p="<?php echo htmlspecialchars($nutrition['soilP']); ?>"
                                            data-k="<?php echo htmlspecialchars($nutrition['soilK']); ?>"
                                            data-ec="<?php echo htmlspecialchars($nutrition['soilEC']); ?>"
                                            data-ph="<?php echo htmlspecialchars($nutrition['soilPH']); ?>"
                                            data-liquid="<?php echo htmlspecialchars($nutrition['liquidVolume']); ?>"
                                            data-fertilizers='<?php echo json_encode($fertilizerData[$nutrition['nutritionID']] ?? []); ?>'><i class="fas fa-list"></i> Details</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div id="modal" class="modal">
        <div class="modal-backdrop"></div>
        <div class="modal-box">
            <div class="modal-header">
                <h3>Nutrition Details</h3>
                <button id="close-btn">&times;</button>
            </div>
            <div id="modal-content"></div>
        </div>
    </div>
    <script src="../assets/js/nutrition_sets.js" defer></script>
</body>
</html> 