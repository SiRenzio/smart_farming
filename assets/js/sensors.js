// sensors.js
let chartInstances = {};
let lastVisualId = 0; // Tracks highest ID for charts
let lastTableId = 0;  // Tracks highest ID for the table

function reloadVisuals() {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.append('last_id', lastVisualId); // Pass the tracker

    fetch('../api/fetch_sensor_data.php?type=visual&' + params.toString())
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            const carouselInner = document.getElementById('carousel-inner');
            if(!carouselInner) return; 
            
            const isInitialLoad = (lastVisualId === 0);

            // If no data is returned
            if (!data || data.length === 0) {
                if (isInitialLoad) {
                    carouselInner.innerHTML = '<div class="empty-state" style="width:100%; text-align:center; padding: 40px;"><p>No recent visual data available for the selected filters.</p></div>';
                    checkCarouselButtons();
                }
                return; // Exit early, nothing to update
            }

            // Update our tracker to the highest ID in this batch
            const maxId = Math.max(...data.map(d => parseInt(d.SensorDataID, 10)));
            if (maxId > lastVisualId) lastVisualId = maxId;

            // Group data by sensor
            const groupedData = {};
            data.forEach(row => {
                const sid = row.soilSensorID;
                if (!groupedData[sid]) {
                    groupedData[sid] = {
                        sensorName: row.sensorName,
                        sensorPrimary: row.isPrimary === 1,
                        farmName: row.farmName || 'Unknown Location',
                        readings: []
                    };
                }
                if (groupedData[sid].readings.length < 10) {
                    groupedData[sid].readings.push(row);
                }
            });

            const currentSensorIds = Object.keys(groupedData);

            // ==========================================
            // SCENARIO 1: First time loading the page
            // ==========================================
            if (isInitialLoad) {
                if (carouselInner.querySelector('.empty-state')) {
                    carouselInner.innerHTML = '';
                }

                currentSensorIds.forEach(sid => {
                    const group = groupedData[sid];
                    const latest = group.readings[0];
                    const chartData = [...group.readings].reverse();

                    let slide = document.createElement('div');
                    slide.className = 'carousel-slide';
                    slide.id = 'slide-' + sid;
                    slide.innerHTML = `
                        <div class="slide-header">
                            <div class="header-title-wrapper">
                                <h3 class="sensor-title">
                                    <i class="fas fa-satellite-dish"></i> ${group.sensorName} 
                                    ${group.sensorPrimary ? `<span class="primary-indicator" title="Primary Sensor">Primary</span>` : ''} 
                                </h3>
                                <span class="sensor-location">
                                    <i class="fas fa-map-marker-alt"></i> ${group.farmName}
                                </span>
                            </div>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-card temp">
                                <div class="stat-icon icon-temp"><i class="fas fa-thermometer-half"></i></div>
                                <div class="stat-info"><h3 id="temp-${sid}">${latest.SoilT ?? '--'}</h3><p>Temp (°C)</p></div>
                            </div>
                            <div class="stat-card mois">
                                <div class="stat-icon icon-mois"><i class="fas fa-tint"></i></div>
                                <div class="stat-info"><h3 id="mois-${sid}">${latest.SoilMois ?? '--'}</h3><p>Moisture (%)</p></div>
                            </div>
                            <div class="stat-card ec">
                                <div class="stat-icon icon-ec"><i class="fas fa-bolt"></i></div>
                                <div class="stat-info"><h3 id="ec-${sid}">${latest.SoilEC ?? '--'}</h3><p>Elec. Cond (EC)</p></div>
                            </div>
                            <div class="stat-card ph">
                                <div class="stat-icon icon-ph"><i class="fas fa-flask"></i></div>
                                <div class="stat-info"><h3 id="ph-${sid}">${latest.SoilPH ?? '--'}</h3><p>Soil pH</p></div>
                            </div>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="chart-${sid}"></canvas>
                        </div>
                    `;

                    carouselInner.appendChild(slide);

                    const ctx = document.getElementById('chart-' + sid).getContext('2d');
                    chartInstances[sid] = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartData.map(d => new Date(d.DateTime).toLocaleTimeString()),
                            datasets: [
                                { label: 'Nitrogen (N)', data: chartData.map(d => parseFloat(d.SoilN) || 0), borderColor: '#2196F3', backgroundColor: 'rgba(33, 150, 243, 0.1)', borderWidth: 2, tension: 0.3, fill: true },
                                { label: 'Phosphorus (P)', data: chartData.map(d => parseFloat(d.SoilP) || 0), borderColor: '#F44336', backgroundColor: 'rgba(244, 67, 54, 0.1)', borderWidth: 2, tension: 0.3, fill: true },
                                { label: 'Potassium (K)', data: chartData.map(d => parseFloat(d.SoilK) || 0), borderColor: '#4CAF50', backgroundColor: 'rgba(76, 175, 80, 0.1)', borderWidth: 2, tension: 0.3, fill: true }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { title: { display: true, text: 'Realtime Soil Nutrient Levels', font: { size: 16 } } },
                            scales: {
                                x: { title: { display: true, text: 'Time' } },
                                y: { title: { display: true, text: 'Sensor Reading' }, beginAtZero: true }
                            }
                        }
                    });
                });
                checkCarouselButtons();
            } 
            // ==========================================
            // SCENARIO 2: Delta Update (New data arriving)
            // ==========================================
            else {
                currentSensorIds.forEach(sid => {
                    const group = groupedData[sid];
                    const latest = group.readings[0];
                    const newChartData = [...group.readings].reverse(); // oldest to newest of the NEW batch

                    // If a brand new sensor was deployed while the page was open, force a full reload
                    if (!chartInstances[sid]) {
                        lastVisualId = 0;
                        reloadVisuals();
                        return;
                    }

                    // 1. Update the static stat cards
                    document.getElementById(`temp-${sid}`).innerText = latest.SoilT ?? '--';
                    document.getElementById(`mois-${sid}`).innerText = latest.SoilMois ?? '--';
                    document.getElementById(`ec-${sid}`).innerText = latest.SoilEC ?? '--';
                    document.getElementById(`ph-${sid}`).innerText = latest.SoilPH ?? '--';

                    // 2. Smoothly push new data to Chart.js and remove oldest
                    const chart = chartInstances[sid];
                    newChartData.forEach(d => {
                        chart.data.labels.push(new Date(d.DateTime).toLocaleTimeString());
                        chart.data.datasets[0].data.push(parseFloat(d.SoilN) || 0);
                        chart.data.datasets[1].data.push(parseFloat(d.SoilP) || 0);
                        chart.data.datasets[2].data.push(parseFloat(d.SoilK) || 0);

                        // Enforce max 10 data points per line
                        if (chart.data.labels.length > 10) {
                            chart.data.labels.shift();
                            chart.data.datasets[0].data.shift();
                            chart.data.datasets[1].data.shift();
                            chart.data.datasets[2].data.shift();
                        }
                    });
                    chart.update(); // Smoothly animates the chart update!
                });
            }
        })
        .catch(err => console.error('Visual reload failed:', err));
}

