<?php
session_start();
require_once '../db.php';
require_once '../includes/notification.php';

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
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <div class="header-content">
                <div class="welcome-section">
                    <h1>Welcome back, <?php echo $username; ?>! 👋</h1>
                    <p>Manage your smart farming ecosystem with precision and ease</p>
                </div>
                <div class="header-actions">
                    <a href="#" class="notif-btn">
                        <i class="fas fa-bell"></i>
                    </a>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
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
                        <h3>Liquid Tank Level Overview</h3>
                        <p>Monitor current tanks liquid level.</p>
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
                    <a href="manage_sensors.php" class="card-btn">
                        <i class="fas fa-paper-plane"></i> Deploy Sensor
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
<script src="../assets/js/dashboard.js"></script>