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

    // Default center (Iloilo City)
    const map = L.map('dashboard-map').setView([10.7202, 122.5621], 10);
    
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
        // Create an array to hold marker coordinates so we can auto-zoom to fit them
        const bounds = [];

        window.sensorMapData.forEach(sensor => {
            const lat = parseFloat(sensor.latitude);
            const lng = parseFloat(sensor.longitude);
            
            // Choose color based on connection status
            const currentIcon = parseInt(sensor.isConnected) === 1 ? greenIcon : redIcon;

            // Add marker to map
            const marker = L.marker([lat, lng], { icon: currentIcon }).addTo(map);
            
            // show sensor name
            marker.bindTooltip(`
                <div class="sensor-label-content">
                    <strong>${sensor.sensorName}</strong>
                    <span>Owner: ${sensor.username}</span>
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