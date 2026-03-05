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

document.addEventListener('DOMContentLoaded', () => {
    fetchLiquidLevel();
    setInterval(fetchLiquidLevel, 1000);
});

const params = new URLSearchParams(window.location.search);
const currentPage = parseInt(params.get('page') || '1', 10);