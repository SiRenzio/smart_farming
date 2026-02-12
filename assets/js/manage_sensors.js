// UI States
const UI_STATES = {
    OFFLINE: 'offline',
    ONLINE_IDLE: 'online_idle',
    CONFIGURED: 'configured',
    UNREGISTERED: 'unregistered'
};

// Helper to get elements within a sensor box
function getBoxEls(box) {
    return {
        checkbox: box.querySelector('.sensor-checkbox'),
        locationLabel: box.querySelector('.location'),
        locationSelect: box.querySelector('.location-select'),
        select: box.querySelector('select'),
        sendBtn: box.querySelector('.send-button'),
        disconnectBtn: box.querySelector('.disconnect'),
        statusText: box.querySelector('.status-text'),
        indicator: box.querySelector('.indicator'),
        offlineText: box.querySelector('.offline-text'),
        onlineText: box.querySelector('.online-text'),
        configuredText: box.querySelector('.configured-text'),
        displayName: box.querySelector('.display-location-name'),
        unregisteredText: box.querySelector('.unregistered-text'),
        registerBtn: box.querySelector('.register'),
        addSensorModal: box.querySelector('.add-sensor-modal'),
        formSensorName: box.querySelector('.form-input'),
        sensorNameLabel: box.querySelector('.sensor-name')
    };
}

function renderState(box, state) {
    const els = getBoxEls(box);

    switch (state) {
        case UI_STATES.CONFIGURED:
            els.checkbox.style.display = 'none';
            els.locationLabel.style.display = 'block';

                els.locationSelect.style.display = 'none';
                els.select.disabled = true;

            els.sendBtn.style.display = 'none';
            els.disconnectBtn.style.display = 'block';
            els.configuredText.style.display = 'block';

            els.statusText.textContent = 'Connected';
            els.indicator.style.background = '#4CAF50';
            els.offlineText.style.display = 'none';
            els.onlineText.style.display = 'none';
            els.unregisteredText.style.display = 'none';
            els.registerBtn.style.display = 'none';
            break;

        case UI_STATES.ONLINE_IDLE:
            els.checkbox.style.display = 'inline-block';
            els.checkbox.disabled = false;
            els.checkbox.checked = false;

            els.locationLabel.style.display = 'none';
            els.locationSelect.style.display = 'none';
            els.configuredText.style.display = 'none';
            els.select.disabled = false;
            els.select.value = '';

            els.sendBtn.style.display = 'block';
            els.sendBtn.disabled = true;
            els.disconnectBtn.style.display = 'none';

            els.statusText.textContent = 'Online';
            els.indicator.style.background = '#4CAF50';
            els.offlineText.style.display = 'none';
            els.onlineText.style.display = 'block';
            els.unregisteredText.style.display = 'none';
            els.registerBtn.style.display = 'none';
            break;

        case UI_STATES.UNREGISTERED:
            els.checkbox.style.display = 'none';
            els.checkbox.disabled = true;
            els.checkbox.checked = false;

            els.locationLabel.style.display = 'none';
            els.locationSelect.style.display = 'none';
            els.configuredText.style.display = 'none';

            els.sendBtn.style.display = 'none';
            els.disconnectBtn.style.display = 'none';

            els.statusText.textContent = 'Unregistered';
            els.indicator.style.background = '#ff9800';
            els.offlineText.style.display = 'none';
            els.onlineText.style.display = 'none';
            els.unregisteredText.style.display = 'block';
            els.registerBtn.style.display = 'block';
            break;  

        case UI_STATES.OFFLINE:
        default:
            els.checkbox.style.display = 'none';
            els.checkbox.disabled = true;
            els.checkbox.checked = false;

            els.locationLabel.style.display = 'none';
            els.locationSelect.style.display = 'none';
            els.configuredText.style.display = 'none';

            els.sendBtn.style.display = 'none';
            els.disconnectBtn.style.display = 'none';

            els.statusText.textContent = 'Offline';
            els.indicator.style.background = '#f44336';
            els.offlineText.style.display = 'block';
            els.onlineText.style.display = 'none';
            els.unregisteredText.style.display = 'none';
            els.registerBtn.style.display = 'none';
            break;
    }
}

