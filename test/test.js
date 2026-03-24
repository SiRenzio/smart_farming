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
            data.tankData.forEach(tank => {
                const sensorReadingM = tank.currentliquidlevel / 100; // Convert cm to m
                const diameter = 0.48;
                const totalHeight = 0.90;
                const radius = diameter / 2;
                let liquidHeightM = totalHeight - sensorReadingM; // Calculate liquid height in meters
                
                // Clamp values between 0 and totalHeight
                liquidHeightM = Math.max(0, Math.min(totalHeight, liquidHeightM));

                // Current liquid volume in Liters
                let liquidLiters = (Math.PI * Math.pow(radius, 2) * liquidHeightM) * 1000;

                updateTank(tank.liquidsensorID, tank.currentliquidlevel, Math.round(liquidLiters));
            });

        tbody.innerHTML = html;
    })
    .catch(err => console.error('Auto reload failed:', err));
}

function updateTank(sensorID, currentLevel, liters) {
    const tank = document.querySelector(`.tank-card[data-liquidsensor-id="${sensorID}"]`);
    console.log("Found element:", tank);
    if (!tank) return;

    const level = tank.querySelector('.liquid-level');
    level.innerText = currentLevel + ' cm' + ' | ' + liters + ' L';
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

function sendManualCommand(command) {
    fetch('manual_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ command: command })
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

// PUMP (mapped to trig_pump in PHP)
document.getElementById('pump-tank-1').onclick = () => sendManualCommand('pump1');
document.getElementById('pump-tank-2').onclick = () => sendManualCommand('pump2');
document.getElementById('pump-tank-3').onclick = () => sendManualCommand('pump3');

// Mixer
document.getElementById('mixer-tank-1').onclick = () => sendManualCommand('mixer1');
document.getElementById('mixer-tank-2').onclick = () => sendManualCommand('mixer2');

/* ================= AUTO REFRESH ================= */

reloadSensorData();
setInterval(reloadSensorData, 5000);