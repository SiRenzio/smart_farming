function reloadSensorData() {
    fetch('fetch_test_data.php')
    .then(res => res.json())
        .then(data => {
            console.log(data);
            const tbody = document.getElementById('sensor-data-body');
            let html = "";
            if (!data.sensorData || data.sensorData.length === 0) {
                tbody.innerHTML = "<tr><td colspan='9'>No data available</td></tr>";
                return;
            }
            data.sensorData.forEach(row => {
                html += `
                    <tr>
                        <td>
                            <div class="sensor-info">
                                <strong>${row.sensorName}</strong><br>
                                <small>${row.farmName ?? 'Unknown'}</small>
                            </div>
                        </td>
                        <td>${formatDate(row.DateTime)}</td>
                        <td>${row.SoilN ?? '-'}</td>
                        <td>${row.SoilP ?? '-'}</td>
                        <td>${row.SoilK ?? '-'}</td>
                        <td>${row.SoilEC ?? '-'}</td>
                        <td>${row.SoilPH ?? '-'}</td>
                        <td>${row.SoilT ?? '-'}</td>
                        <td>${row.SoilMois ?? '-'}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        })
    .catch(err => console.error('Auto reload failed:', err));
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit'
    });
}

reloadSensorData();
setInterval(reloadSensorData, 5000);