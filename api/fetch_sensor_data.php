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
    SELECT sd.*, si.sensorName, fl.farmName
    FROM sensordata sd
    INNER JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID
    LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID
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
            <form method="post" style="display: inline;" 
                  onsubmit="return confirm('Are you sure you want to delete this sensor data? This action cannot be undone.');">
                <input type="hidden" name="data_id" value="<?= $row['SensorDataID']; ?>">
                <button type="submit" name="delete_data" class="btn btn-delete">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </td>
</tr>
<?php endwhile;

$stmt->close();
?>