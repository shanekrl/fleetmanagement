$(document).ready(function () {
    let map;
    let carIcon;
    let carMarker = null;

    initMap();
    loadDriverLocations();

    function initMap() {
        map = L.map('map').setView([12.8797, 121.7740], 6);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap, © CARTO',
            subdomains: 'abcd',
            maxZoom: 20
        }).addTo(map);

        carIcon = L.icon({
            iconUrl: "https://cdn-icons-png.flaticon.com/512/741/741407.png",
            iconSize: [25, 25],
            iconAnchor: [12, 25],
            popupAnchor: [0, -20]
        });
    }

    function loadDriverLocations() {
        let pickup_location = $('#pickup_point').text().trim();
        let dropoff_location = $('#dropoff_point').text().trim();

        $.when(
            getCoordinates(pickup_location),
            getCoordinates(dropoff_location)
        ).done(function (pickupData, dropoffData) {
            const pickupCoords = [parseFloat(pickupData[0].lat), parseFloat(pickupData[0].lon)];
            const dropoffCoords = [parseFloat(dropoffData[0].lat), parseFloat(dropoffData[0].lon)];

            // Add pickup and dropoff markers
            L.marker(pickupCoords).addTo(map).bindPopup("Pickup Point");
            L.marker(dropoffCoords).addTo(map).bindPopup("Dropoff Point");

            // Draw the route (driving path)
            const routeUrl = `https://router.project-osrm.org/route/v1/driving/${pickupCoords[1]},${pickupCoords[0]};${dropoffCoords[1]},${dropoffCoords[0]}?overview=full&geometries=geojson`;

            $.getJSON(routeUrl, function (data) {
                if (data.routes && data.routes.length > 0) {
                    const routeCoords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                    const routeLine = L.polyline(routeCoords, { color: '#ed1c24', weight: 4 }).addTo(map);
                    map.fitBounds(routeLine.getBounds());
                }
            });

            // Show driver’s live GPS
            startRealTimeGPS();
        }).fail(function (err) {
            console.error("Error fetching coordinates:", err);
        });
    }

    function startRealTimeGPS() {
        if (!navigator.geolocation) {
            // alert("Geolocation is not supported by your browser.");
            return;
        }

        navigator.geolocation.watchPosition(
            function (position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                const newLatLng = [lat, lon];

                console.log(` Driver current position: ${lat}, ${lon}`);

                if (!carMarker) {
                    carMarker = L.marker(newLatLng, { icon: carIcon }).addTo(map).bindPopup("Driver Location");
                    map.setView(newLatLng, 15);
                } else {
                    carMarker.setLatLng(newLatLng);
                }
            },
            function (error) {
                console.error("Error getting live location:", error);
                // alert("Unable to retrieve GPS. Please enable location access.");
            },
            {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 10000
            }
        );
    }

    function getCoordinates(locationName) {
        return $.ajax({
            url: 'driver_get_location.php',
            method: 'GET',
            dataType: 'json',
            data: { location: locationName },
            success: function (response) {
                console.log("Geocode success for:", locationName, response);
            },
            error: function (xhr, status, error) {
                console.error("Geocode failed for:", locationName);
                console.error("Status:", status);
                console.error("Error:", error);
                console.error("Response Text:", xhr.responseText);
            }
        });
    }
});
