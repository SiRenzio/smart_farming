<?php
session_start();
if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}
$username = htmlspecialchars($_SESSION['username']);
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
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            color: white;
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
        }

        @media (max-width: 480px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header Section -->
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

        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Plant Management Card -->
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

            <!-- View Plants Card -->
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

            <!-- Sensor Management Card -->
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

            <!-- View Sensors Card -->
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

            <!-- View Water Level Card -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-view">
                        <i class="fas fa-water"></i>
                    </div>
                    <div class="card-content">
                        <h3>Water Level Overview</h3>
                        <p>Monitor tanks water level.</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="water_tank.php" class="card-btn">
                        <i class="fas fa-list"></i> View Tanks Level
                    </a>
                </div>
            </div>

            <!-- Data Collection Card -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-data">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="card-content">
                        <h3>Data Collection</h3>
                        <p>Record soil readings and environmental data</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="add_sensor_data.php" class="card-btn">
                        <i class="fas fa-plus"></i> Add Sensor Data
                    </a>
                </div>
            </div>

            <!-- Data Analysis Card -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon icon-data">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-content">
                        <h3>Data Analysis</h3>
                        <p>Analyze trends and insights from your sensor data</p>
                    </div>
                </div>
                <div class="card-action">
                    <a href="sensor_data.php" class="card-btn">
                        <i class="fas fa-chart-bar"></i> View Sensor Data
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Stats Section -->
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
</body>
</html> 