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

                // 220L barrel tank - initial calculation
                const diameter = 550; // mm
                const totalHeight = 935; // mm
                const radius = diameter / 2; // mm
                const maxCapacity = 220; // Liters capacity of tank

                const sensorReadingMM = sensor.currentliquidlevel * 10; //convert cm to mm
                
                let liquidHeightMM = totalHeight - sensorReadingMM; 
                liquidHeightMM = Math.max(0, Math.min(totalHeight, liquidHeightMM));
                let liquidLiters = (Math.PI * Math.pow(radius, 2) * liquidHeightMM) / 1000000;
                
                // Calculate percentage based on 220L limit
                const percentage = (liquidLiters / maxCapacity) * 100;

                updateTank(sensor.liquidsensorID, Math.round(liquidLiters, 2), percentage);
            });
        })
        .catch(err => console.error(err));
}

document.addEventListener('DOMContentLoaded', () => {
    fetchLiquidLevel();
    setInterval(fetchLiquidLevel, 1000);
});

// function updateTank(sensorID, newLevel) {
//     const tank = document.querySelector(
//         `.tank[data-liquidsensor-id="${sensorID}"]`
//     );
//     if (!tank) return;

//     newLevel = Math.max(0, Math.min(100, newLevel));

//     const water = tank.querySelector('.water');
//     const text  = tank.querySelector('.level-text');

//     const oldLevel = parseInt(tank.dataset.level ?? newLevel);

//     if (tank._counter) {
//         clearInterval(tank._counter);
//         tank._counter = null;
//     }

//     if (oldLevel === newLevel) {
//         text.innerText = newLevel + ' L';
//         water.style.height = (newLevel)+ '%';
//         return;
//     }
// }

// function fetchLiquidLevel() {
//     fetch('fetch_liquidlevel_data.php')
//         .then(res => res.json())
//         .then(data => {
//             data.forEach(sensor => {
                
//                 liquidlevel = 100 - sensor.currentliquidlevel; // put calculation here if ready
//                 updateTank(sensor.liquidsensorID, parseInt(liquidlevel));
//             });
//         })
//         .catch(err => console.error(err));
// }

// document.addEventListener('DOMContentLoaded', () => {
//     fetchLiquidLevel();
//     setInterval(fetchLiquidLevel, 2000);
// });