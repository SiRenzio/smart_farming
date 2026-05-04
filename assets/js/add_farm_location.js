document.addEventListener('DOMContentLoaded', function() {
    // Default center
    const defaultLat = 10.715183;
    const defaultLon = 122.566076;
    const map = L.map('map').setView([defaultLat, defaultLon], 17);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Use a custom red marker to match your previous UI
    const redIcon = new L.Icon({
        iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    // Initialize Marker (Draggable!)
    let currentMarker = L.marker([defaultLat, defaultLon], {
        icon: redIcon,
        draggable: true 
    }).addTo(map);

    // --- Core Functions ---

    // Function to update the UI when the pin moves
    function setPin(latlng) {
        document.getElementById('coord-display').textContent = `Lat: ${latlng.lat.toFixed(6)}, Lon: ${latlng.lng.toFixed(6)}`;
        resetConfirmation();
    }

    // Un-confirm when map is clicked / pin is moved
    function resetConfirmation() {
        const confirmBtn = document.getElementById('btn-confirm');
        confirmBtn.textContent = "Confirm Location";
        confirmBtn.classList.remove('confirmed');
        
        document.getElementById('latitude').value = "";
        document.getElementById('longitude').value = "";
        document.getElementById('submit-btn').disabled = true;
    }

    // Initial load
    setPin(currentMarker.getLatLng());

    // --- Map Interactions ---

    // Click on map to move pin
    map.on('click', function(e) {
        currentMarker.setLatLng(e.latlng);
        setPin(e.latlng);
    });

    // Update coordinates when user drags and drops the pin
    currentMarker.on('dragend', function(e) {
        setPin(currentMarker.getLatLng());
    });

    // "Pin my location" Button (Geolocation)
    document.getElementById('btn-pin-me').addEventListener('click', () => {
        if ("geolocation" in navigator) {
            const btn = document.getElementById('btn-pin-me');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';

            navigator.geolocation.getCurrentPosition(position => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                const latlng = L.latLng(lat, lon);
                
                // Fly to user location and place pin
                map.flyTo(latlng, 17);
                currentMarker.setLatLng(latlng);
                setPin(latlng);
                
                btn.innerHTML = originalText;
            }, error => {
                alert("Could not access your location. Please ensure location services are enabled.");
                btn.innerHTML = originalText;
            });
        } else {
            alert("Geolocation is not supported by your browser.");
        }
    });

    // "Confirm Location" Button
    document.getElementById('btn-confirm').addEventListener('click', function() {
        const latlng = currentMarker.getLatLng();
        
        // Set hidden inputs for the form
        document.getElementById('latitude').value = latlng.lat.toFixed(8);
        document.getElementById('longitude').value = latlng.lng.toFixed(8);

        // Update UI states
        this.textContent = "Location Confirmed!";
        this.classList.add('confirmed');
        
        // Enable the main submit button
        document.getElementById('submit-btn').disabled = false;
    });

    // --- Search Bar and Autocomplete (Nominatim API) ---
    
    const searchInput = document.getElementById('map-search');
    const suggestionsBox = document.getElementById('suggestions');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 3) {
            suggestionsBox.style.display = 'none';
            return;
        }

        // Debounce search to prevent API spam
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
                            
                            // Handle clicking a suggestion
                            div.addEventListener('click', () => {
                                const lat = parseFloat(place.lat);
                                const lon = parseFloat(place.lon);
                                const latlng = L.latLng(lat, lon);
                                
                                // Center map and place pin
                                map.flyTo(latlng, 17);
                                currentMarker.setLatLng(latlng);
                                setPin(latlng);
                                
                                // Update search box and hide suggestions
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

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && e.target !== suggestionsBox) {
            suggestionsBox.style.display = 'none';
        }
    });
});