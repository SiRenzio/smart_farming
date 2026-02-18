function reloadSensorData() {
    const params = new URLSearchParams(window.location.search);

    // Get tankID
    const tankID = params.get('tankID');

    if (!tankID) return;

    fetch(`../api/fetch_tank_data.php?tankID=${tankID}`)
        .then(res => res.text())
        .then(html => {
            const tbody = document.getElementById('tank-data-body');
            if (tbody) {
                tbody.innerHTML = html;
            }
        })
        .catch(err => console.error('Auto reload failed:', err));
}

const params = new URLSearchParams(window.location.search);
const currentPage = parseInt(params.get('page') || '1', 10);

if (currentPage === 1) {
    reloadSensorData();
    setInterval(reloadSensorData, 5000);
}