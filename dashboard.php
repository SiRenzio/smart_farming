<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}
$username = htmlspecialchars($_SESSION['username']);

// Fetch name of tanks
$tankName1stmt = $conn->prepare('SELECT liquidtankname FROM liquidsensorinfo WHERE liquidsensorID = 1');
$tankName1stmt->execute();
$tankName1result = $tankName1stmt->get_result()->fetch_assoc();

$tankName2stmt = $conn->prepare('SELECT liquidtankname FROM liquidsensorinfo WHERE liquidsensorID = 2');
$tankName2stmt->execute();
$tankName2result = $tankName2stmt->get_result()->fetch_assoc();

$tankName3stmt = $conn->prepare('SELECT liquidtankname FROM liquidsensorinfo WHERE liquidsensorID = 3');
$tankName3stmt->execute();
$tankName3result = $tankName3stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            font-size: 1.1rem;
            color: #666;
            font-weight: 400;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
            text-decoration: none;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (min-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .span-2 {
                grid-column: span 2;
            }
        }
        @media (max-width: 1023px) and (min-width: 768px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .span-2 {
                grid-column: span 2;
            }
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .dashboard-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-icon {
            width: 50px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .icon-plant { background: linear-gradient(135deg, #4CAF50, #45a049); }
        .icon-sensor { background: linear-gradient(135deg, #2196F3, #1976D2); }
        .icon-data { background: linear-gradient(135deg, #FF9800, #F57C00); }
        .icon-view { background: linear-gradient(135deg, #9C27B0, #7B1FA2); }

        .card-content h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .card-content p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .card-action {
            margin-top: 1.5rem;
        }

        .card-btn {
            display: inline-block;
            width: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .card-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            text-decoration: none;
        }

        .stats-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-weight: 500;
        }

        /* --- WATER TANK LAYOUT --- */
        .tank-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            overflow: hidden;
        }
        
        .tank-container::before {
             content: '';
             position: absolute;
             top: 0;
             left: 0;
             right: 0;
             height: 4px;
             background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .tank-container:hover {
             transform: translateY(-10px);
             box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
             transition: all 0.3s ease;
        }

        .tanks-wrapper {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            gap: 1rem;
            width: 100%;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .tank-card {
            text-align: center;
            flex: 1 1 80px;
            min-width: 80px;
            max-width: 150px;
        }

        .tank-card-btn {
            text-decoration: none;
            cursor: pointer;
            display: block;
            transition: transform 0.3s ease;
        }

        .tank-card-btn:hover {
            transform: translateY(-5px);
        }

        .tank-card-btn:focus-visible {
            outline: 2px solid #4da3ff;
            border-radius: 12px;
        }

        .tank-name {
            margin-top: 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: #1976D2;
        }

        .tank {
            position: relative;
            width: 60%;
            margin: auto;
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            box-shadow: 
                inset 15px 0 20px rgba(0,0,0,0.05),
                inset -15px 0 20px rgba(0,0,0,0.05),
                0 10px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            z-index: 1;
            aspect-ratio: 4 / 5;
        }

        /* .tankmodal {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 100;
        } */

        .measurement {
            position: absolute;
            right: 15px;
            top: 20px;
            bottom: 20px;
            width: 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 4;
            opacity: 0.4;
        }
        .measurement div {
            width: 100%;
            height: 1px;
            background: #333;
        }
        .measurement div:nth-child(5n) {
            width: 150%;
            margin-left: -50%;
            height: 2px;
            background: #000;
        }

        .glass-glare {
            position: absolute;
            top: 0; left: 20px;
            width: 30px;
            height: 100%;
            background: linear-gradient(to right, rgba(255,255,255,0.1), rgba(255,255,255,0.4), rgba(255,255,255,0.1));
            z-index: 5;
            pointer-events: none;
        }

        .water {
            position: absolute;
            bottom: -7px;
            left: 0;
            width: 100%;
            height: 0; 
            background: linear-gradient(180deg, #4facfe 0%, #00f2fe 100%);
            transition: height 1.2s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
        }

        .wave-container {
            position: absolute;
            top: -15px; 
            left: 0;
            width: 100%;
            height: 30px;
            overflow: hidden;
        }

        .waves-svg {
            width: 200%;
            height: 60%;
        }

        .wave-path {
            animation: moveWave linear infinite;
        }

        .wave-back {
            fill: #00f2fe;
            opacity: 0.5;
            animation-duration: 4s;
            animation-direction: reverse;
        }
        
        .wave-mid {
            fill: #4facfe;
            opacity: 0.7;
            animation-duration: 7s;
        }

        .wave-front {
            fill: #4facfe;
            opacity: 1;
            animation-duration: 3s;
        }

        @keyframes moveWave {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .level-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1rem;
            font-weight: 700;
            color: #1976D2; 
            z-index: 10;
            text-shadow: 0 2px 10px rgba(255,255,255,0.8);
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            .header {
                padding: 1.5rem;
            }
            .welcome-section h1 {
                font-size: 2rem;
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .tank-card {
                flex: 1 1 70px;
                min-width: 70px;
                max-width: 120px;
            }
        }

        @media (max-width: 480px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .tanks-wrapper {
                gap: 0.5rem;
            }
            .tank-card {
                flex: 1 1 60px;
                min-width: 60px;
                max-width: 100px;
            }
            .tank-name {
                font-size: 0.85rem;
            }
            .level-text {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <div class="header-content">
                <div class="welcome-section">
                    <h1>Welcome back, <?php echo $username; ?>! 👋</h1>
                    <p>Manage your smart farming ecosystem with precision and ease</p>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        

        <div class="dashboard-grid">

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-plant">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="card-content">
                        <h3>Plant Management</h3>
                        <p>Add new plants and monitor their growth progress</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="add_plant.php" class="card-btn">
                        <i class="fas fa-plus"></i> Add New Plant
                    </a>
                </div>
            </div>

            <div class="tank-container span-2">
                <div class="card-header">
                    <div class="card-icon icon-view">
                        <i class="fas fa-water"></i>
                    </div>
                    <div class="card-content">
                        <h3>Water Tank Level Overview</h3>
                        <p>Monitor current tanks water level.</p>
                    </div>
                </div>

                <div class="tanks-wrapper">
                    <a href="view_tank_data.php?tankID=1" class="tank-card-btn">
                        <div class="tank-card">
                            <div class="tank" data-liquidsensor-id="1">
                                <div class="glass-glare"></div>
                                <div class="measurement">
                                    <div></div><div></div><div></div><div></div><div></div>
                                    <div></div><div></div><div></div><div></div><div></div>
                                    <div></div><div></div><div></div>
                                </div>
                                <div class="water">
                                    <div class="wave-container">
                                        <svg class="waves-svg" viewBox="0 0 288 50" preserveAspectRatio="none">
                                            <defs>
                                                <path id="wave" d="M0,25 C48,50 96,0 144,25 C192,50 240,0 288,25 V50 H0 Z" />
                                            </defs>
                                            <use xlink:href="#wave" x="0" y="0" class="wave-path wave-back" />
                                            <use xlink:href="#wave" x="0" y="3" class="wave-path wave-mid" />
                                            <use xlink:href="#wave" x="0" y="5" class="wave-path wave-front" />
                                        </svg>
                                    </div>
                                </div>
                                <span class="level-text"></span>
                            </div>
                            <div class="tank-name"><?php echo $tankName1result['liquidtankname']; ?></div>
                        </div>
                    </a>
                    
                    <a href="view_tank_data.php?tankID=2" class="tank-card-btn">
                        <div class="tank-card">
                            <div class="tank" data-liquidsensor-id="2">
                                <div class="glass-glare"></div>
                                <div class="measurement">
                                    <div></div><div></div><div></div><div></div><div></div>
                                    <div></div><div></div><div></div><div></div><div></div>
                                    <div></div><div></div><div></div>
                                </div>
                                <div class="water">
                                    <div class="wave-container">
                                        <svg class="waves-svg" viewBox="0 0 288 50" preserveAspectRatio="none">
                                            <defs>
                                                <path id="wave" d="M0,25 C48,50 96,0 144,25 C192,50 240,0 288,25 V50 H0 Z" />
                                            </defs>
                                            <use xlink:href="#wave" x="0" y="0" class="wave-path wave-back" />
                                            <use xlink:href="#wave" x="0" y="3" class="wave-path wave-mid" />
                                            <use xlink:href="#wave" x="0" y="5" class="wave-path wave-front" />
                                        </svg>
                                    </div>
                                </div>
                                <span class="level-text"></span>
                            </div>
                            <div class="tank-name"><?php echo $tankName2result['liquidtankname']; ?></div>
                        </div>
                    </a>
                    
                    <a href="view_tank_data.php?tankID=3" class="tank-card-btn">
                        <div class="tank-card">
                            <div class="tank" data-liquidsensor-id="3">
                                <div class="glass-glare"></div>
                                <div class="measurement">
                                    <div></div><div></div><div></div><div></div><div></div>
                                    <div></div><div></div><div></div><div></div><div></div>
                                    <div></div><div></div><div></div>
                                </div>
                                <div class="water">
                                    <div class="wave-container">
                                        <svg class="waves-svg" viewBox="0 0 288 50" preserveAspectRatio="none">
                                            <defs>
                                                <path id="wave" d="M0,25 C48,50 96,0 144,25 C192,50 240,0 288,25 V50 H0 Z" />
                                            </defs>
                                            <use xlink:href="#wave" x="0" y="0" class="wave-path wave-back" />
                                            <use xlink:href="#wave" x="0" y="3" class="wave-path wave-mid" />
                                            <use xlink:href="#wave" x="0" y="5" class="wave-path wave-front" />
                                        </svg>
                                    </div>
                                </div>
                                <span class="level-text"></span>
                            </div>
                            <div class="tank-name"><?php echo $tankName3result['liquidtankname']; ?></div>
                        </div>
                    </a>
                </div> 
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-view">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="card-content">
                        <h3>Plant Overview</h3>
                        <p>View and manage all your plants in one place</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="plants.php" class="card-btn">
                        <i class="fas fa-list"></i> View My Plants
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-sensor">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="card-content">
                        <h3>Sensor Management</h3>
                        <p>Deploy and configure soil monitoring sensors</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="add_sensor.php" class="card-btn">
                        <i class="fas fa-plus"></i> Add New Sensor
                    </a>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-view">
                        <i class="fas fa-satellite-dish"></i>
                    </div>
                    <div class="card-content">
                        <h3>Sensor Overview</h3>
                        <p>Monitor and manage all your deployed sensors</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="sensors.php" class="card-btn">
                        <i class="fas fa-list"></i> View Sensors
                    </a>
                </div>
            </div>
        </div>

        <div class="stats-section">
            <h2 style="text-align: center; margin-bottom: 2rem; color: #333; font-weight: 600;">
                <i class="fas fa-chart-pie"></i> Quick Overview
            </h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">🌱</div>
                    <div class="stat-label">Plant Management</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">📡</div>
                    <div class="stat-label">Sensor Network</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">📊</div>
                    <div class="stat-label">Data Analytics</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">⚡</div>
                    <div class="stat-label">Real-time Monitoring</div>
                </div>
            </div>
        </div>
    </div>

<script>
function updateTank(sensorID, newLevel) {
    const tank = document.querySelector(
        `.tank[data-liquidsensor-id="${sensorID}"]`
    );
    if (!tank) return;

    newLevel = Math.max(0, Math.min(100, newLevel));

    const water = tank.querySelector('.water');
    const text  = tank.querySelector('.level-text');

    const oldLevel = parseInt(tank.dataset.level ?? newLevel);

    if (tank._counter) {
        clearInterval(tank._counter);
        tank._counter = null;
    }

    if (oldLevel === newLevel) {
        text.innerText = newLevel + '%';
        water.style.height = newLevel + '%';
        return;
    }

    tank.dataset.level = newLevel;

    water.style.height = newLevel + '%';

    let current = oldLevel;
    const step = current < newLevel ? 1 : -1;

    tank._counter = setInterval(() => {
        current += step;

        if (
            (step === -1 && current <= newLevel) ||
            (step === 1 && current >= newLevel)
        ) {
            current = newLevel;
            clearInterval(tank._counter);
            tank._counter = null;
        }

        text.innerText = current + '%';
    }, 15);
}

function fetchLiquidLevel() {
    fetch('fetch_liquidlevel_data.php')
        .then(res => res.json())
        .then(data => {
            data.forEach(sensor => {
                updateTank(sensor.liquidsensorID, parseInt(100 - sensor.currentliquidlevel));
            });
        })
        .catch(err => console.error(err));
}

// // Tank Modal
// const tank1Btn = document.querySelector('.tank-btn1');
// const tankModal = document.querySelector('.tankmodal');

// tank1Btn.addEventListener('click', () => {
//     tankModal.style.display = 'flex';
//     tankModal.innerHTML = `
//         <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 400px; width: 90%; text-align: center; position: relative;">
//             <h2>Tank Details</h2>
//             <p>More information about Tank 1 can be displayed here.</p>
//             <button id="closeModal" style="margin-top: 1rem; padding: 0.5rem 1rem; border: none; background: #667eea; color: white; border-radius: 8px; cursor: pointer;">Close</button>
//         </div>
//     `;

//     const closeModalBtn = document.getElementById('closeModal');
//     closeModalBtn.addEventListener('click', () => {
//         tankModal.style.display = 'none';
//         tankModal.innerHTML = '';
//     });
// });

document.addEventListener('DOMContentLoaded', () => {
    fetchLiquidLevel();
    setInterval(fetchLiquidLevel, 2000);
});
</script>
</body>
</html> 