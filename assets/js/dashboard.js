function updateTank(sensorID, liters, percent) {
    const tank = document.querySelector(
        `.tank[data-liquidsensor-id="${sensorID}"]`
    );
    if (!tank) return;

    const visualHeight = Math.max(0, Math.min(100, percent));

    const water = tank.querySelector('.water');
    const text  = tank.querySelector('.level-text');

    text.innerText = liters + ' L';
    water.style.height = visualHeight + '%';
}

function fetchLiquidLevel() {
    fetch('../api/fetch_liquidlevel_data.php')
        .then(res => res.json())
        .then(data => {
            data.forEach(sensor => {

                // Barrel tank dimensions in Meters
                const diameter = 0.48;
                const totalHeight = 0.90;
                const radius = diameter / 2;

                // Max capacity in Liters 
                const maxCapacity = (Math.PI * Math.pow(radius, 2) * totalHeight) * 1000;
                const sensorReadingM = sensor.currentliquidlevel / 100; // Convert cm to m
                let liquidHeightM = totalHeight - sensorReadingM; // Calculate liquid height in meters
                
                // Clamp values between 0 and totalHeight
                liquidHeightM = Math.max(0, Math.min(totalHeight, liquidHeightM));

                // Current liquid volume in Liters
                let liquidLiters = (Math.PI * Math.pow(radius, 2) * liquidHeightM) * 1000;
                
                // Calculate percentage
                const percentage = (liquidLiters / maxCapacity) * 100;

                updateTank(
                    sensor.liquidsensorID,
                    Math.round(liquidLiters),
                    percentage
                );
            });
        })
        .catch(err => console.error(err));
}

// Map initialization function
function initDashboardMap() {
    const mapElement = document.getElementById('dashboard-map');
    if (!mapElement) return;
    const map = L.map('dashboard-map').setView([10.7202, 122.5621], 10); // map center
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Define Green Icon (Connected = 1)
    const greenIcon = new L.Icon({
        iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    });

    // Define Red Icon (Disconnected = 0)
    const redIcon = new L.Icon({
        iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    });

    // Loop through the data passed from PHP
    if (window.sensorMapData && window.sensorMapData.length > 0) {
        const bounds = [];
        const groupedLocations = {};

        // 1. Group sensors by their latitude and longitude
        window.sensorMapData.forEach(sensor => {
            const lat = parseFloat(sensor.latitude);
            const lng = parseFloat(sensor.longitude);
            
            // Create a unique key for the location (using 5 decimal places for ~1 meter precision)
            const locationKey = `${lat.toFixed(5)},${lng.toFixed(5)}`;

            if (!groupedLocations[locationKey]) {
                groupedLocations[locationKey] = {
                    lat: lat,
                    lng: lng,
                    sensors: []
                };
            }
            groupedLocations[locationKey].sensors.push(sensor);
        });

        // grouped locations
        Object.values(groupedLocations).forEach(group => {
            const { lat, lng, sensors } = group;
            const allConnected = sensors.every(s => parseInt(s.isConnected) === 1);
            const currentIcon = allConnected ? greenIcon : redIcon;

            // Combine sensor names
            const combinedSensorNames = sensors.map(s => s.sensorName).join(' & ');
            // Combine owner names but
            const uniqueOwners = [...new Set(sensors.map(s => s.username))].join(' & ');
            const marker = L.marker([lat, lng], { icon: currentIcon }).addTo(map);
            
            // Show combined sensor data in tooltip
            marker.bindTooltip(`
                <div class="sensor-label-content">
                    <strong>${combinedSensorNames}</strong><br>
                    <span>Owner: ${uniqueOwners}</span>
                </div>
            `, { 
                permanent: true, 
                direction: 'top', 
                className: 'sensor-map-label',
                offset: [0, -35]
            });

            bounds.push([lat, lng]);
        });

        // Automatically zoom and pan the map to fit all pins
        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLiquidLevel();
    setInterval(fetchLiquidLevel, 1000);
    initDashboardMap(); // Initialize map when DOM is fully loaded
});

const params = new URLSearchParams(window.location.search);
const currentPage = parseInt(params.get('page') || '1', 10);