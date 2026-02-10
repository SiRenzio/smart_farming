<?php
require_once 'db.php';

$filterSensor = $_GET['sensor'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo = $_GET['dateTo'] ?? '';

$whereSQL = " WHERE 1=1";
$params = [];
$types = "";

if ($filterSensor) {
    $whereSQL .= " AND sd.SoilSensorID = ?";
    $params[] = $filterSensor;
    $types .= "i";
}
if ($filterLocation) {
    $whereSQL .= " AND sd.locationID = ?";
    $params[] = $filterLocation;
    $types .= "i";
}
if ($filterDateFrom) {
    $whereSQL .= " AND sd.DateTime >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}
if ($filterDateTo) {
    $whereSQL .= " AND sd.DateTime <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}

$sql = "
SELECT sd.*, si.sensorName, fl.farmName
FROM sensordata sd
LEFT JOIN sensorinfo si ON sd.SoilSensorID = si.soilSensorID
LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID
$whereSQL
ORDER BY sd.DateTime DESC
LIMIT 15
";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
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
    <td><?= $row['liquidVolume'] ?? '-' ?></td>
    <td>
        <div class="actions">
            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this sensor data? This action cannot be undone.');">
                <input type="hidden" name="data_id" value="<?php echo $row['SensorDataID']; ?>">
                <button type="submit" name="delete_data" class="btn btn-delete"><i class="fas fa-trash"></i> Delete</button>
            </form>
        </div>
    </td>
</tr>
<?php endwhile; ?>