<?php
session_start();
require_once 'db.php';

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

    // Validate required fields
    if (!$nutritionSetName) {
        $errors[] = 'Nutrition set name is required.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('INSERT INTO plantnutrionneed (nutritionSetName, plantID, soilN, soilP, soilK, soilEC, soilPH, soilT, soilM, flowRate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if ($stmt === null) {
            $errors[] = 'Invalid SQL statement. Please try again.';
        } else {
            // Debug: Show the final SQL statement
            $finalSQL = getFinalSQL('INSERT INTO plantnutrionneed (nutritionSetName, plantID, soilN, soilP, soilK, soilEC, soilPH, soilT, soilM, flowRate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'siiiiidddd', [$nutritionSetName, $plantID, $soilN, $soilP, $soilK, $soilEC, $soilPH, $soilT, $soilM, $flowRate]);
            
            try {
                $stmt->bind_param('siiiiidddd', $nutritionSetName, $plantID, $soilN, $soilP, $soilK, $soilEC, $soilPH, $soilT, $soilM, $flowRate);
            } catch (Exception $e) {
                $errors[] = 'Failed to bind parameters: ' . $e->getMessage();
                $errors[] = 'Debug SQL: ' . $finalSQL;
            }
        }
        if ($stmt->execute()) {
            $success = 'Nutrition needs added successfully! <a href="view_nutrition.php?plantID=' . $plantID . '">View nutrition details</a> or <a href="plants.php">view all plants</a>.';
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
            padding: 2rem;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header .icon {
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

        .form-header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50, #1976D2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: #666;
            font-size: 1rem;
        }

        .plant-info {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(25, 118, 210, 0.1));
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .plant-info strong {
            color: #1976D2;
            font-weight: 600;
        }

        .plant-info span {
            color: #333;
            font-weight: 500;
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

        .success-message a {
            color: white;
            text-decoration: underline;
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            background: white;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            margin-bottom: 2rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .nav-links {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .nav-links a {
            display: inline-block;
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

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .form-container {
                padding: 2rem;
            }
            
            .form-header h1 {
                font-size: 1.8rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                    <i class="fas fa-layer-group"></i> Nutrition Set Name *
                </label>
                <input 
                    type="text" 
                    id="nutritionSetName" 
                    name="nutritionSetName" 
                    placeholder="e.g., Growth Phase, Flowering Stage, etc."
                    required 
                    value="<?php echo htmlspecialchars($_POST['nutritionSetName'] ?? ''); ?>"
                >
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="soilN">
                        <i class="fas fa-leaf"></i> Soil Nitrogen (N)
                    </label>
                    <input 
                        type="number" 
                        id="soilN" 
                        name="soilN" 
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
                        step="0.1" 
                        placeholder="0.0 - 14.0"
                        value="<?php echo htmlspecialchars($_POST['soilPH'] ?? ''); ?>"
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
                        value="<?php echo htmlspecialchars($_POST['soilT'] ?? ''); ?>"
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
                        value="<?php echo htmlspecialchars($_POST['soilM'] ?? ''); ?>"
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
                        value="<?php echo htmlspecialchars($_POST['flowRate'] ?? ''); ?>"
                    >
                </div>
            </div>
            
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
</body>
</html> 