<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensors - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* [Styles kept exactly as before] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: #333; }
        .page-container { max-width: 1500px; margin: 0 auto; padding: 2rem; }
        .page-header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); text-align: center; }
        .page-header .icon { width: 80px; height: 80px; background: linear-gradient(135deg, #2196F3, #1976D2); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem; color: white; }
        .page-header h1 { font-size: 2.2rem; font-weight: 700; background: linear-gradient(135deg, #2196F3, #1976D2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 0.5rem; }
        .page-header p { color: #666; font-size: 1.1rem; }
        .message-container { margin-bottom: 2rem; }
        .error-message { background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: white; padding: 1rem; border-radius: 12px; margin-bottom: 1rem; text-align: center; font-weight: 500; box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3); }
        .success-message { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 1rem; border-radius: 12px; margin-bottom: 1rem; text-align: center; font-weight: 500; box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); }
        .nav-links { text-align: center; margin-bottom: 2rem; }
        .nav-links a { display: inline-block; margin: 0 0.5rem; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 25px; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
        .nav-links a:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); text-decoration: none; }

        .tank-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            padding: 2rem;
        }

        .tank-card {
            border-radius: 20px;
            text-align: center;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            padding: 2rem 0;
        }

        /* TANK */
        .tank {
            position: relative;
            width: 220px;
            height: 260px;
            margin: auto;
            border-radius: 20px 20px 14px 14px;
            border: 4px solid #2196F3;
            overflow: hidden;
            background: #eaeaea;
        }


        /* WATER CONTAINER */
        .water {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 0;
            transition: height 1.2s ease-in-out;
            background: linear-gradient(180deg, #2196F3, #00BCD4);
        }


        /* SVG WAVES */
        .wave {
            position: absolute;
            top: -20px;
            left: 0;
            width: 200%;
            height: 40px;
            animation: waveMove 4s linear infinite;
        }


        .wave svg {
            width: 100%;
            height: 100%;
        }


        .wave path {
            animation: waveMorph 3s ease-in-out infinite;
        }


        /* LEVEL TEXT */
        .level-text {
            display: flex;
            margin-top: 8rem;
            justify-content: center;
            top: 12px;
            width: 100%;
            color: #fff;
            font-weight: 700;
            text-shadow: 0 2px 6px rgba(0,0,0,0.4);
        }


        /* NAME */
        .tank-name {
            margin-top: 1rem;
            font-weight: 600;
            color: #1976D2;
        }


        /* ANIMATIONS */
        @keyframes waveMove {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }


        @keyframes waveMorph {
        0% {
        d: path("M0 20 Q 30 10 60 20 T 120 20 T 180 20 T 240 20 V40 H0 Z");
        }
        50% {
        d: path("M0 20 Q 30 30 60 20 T 120 20 T 180 20 T 240 20 V40 H0 Z");
        }
        100% {
        d: path("M0 20 Q 30 10 60 20 T 120 20 T 180 20 T 240 20 V40 H0 Z");
        }
        }


    /* RESPONSIVE */
    @media (max-width: 480px) {
        .tank {
            width: 100px;
            height: 220px;
        }
    }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-water"></i>
            </div>
            <h1>Tanks Water Level</h1>
            <p>Monitor tanks water level</p>
        </div>

        <div class="message-container">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="tank-container">

            <div class="tank-card">
                <div class="tank" data-level="65">
                    <div class="water">
                        <span class="level-text"></span>
                        <div class="wave">
                            <svg viewBox="0 0 240 40" preserveAspectRatio="none">
                                <path fill="rgba(255,255,255,0.6)"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="tank-name">Tank 1</div>
            </div>
            

            <div class="tank-card">
                <div class="tank" data-level="65">
                    <div class="water">
                        <span class="level-text"></span>
                        <div class="wave">
                            <svg viewBox="0 0 240 40" preserveAspectRatio="none">
                                <path fill="rgba(255,255,255,0.6)"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="tank-name">Tank 1</div>
            </div>

            <div class="tank-card">
                <div class="tank" data-level="65">
                    <div class="water">
                        <span class="level-text"></span>
                        <div class="wave">
                            <svg viewBox="0 0 240 40" preserveAspectRatio="none">
                                <path fill="rgba(255,255,255,0.6)"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="tank-name">Tank 1</div>
            </div>
        </div>

    </div>
<script>
    document.querySelectorAll('.tank').forEach(tank => {
    const level = tank.dataset.level;
    const water = tank.querySelector('.water');
    const text = tank.querySelector('.level-text');


    water.style.height = level + '%';
    text.textContent = level + '%';
    });
</script>
</body>
</html>