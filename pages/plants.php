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

// Fetch all plants
$plants = [];
$stmt = $conn->prepare('SELECT plantID, plantName, plantVariety FROM plantinfo WHERE userID = ? ORDER BY plantName');
$stmt->bind_param('i', $_SESSION['userID']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $plants[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Plants - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/plants.css" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-seedling"></i>
            </div>
            <h1>My Plants</h1>
            <p>Manage and monitor your plant collection</p>
        </div>

        <!-- Navigation Links -->
        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="add_plant.php">
                <i class="fas fa-plus"></i> Add New Plant
            </a>
        </div>

        <?php if (empty($plants)): ?>
            <div class="empty-state">
                <div class="icon">
                    <i class="fas fa-seedling"></i>
                </div>
                <h3>No Plants Yet</h3>
                <p>Start building your smart farming ecosystem by adding your first plant</p>
                <a href="add_plant.php" class="btn">
                    <i class="fas fa-plus"></i> Add Your First Plant
                </a>
            </div>
        <?php else: ?>
            <div class="plants-grid">
                <?php foreach ($plants as $plant): ?>
                    <div class="plant-card">
                        <div class="plant-header">
                            <div class="plant-icon">
                                <i class="fas fa-seedling"></i>
                            </div>
                            <div class="plant-info">
                                <h3><?php echo htmlspecialchars($plant['plantName']); ?></h3>
                                <?php if ($plant['plantVariety']): ?>
                                    <div class="plant-variety"><?php echo htmlspecialchars($plant['plantVariety']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="plant-actions">
                            <a href="add_nutrition.php?plantID=<?php echo $plant['plantID']; ?>" class="action-btn btn-primary">
                                <i class="fas fa-plus"></i> Add Nutrition
                            </a>
                            <a href="view_nutrition.php?plantID=<?php echo $plant['plantID']; ?>" class="action-btn btn-success">
                                <i class="fas fa-eye"></i> View Nutrition
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 