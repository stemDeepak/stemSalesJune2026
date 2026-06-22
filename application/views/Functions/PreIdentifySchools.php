<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>STEM APP | Sales Data Overview</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.0/css/all.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Tempusdominus Bootstrap 4 -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/tempusdominus-bootstrap-4.min.css">
  <!-- iCheck -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/icheck-bootstrap.min.css">
  <!-- JQVMap -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/jqvmap.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/adminlte.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/OverlayScrollbars.min.css">
  <!-- Daterange picker -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/daterangepicker.css">
  <!-- summernote -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/summernote-bs4.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/buttons.bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Preloader (optional, removed for performance) -->
  
  <!-- Navbar -->
  <?php 
    // Ensure dep_name variable exists (passed from controller in real scenario)
    // For demo/standalone, set a fallback. Remove or adjust as needed.
    if (!isset($dep_name)) {
        $dep_name = 'default';
    }
    $this->load->view($dep_name.'/nav.php');
  ?>
  <!-- /.navbar -->

    <style>
        .card{
            background-image: linear-gradient(248.35deg, #86cdff -11.3%, #f4f4fe 16.44%, #ffffff 28.3%, #ffffff 72.47%, #ebeafe 89.69%, #bec6f7 101.94%);
        }
        .card, .card.card-outline-tabs, .small-box>.inner, table {
            background-image: linear-gradient(248.35deg, #86cdff -11.3%, #f4f4fe 16.44%, #ffffff 28.3%, #ffffff 72.47%, #ebeafe 89.69%, #bec6f7 101.94%);
            color: black;
        }
      .scrollme {
        overflow-x: auto;
      }
      .card-header-modern {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
      }
      .card-header-modern h3, .card-header-modern i {
        color: white;
      }
      .table-modern th {
        font-weight: 600;
        background-color: #f8f9fc;
        border-bottom-width: 1px;
        color: #4e73df;
      }
      .table-modern td {
        vertical-align: middle;
      }
      .total-badge {
        font-size: 1rem;
        padding: 0.5rem 1rem;
        border-radius: 30px;
      }
      .card {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
      }
      .card-body {
        padding: 1.25rem;
      }
      .dataTables_wrapper .dt-buttons {
        margin-bottom: 1rem;
      }
      .btn-sm-custom {
        margin: 0 2px;
      }
  </style>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
      <br>
       <?php if ($this->session->flashdata('success_message')): ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <?= $this->session->flashdata('success_message'); ?>
        </div>
        <?php endif; ?>
        <?php if ($this->session->flashdata('error_message')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <strong> <?php echo $this->session->flashdata('error_message'); ?></strong>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <?php endif; ?>
        
        <!-- Page Header (Modern Stats Overview) -->
        <div class="row mt-2 mb-4">
          <div class="col-md-12">
            <div class="d-sm-flex align-items-center justify-content-between">
              <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-line"></i> Pre Identified Schools</h1>
              <div class="mt-2 mt-sm-0">
                <span class="badge badge-info p-2"><i class="far fa-calendar-alt"></i> <?= date('F d, Y'); ?></span>
              </div>
            </div>
            <hr class="mt-2 mb-0">
          </div>
        </div>

        <!-- Data Table 1: Sales by CID -->
        <div class="row">
          <div class="col-12">
            <div class="card shadow-sm">
              <div class="card-header card-header-modern">
                <div class="d-flex justify-content-between align-items-center">
                  <h3 class="card-title"><i class="fas fa-users"></i> Schools by Sales Representative (CID)</h3>
                  <i class="fas fa-chart-simple fa-lg"></i>
                </div>
              </div>
              <div class="card-body">
                <div class="scrollme">
                  <table id="salesCidTable" class="table table-bordered table-striped table-hover table-modern w-100">
                    <thead class="thead-dark">
                    <tr>
                        <th>📋 Sr. No.</th>
                        <th>🆔 CID</th>
                        <th>🏢 Company Name</th>
                        <th>👤 Main BD</th>
                        <th>📌 Current Status</th>
                        <th>🎓 Total Schools</th>
                    </tr>
                    </thead>
                    <tbody>
                      <?php 
                      $totalUnitsCid = 0;
                      if (!empty($preSchools['data1']) && is_array($preSchools['data1'])): 
                        $counter = 1;
                        foreach ($preSchools['data1'] as $item): 
                          if (isset($item->sales_cid, $item->total)):
                            $totalUnitsCid += (int)$item->total;
                      ?>
                      <tr>
                        <td><?= $counter++; ?></td>
                        <td><a target="_blank" href="<?=base_url();?>Menu/CompanyDetails/<?= $item->sales_cid ?>"><span class="font-weight-bold"><?= htmlspecialchars($item->sales_cid); ?></span></a></td>
                        <td>
                            <?php
                            $companyName      = '';
                            $mainbd_name      = '';
                            $current_status   = '';
                            if(!empty($item->sales_cid)){
                                $companyDetails = $this->Menu_model->GetSalesCompnayNameBYCid($item->sales_cid);
                                if(count($companyDetails) > 0){
                                    $companyName    = $companyDetails[0]->compname;
                                    $mainbd_name    = $companyDetails[0]->mainbd_name;
                                    $current_status = $companyDetails[0]->current_status;
                                }
                            }
                             
                              ?>
                            <a target="_blank" href="<?=base_url();?>Menu/CompanyDetails/<?= $item->sales_cid ?>"><span class="font-weight-bold"><?= $companyName; ?></span></a>
                        </td>
                        <td><?= $mainbd_name ?></td>
                        <td><?= $current_status ?></td>
                        <td>
                            <a href="<?=base_url();?>Menu/PreIdentifySchoolsLists/<?= $item->sales_cid ?>">
                                <span class="badge badge-primary px-3 py-2"><?= number_format($item->total); ?></span>
                            </a>
                        </td>
                      </tr>
                      <?php 
                          endif;
                        endforeach; 
                      else: ?>
                      <tr><td colspan="5" class="text-center">No data available for Sales CID</td></tr>
                      <?php endif; ?>
                    </tbody>
                    <tfoot>
                      <tr class="bg-light">
                        <th colspan="5" class="text-right">Total Schools (All CIDs):</th>
                        <th><?= number_format($totalUnitsCid); ?></th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
              <div class="card-footer text-muted">
                <i class="fas fa-info-circle"></i> Showing total schools per sales representative (CID). <strong>Grand Total: <?= number_format($totalUnitsCid); ?> school</strong>
              </div>
            </div>
          </div>
        </div>




        <?php if(in_array($user['type_id'],[1,2])){ ?>
        <!-- Data Table 2: Sales by Zone -->
        <div class="row mt-3">
          <div class="col-12">
            <div class="card shadow-sm">
              <div class="card-header card-header-modern" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="d-flex justify-content-between align-items-center">
                  <h3 class="card-title"><i class="fas fa-map-marker-alt"></i> School by Zone (Regional)</h3>
                  <i class="fas fa-globe-asia fa-lg"></i>
                </div>
              </div>
              <div class="card-body">
                <div class="scrollme">
                  <table id="salesZoneTable" class="table table-bordered table-striped table-hover table-modern w-100">
                    <thead class="thead-dark">
                     <tr>
                        <th>🔢 #</th>
                        <th>🗺️ Zone</th>
                        <th>🏫 Total Schools</th>
                    </tr>
                    </thead>
                    <tbody>
                      <?php 
                      $totalUnitsZone = 0;
                      if (!empty($preSchools['data2']) && is_array($preSchools['data2'])): 
                        $zoneCounter = 1;
                        foreach ($preSchools['data2'] as $zoneItem): 
                          if (isset($zoneItem->szone, $zoneItem->total)):
                            $totalUnitsZone += (int)$zoneItem->total;
                            $zoneDisplay = (trim($zoneItem->szone) === '') ? '<span class="badge badge-secondary">Undefined / Global</span>' : 'Zone ' . htmlspecialchars($zoneItem->szone);
                      ?>
                      <tr>
                        <td><?= $zoneCounter++; ?></td>
                        <td><?php 
                        $szone_id = 'not_set';
                        if(!empty($zoneItem->szone)){
                            $szone_id = $zoneItem->szone;
                            echo $zone_name = $this->Menu_model->GetOPerationsZoneByZoneID($zoneItem->szone)[0]->name;
                        }else{
                            echo "<span class='badge badge-danger px-3 py-2'>Not Set</span>";
                        } ?></td>
                        <td>
                            <a target="_blank" href="<?=base_url();?>Menu/PreIdentifySchoolsListsByZone/<?= $szone_id ?>">
                                <span class="badge badge-success px-3 py-2"><?= number_format($zoneItem->total); ?></span>
                          </a>
                        </td>
                      </tr>
                      <?php 
                          endif;
                        endforeach; 
                      else: ?>
                      <tr><td colspan="3" class="text-center">No zone-based data available</td></tr>
                      <?php endif; ?>
                    </tbody>
                    <tfoot>
                      <tr class="bg-light">
                        <th colspan="2" class="text-right">Total Schools (All Zones):</th>
                        <th><?= number_format($totalUnitsZone); ?></th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
              <div class="card-footer text-muted">
                <i class="fas fa-chart-pie"></i> Schools distribution across zones. <strong>Total: <?= number_format($totalUnitsZone); ?> schools</strong>
              </div>
            </div>
          </div>
        </div>

         <?php } ?>

        <!-- Optional Quick Stats Row -->
        <div class="row mt-4 mb-2">
          <div class="col-lg-6 col-md-6">
            <div class="small-box bg-info shadow-sm" style="border-radius: 20px;">
              <div class="inner">
                <h3><?= number_format($totalUnitsCid); ?></h3>
                <p>Total Schools (CID Based)</p>
              </div>
              <div class="icon">
                <i class="fas fa-chart-bar"></i>
              </div>
            </div>
          </div>
            <?php if(in_array($user['type_id'],[1,2])){ ?>
                <div class="col-lg-6 col-md-6">
                  <div class="small-box bg-success shadow-sm" style="border-radius: 20px;">
                    <div class="inner">
                      <h3><?= number_format($totalUnitsZone); ?></h3>
                      <p>Total Schools (Zone Based)</p>
                    </div>
                    <div class="icon">
                      <i class="fas fa-store"></i>
                    </div>
                  </div>
                </div>
            <?php } ?>
        </div>
        
      </div><!-- /.container-fluid -->
    </section>
  </div>
  <!-- /.content-wrapper -->

  <footer class="main-footer">
    <strong>Copyright &copy; 2021-2025 <a href="<?=base_url();?>">Stemlearning</a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 2.0
    </div>
  </footer>

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="<?=base_url();?>assets/js/jquery.min.js"></script>
<!-- jQuery UI 1.11.4 -->
<script src="<?=base_url();?>assets/js/jquery-ui.min.js"></script>
<!-- Resolve conflict -->
<script>
  $.widget.bridge('uibutton', $.ui.button)
</script>
<!-- Bootstrap 4 -->
<script src="<?=base_url();?>assets/js/bootstrap.bundle.min.js"></script>
<!-- ChartJS (optional, for potential future use) -->
<script src="<?=base_url();?>assets/js/Chart.min.js"></script>
<!-- Sparkline -->
<script src="<?=base_url();?>assets/js/sparkline.js"></script>
<!-- JQVMap -->
<script src="<?=base_url();?>assets/js/jquery.vmap.min.js"></script>
<script src="<?=base_url();?>assets/js/jquery.vmap.usa.js"></script>
<!-- daterangepicker -->
<script src="<?=base_url();?>assets/js/moment.min.js"></script>
<script src="<?=base_url();?>assets/js/daterangepicker.js"></script>
<!-- Tempusdominus Bootstrap 4 -->
<script src="<?=base_url();?>assets/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- Summernote -->
<script src="<?=base_url();?>assets/js/summernote-bs4.min.js"></script>
<!-- overlayScrollbars -->
<script src="<?=base_url();?>assets/js/jquery.overlayScrollbars.min.js"></script>
<!-- DataTables & Plugins -->
<script src="<?=base_url();?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?=base_url();?>assets/js/dataTables.bootstrap4.min.js"></script>
<script src="<?=base_url();?>assets/js/dataTables.responsive.min.js"></script>
<script src="<?=base_url();?>assets/js/responsive.bootstrap4.min.js"></script>
<script src="<?=base_url();?>assets/js/dataTables.buttons.min.js"></script>
<script src="<?=base_url();?>assets/js/buttons.bootstrap4.min.js"></script>
<script src="<?=base_url();?>assets/js/jszip.min.js"></script>
<script src="<?=base_url();?>assets/js/pdfmake.min.js"></script>
<script src="<?=base_url();?>assets/js/vfs_fonts.js"></script>
<script src="<?=base_url();?>assets/js/buttons.html5.min.js"></script>
<script src="<?=base_url();?>assets/js/buttons.print.min.js"></script>
<script src="<?=base_url();?>assets/js/buttons.colVis.min.js"></script>
<!-- AdminLTE App -->
<script src="<?=base_url();?>assets/js/adminlte.js"></script>
<!-- AdminLTE dashboard demo (optional) -->
<script src="<?=base_url();?>assets/js/dashboard.js"></script>

<!-- Custom Initialization for Modern DataTables -->
<script>
  $(document).ready(function() {
    // Table 1: Sales by CID
    if ($.fn.DataTable) {
      $('#salesCidTable').DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 10,
        buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
          search: "Filter records:",
          lengthMenu: "Show _MENU_ entries"
        },
        initComplete: function () {
          // Append buttons to custom container for better styling
          $('#salesCidTable_wrapper .dt-buttons').addClass('btn-group flex-wrap');
          $('#salesCidTable_wrapper .dt-buttons .btn').addClass('btn-sm btn-secondary');
        }
      }).buttons().container().appendTo('#salesCidTable_wrapper .col-md-6:eq(0)');
      
      // Table 2: Sales by Zone
      $('#salesZoneTable').DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 10,
        buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
          search: "Quick search:",
        },
        initComplete: function () {
          $('#salesZoneTable_wrapper .dt-buttons').addClass('btn-group flex-wrap');
          $('#salesZoneTable_wrapper .dt-buttons .btn').addClass('btn-sm btn-success');
        }
      }).buttons().container().appendTo('#salesZoneTable_wrapper .col-md-6:eq(0)');
    } else {
      console.warn("DataTable library not fully loaded, but basic display works.");
    }
  });
</script>

<!-- Workaround to support any missing base_url if not needed -->
<?php
// In case $preSchools variable is not provided from controller, we set a safety but output provided data is already embedded.
// The following lines ensure the variable exists with demonstration data (if not defined).
// In a real scenario, this array would be passed from controller. This is for standalone rendering.
if (!isset($preSchools) || empty($preSchools)) {
    // Provide default data structure to avoid errors — normally controller will set it.
    // The actual provided array is used above; this fallback ensures no undefined variable notice.
    $preSchools = [
        'data1' => [],
        'data2' => []
    ];
}
?>
</body>
</html>