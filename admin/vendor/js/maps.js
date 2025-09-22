$(document).ready(function () {
        // Declare variables once at the top (no redeclare inside functions)
    let map;
    let markers = {};
    let carIcon;
    initMap();
    getVehicleLogs();


    function initMap() {
        // Initialize the map (remove "let" here!)
        map = L.map('map').setView([12.8797, 121.7740], 6);

        // Basemap
     L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap, © CARTO',
            subdomains: 'abcd',
            maxZoom: 20
        }).addTo(map);

        // Car icon
        carIcon = L.icon({
            iconUrl: "https://cdn-icons-png.flaticon.com/512/743/743922.png",
            iconSize: [20, 20],
            iconAnchor: [20, 20],
            popupAnchor: [0, -20]
        });
    }

function updateMarkers(data) {
    $.each(data, function (i, v) {
        let id = v.plate_no; // 🚘 always use plate_no
        let lat = parseFloat(v.latitude);
        let lng = parseFloat(v.longitude);
        
        console.log("Latitude:" + lat);
        console.log("longitude:" + lng);

        if (isNaN(lat) || isNaN(lng)) return; // skip invalid coords

        let popupHtml = `
            <b>🚘 Plate:</b> ${v.plate_no}<br>
            <b>Speed:</b> ${v.speed} km/h<br>
            <b>RPM:</b> ${v.rpm}<br>
            <b>Fuel:</b> ${v.fuel_level}%<br>
            <b>Time:</b> ${v.created_at || "N/A"}
        `;

        // Display the logs in the card view
        $('#vehicleSpeed').text(v.speed + " km/h");

        if (markers[id]) {
            let prevLatLng = markers[id].getLatLng();
            let distance = map.distance(prevLatLng, L.latLng(lat, lng));

            // Only update if moved more than 5 meters
            if (distance > 5) {
                markers[id].setLatLng([lat, lng]).setPopupContent(popupHtml);
            }
        } else {
            // Create marker once
            let marker = L.marker([lat, lng], { icon: carIcon }).addTo(map);
            marker.bindPopup(popupHtml);
            markers[id] = marker;
        }
    });
}


    function getVehicleLogs() {
        let params = new URLSearchParams(window.location.search);
        let plateNo = params.get("PlateNo"); // ex: Driver=ABC1234
        if(plateNo!="" && plateNo != null){
            $.ajax({
                url: "admin-add-obdlogs.php",
                type: "GET",
                data: { plate_no: plateNo },
                dataType: "json",
                success: function (response) {
                    if (response.status === "success") {
                        updateMarkers(response.logs);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Map AJAX Error:", error);
                }
            });
        }
    }

    // Auto refresh logs every 5 seconds
    setInterval(getVehicleLogs, 5000);
});