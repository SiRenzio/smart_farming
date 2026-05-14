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
$limit = 10;
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
$sql = "SELECT sd.*, si.sensorName, fl.farmName, p.plantName, n.nutritionSetName
        FROM sensordata sd 
        INNER JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID 
        LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID
        LEFT JOIN plantnutrionneed n ON sd.nutritionID = n.nutritionID
        INNER JOIN plantinfo p ON n.plantID = p.plantID"
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
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sensors.css" rel="stylesheet">
    <script src="../assets/js/chart.umd.js"></script>
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
            <form id="filter-form">
                <div class="filters-container">
                    <div class="filter">
                        <label for="sensor"><i class="fa fa-filter"></i> Sensor:</label>
                        <select id="sensor" name="sensor">
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
                        <select id="location" name="location">
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
                        <input type="datetime-local" step="1" name="dateFrom" id="dateFrom" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                    </div>

                    <div class="filter">
                        <label for="dateTo"><i class="fa fa-filter"></i> Date & Time (To):</label>
                        <input type="datetime-local" step="1" name="dateTo" id="dateTo" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                    </div>

                    <div class="filter">
                        <label>&nbsp;</label>
                        <a href="#" id="btn-clear-filters" class="btn btn-clear">
                            <i class="fa-solid fa-rotate-left"></i> Clear
                        </a>
                    </div>
                </div>
            </form>

            <div id="main-data-container">
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
                    
                    <div class="visual-dashboard-container">
                        <button class="carousel-btn prev" onclick="scrollCarousel(-1)"><i class="fas fa-chevron-left"></i></button>
                        
                        <div class="carousel-viewport" id="carousel-inner">
                            </div>
                        
                        <button class="carousel-btn next" onclick="scrollCarousel(1)"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    
                    <table class="data-table">
                        <div class="table-header">
                            <h2> Sensors Raw Data</h2>
                        </div>
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
                                            <button class="details-btn"
                                                data-sensorname="<?php echo htmlspecialchars($row['sensorName']); ?>"
                                                data-farmname="<?php echo htmlspecialchars($row['farmName'] ?? 'Unknown Location'); ?>"
                                                data-plantname="<?php echo htmlspecialchars($row['plantName'] ?? 'N/A'); ?>"
                                                data-nutritionset="<?php echo htmlspecialchars($row['nutritionSetName'] ?? 'N/A'); ?>"
                                                data-datetime="<?php echo date('M j, Y g:i:s A', strtotime($row['DateTime'])); ?>"
                                                data-n="<?php echo $row['SoilN'] !== null ? htmlspecialchars($row['SoilN']) : '-'; ?>"
                                                data-p="<?php echo $row['SoilP'] !== null ? htmlspecialchars($row['SoilP']) : '-'; ?>"
                                                data-k="<?php echo $row['SoilK'] !== null ? htmlspecialchars($row['SoilK']) : '-'; ?>"
                                                data-ec="<?php echo $row['SoilEC'] !== null ? htmlspecialchars($row['SoilEC']) : '-'; ?>"
                                                data-ph="<?php echo $row['SoilPH'] !== null ? htmlspecialchars($row['SoilPH']) : '-'; ?>"
                                                data-temp="<?php echo $row['SoilT'] !== null ? htmlspecialchars($row['SoilT']) : '-'; ?>"
                                                data-mois="<?php echo $row['SoilMois'] !== null ? htmlspecialchars($row['SoilMois']) : '-'; ?>"
                                            ><i class="fas fa-list"></i> Details</button>
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
                               <i class="fa fa-chevron-circle-right"></i>
                            </a>
                        </div>
                        <div class="pagination-info">
                            Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="modal" class="modal">
        <div class="modal-backdrop"></div>
        <div class="modal-box">
            <div class="modal-header">
                <h3>Sensor Details</h3>
                <button id="close-btn">&times;</button>
            </div>
            <div id="modal-content"></div>
        </div>
    </div>
    <script src="../assets/js/sensors.js"></script>
</body>
</html>