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
$errors = [];
$success = '';
$plantName = '';

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

// Fetch Nutrition Sets for dropdown
$nutritionSets = [];
$savedSetsStmt = $conn->prepare('SELECT nutritionID, nutritionSetName, plantnutrionneed.plantID, plantName FROM plantnutrionneed LEFT JOIN plantinfo ON plantnutrionneed.plantID = plantinfo.plantID');
$savedSetsStmt->execute();
$savedSets = $savedSetsStmt->get_result();
while ($row = $savedSets->fetch_assoc()) {
    $nutritionSets[] = $row;
}
$savedSets->close();

//Default values for fertilizer fields
$vegetativeDefaults = [
    'fertilizer' => ['Nitrabor', 'Unik16', 'WINNER'],
    'fertilizerAmount' => [1.5, 1.4, 1.6]
];

$lateVegetativeDefaults = [
    'fertilizer' => ['Nitrabor', 'Unik16', 'WINNER'],
    'fertilizerAmount' => [0.3, 0.5, 0.5]
];

$flowringToFruitingDefaults = [
    'fertilizer' => ['Nitrabor', 'Unik16', 'WINNER'],
    'fertilizerAmount' => [0.5, 0.2, 1.0]
];

$harvestingDefaults = [
    'fertilizer' => ['Nitrabor', 'Unik16', 'WINNER'],
    'fertilizerAmount' => [0.1, 0.1, 0.3]
];