function reloadSensorData() {
    const params = new URLSearchParams(window.location.search);

    // If not in page 1, skip reloading 
    if (params.has('page') && params.get('page') !== '1') return;
    
    params.append('last_id', lastTableId); // Pass the tracker

    fetch('../api/fetch_sensor_data.php?type=table&' + params.toString())
        .then(res => res.text())
        .then(html => {
            if (!html.trim()) return; // Exit if no new rows generated by PHP

            const tbody = document.getElementById('sensor-data-body');
            if (!tbody) return;

            if (lastTableId === 0) {
                // Initial load: Replace everything
                tbody.innerHTML = html;
            } else {
                // Delta update: Prepend new rows to the top
                tbody.insertAdjacentHTML('afterbegin', html);
                
                // Keep table limited to 10 rows
                while (tbody.children.length > 10) {
                    tbody.removeChild(tbody.lastElementChild);
                }
            }

            // Extract the highest SensorDataID from the currently visible buttons
            const buttons = tbody.querySelectorAll('.details-btn');
            let maxId = 0;
            buttons.forEach(btn => {
                const id = parseInt(btn.dataset.id, 10);
                if (id > maxId) maxId = id;
            });
            if (maxId > lastTableId) lastTableId = maxId;

        })
        .catch(err => console.error('Auto reload failed:', err));
}

