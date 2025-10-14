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
            iconUrl: "https://cdn-icons-png.flaticon.com/512/741/741407.png",
            iconSize: [20, 20],
            iconAnchor: [20, 20],
            popupAnchor: [0, -20]
        });
    }

    function updateMarkers(data, trips = []) {
        $.each(data, function (i, v) {
            let id = v.plate_no;
            let lat = parseFloat(v.latitude);
            let lng = parseFloat(v.longitude);

            if (isNaN(lat) || isNaN(lng)) return;

            let popupHtml = `
                <b>🚘 Plate:</b> ${v.plate_no}<br>
                <b>Speed:</b> ${v.speed} km/h<br>
                <b>RPM:</b> ${v.rpm}<br>
                <b>Fuel:</b> ${v.fuel_level}%<br>
                <b>Time:</b> ${v.created_at || "N/A"}
            `;

            // --- Always update vehicle cards here ---
            $("#vehiclePlate").text(v.plate_no);
            $("#vehicleSpeed").text(v.speed + " km/h");
            $("#vehicleRPM").text(v.rpm);
            $("#vehicleTemperature").text(v.coolant_temp);
            $("#vehicleThrottle").text(v.throttle);

            $("#vehicleLoad").text(v.engine_load);
            $("#vehicleVoltage").text(v.battery_voltage);
            $("#vehicleOdometer").text(v.odometer);
            $("#vehicleMAF").text(v.maf);
            $("#vehicleAmbient").text(v.ambient_temp);

            $("#vehicleTime").text(v.created_at || "N/A");

            if (trips?.trips_data && trips.trips_data[v.plate_no]?.length > 0) {
                let latest = trips.trips_data[v.plate_no][0];
                let history = trips.trips_data[v.plate_no][1];
                // Trip History
                $("#th_date_time").text(history?.scheduled_start_at || "N/A");
                $("#th_start_end_location").text(history?.start_end_location || "N/A");
                $("#th_assigned_driver").text(history?.driver_name || "N/A");

                // Current Route
                $("#cr_start_location").text(latest?.start_location || "N/A");
                $("#cr_destination").text(latest?.destination || "N/A");
                $("#cr_assigned_driver").text(latest?.driver_name || "N/A");
            }

            // --- Marker already exists? animate move ---
            if (markers[id]) {
                let prevLatLng = markers[id].getLatLng();
                let distance = map.distance(prevLatLng, L.latLng(lat, lng));
                if (distance > 5) {
                    markers[id].slideTo([lat, lng], {
                        duration: 2000,
                        keepAtCenter: false
                    });
                    markers[id].setPopupContent(popupHtml);
                    map.panTo([lat, lng]);
                }
            } else {
                // Create marker once
                let marker = L.marker([lat, lng], { icon: carIcon }).addTo(map);
                marker.bindPopup(popupHtml);

            // On marker click → update cards
            marker.on("click", function () {
                $("#vehiclePlate").text(v.plate_no);
                $("#vehicleSpeed").text(v.speed + " km/h");
                $("#vehicleRPM").text(v.rpm);
                $("#vehicleTemperature").text(v.coolant_temp);
                $("#vehicleThrottle").text(v.throttle);
                $("#vehicleLoad").text(v.engine_load);
                $("#vehicleVoltage").text(v.battery_voltage);
                $("#vehicleOdometer").text(v.odometer);
                $("#vehicleMAF").text(v.maf);
                $("#vehicleAmbient").text(v.ambient_temp);


                // $("#vehicleFuel").text(v.fuel_level + "%");
                $("#vehicleTime").text(v.created_at || "N/A");

      
                // Handle trips_data (if passed in)

                if (trips?.trips_data && trips.trips_data[v.plate_no]?.length > 0) {
                    let latest = trips.trips_data[v.plate_no][0];
                    let history = trips.trips_data[v.plate_no][1];
                    // Trip History
                    $("#th_date_time").text(history?.scheduled_start_at || "N/A");
                    $("#th_start_end_location").text(history?.start_end_location || "N/A");
                    $("#th_assigned_driver").text(history?.driver_name || "N/A");

                    // Current Route
                    $("#cr_start_location").text(latest?.start_location || "N/A");
                    $("#cr_destination").text(latest?.destination || "N/A");
                    $("#cr_assigned_driver").text(latest?.driver_name || "N/A");
                }
            });

                markers[id] = marker;
            }
        });
    }



    function getVehicleLogs() {
        let params = new URLSearchParams(window.location.search);
        let plateNo = params.get("PlateNo"); // ex: PlateNo=ABC1234

        $.ajax({
            url: "admin-add-obdlogs.php",
            type: "GET",
            data: plateNo ? { plate_no: plateNo } : {},
            dataType: "json",
            success: function (response) {
           if (response.status === "success" && response.logs.length > 0) {
                updateMarkers(response.logs,response || []);

                // Index 1 is the history
                // Index 0 is the latest
                // Trip History
                    if(plateNo === null){
                        return "";
                    }
                    
                    if (response.trips_data && response.trips_data.length > 0) {
                        let latest = response.trips_data[0] || {};
                        let history = response.trips_data[1] || {};

                        // Trip History
                        $("#th_date_time").text(history.scheduled_start_at || "N/A");
                        $("#th_start_end_location").text(history.start_end_location || "N/A");
                        $("#th_assigned_driver").text(history.driver_name || "N/A");

                        // Current Route
                        $("#cr_start_location").text(latest.start_location || "N/A");
                        $("#cr_destination").text(latest.destination || "N/A");
                        $("#cr_assigned_driver").text(latest.driver_name || "N/A");
                    }
                    
                }
            },
            error: function (xhr, status, error) {
                // console.error("Map AJAX Error:", error);
                console.log(xhr);
                console.log(error);
            }
        });
    }

    $("#get_trip_movements").on("change", function () {
        let selectedDate = $(this).val(); // yyyy-mm-dd
        if (!selectedDate) return;

        getVehicleMovements(selectedDate);
    });


    function getVehicleMovements(date) {
        let params = new URLSearchParams(window.location.search);
        let plateNo = params.get("PlateNo"); // ex: PlateNo=ABC1234

        if (!plateNo) {
            alert("No Plate Number selected!");
            return;
        }

        $.ajax({
            url: "admin-add-obdlogs.php",
            type: "GET",
            data: { 
                plate_no: plateNo, 
                date: date  // pass date to backend
            },
            dataType: "json",
            success: function (response) {
                
                if (response.status === "success" && response.logs.length > 0) {
                    playVehicleMovements(response.logs);
                } else {
                    alert("No logs found for this date.");
                }
            },
            error: function (xhr, status, error) {
                console.log(xhr);
                console.log(error);
            }
        });
    }

    function playVehicleMovements(logs) {
        console.log(logs);
        let i = 0;
        let id = logs[0].plate_no;

        // Create marker if not exists
        if (!markers[id]) {
            markers[id] = L.marker(
                [parseFloat(logs[0].latitude), parseFloat(logs[0].longitude)],
                { icon: carIcon }
            ).addTo(map);
        }

        let playback = setInterval(() => {
            if (i >= logs.length) {
                clearInterval(playback);
                return;
            }

            let lat = parseFloat(logs[i].latitude);
            let lng = parseFloat(logs[i].longitude);

            if (!isNaN(lat) && !isNaN(lng)) {
                markers[id].slideTo([lat, lng], {
                    duration: 1000,
                    keepAtCenter: false
                });
            }

            // Update info cards too
            $("#vehiclePlate").text(logs[i].plate_no);
            $("#vehicleSpeed").text(logs[i].speed + " km/h");
            $("#vehicleRPM").text(logs[i].rpm);
            $("#vehicleTemperature").text(logs[i].coolant_temp);
            $("#vehicleThrottle").text(logs[i].throttle);

            $("#vehicleLoad").text(logs[i].engine_load);
            $("#vehicleVoltage").text(logs[i].battery_voltage);
            $("#vehicleOdometer").text(logs[i].odometer);
            $("#vehicleMAF").text(logs[i].maf);
            $("#vehicleAmbient").text(logs[i].ambient_temp);

            $("#vehicleTime").text(logs[i].created_at || "N/A");

            i++;
        }, 1500); // move every 1.5 seconds
    }

    // Auto refresh logs every 5 seconds
    setInterval(getVehicleLogs, 5000);
});