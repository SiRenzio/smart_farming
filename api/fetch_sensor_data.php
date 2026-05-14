<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['userID'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userID = $_SESSION['userID'];

$filterSensor   = $_GET['sensor'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo   = $_GET['dateTo'] ?? '';

$type = $_GET['type'] ?? 'table';

$whereConditions = ["si.userID = ?"];
$params = [$userID];
$types = "i";

if ($filterSensor) {
    $whereConditions[] = "sd.SoilSensorID = ?";
    $params[] = $filterSensor;
    $types .= "i";
}
if ($filterLocation) {
    $whereConditions[] = "sd.locationID = ?";
    $params[] = $filterLocation;
    $types .= "i";
}
if ($filterDateFrom) {
    $whereConditions[] = "sd.DateTime >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}
if ($filterDateTo) {
    $whereConditions[] = "sd.DateTime <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}

$whereSQL = " WHERE " . implode(" AND ", $whereConditions);

// for graph
if ($type === 'visual') {

    header('Content-Type: application/json');

    $sql = "
        SELECT sd.DateTime, sd.SoilN, sd.SoilP, sd.SoilK, sd.SoilEC, sd.SoilPH, sd.SoilT, sd.SoilMois, 
               si.soilSensorID, si.sensorName, fl.farmName, d.isPrimary 
        FROM sensordata sd 
        INNER JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID 
        LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID
        LEFT JOIN deployment d ON sd.SoilSensorID = d.soilSensorID
        $whereSQL
        ORDER BY sd.DateTime DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode($data);
    exit;
}

// Sensor Data
$sql = "
    SELECT sd.*, si.sensorName, fl.farmName, p.plantName, n.nutritionSetName
    FROM sensordata sd
    INNER JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID
    LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID
    LEFT JOIN plantnutrionneed n ON sd.nutritionID = n.nutritionID
    LEFT JOIN plantinfo p ON n.plantID = p.plantID
    $whereSQL
    ORDER BY sd.DateTime DESC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()):
?>
<tr>
    <td>
        <div class="sensor-info">
            <strong><?= htmlspecialchars($row['sensorName']) ?></strong><br>
            <small><?= htmlspecialchars($row['farmName'] ?? 'Unknown') ?></small>
        </div>
    </td>
    <td><?= date('M j, Y g:i:s A', strtotime($row['DateTime'])) ?></td>
    <td><?= $row['SoilN'] ?? '-' ?></td>
    <td><?= $row['SoilP'] ?? '-' ?></td>
    <td><?= $row['SoilK'] ?? '-' ?></td>
    <td><?= $row['SoilEC'] ?? '-' ?></td>
    <td><?= $row['SoilPH'] ?? '-' ?></td>
    <td><?= $row['SoilT'] ?? '-' ?></td>
    <td><?= $row['SoilMois'] ?? '-' ?></td>
    <td>
        <div class="actions">
            <button class="details-btn" 
            data-id="<?= $row['SensorDataID'] ?>"
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
            data-mois="<?php echo $row['SoilMois'] !== null ? htmlspecialchars($row['SoilMois']) : '-'; ?>">
                <i class="fas fa-list"></i> Details
            </button>
        </div>
    </td>
</tr>
<?php endwhile;

$stmt->close();
?>