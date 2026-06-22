<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>STEM APP | Pre-Identified Schools Overview</title>

  <!-- Google Fonts & Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.0/css/all.min.css">
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  
  <!-- AdminLTE & Plugins (local paths from base_url) -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/adminlte.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/tempusdominus-bootstrap-4.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/jqvmap.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/daterangepicker.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/summernote-bs4.min.css">
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="<?=base_url();?>assets/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?=base_url();?>assets/css/buttons.bootstrap4.min.css">
  
  <style>
    /* Global enhancements */
    body {
      background: #f4f6f9;
    }
    .card {
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.02);
      transition: transform 0.2s;
    }
    .card:hover {
      transform: translateY(-2px);
    }
    .card-header-modern {
      background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
      border-bottom: none;
      padding: 1rem 1.5rem;
    }
    .card-header-modern h3, .card-header-modern i {
      color: white;
    }
    .table-modern th {
      font-weight: 600;
      background-color: #f8fafc;
      border-bottom: 2px solid #e2e8f0;
      color: #1e293b;
    }
    .table-modern td {
      vertical-align: middle;
      padding: 0.75rem;
    }
    .scrollme {
      overflow-x: auto;
      border-radius: 16px;
    }
    .badge-stat {
      font-size: 0.9rem;
      padding: 6px 14px;
      border-radius: 30px;
    }
    .small-box {
      border-radius: 24px;
      transition: all 0.3s;
    }
    .small-box:hover {
      transform: scale(1.01);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .dataTables_wrapper .dt-buttons .btn {
      border-radius: 30px;
      margin: 0 3px;
      padding: 0.25rem 0.8rem;
    }
    /* Detailed table responsive fix */
    #example3_wrapper .dataTables_scrollBody {
      border-radius: 12px;
    }
    .table thead th {
      white-space: nowrap;
    }
    .footer-note {
      font-size: 0.85rem;
    }
    .transfer-bar {
      background: #f1f5f9;
      border-radius: 40px;
      padding: 10px 20px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 15px;
      flex-wrap: wrap;
    }
    .transfer-bar label {
      margin-bottom: 0;
      font-weight: 600;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar (dynamic based on department) -->
  <?php 
    // Fallback for dep_name if not passed from controller
    $dep_name = $dep_name ?? 'default';
    $this->load->view($dep_name.'/nav.php');
  ?>

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
        
        <!-- Header with dynamic company info -->
        <div class="row mt-3 mb-3">
          <div class="col-md-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
              <h1 class="h3 mb-2 text-gray-800">
                <i class="fas fa-chalkboard-user me-2"></i> 
                Pre-Identified Schools · 
                <?php 
                  $compId = "" ?? 'N/A';
                  $compName = $zone_name ?? 'Company';
                  echo "<span class='badge badge-dark p-2'></span> <span class='text-primary'>{$zone_name}</span>";
                ?>
              </h1>
              <div>
                <span class="badge badge-info p-2 shadow-sm"><i class="far fa-calendar-alt"></i> <?= date('F d, Y'); ?></span>
              </div>
            </div>
            <hr class="mt-2">
          </div>
        </div>

        <!-- ========== TABLE 1: SALES BY CID ========== -->
        <div class="row">
          <div class="col-12">
            <div class="card shadow-sm">
              <div class="card-header card-header-modern">
                <div class="d-flex justify-content-between align-items-center">
                  <h3 class="card-title"><i class="fas fa-user-tie"></i> Sales Representatives (CID Wise)</h3>
                  <i class="fas fa-chart-simple fa-lg"></i>
                </div>
              </div>
              <div class="card-body">
                <div class="scrollme">
                  <table id="salesCidTable" class="table table-bordered table-hover table-modern w-100">
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
                      $cidCounter = 1;
                      if (!empty($preSchools['data1']) && is_array($preSchools['data1'])): 
                        foreach ($preSchools['data1'] as $cidItem): 
                          if (isset($cidItem->sales_cid, $cidItem->total)):
                            $totalUnitsCid += (int)$cidItem->total;
                            // Fetch company name safely without overwriting global $companyDetails
                            $salesCompName    = '';
                            $mainbd_name      = '';
                            $current_status   = '';

                            if (!empty($cidItem->sales_cid)) {
                                $tempCompany = $this->Menu_model->GetSalesCompnayNameBYCid($cidItem->sales_cid);
                                if (!empty($tempCompany)) {
                                    $salesCompName  = htmlspecialchars($tempCompany[0]->compname);
                                    $mainbd_name    = $tempCompany[0]->mainbd_name;
                                    $current_status = $tempCompany[0]->current_status;
                                }
                            }
                      ?>
                      <tr>
                        <td><?= $cidCounter++; ?></td>
                        <td><a href="<?=base_url();?>Menu/CompanyDetails/<?= $cidItem->sales_cid ?>" class="font-weight-bold text-primary"><?= htmlspecialchars($cidItem->sales_cid); ?></a></td>
                        <td><a href="<?=base_url();?>Menu/CompanyDetails/<?= $cidItem->sales_cid ?>"><?= $salesCompName ?: '—'; ?></a></td>
                        <td><?= $mainbd_name ?></td>
                        <td><?= $current_status ?></td>
                        <td><a href="<?=base_url();?>Menu/PreIdentifySchoolsLists/<?= $cidItem->sales_cid ?>" class="badge badge-primary px-3 py-2"><?= number_format($cidItem->total); ?></a></td>
                      </tr>
                      <?php 
                          endif;
                        endforeach; 
                      else: ?>
                      <tr><td colspan="5" class="text-center text-muted">No CID data available</td></tr>
                      <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-light">
                      <tr>
                        <th colspan="5" class="text-right">Total Schools (All CIDs):</th>
                        <th><?= number_format($totalUnitsCid); ?></th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
              <div class="card-footer text-muted footer-note">
                <i class="fas fa-chart-line"></i> Grand total of pre-identified schools per sales representative. 
                <strong>Overall: <?= number_format($totalUnitsCid); ?> schools</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- ========== DETAILED SCHOOL LIST WITH CHECKBOX FORM (TRANSFER FUNCTIONALITY) ========== -->
        <?php 
          $spdDataByRoles = $preSchools['data3'] ?? [];
          // Ensure $user variable safety
          $userIdForLink = isset($user['user_id']) ? $user['user_id'] : (isset($user->user_id) ? $user->user_id : '');
          // Fetch target companies for transfer dropdown (assumes model method exists)
          $targetCompanies = [];
          if (method_exists($this->Menu_model, 'getAllCompanies')) {
              $targetCompanies = $this->Menu_model->getAllCompanies();
          } else {
              // Fallback: provide dummy companies or empty array (you can extend model)
              $targetCompanies = [];
          }
        ?>
        <div class="row mt-4">
          <div class="col-12">
            <div class="card shadow-sm">
              <div class="card-header card-header-modern" style="background: linear-gradient(135deg, #11998e, #0f6b5e);">
                <div class="d-flex justify-content-between align-items-center">
                  <h3 class="card-title"><i class="fas fa-school"></i> Detailed School Profiles & Contacts</h3>
                  <i class="fas fa-check-double fa-lg"></i>
                </div>
              </div>
              <div class="card-body p-2">
                <!-- Transfer Form: starts here, wraps the table -->
                <form action="<?= base_url('Menu/transferSchoolsToCompanyByZone'); ?>" method="POST" id="transferSchoolsForm">

                  <div class="scrollme table-responsive text-nowrap">
                    <table id="example3" class="table table-bordered table-striped table-hover table-modern" style="min-width: 2300px;">
                      <thead class="thead-dark">
                        <tr>
                          <th class="text-center" style="width: 40px;"><input type="checkbox" id="selectAllCheckbox"></th> <!-- New Checkbox Column -->
                          <th>#</th><th>Year</th><th>SID</th><th>School Name</th><th>Client Name</th>
                          <th>Address</th><th>State</th><th>District</th><th>City</th><th>Tehsil</th>
                          <th>Zone</th><th>Pin Code</th><th>WhatsApp Group</th><th>Group Link</th><th>Academic Year</th>
                          <th>School Status</th><th>Language</th><th>Total Students</th><th>Boys</th><th>Girls</th>
                          <th>Teachers</th><th>Timing</th><th>Standard</th>
                          <th>Principal Name</th><th>Principal Designation</th><th>Principal Contact</th><th>Principal Email</th>
                          <th>Teacher Name</th><th>Teacher Designation</th><th>Teacher Contact</th><th>Teacher Email</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        $rowIdx = 1;
                        if(!empty($spdDataByRoles) && is_array($spdDataByRoles)):
                          foreach($spdDataByRoles as $school):
                            // Fetch contact details safely
                            $principal_name = $principal_designation = $principal_contact = $principal_email = '';
                            $teacher_name = $teacher_designation = $teacher_contact = $teacher_email = '';
                            $contacts = $this->Menu_model->GetRequestSchoolContactInformationsBYSID($school->id);
                            if(!empty($contacts)){
                              foreach($contacts as $contact){
                                $desig = strtolower(trim($contact->designation ?? ''));
                                if($desig == 'principal' || $desig == 'princpal'){
                                  $principal_name = htmlspecialchars($contact->contact_name ?? '');
                                  $principal_designation = htmlspecialchars($contact->designation ?? '');
                                  $principal_contact = htmlspecialchars($contact->contact_no ?? '');
                                  $principal_email = htmlspecialchars($contact->email ?? '');
                                }
                                if($desig == 'teacher'){
                                  $teacher_name = htmlspecialchars($contact->contact_name ?? '');
                                  $teacher_designation = htmlspecialchars($contact->designation ?? '');
                                  $teacher_contact = htmlspecialchars($contact->contact_no ?? '');
                                  $teacher_email = htmlspecialchars($contact->email ?? '');
                                } else if(empty($teacher_name) && !empty($contact->contact_name)){
                                  // fallback: any other contact becomes teacher representative
                                  $teacher_name = htmlspecialchars($contact->contact_name ?? '');
                                  $teacher_designation = htmlspecialchars($contact->designation ?? '');
                                  $teacher_contact = htmlspecialchars($contact->contact_no ?? '');
                                  $teacher_email = htmlspecialchars($contact->email ?? '');
                                }
                              }
                            }
                        ?>
                        <tr>
                          <td class="text-center">
                            <input type="checkbox" name="selected_schools[]" value="<?= $school->id; ?>" class="school-checkbox">
                          </td>
                          <td><?= $rowIdx++; ?></td>
                          <td><?= htmlspecialchars($school->sayear ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->id ?? ''); ?></td>
                          <td><a href="<?=base_url().'Menu/SchoolProfileDetails/'.$school->id; ?>" target="_blank" class="font-weight-bold"><?= htmlspecialchars($school->sname ?? ''); ?></a></td>
                          <td><?= htmlspecialchars($school->clientname ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->saddress ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->sstate ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->sdistrict ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->scity ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->tehshil ?? ''); ?></td>
                          <td>
                            <?php
                            if(!empty($school->szone)){
                              $zoneObj = $this->Menu_model->GetOPerationsZoneByZoneID($school->szone);
                              $zoneDisplayName = $zoneObj[0]->name;
                            } else {
                              $zoneDisplayName = '<span class="badge badge-warning">Undefined</span>';
                            }
                            
                            echo $zoneDisplayName;
                            
                            ?>
                          </td>
                          <td><?= htmlspecialchars($school->spincode ?? ''); ?></td>
                          <td>
                            <?php if(!empty($school->waname)): ?>
                              <a href="<?= $school->wanamelink ?? '#'; ?>" target="_blank" class="badge bg-info"><?= htmlspecialchars($school->waname); ?></a>
                            <?php else: echo "N/A"; endif; ?>
                          </td>
                          <td>
                            <?php if(!empty($school->wanamelink)): ?>
                              <a href="<?= $school->wanamelink; ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Group</a>
                            <?php else: echo "N/A"; endif; ?>
                          </td>
                          <td><?= htmlspecialchars($school->sayear ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->school_status_name ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->slanguage ?? ''); ?></td>
                          <td><?= number_format((float)($school->total_students ?? 0)); ?></td>
                          <td><?= number_format((float)($school->boys ?? 0)); ?></td>
                          <td><?= number_format((float)($school->girls ?? 0)); ?></td>
                          <td><?= number_format((float)($school->total_teachers ?? 0)); ?></td>
                          <td><?= htmlspecialchars($school->timing ?? ''); ?></td>
                          <td><?= htmlspecialchars($school->std ?? ''); ?></td>
                          <td><?= $principal_name ?: '—'; ?></td>
                          <td><?= $principal_designation ?: '—'; ?></td>
                          <td><?= $principal_contact ?: '—'; ?></td>
                          <td><?= $principal_email ?: '—'; ?></td>
                          <td><?= $teacher_name ?: '—'; ?></td>
                          <td><?= $teacher_designation ?: '—'; ?></td>
                          <td><?= $teacher_contact ?: '—'; ?></td>
                          <td><?= $teacher_email ?: '—'; ?></td>
                          <td class="text-center">
                            <a href="<?=base_url().'Menu/SchoolRequestProfileDetails/'.$school->id.'/'.$userIdForLink; ?>" class="btn btn-sm btn-primary rounded-pill" target="_blank">
                              <i class="fas fa-eye"></i> View Profile
                            </a>
                          </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="33" class="text-center">No detailed school records found.</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </form>
              </div>
              <div class="card-footer text-muted footer-note">
                <i class="fas fa-exchange-alt"></i> Select one or more schools using checkboxes, choose target company, and click "Transfer Selected Schools". 
                <strong>Note:</strong> Only checked schools will be moved to the new company.
              </div>
            </div>
          </div>
        </div>

        <!-- ========== TABLE 3: SCHOOLS BY ZONE ========== -->
        <div class="row mt-4">
          <div class="col-12">
            <div class="card shadow-sm">
              <div class="card-header card-header-modern" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="d-flex justify-content-between align-items-center">
                  <h3 class="card-title"><i class="fas fa-map-pin"></i> Schools by Zone (Operations)</h3>
                  <i class="fas fa-layer-group fa-lg"></i>
                </div>
              </div>
              <div class="card-body">
                <div class="scrollme">
                  <table id="salesZoneTable" class="table table-bordered table-hover table-modern w-100">
                    <thead class="thead-dark">
                      <tr><th>#</th><th>Zone Name</th><th>Total Schools</th></tr>
                    </thead>
                    <tbody>
                      <?php 
                      $totalUnitsZone = 0;
                      $zoneCounter = 1;
                      if (!empty($preSchools['data2']) && is_array($preSchools['data2'])): 
                        foreach ($preSchools['data2'] as $zoneItem):
                          if (isset($zoneItem->szone, $zoneItem->total)):
                            $totalUnitsZone += (int)$zoneItem->total;
                            $zoneDisplayName = 'Not Set';
                            if(!empty($zoneItem->szone)){
                              $zoneObj = $this->Menu_model->GetOPerationsZoneByZoneID($zoneItem->szone);
                              $zoneDisplayName = (!empty($zoneObj)) ? htmlspecialchars($zoneObj[0]->name) : "Zone ID: {$zoneItem->szone}";
                            } else {
                              $zoneDisplayName = '<span class="badge badge-secondary">Undefined</span>';
                            }
                      ?>
                      <tr>
                        <td><?= $zoneCounter++; ?></td>
                        <td><strong><?= $zoneDisplayName ?></strong></td>
                        <td><span class="badge badge-success px-3 py-2"><?= number_format($zoneItem->total); ?></span></td>
                      </tr>
                      <?php 
                          endif;
                        endforeach; 
                      else: ?>
                      <tr><td colspan="3" class="text-center">No zone-wise data available</td></tr>
                      <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-light">
                      <tr><th colspan="2" class="text-right">Total Schools (All Zones):</th><th><?= number_format($totalUnitsZone); ?></th></tr>
                    </tfoot>
                  </table>
                </div>
              </div>
              <div class="card-footer text-muted footer-note">
                <i class="fas fa-chalkboard"></i> Geographical distribution across zones. <strong>Total: <?= number_format($totalUnitsZone); ?> schools</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Stats Summary Row -->
        <div class="row mt-4 mb-4">
          <div class="col-lg-6 col-md-6">
            <div class="small-box bg-gradient-info shadow">
              <div class="inner">
                <h3><?= number_format($totalUnitsCid); ?></h3>
                <p>Total Schools (by Sales CID)</p>
              </div>
              <div class="icon"><i class="fas fa-chart-bar"></i></div>
            </div>
          </div>
          <div class="col-lg-6 col-md-6">
            <div class="small-box bg-gradient-success shadow">
              <div class="inner">
                <h3><?= number_format($totalUnitsZone); ?></h3>
                <p>Total Schools (by Zone)</p>
              </div>
              <div class="icon"><i class="fas fa-globe-asia"></i></div>
            </div>
          </div>
        </div>

      </div><!-- /.container-fluid -->
    </section>
  </div>

  <footer class="main-footer">
    <strong>Copyright &copy; 2021-2025 <a href="<?=base_url();?>">Stemlearning</a>.</strong> All rights reserved.
    <div class="float-right d-none d-sm-inline-block"><b>Version</b> 3.1</div>
  </footer>
</div>

<!-- REQUIRED SCRIPTS -->
<script src="<?=base_url();?>assets/js/jquery.min.js"></script>
<script src="<?=base_url();?>assets/js/jquery-ui.min.js"></script>
<script>$.widget.bridge('uibutton', $.ui.button)</script>
<script src="<?=base_url();?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?=base_url();?>assets/js/Chart.min.js"></script>
<script src="<?=base_url();?>assets/js/sparkline.js"></script>
<script src="<?=base_url();?>assets/js/jquery.vmap.min.js"></script>
<script src="<?=base_url();?>assets/js/jquery.vmap.usa.js"></script>
<script src="<?=base_url();?>assets/js/moment.min.js"></script>
<script src="<?=base_url();?>assets/js/daterangepicker.js"></script>
<script src="<?=base_url();?>assets/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="<?=base_url();?>assets/js/summernote-bs4.min.js"></script>
<script src="<?=base_url();?>assets/js/jquery.overlayScrollbars.min.js"></script>
<!-- DataTables core & plugins -->
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
<script src="<?=base_url();?>assets/js/adminlte.js"></script>

<script>
  // Confirm transfer action with user feedback
  function confirmTransfer() {
    var checkboxes = document.querySelectorAll('.school-checkbox:checked');
    if (checkboxes.length === 0) {
      alert('Please select at least one school to transfer.');
      return false;
    }
    var targetCompany = document.getElementById('target_company_id').value;
    if (!targetCompany) {
      alert('Please select a target company.');
      return false;
    }
    return confirm('Are you sure you want to transfer ' + checkboxes.length + ' selected school(s) to the chosen company?');
  }

  $(document).ready(function() {
    // Initialize DataTable for Sales by CID
    if ($.fn.DataTable) {
      $('#salesCidTable').DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 10,
        buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: { search: "Filter:", lengthMenu: "Show _MENU_ entries" }
      }).buttons().container().appendTo('#salesCidTable_wrapper .col-md-6:eq(0)');
      
      // Sales by Zone Table
      $('#salesZoneTable').DataTable({
        responsive: true,
        lengthChange: true,
        pageLength: 10,
        buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
      }).buttons().container().appendTo('#salesZoneTable_wrapper .col-md-6:eq(0)');
      
      // Detailed table with checkboxes (horizontal scroll enabled)
      $('#example3').DataTable({
        scrollX: true,
        scrollCollapse: true,
        autoWidth: true,
        deferRender: true,
        responsive: false,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        pageLength: 25,
        buttons: [
          { extend: 'copy', className: 'btn-sm btn-secondary' },
          { extend: 'csv', className: 'btn-sm btn-secondary' },
          { extend: 'excel', className: 'btn-sm btn-success' },
          { extend: 'pdf', className: 'btn-sm btn-danger', orientation: 'landscape', pageSize: 'A3' },
          { extend: 'print', className: 'btn-sm btn-info', autoPrint: true },
          { extend: 'colvis', className: 'btn-sm btn-dark', text: 'Columns' }
        ],
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: { search: "Quick Search:", lengthMenu: "Show _MENU_ entries" },
        initComplete: function() {
          $('#example3_wrapper .dt-buttons .btn').addClass('mx-1');
        }
      });
      
      // "Select All" checkbox functionality
      $('#selectAllCheckbox').on('change', function() {
        $('.school-checkbox').prop('checked', $(this).prop('checked'));
      });
    } else {
      console.warn("DataTables library missing - tables will display basic version");
    }
  });
</script>

<?php
// Final safety: Ensure no undefined variable errors if controller missed something.
if (!isset($preSchools) || !is_array($preSchools)) {
    $preSchools = ['data1' => [], 'data2' => [], 'data3' => []];
}
if (!isset($companyDetails)) {
    $companyDetails = [(object)['id' => 'N/A', 'compname' => 'Demo Company']];
}
?>
</body>
</html>