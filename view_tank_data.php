<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$tankID = $_GET['tankID'] ?? '';

// Temporary
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("INSERT INTO tankpumpevent (liquidsensorID, dateandtime, wateringstatus, wateringvolume) VALUES (?, NOW(), 1, FLOOR(RAND() * 100 + 1))");
    $stmt->bind_param("i", $tankID);
    $stmt->execute();
    $stmt->close();
}

// Pagination
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filters
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo = $_GET['dateTo'] ?? '';


$whereSQL = " WHERE liquidsensorID = ? AND wateringstatus = 1";
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .page-container { max-width: 1300px; margin: 0 auto; padding: 2rem; }
        .page-header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); text-align: center; }
        .page-header .icon { width: 80px; height: 80px; background: linear-gradient(135deg, #2196F3, #1976D2); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem; color: white; }
        .page-header h1 { font-size: 2.2rem; font-weight: 700; background: linear-gradient(135deg, #2196F3, #1976D2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 0.5rem; }
        .page-header p { color: #666; font-size: 1.1rem; }
        .message-container { margin-bottom: 2rem; }
        .nav-links { text-align: center; margin-bottom: 2rem; display: flex; justify-content: center;}
        .nav-links a { display: inline-block; margin: 0 0.5rem; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 25px; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
        .nav-links a:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); text-decoration: none; }
        
        .nav-links button { display: inline-block; margin: 0 0.5rem; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 25px; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
        .nav-links button:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); text-decoration: none; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 1em; }
        .data-table th, .data-table td { padding: 0.75em; text-align: center; border-bottom: 1px solid #dee2e6; }
        .data-table th { background: #f8f9fa; font-weight: bold; }
        .data-table tr:hover { background: #f8f9fa; }
        .btn { padding: 0.5em 1em; border: none; border-radius: 4px; text-decoration: none; font-size: 0.9em; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; height: 38px;}
        .btn-clear { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .error { color: #b30000; background: #ffe5e5; padding: 0.5em; border-radius: 4px; margin-bottom: 1em; }
        .success { color: #155724; background: #d4edda; padding: 0.5em; border-radius: 4px; margin-bottom: 1em; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .empty-state { text-align: center; color: #6c757d; padding: 2em; }
        .numeric-value { font-family: monospace; }
        .filters-container { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; justify-content: center; align-items: flex-end; }
        .filter { display: flex; flex-direction: column; align-items: flex-start; }
        .filter label { font-weight: 500; margin-bottom: 0.3rem; color: #333; }
        .filter select, .filter input { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #ccc; background: #fff; cursor: pointer; transition: all 0.3s ease; min-width: 180px; height: 38px; }
        .filter select:hover, .filter input:hover { border-color: #667eea; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); }
        .pagination-container { display: flex; justify-content: center; align-item: center; margin-top: 2rem; gap: 5px}
        .pagination-link { display: flex; align-item: center; justify-content: center; min-width: 40px; height: 40px; background: rgba(255, 255, 255, 0.9); padding: 0.5rem 0.5rem; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; color: #555; font-weight: 600; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .pagination-link { font-size: 1.1rem;}
        .pagination-link:hover { background: white; color: #667eea; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-decoration: none; }
        .pagination-link.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-color: transparent; box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3); }
        .pagination-link.disabled { background: rgba(255, 255, 255, 0.5); color: #aaa; cursor: not-allowed; pointer-events: none; }
        .pagination-info { text-align: center; margin-top: 1rem; color: rgba(14, 0, 0, 0.9); font-size: 0.9rem; }
    </style>
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
            <form method="POST" action="">
                <button type="submit" class="tempbtn">
                    <i class="fas fa-upload">Click Me</i>
                </button>
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
                            <th><i class="fas fa-water"></i> Watering Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['dateandtime'])); ?></td>
                                <td class="numeric-value"><?php echo $row['wateringstatus'] !== null ? 'Pumped' : '-'; ?></td>
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