const bell = document.getElementById("notificationBell");
const dropdown = document.getElementById("notificationDropdown");
let lastNotificationId = 0; // Track the highest ID seen

bell.addEventListener("click", function (e) {
    e.stopPropagation();
    
    const isOpen = dropdown.style.display === "block";
    const count = document.getElementById('notification-count');
    
    const hasUnread = count.style.display !== "none" && parseInt(count.textContent || 0) > 0;

    if (isOpen && hasUnread) {
        markAsRead(); 
        return; 
    }

    const isOpening = !isOpen;
    dropdown.style.display = isOpening ? "block" : "none";

    if (isOpening) {
        // We only trigger load immediately if we want to ensure we have the very latest right on click
        loadNotifications();
        setTimeout(markAsRead, 500);
    }
});

document.addEventListener("click", function () {
    if (dropdown.style.display === "block") {
        dropdown.style.display = "none";
        markAsRead(); 
    }
});

dropdown.addEventListener("click", function (e) {
    e.stopPropagation();
});

function loadNotifications() {
    // Pass the last seen ID to the backend
    fetch(`../api/fetch_notifications.php?last_id=${lastNotificationId}`)
        .then(res => res.json())
        .then(data => {
            if (data.length === 0) return; // Exit early if no new data (saves processing)

            const list = document.getElementById('notification-list');
            
            // If this is the first load and we get data, clear the "No notifications" text
            if (lastNotificationId === 0 && list.innerHTML.includes('No notifications')) {
                list.innerHTML = '';
            }

            // The data is ordered DESC by createdAt, so data[0] is the absolute newest.
            // Update our tracker to the highest ID in this batch.
            const maxIdInBatch = Math.max(...data.map(n => n.notificationID));
            if (maxIdInBatch > lastNotificationId) {
                lastNotificationId = maxIdInBatch;
            }

            // Reverse the data array before prepending so they stack correctly in the UI
            // (Oldest of the new batch inserted first, newest inserted last at the very top)
            data.reverse().forEach(n => {
                const isUnread = (n.isRead == 0);
                const div = document.createElement('div');
                div.className = `notif-item ${isUnread ? 'unread' : ''}`.trim();
                div.innerHTML = `
                    <p>${n.message}</p>
                    <small>${n.createdAt}</small>
                `;
                // Insert at the top of the list
                list.prepend(div);
            });

            // Clean up: Prevent infinite DOM growth by removing oldest elements if over 99
            while (list.children.length > 99) {
                list.removeChild(list.lastChild);
            }

            updateUnreadCount();
        })
        .catch(error => console.error('Error fetching notifications:', error));
}

function updateUnreadCount() {
    const count = document.getElementById('notification-count');
    // Count how many unread items currently exist in the DOM
    const unreadCount = document.querySelectorAll('.notif-item.unread').length;

    if (unreadCount > 0) {
        count.textContent = unreadCount;
        count.style.display = 'inline-block';
    } else {
        count.style.display = 'none';
    }
}

function markAsRead() {
    fetch('../api/update_notification_indicator.php', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(() => {
        const count = document.getElementById('notification-count');
        if (count) count.style.display = 'none';

        const unreadItems = document.querySelectorAll('.notif-item.unread');
        unreadItems.forEach(item => item.classList.remove('unread'));
    })
    .catch(error => console.error('Error marking as read:', error));
}

// Auto refresh every 3 seconds
setInterval(loadNotifications, 5000);
// Initial load
loadNotifications();