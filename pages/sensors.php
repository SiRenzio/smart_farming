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
$userID = $_SESSION['userID'];

// Handle data deletion
if (isset($_POST['delete_data']) && isset($_POST['data_id'])) {
    $dataID = (int)$_POST['data_id'];
    $deleteStmt = $conn->prepare('DELETE sd FROM sensordata sd 
                                 JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID 
                                 WHERE sd.SensorDataID = ? AND si.userID = ?');
    $deleteStmt->bind_param('ii', $dataID, $userID);
    if ($deleteStmt->execute()) {
        $success = "Sensor data deleted successfully!";
    } else {
        $error = "Failed to delete: " . $conn->error;
    }
    $deleteStmt->close();
}

// Filters & Pagination Setup
$limit = 15;
$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
$offset = max(0, ($page - 1) * $limit);

$filterSensor = $_GET['sensor'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo = $_GET['dateTo'] ?? '';

// Build the WHERE clause dynamically
$whereConditions = ["si.userID = ?"];
$bindParams = [$userID];
$bindTypes = "i";

if (!empty($filterSensor)) {
    $whereConditions[] = "sd.SoilSensorID = ?";
    $bindParams[] = $filterSensor;
    $bindTypes .= "i";
}
if (!empty($filterLocation)) {
    $whereConditions[] = "sd.locationID = ?";
    $bindParams[] = $filterLocation;
    $bindTypes .= "i";
}
if (!empty($filterDateFrom)) {
    $whereConditions[] = "sd.DateTime >= ?";
    $bindParams[] = $filterDateFrom;
    $bindTypes .= "s";
}
if (!empty($filterDateTo)) {
    $whereConditions[] = "sd.DateTime <= ?";
    $bindParams[] = $filterDateTo;
    $bindTypes .= "s";
}

$whereSQL = " WHERE " . implode(" AND ", $whereConditions);

// Get Total Count for Pagination
$countSql = "SELECT COUNT(*) as total 
             FROM sensordata sd 
             INNER JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID 
             LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID" . $whereSQL;

$stmtCount = $conn->prepare($countSql);
$stmtCount->bind_param($bindTypes, ...$bindParams);
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$stmtCount->close();

// Fetch Actual Data
$sql = "SELECT sd.*, si.sensorName, fl.farmName 
        FROM sensordata sd 
        INNER JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID 
        LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID" 
        . $whereSQL . " ORDER BY sd.DateTime DESC LIMIT ? OFFSET ?";

// Prepare params for the final query (Adding limit and offset)
$finalParams = $bindParams;
$finalParams[] = $limit;
$finalParams[] = $offset;
$finalTypes = $bindTypes . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($finalTypes, ...$finalParams);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Dropdowns (Helper lists)
$sensorsList = [];
$sensorQuery = $conn->prepare("SELECT soilSensorID, sensorName FROM sensorinfo WHERE userID = ? ORDER BY sensorName");
$sensorQuery->bind_param("i", $userID);
$sensorQuery->execute();
$sensorsList = $sensorQuery->get_result()->fetch_all(MYSQLI_ASSOC);

$locationsList = [];
$locQuery = $conn->prepare("SELECT locationID, farmName FROM farmlocation WHERE userID = ? ORDER BY farmName");
$locQuery->bind_param("i", $userID);
$locQuery->execute();
$locationsList = $locQuery->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Sensors - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/sensors.css" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-microchip"></i>
            </div>
            <h1>Soil Sensors</h1>
            <p>Monitor and manage your deployed sensors</p>
        </div>

        <div class="message-container">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="manage_sensors.php">
                <i class="fas fa-list"></i> Manage Sensors
            </a>
        </div>
        
        <div class="page-header">
            <form method="GET" action="">
                <div class="filters-container">
                    <div class="filter">
                        <label for="sensor"><i class="fa fa-filter"></i> Sensor:</label>
                        <select id="sensor" name="sensor" onchange="this.form.submit()">
                            <option value="">All Sensors</option>
                            <?php foreach ($sensorsList as $s): ?>
                                <option value="<?php echo $s['soilSensorID']; ?>" <?php echo ($filterSensor == $s['soilSensorID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['sensorName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter">
                        <label for="location"><i class="fa fa-filter"></i> Location:</label>
                        <select id="location" name="location" onchange="this.form.submit()">
                            <option value="">All Locations</option>
                            <?php foreach ($locationsList as $l): ?>
                                <option value="<?php echo $l['locationID']; ?>" <?php echo ($filterLocation == $l['locationID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($l['farmName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter">
                        <label for="dateFrom"><i class="fa fa-filter"></i> Date & Time (From):</label>
                        <input type="datetime-local" name="dateFrom" id="dateFrom" value="<?php echo htmlspecialchars($filterDateFrom); ?>" onchange="this.form.submit()">
                    </div>

                    <div class="filter">
                        <label for="dateTo"><i class="fa fa-filter"></i> Date & Time (To):</label>
                        <input type="datetime-local" name="dateTo" id="dateTo" value="<?php echo htmlspecialchars($filterDateTo); ?>" onchange="this.form.submit()">
                    </div>

                    <div class="filter">
                        <label>&nbsp;</label>
                        <a href="sensors.php" class="btn btn-clear">
                            <i class="fa-solid fa-rotate-left"></i> Clear
                        </a>
                    </div>

                </div>
            </form>

            <?php if (empty($data)): ?>
            <div class="empty-state">
                <p>No sensor data found matching your criteria.</p>
                <?php if(!empty($filterSensor) || !empty($filterLocation) || !empty($filterDateFrom)): ?>
                    <p><a href="sensors.php">Clear Filters</a></p>
                <?php else: ?>
                    <p><a href="manage_sensors.php">Register or Deploy your first sensor</a> to get started.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <table class="data-table">
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
                            <th><i class="fas fa-cogs"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody id="sensor-data-body">
                        <?php foreach ($data as $row): ?>
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
                                <td>
                                    <div class="actions">
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this sensor data? This action cannot be undone.');">
                                            <input type="hidden" name="data_id" value="<?php echo $row['SensorDataID']; ?>">
                                            <button type="submit" name="delete_data" class="btn btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </div>
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
    </div>
    <script src="../assets/js/sensors.js"></script>
</body>
</html>