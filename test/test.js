function reloadSensorData() {
    fetch('fetch_test_data.php')
    .then(res => res.json())
    .then(data => {
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

/* ================= SEND COMMAND ================= */

function sendCommand(command) {
    const liquidAmountInput = document.getElementById('liquidAmount');
    const liquidAmount = parseFloat(liquidAmountInput.value) || 0;

    if (liquidAmount <= 0) {
        alert("Please enter a valid liquid amount.");
        return;
    }

    fetch('../api/intel_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            command: command,
            liquidAmount: liquidAmount
        })
    })
    .then(res => res.json())
    .then(data => {
        console.log("Response:", data);
        alert(data.message);
    })
    .catch(err => {
        console.error("Error:", err);
        alert("Request failed");
    });
}

/* ================= BUTTON EVENTS ================= */

// VALVES (mapped to trig_tslX in PHP)
document.getElementById('valve-1').onclick = () => sendCommand('valve1');
document.getElementById('valve-2').onclick = () => sendCommand('valve2');
document.getElementById('valve-3').onclick = () => sendCommand('valve3');

// ALTERNATING
document.getElementById('valve-4').onclick = () => sendCommand('alternate');

/* ================= AUTO REFRESH ================= */

reloadSensorData();
setInterval(reloadSensorData, 5000);