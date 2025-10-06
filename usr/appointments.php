<?php
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';
// Fetch appointment schedules from DB
$appointments = [];
$result = $mysqli->query("
    SELECT 
        u_id AS id,
        u_car_bookdate AS date,
        CONCAT(u_fname, ' ', u_lname) AS customer,
        u_car_pickup AS pickup,
        u_car_destination AS destination,
        u_car_book_status AS status
    FROM tms_user
    ORDER BY u_car_bookdate ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $result->free();
}
// Calendar setup
$currentMonth = date('n');
$currentYear = date('Y');
$firstDayOfMonth = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$daysInMonth = date('t', $firstDayOfMonth);
$startDay = date('w', $firstDayOfMonth);
?>
<html lang="en">
<head>
    <?php include('vendor/inc/head.php'); ?>
    <title>Appointments</title>
    <style>
        @font-face {
        font-family: 'Lucky Rainbow';
        src: url('../assets/fonts/LuckyRainbowDemo-400.ttf') format('truetype');
        font-weight: 400;
        font-style: normal;
    }
    </style>
    <style>
        .badge-ongoing { background-color: orange; color: white; padding: 5px 10px; border-radius: 5px; }
        .badge-cancel { background-color: red; color: white; padding: 5px 10px; border-radius: 5px; }
        .badge-today { background-color: green; color: white; padding: 5px 10px; border-radius: 5px; }
        .calendar-table { border-collapse: collapse; width: 100%; }
        .calendar-table th, .calendar-table td { border: 1px solid #ddd; padding: 10px; height: 100px; vertical-align: top; cursor: pointer; }
        .calendar-table th { background-color: #f4f4f4; text-align: center; }
        .calendar-number { font-weight: bold; }
    </style>
    <style>
        .badge-ongoing {
            background-color: orange;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .badge-cancel {
            background-color: red;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .badge-today {
            background-color: green;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .calendar-table {
            border-collapse: collapse;
            width: 100%;
        }
        .calendar-table th, .calendar-table td {
            border: 1px solid #ddd;
            padding: 10px;
            height: 100px;
            vertical-align: top;
        }
        .calendar-table th {
            background-color: #f4f4f4;
            text-align: center;
        }
        .calendar-number {
            font-weight: bold;
        }
    </style>
</head>
<body id="page-top">
<div class="container mt-3 fas" style="padding: 2%;">
    <!-- Top Right Button -->
     <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary" style="background-color: navy; border-color: navy;" data-toggle="modal" data-target="#logTripModal">
            + Log Trip
        </button>
     </div>
     <ol class="breadcrumb" style="background-color: #000047;">
                    <h6 class="m-1 font-weight-bold fas" style="font-size: 30px; color:white;">My Appointment Schedule</h6>
                 </ol>
    <!-- Appointment Table -->
    <table class="card-body table-table table-striped card-body table-responsive h-30" style="color:#000047; margin: 20px 10px 30px 30px;">
        <thead style="font-size: .6rem;">
            <tr style="font-size: .6rem;">
                <th>Date</th>
                <th>Customer</th>
                <th>Pick Up Location</th>
                <th>Destination</th>
                <th>Status</th>
            </tr>
        </thead>
         <tbody >
            <?php if (!empty($appointments)): ?>
                <?php foreach ($appointments as $appt): ?>
                    <tr style="font-size: .7rem;">
                        <td><?= htmlspecialchars($appt['date']) ?></td>
                        <td><?= htmlspecialchars($appt['customer']) ?></td>
                        <td><?= htmlspecialchars($appt['pickup']) ?></td>
                        <td><?= htmlspecialchars($appt['destination']) ?></td>
                        <td>
                            <?php
                            $status = strtolower($appt['status']);
                            if ($status === 'ongoing') {
                                echo "<span class='badge-ongoing'>Ongoing</span>";
                            } elseif ($status === 'cancel') {
                                echo "<span class='badge-cancel'>Cancel</span>";
                            } elseif (date('Y-m-d') === $appt['date']) {
                                echo "<span class='badge-today'>Today's Schedule</span>";
                            } else {
                                echo htmlspecialchars($appt['status']);
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center">No appointments found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
     <ol class="breadcrumb" style="background: #000047;">
                    <h6 class="m-1 font-weight-bold fas" style="font-size: 30px; color:white;">Appointment Calendar</h6>
                 </ol>
    <!-- Appointment Calendar -->
    <!-- <h3 class="mt-3">Appointment Calendar</h3> -->
    <table class="table-table table-striped table-border card-body table-responsive" style="color:#000047; margin: 20px 10px 30px 40px;">
        <thead  class="" style="font-size: .7rem;">
            <tr class="calendar-responsive">
                <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                <th>Thu</th><th>Fri</th><th>Sat</th>
            </tr>
        </thead>
        <tbody class="calendar-table calendar-responsive" >
            <?php
            $day = 1;
            $cellCount = 0;
            echo "<tr style='font-size: 1rem;'>";
            // Empty cells before first day
            for ($i = 0; $i < $startDay; $i++) {
                echo "<td></td>";
                $cellCount++;
            }
            // Days of month
            while ($day <= $daysInMonth) {
                if ($cellCount % 7 === 0 && $cellCount !== 0) {
                    echo "</tr><tr>";
                }
                // Check if there’s an appointment for this date
                $dateString = date('Y-m-d', mktime(0, 0, 0, $currentMonth, $day, $currentYear));
                $dayAppointments = array_filter($appointments, function($appt) use ($dateString) {
                    return $appt['date'] === $dateString;
                });
                echo "<td data-date='$dateString'>";
                echo "<div class='calendar-number'>$day</div>";
                foreach ($dayAppointments as $appt) {
                    echo "<div style='font-size:9px;'>" . htmlspecialchars($appt['customer']) . "</div>";
                }
                echo "</td>";
                $day++;
                $cellCount++;
            }
            // Empty cells after last day
            while ($cellCount % 7 !== 0) {
                echo "<td style='font-size:.8px;></td>";
                $cellCount++;
            }
            echo "</tr>";
            ?>
        </tbody>
    </table>
</div>
<!-- Appointment Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true" >
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointments on <span id="modalDate"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <table class="table table-bordered w-50">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Pickup</th>
                            <th>Destination</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="modalAppointments">
                        <!-- JS fills this -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<nav class="navbar navbar-dark bg-white navbar-expand fixed-bottom fas">
    <ul class="navbar-nav nav-justified w-100">
        <li class="nav-item text-center lucky-rainbow-font">
            <a class="nav-link" href="user-dashboard.php" style="color:black; font-family: 'Lucky Rainbow', sans-serif;">
                <img src="../assets/icons/home3.png" alt="Home" style="width:45px; height:45px;"><br>
                Home
            </a>
        </li>
        <li class="nav-item text-center">
            <a class="nav-link" href="appointments.php" style="color:black; font-family: 'Lucky Rainbow', sans-serif;">
                <img src="../assets/icons/appo3.png" alt="Appointment" style="width:45px; height:45px;"><br>
                Appointment
            </a>
        </li>
        <li class="nav-item text-center">
            <a class="nav-link" href="logs.php" style="color:black; font-family: 'Lucky Rainbow', sans-serif;">
                <img src="../assets/icons/log3.png" alt="Log" style="width:45px; height:45px;"><br>
                Log
            </a>
        </li>
    </ul>
</nav>
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    const appointments = <?php echo json_encode($appointments); ?>;
    $(document).on('click', '.calendar-table td[data-date]', function() {
        const date = $(this).data('date');
        $('#modalDate').text(date);
        const dayAppointments = appointments.filter(appt => appt.date === date);
        const tbody = $('#modalAppointments');
        tbody.empty();
        if (dayAppointments.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center">No appointments for this date</td></tr>');
        } else {
            dayAppointments.forEach(appt => {
                tbody.append(`
                    <tr class="table-responsive">
                        <td>${appt.customer}</td>
                        <td>${appt.pickup}</td>
                        <td>${appt.destination}</td>
                        <td>${appt.status}</td>
                        <td>
                            <a href="edit_appointment.php?id=${appt.id}" class="btn btn-sm btn-warning">Edit</a>
                            <a href="cancel_appointment.php?id=${appt.id}" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                        </td>
                    </tr>
                `);
            });
        }
        $('#appointmentModal').modal('show');
    });
</script>
</body>
</html>