$(document).ready(function () {
        // Declare variables once at the top (no redeclare inside functions)
    let map;
    let markers = {};
    let carIcon;
    initMap();
    getVehicleLogs();

     $("#newTripForm").on("submit", function (e) {
        e.preventDefault(); // stop normal form submit

        let form = $(this);
        let submitBtn = form.find("button[type='submit']");
        let formData = form.serialize(); // all inputs

        // Disable button + loading text
        submitBtn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: "admin_telemetry_new_trip.php",
            type: "POST",
            data: formData,
            dataType: "json",

            success: function (res) {

                if (res.success) {
                    // Close modal
                    $("#newTripModal").modal("hide");
                    // Reset form
                    form.trigger("reset");
                } 
            },

            error: function (xhr) {
                console.log(xhr)
            },

            complete: function () {
                submitBtn.prop("disabled", false).html('<i class="fas fa-save mr-1"></i> Create Booking');
            }
        });

    });

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

function updateMarkers(data, trips = [], api_plate_no) {
    $.each(data, function (i, v) {
        let id = v.plate_no;
        let lat = parseFloat(v.latitude);
        let lng = parseFloat(v.longitude);

        if (isNaN(lat) || isNaN(lng)) return;

        // --- Default status color ---
        let statusColor = "#6C757D"; // gray by default

        // --- Fetch status immediately via AJAX ---
        $.ajax({
            url: "admin_color_coding.php",
            type: "GET",
            data: { plate_no: v.plate_no },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    switch(response.v_status.toLowerCase()) {
                        case "maintenance": statusColor = "#ED1C24"; break;
                        case "available":   statusColor = "#007BFF"; break;
                        case "idle":        statusColor = "#28A745"; break;
                        case "in_progress":
                        case "in progress": statusColor = "#FFC107"; break;
                        default: statusColor = "#6C757D";
                    }
                } else {
                    statusColor = "#6C757D";
                }

                // --- Popup HTML with status circle + Create Trip button ---
                let popupHtml = `
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                        <span class="status-circle" style="
                            display: inline-block;
                            width: 12px;
                            height: 12px;
                            border-radius: 50%;
                            background-color: ${statusColor};
                        "></span>
                        <b>Plate:</b> ${v.plate_no}
                    </div>
                    <b>Speed:</b> ${v.speed} km/h<br>
                    <b>RPM:</b> ${v.rpm}<br>
                    <b>Fuel:</b> ${v.fuel_level}%<br>
                    <b>Time:</b> ${v.created_at || "N/A"}<br><br>
                    <button class="btn btn-primary btn-sm createTripBtn" data-plate="${v.plate_no}">Create Trip</button>
                `;

                // --- Marker exists? Animate move ---
                if (markers[id]) {
                    let prevLatLng = markers[id].getLatLng();
                    let distance = map.distance(prevLatLng, L.latLng(lat, lng));
                    if (distance > 5) {
                        markers[id].slideTo([lat, lng], { duration: 2000, keepAtCenter: false });
                        markers[id].setPopupContent(popupHtml);
                        map.setView([lat, lng]);
                    } else {
                        markers[id].setPopupContent(popupHtml); // update color
                    }
                } else {
                    // --- Create marker ---
                    let marker = L.marker([lat, lng], { icon: carIcon }).addTo(map);
                    marker.bindPopup(popupHtml);

                    // --- Handle popup open for Create Trip button ---
                    marker.on("popupopen", function(e) {
                        let popupNode = e.popup.getElement();
                        $(popupNode).find(".createTripBtn").on("click", function() {
                            let plateNo = $(this).data("plate");
                            $("#newTripModal").modal("show");
                            $("#newTripModal #vehiclePlateInput").val(plateNo);

                            // Fetch status again for modal
                            $.ajax({
                                url: "admin_color_coding.php",
                                type: "GET",
                                data: { plate_no: plateNo },
                                dataType: "json",
                                success: function(response) {
                                    let color = "#6C757D";
                                    if (response.success) {
                                        switch(response.v_status.toLowerCase()) {
                                            case "maintenance": color = "#ED1C24"; break;
                                            case "available":   color = "#007BFF"; break;
                                            case "idle":        color = "#28A745"; break;
                                            case "in_progress":
                                            case "in progress": color = "#FFC107"; break;
                                        }
                                        $("#vehicle_status_trip").text(response.v_status).css("color", color);
                                    } else {
                                        $("#vehicle_status_trip").text("Unknown").css("color", "#6C757D");
                                    }
                                },
                                error: function(xhr) { console.error(xhr); }
                            });
                        });
                    });

                    // --- On marker click → update vehicle cards ---
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
                        $("#vehicleTime").text(v.created_at || "N/A");

                        if (trips?.trips_data && trips.trips_data[v.plate_no]?.length > 0) {
                            let latest = trips.trips_data[v.plate_no][0];
                            let history = trips.trips_data[v.plate_no][1];
                            $("#th_date_time").text(history?.scheduled_start_at || "N/A");
                            $("#th_start_end_location").text(history?.start_end_location || "N/A");
                            $("#th_assigned_driver").text(history?.driver_name || "N/A");
                            $("#cr_start_location").text(latest?.start_location || "N/A");
                            $("#cr_destination").text(latest?.destination || "N/A");
                            $("#cr_assigned_driver").text(latest?.driver_name || "N/A");
                        }
                    });

                    markers[id] = marker;
                }
            },
            error: function(xhr) { console.error(xhr); }
        });

        // --- Always update vehicle cards even without clicking ---
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

        if (api_plate_no && api_plate_no.trim() !== "") {
            saveDiagnostics(v);
        }

        if (trips?.trips_data && trips.trips_data[v.plate_no]?.length > 0) {
            let latest = trips.trips_data[v.plate_no][0];
            let history = trips.trips_data[v.plate_no][1];
            $("#th_date_time").text(history?.scheduled_start_at || "N/A");
            $("#th_start_end_location").text(history?.start_end_location || "N/A");
            $("#th_assigned_driver").text(history?.driver_name || "N/A");
            $("#cr_start_location").text(latest?.start_location || "N/A");
            $("#cr_destination").text(latest?.destination || "N/A");
            $("#cr_assigned_driver").text(latest?.driver_name || "N/A");
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
                updateMarkers(response.logs,response || [],response.api_plate_no);

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

    function saveDiagnostics(v) {
        let diagnostics = {
            plate_no: v.plate_no,
            rpm_status: v.rpm > 4000 ? "High" : "Normal",
            speed_status: v.speed > 120 ? "Over Speed" : "Normal",
            coolant_status: v.coolant_temp > 100 ? "Overheat" : "Normal",
            throttle_status: v.throttle > 90 ? "Wide Open" : "Normal",
            load_status: v.engine_load > 80 ? "Heavy" : "Normal",
            voltage_status: v.battery_voltage < 12 ? "Low" : "Normal",
            mil_status: v.mil_status != 0 ? "Check Engine" : "Normal",
            overall_status: "Normal"
        };
        
        // Basic logic to determine overall status
        if (
            diagnostics.rpm_status !== "Normal" ||
            diagnostics.speed_status !== "Normal" ||
            diagnostics.coolant_status !== "Normal" ||
            diagnostics.voltage_status !== "Normal" || 
            diagnostics.mil_status !== "Normal"
        ) {
            diagnostics.overall_status = "Needs Attention!";
        }

        $.ajax({
            url: "admin-add-obd-diagnostics.php",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify(diagnostics),
            success: function (res) {
                $("#obdStatus")
                    .text(diagnostics.overall_status)
                    .removeClass()
                    .addClass(
                        diagnostics.overall_status === "Normal"
                            ? "kaya-badge kaya-badge--success"
                            : "kaya-badge kaya-badge--warn"
                    );
            },
            error: function (xhr, status, error) {
                console.error("Failed to save diagnostics:", error);
            }
        });
    }

    // Auto refresh logs every 5 seconds
    setInterval(getVehicleLogs, 5000);
});