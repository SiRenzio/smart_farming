document.addEventListener('DOMContentLoaded', function() {
    const defaultLat = 10.715183;
    const defaultLon = 122.566076;
    const map = L.map('map').setView([defaultLat, defaultLon], 17);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const redIcon = new L.Icon({
        iconUrl: '../assets/leaflet/images/marker-icon-2x-red.png',
        shadowUrl: '../assets/leaflet/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    let currentMarker = L.marker([defaultLat, defaultLon], {
        icon: redIcon,
        draggable: true 
    }).addTo(map);

    function setPin(latlng) {
        document.getElementById('coord-display').textContent = `Lat: ${latlng.lat.toFixed(6)}, Lon: ${latlng.lng.toFixed(6)}`;
        resetConfirmation();
    }

    function resetConfirmation() {
        const confirmBtn = document.getElementById('btn-confirm');
        confirmBtn.textContent = "Confirm Location";
        confirmBtn.classList.remove('confirmed');
        
        document.getElementById('latitude').value = "";
        document.getElementById('longitude').value = "";
        document.getElementById('submit-btn').disabled = true;
    }

    setPin(currentMarker.getLatLng());

    map.on('click', function(e) {
        currentMarker.setLatLng(e.latlng);
        setPin(e.latlng);
    });

    currentMarker.on('dragend', function() {
        setPin(currentMarker.getLatLng());
    });

    document.getElementById('btn-pin-me').addEventListener('click', () => {
        if ("geolocation" in navigator) {
            const btn = document.getElementById('btn-pin-me');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Locating...';

            navigator.geolocation.getCurrentPosition(position => {
                const latlng = L.latLng(position.coords.latitude, position.coords.longitude);
                map.flyTo(latlng, 17);
                currentMarker.setLatLng(latlng);
                setPin(latlng);
                btn.innerHTML = originalText;
            }, () => {
                alert("Could not access your location. Please ensure location services (GPS) are enabled.");
                btn.innerHTML = originalText;
            }, { enableHighAccuracy: true, timeout: 10000 });
        } else {
            alert("Geolocation is not supported by your browser.");
        }
    });

    document.getElementById('btn-confirm').addEventListener('click', function() {
        const latlng = currentMarker.getLatLng();
        document.getElementById('latitude').value = latlng.lat.toFixed(8);
        document.getElementById('longitude').value = latlng.lng.toFixed(8);

        this.textContent = "Location Confirmed!";
        this.classList.add('confirmed');
        document.getElementById('submit-btn').disabled = false;
    });

    const searchInput = document.getElementById('map-search');
    const suggestionsBox = document.getElementById('suggestions');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (!navigator.onLine) {
            suggestionsBox.innerHTML = '<div class="suggestion-item" style="color:#e74c3c;">Search is unavailable offline. You can still manually move the pin.</div>';
            suggestionsBox.style.display = 'block';
            return;
        }

        if (query.length < 3) {
            suggestionsBox.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
                .then(response => response.json())
                .then(data => {
                    suggestionsBox.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(place => {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            div.textContent = place.display_name;
                            
                            div.addEventListener('click', () => {
                                const latlng = L.latLng(parseFloat(place.lat), parseFloat(place.lon));
                                map.flyTo(latlng, 17);
                                currentMarker.setLatLng(latlng);
                                setPin(latlng);
                                searchInput.value = place.name || place.display_name.split(',')[0];
                                suggestionsBox.style.display = 'none';
                            });
                            suggestionsBox.appendChild(div);
                        });
                        suggestionsBox.style.display = 'block';
                    } else {
                        suggestionsBox.style.display = 'none';
                    }
                })
                .catch(err => console.error("Search error:", err));
        }, 500);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && e.target !== suggestionsBox) {
            suggestionsBox.style.display = 'none';
        }
    });

    function updateOnlineStatus() {
        const offlineBanner = document.getElementById('offline-banner');
        if (offlineBanner) {
            offlineBanner.style.display = navigator.onLine ? 'none' : 'block';
        }
    }
    
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();
});