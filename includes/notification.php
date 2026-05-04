<?php
require_once '../db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../assets/css/notifications.css" rel="stylesheet">
</head>
<body>
    <div class="notification-wrapper">
    <div class="notification-bell" id="notificationBell">
        <i class="fas fa-bell"></i>
        <span class="notification-count" id="notification-count"></span>
    </div>

    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notif-header">
            <strong>Notifications</strong>
        </div>
        <div class="notif-list" id="notification-list"></div>
    </div>
</div>
    <script src="../assets/js/notifications.js" defer></script>
</body>
</html>