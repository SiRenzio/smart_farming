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
$username = htmlspecialchars($_SESSION['username']);

// Fetch name of tanks
$tanks = [];
$stmt = $conn->prepare('SELECT liquidsensorID, liquidtankname FROM liquidsensorinfo');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tanks[] = $row;
}
$stmt->close();

// For sensor deployment table
$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM deployment WHERE userID = ?";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param("i", $_SESSION['userID']);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch the actual data for the table
$deployedSql = "SELECT d.soilSensorID, s.sensorName, f.farmName, d.isConnected, d.nutritionID
                FROM deployment d
                LEFT JOIN sensorinfo s ON d.soilSensorID = s.soilSensorID
                LEFT JOIN farmlocation f ON d.locationID = f.locationID
                WHERE s.userID = ?
                ORDER BY d.deploymentID DESC 
                LIMIT ? OFFSET ?";

$stmt = $conn->prepare($deployedSql);
$stmt->bind_param("iii", $_SESSION['userID'], $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$deployedResult = [];
while ($row = $result->fetch_assoc()) {
    $deployedResult[] = $row;
}
$stmt->close();

// Get the lates plant nutrition data for the dashboard
$depPlants = $conn->prepare('SELECT nutritionID FROM deployment WHERE userID = ? ORDER BY deploymentID DESC LIMIT 1');
$depPlants->bind_param('i', $_SESSION['userID']);
$depPlants->execute();
$result = $depPlants->get_result();
$latestNutritionID = null; 
if ($row = $result->fetch_assoc()) {
    $latestNutritionID = $row['nutritionID'];
}
$depPlants->close();

// Helper to keep filters in URL
function getFilterParams($excludePage = true) {
    $params = $_GET;
    if ($excludePage) unset($params['page']);
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <div class="header-content">
                <div class="welcome-section">
                    <h1>Welcome back, <?php echo $username; ?>! 👋</h1>
                    <p>Manage your smart farming ecosystem with precision and ease</p>
                </div>
                <div class="header-actions">
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-plant">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="card-content">
                        <h3>Plant Management</h3>
                        <p>Add new plants and monitor their growth progress</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="add_plant.php" class="card-btn">
                        <i class="fas fa-plus"></i> Add New Plant
                    </a>
                </div>
            </div>

            <div class="tank-container span-2">
                <div class="card-header">
                    <div class="card-icon icon-view">
                        <i class="fas fa-water"></i>
                    </div>
                    <div class="card-content">
                        <h3>Liquid Tank Level Overview</h3>
                        <p>Monitor current tanks liquid level.</p>
                    </div>
                    <div class="failsafe_btn">
                        <a href="failsafe.php" class="fsafe-btn">
                            <i class="fa-solid fa-gear"></i>  Settings
                        </a>
                    </div>
                </div>
                <?php if ($tanks): ?>
                    <div class="tanks-wrapper">
                    <?php foreach ($tanks as $tank): ?>
                            <a href="view_tank_data.php?tankID=<?= $tank['liquidsensorID'] ?>" class="tank-card-btn">
                                <div class="tank-card">
                                    <div class="tank" data-liquidsensor-id="<?= $tank['liquidsensorID'] ?>">
                                        <div class="glass-glare"></div>
                                        <div class="measurement">
                                            <div></div><div></div><div></div><div></div><div></div>
                                            <div></div><div></div><div></div><div></div><div></div>
                                            <div></div><div></div><div></div>
                                        </div>
                                        <div class="water">
                                            <div class="wave-container">
                                                <svg class="waves-svg" viewBox="0 0 288 50" preserveAspectRatio="none">
                                                    <defs>
                                                        <path id="wave" d="M0,25 C48,50 96,0 144,25 C192,50 240,0 288,25 V50 H0 Z" />
                                                    </defs>
                                                    <use xlink:href="#wave" x="0" y="0" class="wave-path wave-back" />
                                                    <use xlink:href="#wave" x="0" y="3" class="wave-path wave-mid" />
                                                    <use xlink:href="#wave" x="0" y="5" class="wave-path wave-front" />
                                                </svg>
                                            </div>
                                        </div>
                                        <span class="level-text"></span>
                                    </div>
                                    <div class="tank-name"><?php echo $tank['liquidtankname']; ?></div>
                                </div>
                            </a>
                    <?php endforeach; ?>
                    </div> 
                <?php else: ?>
                    <div class="empty">
                        <strong>No Tanks Available.</strong>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-view">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="card-content">
                        <h3>Plant Overview</h3>
                        <p>View and manage all your plants in one place</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="plants.php" class="card-btn">
                        <i class="fas fa-list"></i> View My Plants
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-sensor">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="card-content">
                        <h3>Sensor Management</h3>
                        <p>Deploy and configure soil monitoring sensors</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="manage_sensors.php" class="card-btn">
                        <i class="fas fa-paper-plane"></i> Deploy Sensor
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-view">
                        <i class="fas fa-satellite-dish"></i>
                    </div>
                    <div class="card-content">
                        <h3>Sensor Overview</h3>
                        <p>Monitor and manage all your deployed sensors</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="sensors.php" class="card-btn">
                        <i class="fas fa-list"></i> View Sensors
                    </a>
                </div>
            </div>
            
        </div>

        <div class="sensors-deployment-section">
            <div class="card-header">
                <div class="card-icon icon-sensor">
                    <i class="fas fa-microchip"></i>
                </div>
                <div class="card-content">
                    <h3>Deployment Summary</h3>
                    <p>Summary of your current deployed sensors.</p>
                </div>
            </div>
            
            <?php if (empty($deployedResult)): ?>
                <div class="empty-state">
                    <p>No deployed sensor available for this user.</p>
                </div>
            <?php else: ?>
                <table class="deployment-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-microchip"></i> Sensor</th>
                            <th><i class="fas fa-map-marker-alt"></i> Location</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-layer-group"></i> Plant Nutrition</th>
                        </tr>
                    </thead>
                    <tbody id="userDeploymentTable">
                        <?php foreach ($deployedResult as $deployment): ?>
                            <tr>
                                <td><?= htmlspecialchars($deployment['sensorName']) ?></td>
                                <td><?= htmlspecialchars($deployment['farmName']) ?></td>
                                <td>
                                    <?php if ((int)$deployment['isConnected'] === 1): ?>
                                        <span class="status connected" style="color: #2e7d32;"><i class="fas fa-check-circle"></i> Connected</span>
                                    <?php else: ?>
                                        <span class="status disconnected" style="color: #e74c3c;"><i class="fas fa-times-circle"></i> Disconnected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="deployed_nutrition.php?nutritionID=<?php echo $latestNutritionID; ?>" class="action-btn btn-success">
                                        <i class="fas fa-eye"></i> View Data
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <?php
                            $queryParams = getFilterParams(); 
                            $maxButtons = 5;
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + $maxButtons - 1);
                            
                            // Adjust if we are near the end
                            if ($endPage - $startPage < $maxButtons - 1) {
                                $startPage = max(1, $endPage - $maxButtons + 1);
                            }
                        ?>

                        <a href="?<?php echo $queryParams; ?>&page=<?php echo max(1, $page - 1); ?>" 
                            class="pagination-link <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <i class="fa fa-chevron-circle-left"></i>
                        </a>

                        <a href="?<?php echo $queryParams; ?>&page=1"
                            class="pagination-link <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            First
                        </a>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?<?php echo $queryParams; ?>&page=<?php echo $i; ?>" 
                                class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <a href="?<?php echo $queryParams; ?>&page=<?php echo $totalPages; ?>"
                            class="pagination-link <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            Last
                        </a>

                        <a href="?<?php echo $queryParams; ?>&page=<?php echo min($totalPages, $page + 1); ?>" 
                            class="pagination-link <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <i class="	fa fa-chevron-circle-right"></i>
                        </a>
                    </div>
                    <div class="pagination-info">
                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="stats-section">
            <h2 style="text-align: center; margin-bottom: 2rem; color: #333; font-weight: 600;">
                <i class="fas fa-chart-pie"></i> Quick Overview
            </h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">🌱</div>
                    <div class="stat-label">Plant Management</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">📡</div>
                    <div class="stat-label">Sensor Network</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">📊</div>
                    <div class="stat-label">Data Analytics</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">⚡</div>
                    <div class="stat-label">Real-time Monitoring</div>
                </div>
            </div>
        </div>
    </div>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>