<?php
session_start();
require_once '../db.php';
require_once '../includes/notification.php';

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

// Default values for form fields
$vegetativeDefaults = [
    'soilN' => 50,
    'soilP' => 30,
    'soilK' => 20,
    'soilEC' => 1.5,
    'soilPH' => 6.0,
    'soilT' => 22.0,
    'soilM' => 60.0,
    'flowRate' => 1.0,
    'fertilizer' => 'Nitrabor',
    'fertilizerAmount' => 3.5
];

$lateVegetativeDefaults = [
    'soilN' => 70,
    'soilP' => 40,
    'soilK' => 30,
    'soilEC' => 2.0,
    'soilPH' => 6.5,
    'soilT' => 24.0,
    'soilM' => 65.0,
    'flowRate' => 1.5,
    'fertilizer' => 'Nitrabor',
    'fertilizerAmount' => 3.5
];

$flowringToFruitingDefaults = [
    'soilN' => 60,
    'soilP' => 50,
    'soilK' => 40,
    'soilEC' => 2.5,
    'soilPH' => 6.8,
    'soilT' => 26.0,
    'soilM' => 70.0,
    'flowRate' => 2.0,
    'fertilizer' => 'Nitrabor',
    'fertilizerAmount' => 3.5
];

$harvestingDefaults = [
    'soilN' => 40,
    'soilP' => 30,
    'soilK' => 50,
    'soilEC' => 3.0,
    'soilPH' => 7.0,
    'soilT' => 28.0,
    'soilM' => 75.0,
    'flowRate' => 2.5,
    'fertilizer' => 'Nitrabor',
    'fertilizerAmount' => 7.0
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nutritionSetName = trim($_POST['nutritionSetName'] ?? '');
    $soilN = $_POST['soilN'] ?? '';
    $soilP = $_POST['soilP'] ?? '';
    $soilK = $_POST['soilK'] ?? '';
    $soilEC = $_POST['soilEC'] ?? '';
    $soilPH = $_POST['soilPH'] ?? '';
    $soilT = $_POST['soilT'] ?? '';
    $soilM = $_POST['soilM'] ?? '';
    $flowRate = $_POST['flowRate'] ?? '';
    $fertilizers = $_POST['fertilizer'] ?? [];
    $fertilizerAmounts = $_POST['fertilizerAmount'] ?? [];

    // Validate required fields
    if (!$nutritionSetName) {
        $errors[] = 'Nutrition set name is required.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('INSERT INTO plantnutrionneed (userID, nutritionSetName, plantID, soilN, soilP, soilK, soilEC, soilPH, soilT, soilM, flowRate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if ($stmt === null) {
            $errors[] = 'Invalid SQL statement. Please try again.';
        } else {
            // Debug: Show the final SQL statement
            $finalSQL = getFinalSQL('INSERT INTO plantnutrionneed (userID, nutritionSetName, plantID, soilN, soilP, soilK, soilEC, soilPH, soilT, soilM, flowRate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'siiiiidddd', [$nutritionSetName, $plantID, $soilN, $soilP, $soilK, $soilEC, $soilPH, $soilT, $soilM, $flowRate]);
            
            try {
                $stmt->bind_param('isiiiiidddd', $_SESSION['userID'], $nutritionSetName, $plantID, $soilN, $soilP, $soilK, $soilEC, $soilPH, $soilT, $soilM, $flowRate);
            } catch (Exception $e) {
                $errors[] = 'Failed to bind parameters: ' . $e->getMessage();
                $errors[] = 'Debug SQL: ' . $finalSQL;
            }
        }
        if ($stmt->execute()) {
            $nutritionID = $conn->insert_id; // Get the auto-generated ID
            $success = 'Nutrition needs added successfully! <a href="view_nutrition.php?plantID=' . $plantID . '">View nutrition details</a> or <a href="plants.php">view all plants</a>.';
        } else {
            $errors[] = 'Failed to add nutrition needs. Please try again.';
        }
        $stmt->close();
    }

    for ($i = 0; $i < count($fertilizers); $i++) {
        $fertilizer = trim($fertilizers[$i]);
        $amount = trim($fertilizerAmounts[$i]);
        if ($fertilizer && $amount) {
            $stmt = $conn->prepare('INSERT INTO fertilizer (nutritionID, fertilizerName, fertilizerAmount) VALUES (?, ?, ?)');
            $stmt->bind_param('isd', $nutritionID, $fertilizer, $amount);
            $stmt->execute();
            $stmt->close();
        }
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
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Nutrition Form -->
        <form method="post" action="add_nutrition.php?plantID=<?php echo $plantID; ?>">
            <div class="form-group full-width">
                <label for="nutritionSetName">
                    <i class="fas fa-layer-group"></i> Growth Stages *
                </label>
                <select name="nutritionSetName" id="plant-stages">
                    <option value="Vegetative">Vegetative (3-15 Days)</option>
                    <option value="Late Vegetative">Late Vegetative (16-45 Days)</option>
                    <option value="Flowering To Fruiting">Flowering to Fruiting (46-55 Days)</option>
                    <option value="Harvesting">Harvesting (56+ Days)</option>
                </select>
            </div>
            
            <div class="form-grid">
                <h2>Fertilizer Information</h2>
                <div id="fertilizerContainer">
                    <div class="fertilizer-group">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-poo-storm"></i> Fertilizer
                            </label>
                            <input 
                                type="text" 
                                name="fertilizer[]" 
                                placeholder="Enter fertilizer name"
                                value="<?php echo htmlspecialchars($vegetativeDefaults['fertilizer']); ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-weight-hanging"></i> Fertilizer Amount (g/L)
                            </label>
                            <input 
                                type="number" 
                                name="fertilizerAmount[]" 
                                placeholder="Enter fertilizer amount"
                                value="<?php echo htmlspecialchars($vegetativeDefaults['fertilizerAmount']); ?>"
                            >
                        </div>
                    </div>
                </div>

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
                        placeholder="Enter N value"
                        value="<?php echo htmlspecialchars($vegetativeDefaults['soilN']); ?>"
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
                        placeholder="Enter P value"
                        value="<?php echo htmlspecialchars($vegetativeDefaults['soilP']); ?>"
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
                        placeholder="Enter K value"
                        value="<?php echo htmlspecialchars($vegetativeDefaults['soilK']); ?>"
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
                        placeholder="Enter EC value"
                        value="<?php echo htmlspecialchars($vegetativeDefaults['soilEC']); ?>"
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
                        step="0.1" 
                        placeholder="0.0 - 14.0"
                        value="<?php echo htmlspecialchars($vegetativeDefaults['soilPH']); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="soilT">
                        <i class="fas fa-thermometer-half"></i> Soil Temperature (°C)
                    </label>
                    <input 
                        type="number" 
                        id="soilT" 
                        name="soilT" 
                        step="0.1" 
                        placeholder="Enter temperature"
                        value="<?php echo htmlspecialchars($vegetativeDefaults['soilT']); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="soilM">
                        <i class="fas fa-tint"></i> Soil Moisture (%)
                    </label>
                    <input 
                        type="number" 
                        id="soilM" 
                        name="soilM" 
                        step="0.1" 
                        placeholder="0.0 - 100.0"
                        value="<?php echo htmlspecialchars($vegetativeDefaults['soilM']); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="flowRate">
                        <i class="fas fa-water"></i> Flow Rate (L/min)
                    </label>
                    <input 
                        type="number" 
                        id="flowRate" 
                        name="flowRate" 
                        step="0.1" 
                        placeholder="Enter flow rate"
                        value="<?php echo htmlspecialchars($vegetativeDefaults['flowRate']); ?>"
                    >
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
    window.nutritionDefaults = {
        vegetative: <?php echo json_encode($vegetativeDefaults); ?>,
        lateVegetative: <?php echo json_encode($lateVegetativeDefaults); ?>,
        floweringToFruiting: <?php echo json_encode($flowringToFruitingDefaults); ?>,
        harvesting: <?php echo json_encode($harvestingDefaults); ?>
    };
    </script>
    <script src="../assets/js/nutrition.js"></script>
</body>
</html> 