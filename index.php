<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Farming App</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .welcome-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 3rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 500px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .app-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: white;
            box-shadow: 0 15px 30px rgba(76, 175, 80, 0.3);
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .welcome-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            font-weight: 400;
            line-height: 1.6;
        }

        .action-links {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .action-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .action-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .action-link:hover::before {
            left: 100%;
        }

        .btn-register {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }

        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(76, 175, 80, 0.4);
        }

        .btn-login {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(33, 150, 243, 0.4);
        }

        .features {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .features h3 {
            color: #666;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .feature-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .feature-item i {
            font-size: 1.2rem;
            color: #4CAF50;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .welcome-container {
                padding: 2rem;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .app-icon {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            
            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .feature-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <!-- App Icon -->
        <div class="app-icon">
            <i class="fas fa-seedling"></i>
        </div>

        <!-- Welcome Title -->
        <h1 class="welcome-title">Smart Farming</h1>
        <p class="welcome-subtitle">
            Revolutionize your agricultural practices with intelligent soil monitoring, 
            plant management, and data-driven insights for optimal crop yields.
        </p>

        <!-- Action Links -->
        <div class="action-links">
            <a href="pages/register.php" class="action-link btn-register">
                <i class="fas fa-user-plus"></i>
                Get Started
            </a>
            <a href="pages/login.php" class="action-link btn-login">
                <i class="fas fa-sign-in-alt"></i>
                Sign In
            </a>
        </div>

        <!-- Features Preview -->
        <div class="features">
            <h3>Key Features</h3>
            <div class="feature-grid">
                <div class="feature-item">
                    <i class="fas fa-microchip"></i>
                    <span>Soil Sensors</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-leaf"></i>
                    <span>Plant Management</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Data Analytics</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
