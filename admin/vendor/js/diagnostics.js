$(document).ready(function() {

  let diagnosticsTable = $('#diagnosticsTable').DataTable({
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[0, 'desc']],
    columnDefs: [
      { targets: -1, orderable: false, searchable: false }
    ],
    createdRow: function (row, data, dataIndex) {
        // assuming "overall_status" is the last column
        const status = data[7] ? data[7].toLowerCase() : ''; // adjust index if needed
        if (status === 'needs attention!') {
            $(row).css('background-color', '#ed1c24');  
            $(row).css('color', '#FAFAFA');            
        } else {
            $(row).css('background-color', '#4CAF50');  
            $(row).css('color', '#FAFAFA');
        }
    }
  });

  // When user clicks "Normal" badge
  $(document).on('click', '#obdStatus', function() {
    let params = new URLSearchParams(window.location.search);
    let plateNo = params.get("PlateNo"); // ex: PlateNo=ABC1234

    if (!plateNo) {
      alert("No plate number available.");
      return;
    }

    // Clear table before loading new data
    diagnosticsTable.clear().draw();

    // Fetch diagnostics from backend
    $.ajax({
      url: 'admin-add-obd-diagnostics.php',
      type: 'GET',
      data: { plate_no: plateNo },
      dataType: 'json',
      success: function(data) {
        console.log(data);
        if (Array.isArray(data) && data.length > 0) {
          $.each(data, function(i, v) {
            diagnosticsTable.row.add([
              v.id,
              v.rpm_status,
              v.speed_status,
              v.coolant_status,
              v.throttle_status,
              v.load_status,
              v.voltage_status,
              v.mil_status,
              v.overall_status,
              v.created_at
            ]);
          });
          diagnosticsTable.draw();
        } else {
          diagnosticsTable.row.add(["", "No records found", "", "", "", "", "", "", ""]).draw();
        }
      },
      error: function(xhr, status, error) {
        console.error("Error fetching diagnostics:", error);
      }
    });

    // Show modal after loading data
    $('#showDiagnosticsModal').modal('show');
  });
});
