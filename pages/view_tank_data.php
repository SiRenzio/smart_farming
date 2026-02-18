<?php
session_start();
require_once '../db.php';
require_once '../includes/notification.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$tankID = $_GET['tankID'] ?? '';

$getTanlkSql = "SELECT * FROM liquidsensorinfo WHERE liquidsensorID = ?";
$stmtTank = $conn->prepare($getTanlkSql);
$stmtTank->bind_param("i", $tankID);
$stmtTank->execute();
$tankResult = $stmtTank->get_result();
if ($tankResult->num_rows === 0) {
    echo "Invalid Tank ID.";
    exit;
}
$stmtTank->close();

$tankName = $tankResult->fetch_assoc()['liquidtankname'];

// Temporary
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['action_watering'])) {
        $checkFlag = $conn->prepare("SELECT wateringFlag FROM tankpumpevent WHERE liquidsensorID = ? ORDER BY dateandtime DESC LIMIT 1");
        $checkFlag->bind_param("i", $tankID);
        $checkFlag->execute();
        $fResult = $checkFlag->get_result()->fetch_assoc();

        if(!$fResult || $fResult['wateringFlag'] == 0) {
            $stmt = $conn->prepare("INSERT INTO tankpumpevent (liquidsensorID, dateandtime, wateringstatus, wateringFlag, wateringvolume) VALUES (?, NOW(), 1, NULL, FLOOR(RAND() * 100 + 1))");
            $stmt->bind_param("i", $tankID);
            $stmt->execute();
            $stmt->close();
        }
    }
}
// Fetch current state for button disabling
$latestStmt = $conn->prepare("SELECT wateringFlag FROM tankpumpevent WHERE liquidsensorID = ? ORDER BY dateandtime DESC LIMIT 1");
$latestStmt->bind_param("i", $tankID);
$latestStmt->execute();
$currentState = $latestStmt->get_result()->fetch_assoc();
$isMixing = ($currentState['wateringFlag'] ?? 0) == 1;

// Pagination
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filters
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo = $_GET['dateTo'] ?? '';


$whereSQL = " WHERE liquidsensorID = ?";
$params = [$tankID];
$types = "i";

if (!empty($filterDateFrom)) {
    $whereSQL .= " AND dateandtime >= ?";
    $params[] = str_replace('T', ' ', $filterDateFrom); 
    $types .= "s";
}

if (!empty($filterDateTo)) {
    $whereSQL .= " AND dateandtime <= ?";
    $params[] = str_replace('T', ' ', $filterDateTo);
    $types .= "s";
}


// pagination count
$countSql = "SELECT COUNT(*) AS total FROM tankpumpevent" . $whereSQL;
$stmtCount = $conn->prepare($countSql);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$countResult = $stmtCount->get_result();
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$stmtCount->close();

// fetching data
$dataSql = "SELECT * FROM tankpumpevent $whereSQL ORDER BY dateandtime DESC LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($dataSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

//fetch tank name
$tankNameSql = "SELECT liquidtankname FROM liquidsensorinfo WHERE liquidsensorID = ?";
$stmtTankName = $conn->prepare($tankNameSql);
$stmtTankName->bind_param("i", $tankID);
$stmtTankName->execute();
$tankNameResult = $stmtTankName->get_result();
$tankName = $tankNameResult->fetch_assoc()['liquidtankname'] ?? 'Unknown Tank';
$stmtTankName->close();

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
    <link href="../assets/css/view_tank_data.css" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-tint"></i>
            </div>
            <h1><?php echo htmlspecialchars($tankName); ?></h1>
            <p>Monitor your liquid tank event.</p>
        </div>

        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <form method="POST" action="" style="display: inline;" id="actionForm">
                <button type="submit" name="action_watering" class="tempbtn" <?php echo $isMixing ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                    <i class="fas fa-faucet"></i> Watering
                </button>
                
                <button type="submit" name="action_reset_flag" id="resetBtn" style="display:none;"></button>
            </form>
        </div>
        
        <div class="page-header">
            <form method="GET" action="">
                <input type="hidden" name="tankID" value="<?php echo htmlspecialchars($tankID); ?>">
                
                <div class="filters-container">

                    <div class="filter">
                        <label for="dateFrom"><i class="fa fa-filter"></i> Date & Time (From):</label>
                        <input type="datetime-local" name="dateFrom" id="dateFrom" 
                               value="<?php echo htmlspecialchars($filterDateFrom); ?>" 
                               onchange="this.form.submit()">
                    </div>

                    <div class="filter">
                        <label for="dateTo"><i class="fa fa-filter"></i> Date & Time (To):</label>
                        <input type="datetime-local" name="dateTo" id="dateTo" 
                               value="<?php echo htmlspecialchars($filterDateTo); ?>" 
                               onchange="this.form.submit()">
                    </div>

                    <div class="filter">
                        <label>&nbsp;</label>
                        <a href="?tankID=<?php echo htmlspecialchars($tankID); ?>" class="btn btn-clear">
                            <i class="fa-solid fa-rotate-left"></i> Clear
                        </a>
                    </div>

                </div>
            </form>

            <?php if (empty($data)): ?>
            <div class="empty-state">
                <p>No sensor data found matching your criteria.</p>
                <?php if(!empty($filterDateFrom) || !empty($filterDateTo)): ?>
                    <p><a href="?tankID=<?php echo htmlspecialchars($tankID); ?>">Clear Filters</a></p>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Date & Time</th>
                            <th><i class="fas fa-tint"></i> Watering Status</th>
                            <th><i class="fas fa-tint"></i> Watering Flag</th>
                            <th><i class="fas fa-water"></i> Watering Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['dateandtime'])); ?></td>
                                <td class="numeric-value"><?php echo ($row['wateringstatus'] === 1) ? 'Pumped' : ($row['wateringstatus'] === 0 ? 'Hold Watering' : 'Able Watering'); ?></td>
                                <td class="numeric-value"><?php echo ($row['wateringFlag'] === 1) ? 'Low' : ($row['wateringFlag'] === 0 ? 'Full' : 'Idle'); ?></td>
                                <td class="numeric-value"><?php echo $row['wateringvolume'] !== null ? htmlspecialchars($row['wateringvolume']) : '-'; ?></td>
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

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?<?php echo $queryParams; ?>&page=<?php echo $i; ?>" 
                               class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                               <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

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
</body>
</html>