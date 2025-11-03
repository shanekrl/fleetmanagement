$(document).ready(function() {
    
$('#report_form').on('submit', function (e) {
    e.preventDefault(); 
    $('#vehicleDailyTable tbody').empty();
    let plateNo = $('#trips_plate_no').val();
    let plateNoText = $('#trips_plate_no').find('option:selected').text().replace(/[()]/g, '').trim();
    let fromDate = $('#trips_from_date').val();
    let toDate = $('#trips_to_date').val();
    if (plateNo === "" || fromDate === "" || toDate === "") {
      alert("Please select all fields before running the report.");
      return;
    }

    $.ajax({
        url: 'report.php',
        method: 'POST',
        data: {
            plate_no: plateNo,
            from_date: fromDate,
            to_date: toDate,
            plate_no_text: plateNoText
        },
        success: function (response) {
            // Display total trips
            $('#total_trips').text(response.total_trips);

            // Reference to table body
            let tbody = $('#vehicleDailyTable tbody');
            tbody.empty(); // clear previous rows

            // Check for OBD data
            if (response.obd_list && response.obd_list.length > 0) {
                $.each(response.obd_list, function (i, log) {
                    tbody.append(`
                        <tr>
                            <td>${i + 1}</td>
                            <td>${log.created_at}</td>
                            <td>${log.plate_no}</td>
                            <td>${log.rpm}</td>
                            <td>${log.speed}</td>
                            <td>${log.throttle}</td>
                            <td>${log.coolant_temp}</td>
                            <td>${log.fuel_type}</td>
                        </tr>
                    `);
                });
            } else {
                tbody.append(`
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            No OBD data found for this vehicle and date range.
                        </td>
                    </tr>
                `);
            }
        },
        error: function (xhr) {
            console.log(xhr)
        }
    });
  });

});
