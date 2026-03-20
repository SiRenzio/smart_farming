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

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plantName = trim($_POST['plantName'] ?? '');
    $plantVariety = trim($_POST['plantVariety'] ?? '');

    // Validate
    if (!$plantName) {
        $errors[] = 'Plant name is required.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('INSERT INTO plantinfo (userID, plantName, plantVariety) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $_SESSION['userID'], $plantName, $plantVariety);
        if ($stmt->execute()) {
            $plantID = $conn->insert_id; // Get the auto-generated ID
            $success = 'Plant added successfully! <a href="add_nutrition.php?plantID=' . $plantID . '">Add nutrition needs</a> or <a href="plants.php">view all plants</a>.';
        } else {
            $errors[] = 'Failed to add plant. Please try again.';
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
    <title>Add Plant - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/add_plant.css" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-seedling"></i>
            </div>
            <h1>Add New Plant</h1>
            <p>Register a new plant in your smart farming system</p>
        </div>

        <!-- Form Card -->
        <div class="form-card">
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

            <form method="post" action="add_plant.php">
                <div class="form-group">
                    <label for="plantName">Plant Name *</label>
                    <input type="text" 
                           id="plantName"
                           name="plantName" 
                           class="form-input"
                           placeholder="Enter plant name (e.g., Tomato, Corn, Wheat)" 
                           required 
                           value="<?php echo htmlspecialchars($_POST['plantName'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="plantVariety">Plant Variety</label>
                    <input type="text" 
                           id="plantVariety"
                           name="plantVariety" 
                           class="form-input"
                           placeholder="Enter variety (e.g., Beefsteak, Sweet Corn, Winter Wheat)" 
                           value="<?php echo htmlspecialchars($_POST['plantVariety'] ?? ''); ?>">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i> Add Plant
                </button>
            </form>

            <div class="nav-links">
                <a href="dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="plants.php">
                    <i class="fas fa-list"></i> View All Plants
                </a>
            </div>
        </div>
    </div>
</body>
</html> 