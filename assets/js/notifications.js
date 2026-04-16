const bell = document.getElementById("notificationBell");
const dropdown = document.getElementById("notificationDropdown");

bell.addEventListener("click", function (e) {
    e.stopPropagation();
    
    const isOpen = dropdown.style.display === "block";
    const count = document.getElementById('notification-count');
    
    // Check if there are currently unread notifications shown
    const hasUnread = count.style.display !== "none" && parseInt(count.textContent || 0) > 0;

    // Menu is open and new notifications arrived
    if (isOpen && hasUnread) {
        markAsRead(); 
        return; // Exit early so the dropdown DOES NOT close
    }

    // Standard open/close toggle behavior
    const isOpening = !isOpen;
    dropdown.style.display = isOpening ? "block" : "none";

    if (isOpening) {
        loadNotifications();
        setTimeout(markAsRead, 500);
    }
});

// Close when clicking outside
document.addEventListener("click", function () {
    dropdown.style.display = "none";
    markAsRead(); // Mark as read when closing the dropdown by clicking outside
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
                // Check if the notification is unread
                const isUnread = (n.isRead == 0);

                if (isUnread) {
                    unreadCount++;
                }

                // Create the element and apply classes dynamically
                const div = document.createElement('div');
                
                // This combines the classes and trims any extra spaces if safeType is empty
                div.className = `notif-item ${isUnread ? 'unread' : ''}`.trim();
                
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
        })
        .catch(error => console.error('Error fetching notifications:', error)); // Added basic error handling
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

        // Instantly remove the "unread" class from all notifications
        // so the background color changes back to normal without waiting for a reload
        const unreadItems = document.querySelectorAll('.notif-item.unread');
        unreadItems.forEach(item => item.classList.remove('unread'));
    })
    .catch(error => console.error('Error marking as read:', error));
}

// Auto refresh every 5 seconds
setInterval(loadNotifications, 3000);
loadNotifications();