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
    fetch('../failsafe/current_tank_levels.json?t=' + new Date().getTime())
        .then(res => res.json())
        .then(data => {
            for (const tankID in data) {
                const tank = data[tankID];

                // Barrel tank dimensions in Meters
                const diameter = 0.48;
                const totalHeight = 0.90;
                const radius = diameter / 2;

                // Max capacity in Liters 
                const maxCapacity = (Math.PI * Math.pow(radius, 2) * totalHeight) * 1000;
                const sensorReadingM = tank.currentliquidlevel / 100; // Convert cm to m
                let liquidHeightM = totalHeight - sensorReadingM; // Calculate liquid height in meters
                
                // Clamp values between 0 and totalHeight
                liquidHeightM = Math.max(0, Math.min(totalHeight, liquidHeightM));

                // Current liquid volume in Liters
                let liquidLiters = (Math.PI * Math.pow(radius, 2) * liquidHeightM) * 1000;
                
                // Calculate percentage
                const percentage = (liquidLiters / maxCapacity) * 100;

                updateTank(
                    tankID,
                    Math.round(liquidLiters),
                    percentage
                );
            }
        })
        .catch(err => console.error(err));
}

// Function to handle opening the nutrition modal
function showNutritionModal(sensorsData) {
    const modal = document.getElementById('nutritionModal');
    const modalContent = document.getElementById('nutritionModalContent');
    
    let htmlContent = '';

    sensorsData.forEach(sensor => {
        // Fallback for null values
        const nName = sensor.nutritionSetName || 'Not Set';
        const sType = sensor.soilType || 'N/A';
        const gStage = sensor.growthStage || 'N/A';
        const mMoisture = sensor.meanMoistureThreshold ? sensor.meanMoistureThreshold : 'N/A';
        const pCount = sensor.numberOfPlants || '0';
        const nitrogen = sensor.soilN || 'N/A';
        const phosphorus = sensor.soilP || 'N/A';
        const potassium = sensor.soilK || 'N/A';
        const ec = sensor.soilEC || 'N/A';
        const ph = sensor.soilPH || 'N/A';
        const lVolume = sensor.liquidVolume ? sensor.liquidVolume + ' L' : 'N/A';
        const fertilizer = sensor.fertilizerNames || 'N/A';
        const plantName = sensor.plantName || 'N/A';
        const plantVariety = sensor.plantVariety || 'N/A';

        let growthStage = 'N/A';

        if(gStage === 'vegetative'){
            growthStage = 'Vegetative';
        }
        else if(gStage === 'lateVegetative'){
            growthStage = 'Late Vegetative';
        }
        else if(gStage === 'floweringToFruiting') {
            growthStage = 'Flowering to Fruiting';
        }
        else if(gStage === 'harvesting'){
            growthStage = 'Harvesting';
        }

        htmlContent += `
            <div class="nutrition-item">
                <div class="deployment-header">
                    <h4><i class="fas fa-microchip"></i> Sensor: ${sensor.sensorName}</h4>
                    <span>Owner: ${sensor.username}</span>
                </div>
                <div class="nutrition-grid">
                    <div><strong>Plant Name:</strong> ${plantName}</div>
                    <div><strong>Plant Variety:</strong> ${plantVariety}</div>
                    <div><strong>Nutrition Set:</strong> ${nName}</div>
                    <div><strong>Soil Type:</strong> ${sType}</div>
                    <div><strong>Growth Stage:</strong> ${growthStage}</div>
                    <div><strong>Mean Moisture:</strong> ${mMoisture} | ${parseInt(mMoisture) + 5} | ${parseInt(mMoisture) + 10}</div>
                    <div><strong>No. of Plants:</strong> ${pCount}</div>
                    <div><strong>N:</strong> ${nitrogen} &nbsp;&nbsp;  <strong>P:</strong> ${phosphorus}, &nbsp;&nbsp;  <strong>K:</strong> ${potassium}</div>
                    <div><strong>EC:</strong> ${ec}</div>
                    <div><strong>pH:</strong> ${ph}</div>
                    <div><strong>Liquid Volume:</strong> ${lVolume}</div>
                    <div><strong>Fertilizer:</strong> ${fertilizer}</div>
                </div>
            </div>
        `;
    });

    if(htmlContent === '') {
        htmlContent = '<p>No nutrition data available for this location.</p>';
    }

    modalContent.innerHTML = htmlContent;
    modal.style.display = "block";
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

        // Group sensors by their latitude and longitude
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

        // Iterate grouped locations
        Object.values(groupedLocations).forEach(group => {
            const { lat, lng, sensors } = group;
            const allConnected = sensors.every(s => parseInt(s.isConnected) === 1);
            const currentIcon = allConnected ? greenIcon : redIcon;

            // Combine sensor names
            const combinedSensorNames = sensors.map(s => s.sensorName).join(' & ');
            // Combine owner names
            const uniqueOwners = [...new Set(sensors.map(s => s.username))].join(' & ');
            
            const marker = L.marker([lat, lng], { icon: currentIcon }).addTo(map);
            
            // Show combined sensor data in tooltip
            marker.bindTooltip(`
                <div class="sensor-label-content">
                    <strong>${combinedSensorNames}</strong><br>
                    <span>Owner: ${uniqueOwners}</span><br>
                    <span style="font-size: 0.7rem; color: #555;">(Click to view nutrition data)</span>
                </div>
            `, { 
                permanent: true, 
                direction: 'top', 
                className: 'sensor-map-label',
                offset: [0, -35]
            });

            // Make the marker clickable to open the Modal
            marker.on('click', function() {
                showNutritionModal(sensors);
            });

            bounds.push([lat, lng]);
        });

        // Automatically zoom and pan the map to fit all pins
        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [80, 80] });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLiquidLevel();
    setInterval(fetchLiquidLevel, 1000);
    initDashboardMap(); // Initialize map when DOM is fully loaded

    // Modal Close Logic
    const modal = document.getElementById('nutritionModal');
    const closeBtn = document.querySelector('.close-modal');

    if (closeBtn && modal) {
        // Close on X click
        closeBtn.onclick = function() {
            modal.style.display = "none";
        }
        // Close on outside click
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    }
});

const params = new URLSearchParams(window.location.search);
const currentPage = parseInt(params.get('page') || '1', 10);