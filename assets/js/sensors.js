function reloadSensorData() {
    const params = new URLSearchParams(window.location.search);

    fetch('../api/fetch_sensor_data.php?' + params.toString())
        .then(res => res.text())
        .then(html => {
            const tbody = document.getElementById('sensor-data-body');
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