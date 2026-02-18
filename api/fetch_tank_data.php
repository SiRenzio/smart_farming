<?php
session_start();
require_once '../db.php';

$tankID = $_GET['tankID'] ?? '';

// Initialize filtering
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo = $_GET['dateTo'] ?? '';


$whereSQL = " WHERE liquidsensorID = ? ";
$params = [$tankID];
$types = "i";

// Append optional filters
if ($filterDateFrom) {
    $whereSQL .= " AND dateandtime >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}
if ($filterDateTo) {
    $whereSQL .= " AND dateandtime <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}

$sql = "
    SELECT *
    FROM tankpumpevent
    $whereSQL
    ORDER BY dateandtime DESC
    LIMIT 15
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()):
?>
<tr>
    <td><?php echo date('M j, Y g:i A', strtotime($row['dateandtime'])); ?></td>
    <td class="numeric-value"><?php echo ($row['wateringstatus'] === 1) ? 'Pumped' : ($row['wateringstatus'] === 0 ? 'Hold Watering' : 'Able Watering'); ?></td>
    <td class="numeric-value"><?php echo ($row['wateringFlag'] === 1) ? 'Low' : ($row['wateringFlag'] === 0 ? 'Full' : 'Idle'); ?></td>
    <td class="numeric-value"><?php echo $row['wateringvolume'] !== null ? htmlspecialchars($row['wateringvolume']) : '-'; ?></td>
</tr>
<?php endwhile; ?>