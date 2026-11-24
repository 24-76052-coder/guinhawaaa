<?php require __DIR__ . '/../shared/guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00291c">
  <meta name="description" content="Guinhawa Poultry Supply staff attendance.">
  <title>Staff Attendance &middot; Guinhawa Admin</title>

  <script src="/guinhawa_system/shared/js/theme-init.js?v=cleanup-20261122-2"></script>
  <link rel="icon" type="image/png" href="/guinhawa_system/assets/images/guinhawa-logo.png">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap5/css/bootstrap.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/guinhawa_system/shared/css/style.css">
</head>
<body data-area="cashier" data-page="attendance">
  <a class="skip-link" href="#mainContent">Skip to content</a>

  <div id="sidebarRoot"></div>
  <div id="offcanvasRoot"></div>

  <div class="app-main">
    <div id="topbarRoot"></div>

    <main class="page-content" id="mainContent">
      <div class="page-head">
        <div>
          <div class="page-eyebrow"><i class="bi bi-calendar2-check" aria-hidden="true"></i> Operations</div>
          <h1 class="page-title">Staff Attendance</h1>
          <p class="page-sub" id="attendanceSub">
            Daily time-in and time-out records for the team.
          </p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-app" type="button" id="exportAttendance">
            <i class="bi bi-download" aria-hidden="true"></i> Export CSV
          </button>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-person-check" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Present</span><span class="inventory-stat__value" id="statPresent">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Late</span><span class="inventory-stat__value" id="statLate">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-calendar-x" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Leave &amp; absent</span><span class="inventory-stat__value" id="statAway">&mdash;</span></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="inventory-stat">
            <div class="inventory-stat__icon"><i class="bi bi-graph-up" aria-hidden="true"></i></div>
            <div><span class="inventory-stat__label">Attendance rate</span><span class="inventory-stat__value" id="statRate">&mdash;</span></div>
          </div>
        </div>
      </div>

      <section class="panel mb-3">
        <div class="panel__body panel__body--filters">
          <div class="filter-bar">
            <div class="d-flex align-items-center gap-1">
              <button class="btn btn-icon" type="button" id="prevDate" title="Previous day" aria-label="Previous day"><i class="bi bi-chevron-left"></i></button>
              <input class="form-control" type="date" id="attendanceDate" aria-label="Attendance date" style="max-width:170px">
              <button class="btn btn-icon" type="button" id="nextDate" title="Next day" aria-label="Next day"><i class="bi bi-chevron-right"></i></button>
              <button class="btn btn-soft btn-row" type="button" id="todayDate">Today</button>
            </div>
            <div class="search-field">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input class="form-control" type="search" id="attendanceSearch" placeholder="Search staff member or role" aria-label="Search attendance">
            </div>
            <select class="form-select" id="attendanceFilter" aria-label="Filter by attendance status" style="max-width:170px">
              <option value="">All statuses</option>
              <option value="Present">Present</option>
              <option value="Late">Late</option>
              <option value="Half Day">Half Day</option>
              <option value="On Leave">On Leave</option>
              <option value="Absent">Absent</option>
              <option value="Not recorded">Not recorded</option>
            </select>
            <span class="pill-count ms-auto" id="attendanceCount">0 staff</span>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel__header">
          <div>
            <h2 class="panel__title"><i class="bi bi-fingerprint" aria-hidden="true"></i> Daily attendance sheet</h2>
            <p class="panel__sub" id="attendanceDayLabel">&mdash;</p>
          </div>
          <span class="badge-soft" id="attendanceNote"><i class="bi bi-info-circle"></i> Live records</span>
        </div>
        <div class="panel__body panel__body--flush">
          <div class="table-responsive">
            <table class="table table-app" id="attendanceTable">
              <thead>
                <tr>
                  <th class="sortable">Staff member <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Role <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th>Schedule</th>
                  <th>Time in</th>
                  <th>Time out</th>
                  <th class="sortable text-end">Hours <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="sortable">Status <i class="bi bi-arrow-down-up sort-icon"></i></th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="attendanceBody"></tbody>
            </table>
          </div>
          <div class="table-foot">
            <span id="attendanceFoot">Showing 0 of 0 staff</span>
            <span class="text-app-muted">Times are recorded in local time</span>
          </div>
        </div>
      </section>
    </main>

    <div id="footerRoot"></div>
  </div>

  <!-- Attendance edit modal -->
  <div class="modal fade" id="attendanceModal" tabindex="-1" aria-labelledby="attendanceModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div class="d-flex align-items-center gap-3">
            <div class="modal-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
            <div>
              <h2 class="modal-title" id="attendanceModalTitle">Edit attendance</h2>
              <div class="text-app-muted" style="font-size:.8rem" id="attendanceModalSub">&mdash;</div>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-app" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="attendanceForm" novalidate>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <div class="alert-app">
                  <i class="bi bi-person-badge" aria-hidden="true"></i>
                  <div><strong id="attendanceStaffName">&mdash;</strong><br><span id="attendanceStaffRole">&mdash;</span></div>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="attendanceStatusInput">Status</label>
                <select class="form-select" id="attendanceStatusInput">
                  <option value="Present">Present</option>
                  <option value="Late">Late</option>
                  <option value="Half Day">Half Day</option>
                  <option value="On Leave">On Leave</option>
                  <option value="Absent">Absent</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="attendanceNoteInput">Note</label>
                <input class="form-control" id="attendanceNoteInput" type="text" placeholder="Optional note">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="attendanceTimeIn">Time in</label>
                <input class="form-control" id="attendanceTimeIn" type="time">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="attendanceTimeOut">Time out</label>
                <input class="form-control" id="attendanceTimeOut" type="time">
              </div>
              <div class="col-12">
                <div class="alert-app alert-app--danger d-none" id="attendanceError"></div>
                <div class="alert-app" id="attendanceHoursPreview">
                  <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                  <div>Worked hours are computed on save, excluding a one-hour break.</div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-app" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand"><i class="bi bi-check2" aria-hidden="true"></i> Save attendance</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="/guinhawa_system/shared/bootstrap5/js/bootstrap.bundle.min.js" defer></script>
  <script src="/guinhawa_system/shared/js/store.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/charts.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/app.js?v=cleanup-20261122-2" defer></script>
  <script src="/guinhawa_system/shared/js/attendance.js?v=cleanup-20261122-2" defer></script>
</body>
</html>
