<?php
    session_start();

    // Define the path to the JSON file
    $jsonFile = '../failsafe/settings.json';

    // Default settings
    $settings = [
        'wateringTime' => 120,
        'backOffTime' => 120,
        'mixingTime' => 120
    ];

    $successMessage = false;

    // Load existing settings if the file exists
    if (file_exists($jsonFile)) {
        $jsonData = file_get_contents($jsonFile);
        $decodedData = json_decode($jsonData, true);
        if (is_array($decodedData)) {
            // Merge existing data with defaults in case some keys are missing
            $settings = array_merge($settings, $decodedData);
        }
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check which form was submitted and update the specific value
        if (isset($_POST['wateringTime'])) {
            $settings['wateringTime'] = (float)$_POST['wateringTime'];
        }
        if (isset($_POST['backOffTime'])) {
            $settings['backOffTime'] = (float)$_POST['backOffTime'];
        }
        if (isset($_POST['mixingTime'])) {
            $settings['mixingTime'] = (float)$_POST['mixingTime'];
        }

        // Save to JSON and trigger the success message
        if (file_put_contents($jsonFile, json_encode($settings, JSON_PRETTY_PRINT))) {
            $successMessage = true; 
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Failsafe Settings</title>
    <link rel="stylesheet" href="../assets/css/failsafe.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="page-container">
        <?php if ($successMessage): ?>
            <div id="successToast" class="success-toast">
                <i class="fas fa-check-circle"></i>
                <span>Settings saved successfully!</span>
            </div>
            
            <script>
                setTimeout(() => {
                    const toast = document.getElementById('successToast');
                    if(toast) {
                        toast.style.opacity = '0';
                        setTimeout(() => toast.remove(), 500); // Wait for fade transition
                    }
                }, 3000);
            </script>
        <?php endif; ?>
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>Failsafe Settings</h1>
            <p>Monitoring sensor connectivity and primary status to ensure accurate moisture analysis</p>
        </div>

        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="failsafe-grid">
            <div class="failsafe-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-title">
                        <h3>Watering Time</h3>
                    </div>
                </div>
                <div class="card-content">
                    <form action="" method="POST" class="failsafe-form">
                        <label for="wateringTime">Set failsafe watering time (in seconds):</label>
                        <input type="number" id="wateringTime" name="wateringTime" min="60" max="3600" value="<?php echo htmlspecialchars($settings['wateringTime']); ?>" step="any" required>
                        <button type="submit" class="btn btn-primary" id="wateringTimeBtn" disabled>Save</button>
                    </form>
                </div>
            </div>

            <div class="failsafe-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-title">
                        <h3>Hold Watering</h3>
                    </div>
                </div>
                <div class="card-content">
                    <form action="" method="POST" class="failsafe-form">
                        <label for="backOffTime">Set back-off time for watering (in seconds):</label>
                        <input type="number" id="backOffTime" name="backOffTime" min="60" max="3600" value="<?php echo htmlspecialchars($settings['backOffTime']); ?>" step="any" required>
                        <button type="submit" class="btn btn-primary" id="backOffTimeBtn" disabled>Save</button>
                    </form>
                </div>
            </div>

            <div class="failsafe-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-title">
                        <h3>Mixer Duration</h3>
                    </div>
                </div>
                <div class="card-content">
                    <form action="" method="POST" class="failsafe-form">
                        <label for="mixingTime">Set mixer/mixing time duration (in seconds):</label>
                        <input type="number" id="mixingTime" name="mixingTime" min="60" max="3600" value="<?php echo htmlspecialchars($settings['mixingTime']); ?>" step="any" required>
                        <button type="submit" class="btn btn-primary" id="mixingTimeBtn" disabled>Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/failsafe.js"></script>
</body>
</html>