document.addEventListener('DOMContentLoaded', () => {

    // update content of the pages
    function updateContent(urlStr) {
        fetch(urlStr, { cache: 'no-store' })
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Update Table Content
                const newContent = doc.getElementById('data-wrapper');
                const currentContent = document.getElementById('data-wrapper');
                if (newContent && currentContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                }

                // Update Indicator Content
                const newIndicator = doc.getElementById('tank-status-container');
                const currentIndicator = document.getElementById('tank-status-container');
                if (newIndicator && currentIndicator) {
                    currentIndicator.innerHTML = newIndicator.innerHTML;
                }

                // Update Cancel Button Content
                const newCancelWrapper = doc.getElementById('cancel-mixing-wrapper');
                const currentCancelWrapper = document.getElementById('cancel-mixing-wrapper');
                if (newCancelWrapper && currentCancelWrapper) {
                    currentCancelWrapper.innerHTML = newCancelWrapper.innerHTML;
                }
            })
            .catch(err => console.error('Data update failed:', err));
    }

    // filtering event
    function applyFilters() {
        const url = new URL(window.location.href);
        url.searchParams.set('event', document.getElementById('event').value);
        url.searchParams.set('dateFrom', document.getElementById('dateFrom').value);
        url.searchParams.set('dateTo', document.getElementById('dateTo').value);
        url.searchParams.set('page', '1'); // Reset to first page on filter change
        
        window.history.pushState({}, '', url);
        updateContent(url.toString());
        reloadSensorData(1);
    }

    // Assigning applyFilters explicitly to the window scope so it can be called directly from inline HTML
    window.applyFilters = applyFilters;

    document.getElementById('event').addEventListener('change', applyFilters);
    document.getElementById('dateFrom').addEventListener('change', applyFilters);
    document.getElementById('dateTo').addEventListener('change', applyFilters);

    //Pagination and Clear button handling
    document.addEventListener('click', function(e) {
        
        // Pagination
        const paginationLink = e.target.closest('.pagination-link');
        if (paginationLink && !paginationLink.classList.contains('disabled')) {
            e.preventDefault();
            
            const url = new URL(paginationLink.href);
            window.history.pushState({}, '', url);
            updateContent(url.toString());
            
            const newPage = parseInt(url.searchParams.get('page') || '1', 10);
            reloadSensorData(newPage);
        }

        // Clear Button
        const clearLink = e.target.closest('.btn-clear, .clear-empty-link');
        if (clearLink) {
            e.preventDefault();
            
            const url = new URL(clearLink.href);
            window.history.pushState({}, '', url);
            
            // Visually clear the input fields
            document.getElementById('event').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            
            updateContent(url.toString());
            reloadSensorData(1);
        }
    });

    let autoReloadInterval = null;

    function reloadSensorData(page) {
        if (autoReloadInterval) {
            clearInterval(autoReloadInterval);
        }
        
        // Only run live updates if the user is looking at the first page
        if (page === 1) {
            autoReloadInterval = setInterval(() => {
                updateContent(window.location.href);
            }, 2500); 
        }
    }

    document.addEventListener('click', function(e) {
        // Look for clicks on the cancelBtn (or the icon inside it)
        const btn = e.target.closest('#cancelBtn');
        
        // If the button was clicked and it's not disabled
        if (btn && !btn.disabled) {
            e.preventDefault(); 
            
            // Immediately disable visually to prevent double clicks
            btn.disabled = true;
            btn.style.backgroundColor = '#bdc3c7';
            btn.style.cursor = 'not-allowed';

            // Grab the tankID we stored in the HTML data attribute
            const tankID = btn.getAttribute('data-tankid');
            
            // Send the API request
            fetch('../api/cancel_mixing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json' // Tell PHP we are sending JSON
                },
                body: JSON.stringify({ tankID: tankID })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    console.log("Mixing cancelled successfully via API.");
                    // Your 2.5s auto-reload will handle updating the button state 
                    // once the backend databases update!
                } else {
                    console.error("Cancellation failed:", data.error);
                }
            })
            .catch(err => console.error('API request failed:', err));
        }
    });

    const initialParams = new URLSearchParams(window.location.search);
    const currentPage = parseInt(initialParams.get('page') || '1', 10);
    reloadSensorData(currentPage);
});