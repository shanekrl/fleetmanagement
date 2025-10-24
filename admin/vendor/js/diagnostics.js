$(document).ready(function() {

    function loadLiveVehicleCount() {
        $.ajax({
            url: "admin-dashboard-display-logs.php",
            type: "GET",
            dataType: "json",
            success: function(response) {
                // console.log("Live Vehicles:", response.live_vehicle_count);
                // console.log($("#availableVehicleCount").text().trim());
                let availableUnits = parseInt($("#totalVehicleCount").text().trim()) - parseInt(response.live_vehicle_count);
                $("#liveVehicleCount").text(response.live_vehicle_count);
                $("#availableVehicleCount").text(availableUnits);
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
            }
        });
    }

    function reloadLiveVehicles() {
     $("#live_vehicles").load(" #live_vehicles > *");
    }

    // Refresh every 2 seconds (2000 ms)
    setInterval(reloadLiveVehicles, 2000);

    loadLiveVehicleCount();

    // Optional: auto-refresh every 10 seconds
    setInterval(loadLiveVehicleCount, 10000);
});