// filtering without page refresh
function updateDataFromFilters(resetPage = true, targetPage = 1) {
    const filterForm = document.getElementById('filter-form');
    const formData = new FormData(filterForm);
    const params = new URLSearchParams(formData);

    if (!resetPage) {
        params.set('page', targetPage);
    } else {
        params.delete('page');
    }

    const newUrl = window.location.pathname + '?' + params.toString();
    window.history.pushState({ path: newUrl }, '', newUrl);

    // Reset trackers because the dataset filters have completely changed
    lastVisualId = 0;
    lastTableId = 0;

    fetch(newUrl)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const newContainer = doc.getElementById('main-data-container');
            const currentContainer = document.getElementById('main-data-container');
            
            if (newContainer && currentContainer) {
                for (let id in chartInstances) {
                    if (chartInstances[id]) chartInstances[id].destroy();
                }
                chartInstances = {}; 

                currentContainer.innerHTML = newContainer.innerHTML;
                
                reloadVisuals();
                reloadSensorData(); // Added this here to fetch initial table data on filter!
                attachPaginationEvents();
            }
        })
        .catch(err => console.error('Error fetching filtered data:', err));
}

function attachPaginationEvents() {
    const paginationLinks = document.querySelectorAll('.pagination-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            if (link.classList.contains('disabled') || link.classList.contains('active')) return;
            
            const url = new URL(link.href);
            const page = url.searchParams.get('page') || 1;
            
            updateDataFromFilters(false, page);
        });
    });
}

// Carousel Controls (unchanged)
window.scrollCarousel = function(direction) {
    const viewport = document.getElementById('carousel-inner');
    const scrollAmount = viewport.clientWidth;
    viewport.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

function checkCarouselButtons() {
    const viewport = document.getElementById('carousel-inner');
    if(!viewport) return;
    const prevBtn = document.querySelector('.carousel-btn.prev');
    const nextBtn = document.querySelector('.carousel-btn.next');

    const slidesCount = viewport.querySelectorAll('.carousel-slide').length;
    if (slidesCount > 1) {
        if(prevBtn) prevBtn.style.display = 'flex';
        if(nextBtn) nextBtn.style.display = 'flex';
    } else {
        if(prevBtn) prevBtn.style.display = 'none';
        if(nextBtn) nextBtn.style.display = 'none';
    }
}

// Modal handling
const closeBtn = document.getElementById("close-btn");

document.addEventListener("click", function(event) {
    const button = event.target.closest(".details-btn");

    if (button) {
        const data = button.dataset;
        const modalContent = document.getElementById("modal-content"); 
        const modal = document.getElementById("modal");               

        modalContent.innerHTML = `
            <div class="detail-item"><span>Sensor Name</span><span>${data.sensorname || '-'}</span></div>
            <div class="detail-item"><span>Farm Name</span><span>${data.farmname || '-'}</span></div>
            <div class="detail-item"><span>Plant Name</span><span>${data.plantname || '-'}</span></div>
            <div class="detail-item"><span>Nutrition Set</span><span>${data.nutritionset || '-'}</span></div>
            <div class="detail-item"><span>Date and Time</span><span>${data.datetime || '-'}</span></div>
            <div class="detail-item"><span>Nitrogen (N)</span><span>${data.n || '-'}</span></div>
            <div class="detail-item"><span>Phosphorus (P)</span><span>${data.p || '-'}</span></div>
            <div class="detail-item"><span>Potassium (K)</span><span>${data.k || '-'}</span></div>
            <div class="detail-item"><span>EC</span><span>${data.ec || '-'}</span></div>
            <div class="detail-item"><span>pH</span><span>${data.ph || '-'}</span></div>
            <div class="detail-item"><span>Temperature</span><span>${data.temp || '-'} °C</span></div>
            <div class="detail-item"><span>Moisture</span><span>${data.mois || '-'} %</span></div>
        `;

        modal.style.display = "block";
    }
});

closeBtn.addEventListener("click", () => {
    modal.style.display = "none";
});

modal.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal-backdrop")) {
        modal.style.display = "none";
    }
});


// MOBILE ACCORDION LOGIC FOR TABLE ROWS
document.addEventListener("click", function(event) {
    // Only execute if on mobile/tablet view
    if (window.innerWidth <= 768) {
        const row = event.target.closest("#sensor-data-body tr");
        // Ensure the click was on a table row, but NOT on a button (like the Details button)
        if (row && !event.target.closest(".actions") && !event.target.closest(".details-btn")) {
            row.classList.toggle("expanded");
        }
    }
});


// Master execution
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filter-form');
    const filterInputs = filterForm.querySelectorAll('select, input');
    const clearBtn = document.getElementById('btn-clear-filters');

    filterInputs.forEach(input => {
        input.addEventListener('change', () => updateDataFromFilters(true));
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            filterForm.reset();
            updateDataFromFilters(true);
        });
    }

    reloadVisuals();
    reloadSensorData(); // Fetch table on load!
    attachPaginationEvents();

    setInterval(() => {
        reloadVisuals();
        reloadSensorData();
    }, 10000);
});