function toggleLocation(cb) {
    const box = cb.closest('.sensor-box');
    const { locationSelect, select, sendBtn, onlineText } = getBoxEls(box);

    box.dataset.userInteracting = cb.checked ? '1' : '0';
    onlineText.style.display = 'none';

    locationSelect.style.display = cb.checked ? 'block' : 'none';
    select.required = cb.checked;
    if (!cb.checked) {
        select.value = '';
        sendBtn.disabled = true;
    }
}

document.querySelectorAll('.location-select select').forEach(select => {
    select.addEventListener('change', () => {
        const box = select.closest('.sensor-box');
        const { checkbox, sendBtn } = getBoxEls(box);
        sendBtn.disabled = !(checkbox.checked && select.value);
    });
});

let firstLoadDone = false;

function updateSensors() {
    fetch('../api/fetch_sensor.php')
        .then(res => res.json())
        .then(data => {
            const container = document.querySelector('.sensors-container');
            const emptyState = document.querySelector('.empty-state');

            if (!firstLoadDone) {
                document.getElementById('app-loading').style.display = 'none';
                firstLoadDone = true;
            }

            // Handle empty state
            emptyState.style.display = data.length === 0 ? 'block' : 'none';

            // Collect sensor IDs from backend
            const serverIDs = new Set(
                data.map(s => String(s.soilSensorID))
            );

            //  Remove DOM sensors not in DB anymore
            container.querySelectorAll('.sensor-box').forEach(box => {
                const id = box.dataset.sensorId;
                if (!serverIDs.has(id)) {
                    box.remove();
                }
            });

            // Update existing sensors
            data.forEach(sensor => {
                const box = document.querySelector(
                    `.sensor-box[data-sensor-id="${sensor.soilSensorID}"]`
                );

                // If brand-new sensor appears
                if (!box) {
                    location.reload();
                    return;
                }

                const els = getBoxEls(box);

                // Update location name if configured
                if (sensor.sensorStatus == 1 && sensor.isConnected == 1) {
                    if (els.displayName) {
                        els.displayName.textContent = sensor.farmName || 'Unknown';
                    }
                }

                if (box.dataset.userInteracting === '1') return;

                // Render correct UI state
                if (sensor.isRegistered == 0) {
                    renderState(box, UI_STATES.UNREGISTERED);
                    els.sensorNameLabel.textContent = 'Unknown';
                } 
                else if (sensor.sensorStatus == 1 && sensor.isConnected == 1) {
                    renderState(box, UI_STATES.CONFIGURED);
                } 
                else if (sensor.sensorStatus == 1) {
                    renderState(box, UI_STATES.ONLINE_IDLE);
                    els.sensorNameLabel.textContent = sensor.sensorName || 'Unknown';
                } 
                else {
                    renderState(box, UI_STATES.OFFLINE);
                }
            });
        })
        .catch(err => console.error('AJAX error:', err));
}

function registerSensor(sensorID) {
    const box = document.querySelector(`.sensor-box[data-sensor-id="${sensorID}"]`);
    const { addSensorModal, formSensorName } = getBoxEls(box);

    formSensorName.value = '';
    addSensorModal.style.display = 'block';
    document.getElementById('modal-backdrop').style.display = 'block';
}

function closeRegisterModal() {
    document.querySelectorAll('.add-sensor-modal').forEach(modal => {
        modal.style.display = 'none';
    });
    document.getElementById('modal-backdrop').style.display = 'none';
}

function connectSensor  (btn) {
    const box = btn.closest('.sensor-box');
    const { select, displayName } = getBoxEls(box);

    btn.disabled = true;
    const selectLocationName = select.options[select.selectedIndex].text;

    fetch('../api/connect_sensor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            sensor_id: box.dataset.sensorId,
            location_id: select.value,
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message);
        }
        else {
            alert(data.message);
        }
        if(displayName) displayName.textContent = selectLocationName;
        box.dataset.userInteracting = '0';
        renderState(box, UI_STATES.CONFIGURED);
    })
    .catch(err => {
        alert(err.message || 'Server error');
        btn.disabled = false;
    });
}

function disconnectSensor(btn) {
    const box = btn.closest('.sensor-box');

    btn.disabled = true;

    fetch('../api/disconnect_sensor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ sensor_id: btn.dataset.id })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message);
        }
        else {
            alert(data.message);
        }
        box.dataset.userInteracting = '0';
        renderState(box, UI_STATES.ONLINE_IDLE);
    })
    .catch(err => alert(err.message || 'Server error'))
    .finally(() => btn.disabled = false);
}

// Poll every 3 seconds
setInterval(updateSensors, 1000);