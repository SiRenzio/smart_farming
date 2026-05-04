let chartInstances = {};

function reloadVisuals() {
    const params = new URLSearchParams(window.location.search);
    // ignore pagination
    params.delete('page');

    fetch('../api/fetch_sensor_data.php?type=visual&' + params.toString())
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            const carouselInner = document.getElementById('carousel-inner');
            if(!carouselInner) return; // Prevent errors if DOM is empty
            
            if (!data || data.length === 0) {
                carouselInner.innerHTML = '<div class="empty-state" style="width:100%; text-align:center; padding: 40px;"><p>No recent visual data available for the selected filters.</p></div>';
                checkCarouselButtons();
                return;
            }

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

            currentSensorIds.forEach(sid => {
                const group = groupedData[sid];
                const latest = group.readings[0];
                const chartData = [...group.readings].reverse();

                let slide = document.getElementById('slide-' + sid);
                
                if (!slide) {
                    slide = document.createElement('div');
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
                                <div class="stat-icon icon-temp">
                                    <i class="fas fa-thermometer-half"></i>
                                </div>
                                <div class="stat-info">
                                    <h3 id="temp-${sid}">--</h3>
                                    <p>Temp (°C)</p>
                                </div>
                            </div>
                            
                            <div class="stat-card mois">
                                <div class="stat-icon icon-mois">
                                    <i class="fas fa-tint"></i>
                                </div>
                                <div class="stat-info">
                                    <h3 id="mois-${sid}">--</h3>
                                    <p>Moisture (%)</p>
                                </div>
                            </div>
                            
                            <div class="stat-card ec">
                                <div class="stat-icon icon-ec">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="stat-info">
                                    <h3 id="ec-${sid}">--</h3>
                                    <p>Elec. Cond (EC)</p>
                                </div>
                            </div>
                            
                            <div class="stat-card ph">
                                <div class="stat-icon icon-ph">
                                    <i class="fas fa-flask"></i>
                                </div>
                                <div class="stat-info">
                                    <h3 id="ph-${sid}">--</h3>
                                    <p>Soil pH</p>
                                </div>
                            </div>
                        </div>

                        <div class="chart-wrapper">
                            <canvas id="chart-${sid}"></canvas>
                        </div>
                    `;

                    if (carouselInner.querySelector('.empty-state')) {
                        carouselInner.innerHTML = '';
                    }

                    carouselInner.appendChild(slide);

                    const ctx = document.getElementById('chart-' + sid).getContext('2d');
                    chartInstances[sid] = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [
                                { 
                                    label: 'Nitrogen (N)', 
                                    data: [], 
                                    borderColor: '#2196F3', 
                                    backgroundColor: 'rgba(33, 150, 243, 0.1)', 
                                    borderWidth: 2, 
                                    tension: 0.3, 
                                    fill: true 
                                },
                                { 
                                    label: 'Phosphorus (P)', 
                                    data: [], 
                                    borderColor: '#F44336', 
                                    backgroundColor: 'rgba(244, 67, 54, 0.1)', 
                                    borderWidth: 2, 
                                    tension: 0.3, 
                                    fill: true 
                                },
                                { 
                                    label: 'Potassium (K)', 
                                    data: [], 
                                    borderColor: '#4CAF50', 
                                    backgroundColor: 'rgba(76, 175, 80, 0.1)', 
                                    borderWidth: 2, 
                                    tension: 0.3, 
                                    fill: true 
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: { 
                                    display: true, 
                                    text: 'Realtime Soil Nutrient Levels', 
                                    font: { size: 16 } 
                                }
                            },
                            scales: {
                                x: { 
                                    title: { display: true, text: 'Time' } 
                                },
                                y: { 
                                    title: { display: true, text: 'Sensor Reading' }, 
                                    beginAtZero: true 
                                }
                            }
                        }
                    });
                }

                document.getElementById(`temp-${sid}`).innerText = latest.SoilT ?? '--';
                document.getElementById(`mois-${sid}`).innerText = latest.SoilMois ?? '--';
                document.getElementById(`ec-${sid}`).innerText = latest.SoilEC ?? '--';
                document.getElementById(`ph-${sid}`).innerText = latest.SoilPH ?? '--';

                const chart = chartInstances[sid];
                chart.data.labels = chartData.map(d =>
                    new Date(d.DateTime).toLocaleTimeString()
                );
                chart.data.datasets[0].data = chartData.map(d => parseFloat(d.SoilN) || 0);
                chart.data.datasets[1].data = chartData.map(d => parseFloat(d.SoilP) || 0);
                chart.data.datasets[2].data = chartData.map(d => parseFloat(d.SoilK) || 0);
                chart.update();
            });

            checkCarouselButtons();
        })
        .catch(err => console.error('Visual reload failed:', err));
}

function reloadSensorData() {
    const params = new URLSearchParams(window.location.search);

    // if not in page 1, skip reloading 
    if (params.has('page') && params.get('page') !== '1') {
        return;
    }

    fetch('../api/fetch_sensor_data.php?type=table&' + params.toString())
        .then(res => res.text())
        .then(html => {
            const tbody = document.getElementById('sensor-data-body');
            if (tbody) {
                tbody.innerHTML = html;
            }
        })
        .catch(err => console.error('Auto reload failed:', err));
}

// filtering without page refresh
function updateDataFromFilters(resetPage = true, targetPage = 1) {
    const filterForm = document.getElementById('filter-form');
    const formData = new FormData(filterForm);
    const params = new URLSearchParams(formData);

    // Handle pagination state
    if (!resetPage) {
        params.set('page', targetPage);
    } else {
        params.delete('page');
    }

    const newUrl = window.location.pathname + '?' + params.toString();
    
    // Update Browser URL seamlessly without reloading
    window.history.pushState({ path: newUrl }, '', newUrl);

    // Fetch the new filtered data 
    fetch(newUrl)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const newContainer = doc.getElementById('main-data-container');
            const currentContainer = document.getElementById('main-data-container');
            
            if (newContainer && currentContainer) {
                // reset existing charts to prevent memory leaks and ensure clean state
                for (let id in chartInstances) {
                    if (chartInstances[id]) chartInstances[id].destroy();
                }
                chartInstances = {}; 

                // replace current content with new content
                currentContainer.innerHTML = newContainer.innerHTML;
                
                // re-initialize graphs and pagination
                reloadVisuals();
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
            
            // update filters and fetch new data for the selected page without refreshing
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


// Master execution
document.addEventListener('DOMContentLoaded', () => {
    
    // Setup Filter Events
    const filterForm = document.getElementById('filter-form');
    const filterInputs = filterForm.querySelectorAll('select, input');
    const clearBtn = document.getElementById('btn-clear-filters');

    // Automatically update when inputs change
    filterInputs.forEach(input => {
        input.addEventListener('change', () => updateDataFromFilters(true));
    });

    // reset filters and refresh data when clear button is clicked
    if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            filterForm.reset();
            updateDataFromFilters(true);
        });
    }

    // Initialize visuals & bindings
    reloadVisuals();
    attachPaginationEvents();

    setInterval(() => {
        reloadVisuals();
        reloadSensorData();
    }, 5000);
});