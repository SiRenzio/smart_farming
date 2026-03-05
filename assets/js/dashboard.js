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

                // Barrel tank - calculation in CM
                const diameter = 48;
                const totalHeight = 90;
                const radius = diameter / 2;

                // compute max capacity in liters
                const maxCapacity = (Math.PI * Math.pow(radius, 2) * totalHeight) / 1000;

                const sensorReadingCM = sensor.currentliquidlevel;
                
                let liquidHeightCM = totalHeight - sensorReadingCM; 
                liquidHeightCM = Math.max(0, Math.min(totalHeight, liquidHeightCM));

                // Current liquid volume in Liters
                let liquidLiters = (Math.PI * Math.pow(radius, 2) * liquidHeightCM) / 1000;
                
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