$moistureThresholdValues = [
    'sandyClayLoam' => '26% | 31% | 36%',
    'loam' => '21% | 26% | 31%',
    'sandyClay' => '33% | 38% | 43%',
    'siltLoam' => '20% | 25% | 30%',
    'silt' => '17% | 22% | 27%',
    'clayLoam' => '30% | 35% | 40%',
    'siltyClayLoam' => '29% | 34% | 39%'
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nutritionSetName = trim($_POST['nutritionSetName'] ?? '');
    $growthStage = $_POST['growthStage'] ?? '';
    $soilN = $_POST['soilN'] ?? '';
    $soilP = $_POST['soilP'] ?? '';
    $soilK = $_POST['soilK'] ?? '';
    $soilEC = $_POST['soilEC'] ?? '';
    $soilPH = $_POST['soilPH'] ?? '';
    $liquidVolume = $_POST['liquidVolume'] ?? '';
    $fertilizers = $_POST['fertilizer'] ?? [];
    $fertilizerAmounts = $_POST['fertilizerAmount'] ?? [];
    $soilType = $_POST['soilType'] ?? '';

    // Validate required fields
    if (!$nutritionSetName || !$growthStage || !$soilType) {
        $errors[] = 'All fields with asterisk are required.';
    }


    $moistureThreshold = 0;
    switch ($soilType) {
        case 'sandyClayLoam':
            $moistureThreshold = 26;
            break;
        case 'loam':
            $moistureThreshold = 21;
            break;
        case 'sandyClay':
            $moistureThreshold  = 33;
            break;
        case 'siltLoam':
            $moistureThreshold = 20;
            break;
        case 'silt':
            $moistureThreshold  = 17;
            break;
        case 'clayLoam':
            $moistureThreshold = 30;
            break;
        case 'siltyClayLoam':
            $moistureThreshold = 29;
            break;
        default:
            $errors[] = 'Invalid soil type selected.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('INSERT INTO plantnutrionneed (userID, nutritionSetName, plantID, soilType, meanMoistureThreshold, growthStage, soilN, soilP, soilK, soilEC, soilPH, liquidVolume) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if ($stmt === null) {
            $errors[] = 'Invalid SQL statement. Please try again.';
        } else {
            // Debug: Show the final SQL statement
            $finalSQL = getFinalSQL('INSERT INTO plantnutrionneed (userID, nutritionSetName, plantID, soilType, meanMoistureThreshold, growthStage, soilN, soilP, soilK, soilEC, soilPH, liquidVolume) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'isisiiiidddd', [$nutritionSetName, $plantID, $soilType, $moistureThreshold, $growthStage, $soilN, $soilP, $soilK, $soilEC, $soilPH, $liquidVolume]);
            
            try {
                $stmt->bind_param('isisisiiiidd', $_SESSION['userID'], $nutritionSetName, $plantID, $soilType, $moistureThreshold, $growthStage, $soilN, $soilP, $soilK, $soilEC, $soilPH, $liquidVolume);
            } catch (Exception $e) {
                $errors[] = 'Failed to bind parameters: ' . $e->getMessage();
                $errors[] = 'Debug SQL: ' . $finalSQL;
            }
        }
        if ($stmt->execute()) {
            $nutritionID = $conn->insert_id; // Get the auto-generated ID

            for ($i = 0; $i < count($fertilizers); $i++) {
                $fertilizer = trim($fertilizers[$i]);
                $fertToLower = strtolower($fertilizer);
                $amount = trim($fertilizerAmounts[$i]);
                if ($fertilizer && $amount) {
                    if ($fertToLower === 'nitrabor') {
                        $stmt = $conn->prepare('INSERT INTO fertilizer (liquidsensorID, nutritionID, fertilizerName, fertilizerAmount) VALUES (2, ?, ?, ?)');
                        $stmt->bind_param('isd', $nutritionID, $fertilizer, $amount);
                        $stmt->execute();
                        $stmt->close();
                    }
                    else if ($fertToLower === 'unik16' || $fertToLower === '16-16-16' || $fertToLower === 'winner') {
                        $stmt = $conn->prepare('INSERT INTO fertilizer (liquidsensorID, nutritionID, fertilizerName, fertilizerAmount) VALUES (3, ?, ?, ?)');
                        $stmt->bind_param('isd', $nutritionID, $fertilizer, $amount);
                        $stmt->execute();
                        $stmt->close();
                    }
                    else {
                        $errors[] = 'Unknown fertilizer: ' . htmlspecialchars($fertilizer) . '. Please use "Nitrabor", "Unik16", "16-16-16", or "Winner".';
                    }
                }
            }
            
            $success = 'Nutrition needs added successfully! <a href="view_nutrition.php?plantID=' . $plantID . '">View nutrition details</a> or <a href="plants.php">view all plants</a>.';
            $_SESSION['success'] = $success;
            header('Location: add_nutrition.php?plantID=' . $plantID);
            exit;
        } else {
            $errors[] = 'Failed to add nutrition needs. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Nutrition Needs - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <link href="../assets/css/add_nutrition.css" rel="stylesheet">
</head>
<body>
    <div class="form-container">
        <!-- Form Header -->
        <div class="form-header">
            <div class="icon">
                <i class="fas fa-leaf"></i>
            </div>
            <h1>Add Nutrition Needs</h1>
            <p>Define optimal soil conditions for your plant</p>
        </div>

        <!-- Plant Info -->
        <div class="plant-info">
            <strong>Plant:</strong> <span><?php echo htmlspecialchars($plantName); ?></span>
        </div>

        <!-- Messages -->
        <div class="message-container">
            <?php if ($errors): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
        </div>

        <!-- Nutrition Form -->
        <form method="post" action="add_nutrition.php?plantID=<?php echo $plantID; ?>">
            <div class="form-group full-width">
                <label for="nutritionSetName">
                    <i class="fas fa-pen"></i> Custom Nutrition Set Name *
                </label>
                <input 
                    type="text" 
                    id="nutritionSetName" 
                    name="nutritionSetName" 
                    placeholder="Enter a name for this nutrition set"
                >
            </div>

            <div class="form-group full-width">
                <label for="savedNutritionSetName">
                    <i class="fas fa-floppy-disk"></i> Saved Nutrition Set Name
                </label>
                <select name="savedNutritionSetName" id="saved-nutrition-set" class="dropdown">
                    <?php if (!empty($nutritionSets)): ?>
                        <option value="">Select a saved nutrition set (optional)</option>
                        <?php foreach ($nutritionSets as $set): ?>
                            <option value="<?php echo htmlspecialchars($set['nutritionID']); ?>">
                                <?php echo htmlspecialchars($set['nutritionSetName']); ?> - <?php echo htmlspecialchars($set['plantName']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No saved nutrition sets available</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group full-width">
                <label for="soilType">
                    <i class="fas fa-layer-group"></i> Soil Type *
                </label>
                <select name="soilType" class="dropdown" id="soil-type">
                    <option value="">Select soil type</option>
                    <option value="sandyClayLoam">Sandy clay loam</option>
                    <option value="loam">Loam</option>
                    <option value="sandyClay">Sandy clay</option>
                    <option value="siltLoam">Silt loam</option>
                    <option value="silt">Silt</option>
                    <option value="clayLoam">Clay loam</option>
                    <option value="siltyClayLoam">Silty clay loam</option>
                </select>
            </div>

            <div class="form-group full-width">
                <label for="growthStage">
                    <i class="fas fa-tree"></i> Growth Stages *
                </label>
                <select name="growthStage" class="dropdown" id="plant-stage">
                    <option value="">Select growth stage</option>
                    <option value="vegetative">Vegetative (3-15 Days)</option>
                    <option value="lateVegetative">Late Vegetative (16-45 Days)</option>
                    <option value="floweringToFruiting">Flowering to Fruiting (46-55 Days)</option>
                    <option value="harvesting">Harvesting (56+ Days)</option>
                </select>
            </div>
            
            <div class="form-grid">
                <h2>Fertilizer Information</h2>
                <div id="fertilizerContainer"></div>

                <button class="add-fert-btn" type="button" onclick="addFertilizer()">
                    <i class="fas fa-plus"></i>Add Fertilizer
                </button><br>

                <h2>Plant Nutrition Parameters</h2><br>

                <div class="form-group">
                    <label for="soilN">
                        <i class="fas fa-leaf"></i> Soil Nitrogen (N)
                    </label>
                    <input 
                        type="number" 
                        id="soilN" 
                        name="soilN" 
                        step="any"
                        placeholder="Enter N value"
                        value="<?php echo htmlspecialchars($_POST['soilN'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="soilP">
                        <i class="fas fa-seedling"></i> Soil Phosphorus (P)
                    </label>
                    <input 
                        type="number" 
                        id="soilP" 
                        name="soilP" 
                        step="any"
                        placeholder="Enter P value"
                        value="<?php echo htmlspecialchars($_POST['soilP'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="soilK">
                        <i class="fas fa-tree"></i> Soil Potassium (K)
                    </label>
                    <input 
                        type="number" 
                        id="soilK" 
                        name="soilK" 
                        step="any"
                        placeholder="Enter K value"
                        value="<?php echo htmlspecialchars($_POST['soilK'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="soilEC">
                        <i class="fas fa-bolt"></i> Soil Electrical Conductivity
                    </label>
                    <input 
                        type="number" 
                        id="soilEC" 
                        name="soilEC" 
                        step="any"
                        placeholder="Enter EC value"
                        value="<?php echo htmlspecialchars($_POST['soilEC'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="soilPH">
                        <i class="fas fa-tint"></i> Soil pH
                    </label>
                    <input 
                        type="number" 
                        id="soilPH" 
                        name="soilPH" 
                        step="any"
                        placeholder="0.0 - 14.0"
                        value="<?php echo htmlspecialchars($_POST['soilPH'] ?? ''); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="liquidVolume">
                        <i class="fas fa-water"></i> Liquid Volume (mL)
                    </label>
                    <input 
                        type="number" 
                        id="liquidVolume" 
                        name="liquidVolume" 
                        step="any"
                        placeholder="Enter liquid volume"
                        value="<?php echo htmlspecialchars($_POST['liquidVolume'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="soilT">
                        <i class="fas fa-thermometer-half"></i> Soil Temperature (°C)
                    </label>
                    <p class="temp">30° | 31°-34° | 35°</p>
                </div>
                
                <div class="form-group">
                    <label for="soilM">
                        <i class="fas fa-tint"></i> Soil Moisture (%)
                    </label>
                    <p id="soil-moisture-display" class="empty-moisture-warning">Please Select a Soil Type</p>
                </div>
            </div>
            
            <button type="reset" class="reset-btn">
                <i class="fas fa-undo"></i> Reset
            </button>
            <button type="submit" class="submit-btn">
                <i class="fas fa-plus"></i> Add Nutrition Needs
            </button>
        </form>

        <!-- Navigation Links -->
        <div class="nav-links">
            <a href="plants.php">
                <i class="fas fa-arrow-left"></i> Back to Plants
            </a>
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
    </div>
    <script>
        const fertilizerDefaults = {
            vegetative: <?php echo json_encode($vegetativeDefaults); ?>,
            lateVegetative: <?php echo json_encode($lateVegetativeDefaults); ?>,
            floweringToFruiting: <?php echo json_encode($flowringToFruitingDefaults); ?>,
            harvesting: <?php echo json_encode($harvestingDefaults); ?>
        };
        const moistureThresholdValues = <?php echo json_encode($moistureThresholdValues); ?>;
    </script>
    <script src="../assets/js/nutrition.js"></script>
</body>
</html> 