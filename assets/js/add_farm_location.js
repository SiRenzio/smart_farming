document.addEventListener('DOMContentLoaded', function() {
    // Default center: Iloilo City
    const defaultCoords = ol.proj.fromLonLat([122.565986, 10.715266]); 
    
    const map = new ol.Map({
        target: 'map',
        layers: [
            new ol.layer.Tile({
                source: new ol.source.OSM()
            })
        ],
        view: new ol.View({
            center: defaultCoords,
            zoom: 17
        })
    });

    // Layer for the Pin
    const markerSource = new ol.source.Vector();
    const markerLayer = new ol.layer.Vector({
        source: markerSource,
        style: new ol.style.Style({
            image: new ol.style.Icon({
                anchor: [0.5, 1],
                src: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                scale: 0.7
            })
        })
    });
    map.addLayer(markerLayer);

    let currentPin = null;

    // --- Core Functions ---

    // Function to set/move the pin
    function setPin(coords) {
        markerSource.clear(); // Remove old pin
        currentPin = new ol.Feature({
            geometry: new ol.geom.Point(coords)
        });
        markerSource.addFeature(currentPin);

        const lonLat = ol.proj.toLonLat(coords);
        document.getElementById('coord-display').textContent = `Lat: ${lonLat[1].toFixed(6)}, Lon: ${lonLat[0].toFixed(6)}`;

        // Reset confirmation status whenever pin is moved
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

    // Initialize map with default pin
    setPin(defaultCoords);

    // --- Map Interactions ---

    // Click on map to move pin
    map.on('singleclick', function(evt) {
        setPin(evt.coordinate);
    });

    // "Pin my location" Button (Geolocation)
    document.getElementById('btn-pin-me').addEventListener('click', () => {
        if ("geolocation" in navigator) {
            const btn = document.getElementById('btn-pin-me');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';

            navigator.geolocation.getCurrentPosition(position => {
                const coords = ol.proj.fromLonLat([position.coords.longitude, position.coords.latitude]);
                map.getView().animate({ center: coords, zoom: 17 });
                setPin(coords);
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
        if (currentPin) {
            const coords = currentPin.getGeometry().getCoordinates();
            const lonLat = ol.proj.toLonLat(coords);
            
            // Set hidden inputs for the form
            document.getElementById('latitude').value = lonLat[1].toFixed(8);
            document.getElementById('longitude').value = lonLat[0].toFixed(8);

            // Update UI states
            this.textContent = "Location Confirmed!";
            this.classList.add('confirmed');
            
            // Enable the main submit button
            document.getElementById('submit-btn').disabled = false;
        }
    });
    
    // search bar suggestions
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
            // Using OpenStreetMap Nominatim Free API
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
                                const lon = parseFloat(place.lon);
                                const lat = parseFloat(place.lat);
                                const coords = ol.proj.fromLonLat([lon, lat]);
                                
                                // Center map and place pin
                                map.getView().animate({ center: coords, zoom: 15 });
                                setPin(coords);
                                
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
        }, 500); // Wait 500ms after user stops typing
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && e.target !== suggestionsBox) {
            suggestionsBox.style.display = 'none';
        }
    });
});