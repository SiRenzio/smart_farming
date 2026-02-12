<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plantName = trim($_POST['plantName'] ?? '');
    $plantVariety = trim($_POST['plantVariety'] ?? '');

    // Validate
    if (!$plantName) {
        $errors[] = 'Plant name is required.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('INSERT INTO plantinfo (plantName, plantVariety) VALUES (?, ?)');
        $stmt->bind_param('ss', $plantName, $plantVariety);
        if ($stmt->execute()) {
            $plantID = $conn->insert_id; // Get the auto-generated ID
            $success = 'Plant added successfully! <a href="add_nutrition.php?plantID=' . $plantID . '">Add nutrition needs</a> or <a href="plants.php">view all plants</a>.';
        } else {
            $errors[] = 'Failed to add plant. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Plant - Smart Farming</title>
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

        .page-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .page-header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .error-message {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .success-message {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .success-message a {
            color: white;
            text-decoration: underline;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            background: white;
        }

        .form-input::placeholder {
            color: #999;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            margin-bottom: 1.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }

        .nav-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .nav-links a {
            display: inline-block;
            margin: 0 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .nav-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }
            
            .page-header, .form-card {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-seedling"></i>
            </div>
            <h1>Add New Plant</h1>
            <p>Register a new plant in your smart farming system</p>
        </div>

        <!-- Form Card -->
        <div class="form-card">
            <?php if ($errors): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="add_plant.php">
                <div class="form-group">
                    <label for="plantName">Plant Name *</label>
                    <input type="text" 
                           id="plantName"
                           name="plantName" 
                           class="form-input"
                           placeholder="Enter plant name (e.g., Tomato, Corn, Wheat)" 
                           required 
                           value="<?php echo htmlspecialchars($_POST['plantName'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="plantVariety">Plant Variety</label>
                    <input type="text" 
                           id="plantVariety"
                           name="plantVariety" 
                           class="form-input"
                           placeholder="Enter variety (e.g., Beefsteak, Sweet Corn, Winter Wheat)" 
                           value="<?php echo htmlspecialchars($_POST['plantVariety'] ?? ''); ?>">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i> Add Plant
                </button>
            </form>

            <div class="nav-links">
                <a href="dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="plants.php">
                    <i class="fas fa-list"></i> View All Plants
                </a>
            </div>
        </div>
    </div>
</body>
</html> 