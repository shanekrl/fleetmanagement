<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <title>Kaya — Driver</title>

  <!-- Fonts / Icons -->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Plugins -->
  <link href="vendor/datatables/dataTables.bootstrap4.css" rel="stylesheet">

  <!-- Base theme (SB Admin) -->
  <link href="vendor/css/sb-admin.css" rel="stylesheet">

  <!-- Driver overrides to match Admin theme -->
  <style>
    :root{
      --kaya-navy:#0A0F2C;           /* brand navy used across admin */
      --kaya-navy-600:#101737;
      --kaya-slate:#1f2937;          /* deep slate for sidebar bg */
      --kaya-text:#0f172a;
      --kaya-muted:#6b7280;
      --kaya-card:#ffffff;
      --kaya-thead:#e9edf5;          /* table header bg */
    }

    /* navbar look & spacing */
    .navbar {
      background: var(--kaya-slate)!important;
      min-height:56px;
    }
    .navbar .navbar-brand{
      font-weight:700;
      letter-spacing:.3px;
    }

    /* sidebar */
    .sidebar{
      background: var(--kaya-slate)!important;
    }
    .sidebar .nav-item .nav-link{
      color:#dbe2ef!important;
      padding:.65rem 1rem;
      font-weight:600;
    }
    .sidebar .nav-item .nav-link i{
      opacity:.9; margin-right:.35rem;
    }
    .sidebar.toggled{
      overflow:visible;
    }

    /* page chrome */
    body{ color:var(--kaya-text); }
    .breadcrumb{
      background:#eef2f7; border-radius:.5rem;
    }
    .card{
      border:1px solid #e5e7eb; border-radius: .75rem;
      box-shadow:0 8px 24px rgba(0,0,0,.05);
    }

    /* tables — make all headers consistent & centered */
    table.dataTable thead th,
    .table thead th{
      background: var(--kaya-thead)!important;
      color:#334155!important;
      text-align:center!important;
      vertical-align:middle!important;
      border-bottom:0!important;
      font-weight:700;
    }
    .table td{ vertical-align:middle; }

    /* buttons to match admin navy */
    .btn-primary{
      background: var(--kaya-navy); border-color: var(--kaya-navy);
    }
    .btn-primary:hover{
      background: var(--kaya-navy-600); border-color: var(--kaya-navy-600);
    }

    /* hide sticky footer on driver side (you asked to remove it) */
    .sticky-footer{ display:none!important; }

    /* small mobile polish */
    @media (max-width: 576px){
      .breadcrumb{ font-size:.85rem; }
      .navbar-brand{ font-size:1rem; }
      .sidebar .nav-item .nav-link{ padding:.6rem .9rem; }
    }
  </style>
</head>
