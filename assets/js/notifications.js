const bell = document.getElementById("notificationBell");
const dropdown = document.getElementById("notificationDropdown");

bell.addEventListener("click", function (e) {
    e.stopPropagation();
    const isOpening = dropdown.style.display !== "block";

    dropdown.style.display = isOpening ? "block" : "none";

    if (isOpening) {
        loadNotifications();
        setTimeout(markAsRead, 500); // small delay after rendering
    }
});

// Close when clicking outside
document.addEventListener("click", function () {
    dropdown.style.display = "none";
});

function loadNotifications() {
    fetch('../api/fetch_notifications.php')
        .then(res => res.json())
        .then(data => {
            const list = document.getElementById('notification-list');
            const count = document.getElementById('notification-count');

            list.innerHTML = '';

            if (data.length === 0) {
                count.style.display = 'none';
                list.innerHTML = '<p>No notifications</p>';
                return;
            }

            let unreadCount = 0;

            data.forEach(n => {
                if (n.isRead == 0) unreadCount++;

                const div = document.createElement('div');
                div.className = 'notif-item ' + n.type;
                div.innerHTML = `
                    <p>${n.message}</p>
                    <small>${n.createdAt}</small>
                `;
                list.appendChild(div);
            });

            if (unreadCount > 0) {
                count.textContent = unreadCount;
                count.style.display = 'inline-block';
            } else {
                count.style.display = 'none';
            }
        });
}

function markAsRead() {
    fetch('../api/update_notification_indicator.php', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(() => {
        // Hide badge visually
        const count = document.getElementById('notification-count');
        if (count) count.style.display = 'none';
    });
}

// Auto refresh every 5 seconds
setInterval(loadNotifications, 3000);
loadNotifications();