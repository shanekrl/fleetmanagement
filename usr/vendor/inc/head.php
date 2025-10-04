<!-- Tailwind config MUST be before the CDN script -->
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] },
        colors: { kaya: { navy: '#000047' } }
      }
    }
  }
</script>
<script src="https://cdn.tailwindcss.com"></script>

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Kaya — Driver">
  <meta name="author" content="Kaya">
  <title>Kaya – Driver</title>

  <!-- Fonts / Icons -->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Datatables -->
  <link href="vendor/datatables/dataTables.bootstrap4.css" rel="stylesheet">

  <!-- Base SB Admin skin -->
  <link href="vendor/css/sb-admin.css" rel="stylesheet">

  <!-- Driver overrides -->
  <style>
    /* same dark palette as admin */
    .navbar-dark.bg-dark, .sidebar { background:#1f2937 !important; } /* slate-800 */
    .sidebar .nav-item .nav-link, .navbar-dark .navbar-nav .nav-link { color:#e5e7eb !important; }
    .navbar-brand { font-weight:700; letter-spacing:.3px }
    .sidebar .nav-item.active .nav-link,
    .sidebar .nav-item .nav-link:hover { color:#fff !important; }

    /* always center table headers (Datatables or plain) */
    .table thead th, .dataTables_wrapper .dataTable thead th { text-align:center; }

    /* kill sticky footer on driver side */
    .sticky-footer { display:none !important; }
  </style>
</head>




<!-- Modal: place this right before the closing </body> (outside main container) -->
<div class="modal fade" id="logTripModal" tabindex="-1" role="dialog" aria-labelledby="logTripModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document" >
    <div class="modal-content">

      <div class="modal-header" style="background-color: #000047; border-color: navy; color:antiquewhite;">
        <h5 class="modal-title w-100 text-center" id="logTripModalLabel">PLEASE PROVIDE DETAILS OF THE TRIP</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body" style="background-color: #000047; border-color: navy; color:antiquewhite;">
        <form method="POST" id="logTripForm" action="">
          <div class="form-group text-center">
            <label for="trip_id">Trip ID</label>
            <input type="text" id="trip_id" name="trip_id" class="form-control w-50 mx-auto" required>
          </div>

          <div class="form-group text-center">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" class="form-control w-50 mx-auto" required>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="pickup">Pick-Up Location</label>
              <input type="text" id="pickup" name="pickup" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label for="dropoff">Drop-Off Location</label>
              <input type="text" id="dropoff" name="dropoff" class="form-control" required>
            </div>
          </div>

          <div class="form-group text-center">
            <label for="odometer">Odometer Reading</label>
            <input type="number" id="odometer" name="odometer" class="form-control w-50 mx-auto" required>
          </div>

          <div class="text-center">
            <button type="submit" name="add_log" class="btn btn-success" style="background-color: navy; border-color: navy;">+ Log Trip</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>
