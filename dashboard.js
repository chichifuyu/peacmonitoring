const FIELD_NAMES = [
  'full_name', 'lrn', 'first_name', 'middle_name',
  'last_name', 'suffix', 'gender', 'birthdate',
  'attendance_status_15_days', 'lis_remarks_1stsem',
  'attendance_status_30_days','lis_remarks_2ndsem', 'billing_remarks_1',
  'peac_remarks', 'billing_remarks_2', 'billing_remarks_3',
  'id_picture_2x2', 'sf9_grade10_report_card_photocopy',
  'psa_birth_certificate_ap', 'psa_birth_certificate_registrar',
  'sf9_grade10_report_card_original', 'scanning_status_sf9',
  'sf10_form137_original'
];

let dashboardsData = {};
let currentDashboard = null;

const mode = (window.DASHBOARD_MODE === 'edit' && window.CURRENT_PHASE !== null) ? 'edit' : 'view';
const editableFields = window.EDITABLE_FIELDS || [];
const showRowButtons = window.SHOW_ROW_BUTTONS || false;
let hasUnsavedChanges = false;

const debounce = (fn, delay) => {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
};

// --- PHASE BANNER & ENFORCEMENT ---
if (window.CURRENT_PHASE === null || window.CURRENT_PHASE === "null") {
  const phaseBanner = document.createElement('div');
  phaseBanner.textContent = "Editing is disabled: No active phase at this time.";
  phaseBanner.style = "background: #dc3545; color: #fff; padding: 1rem; text-align: center;";
  document.body.prepend(phaseBanner);
}

document.addEventListener('DOMContentLoaded', function () {
  if (typeof window.CURRENT_PHASE === "undefined" || window.CURRENT_PHASE === null || window.CURRENT_PHASE === "null") {
    // Disable/hide editing controls
    const saveBtn = document.getElementById('saveChangesButton');
    const addBtn = document.getElementById('addRowButton');
    const delBtn = document.getElementById('deleteRowsButton');
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.title = "Editing is disabled: No active phase.";
      saveBtn.style.opacity = "0.5";
      saveBtn.style.pointerEvents = "none";
    }
  }
});

// -------------- END PHASE ENFORCEMENT ------------------

function isEditable(field) {
  return mode === 'edit' && editableFields.includes(field);
}

const validationRules = {
  lrn: value => /^\d{12}$/.test(value) || value === '',
  birthdate: value => {
    if (!value) return true;
    const d = Date.parse(value);
    return !isNaN(d);
  },
  gender: value => ['Male', 'Female', ''].includes(value),
};

function validateCell(fieldName, value) {
  if (validationRules[fieldName]) {
    return validationRules[fieldName](value);
  }
  return true;
}

function markCellValidity(td, isValid) {
  if (isValid) {
    td.classList.remove('invalid-cell');
  } else {
    td.classList.add('invalid-cell');
  }
}

function renderTableHeaders() {
  const theadRow = document.querySelector('thead tr');
  let hdr = '';
  if (mode === 'edit' && showRowButtons) {
    hdr += `<th class="sticky-col-checkbox"><input type="checkbox" id="selectAllCheckbox"></th>`;
  }
  hdr += `<th class="sticky-col-number">#</th>`;
  hdr += FIELD_NAMES.map(f => {
    const editableClass = isEditable(f) ? 'editable' : 'non-editable';
    if (f === 'full_name') {
      return `<th class="sticky-col ${editableClass}" data-field="${f}">${f.replace(/_/g, ' ').toUpperCase()}</th>`;
    }
    return `<th class="${editableClass}" data-field="${f}">${f.replace(/_/g, ' ').toUpperCase()}</th>`;
  }).join('');
  theadRow.innerHTML = hdr;
}

function renderTable() {
  const tbody = document.getElementById('tableBody');
  if (!tbody || !currentDashboard || !dashboardsData[currentDashboard]) {
    tbody.innerHTML = '';
    return;
  }
  tbody.innerHTML = '';
  dashboardsData[currentDashboard].forEach((row, idx) => {
    const tr = document.createElement('tr');
    if (mode === 'edit' && showRowButtons) {
      const tdChk = document.createElement('td');
      tdChk.classList.add('sticky-col-checkbox');
      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.classList.add('selectRowCheckbox');
      tdChk.appendChild(chk);
      tr.appendChild(tdChk);
    }
    // Numbering column
    const tdNo = document.createElement('td');
    tdNo.classList.add('sticky-col-number');
    tdNo.textContent = idx + 1;
    tr.appendChild(tdNo);

    FIELD_NAMES.forEach((fieldName, i) => {
      const td = document.createElement('td');
      td.textContent = row[i] || '';
      if (fieldName === 'full_name') {
        td.classList.add('sticky-col');
      }
      if (isEditable(fieldName)) {
        td.contentEditable = true;
        td.classList.add('editable-cell');
        const valid = validateCell(fieldName, td.textContent.trim());
        markCellValidity(td, valid);
        td.addEventListener('input', () => {
          const value = td.textContent.trim();
          const valid = validateCell(fieldName, value);
          markCellValidity(td, valid);
        });
        td.addEventListener('blur', () => {
          const value = td.textContent.trim();
          const valid = validateCell(fieldName, value);
          markCellValidity(td, valid);
          if (valid) {
            dashboardsData[currentDashboard][idx][i] = value;
            setUnsavedChanges(true);
          } else {
            td.textContent = dashboardsData[currentDashboard][idx][i] || '';
          }
        });
      } else {
        td.classList.add('readonly-cell');
      }
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });

  if (mode === 'edit' && showRowButtons) {
    document.getElementById('selectAllCheckbox')?.addEventListener('change', e => {
      document.querySelectorAll('.selectRowCheckbox').forEach(chk => chk.checked = e.target.checked);
    });
  }
}

function setUnsavedChanges(value) {
  hasUnsavedChanges = value;
  const saveBtn = document.getElementById('saveChangesButton');
  if (saveBtn) {
    saveBtn.disabled = !value;
  }
  if (value) {
    document.title = '* ' + (document.title.replace(/^\*\s*/, ''));
  } else {
    document.title = document.title.replace(/^\*\s*/, '');
  }
}

function loadDashboardsSidebar() {
  const list = document.getElementById('dashboardList');
  if (!list) return;

  list.innerHTML = '';

  fetch('dashboard.php?action=getDashboards')
    .then(response => response.json())
    .then(result => {
      if (result.success && result.dashboards) {
        const grouped = {};
        result.dashboards.forEach(dashboard => {
          // Normalize for grouping
          const grade = (dashboard.grade_level || '').trim().toUpperCase();
          const strand = (dashboard.strand || '').trim().toUpperCase();
          if (!grouped[grade]) grouped[grade] = {};
          if (!grouped[grade][strand]) grouped[grade][strand] = [];
          grouped[grade][strand].push(dashboard);
        });

        const allowedGrade = window.ALLOWED_GRADE || 'all';

        Object.keys(grouped).forEach(grade => {
          if (allowedGrade !== 'all' && allowedGrade !== grade) return;

          const gradeLi = document.createElement('li');
          gradeLi.className = 'grade-group collapsible';

          const gradeBtn = document.createElement('button');
          gradeBtn.className = 'grade-group-title dropdown-btn';
          gradeBtn.textContent = grade;
          gradeBtn.type = 'button';
          gradeBtn.setAttribute('aria-expanded', 'false');
          gradeLi.appendChild(gradeBtn);

          const strandsUl = document.createElement('ul');
          strandsUl.className = 'strand-group-list';
          strandsUl.style.display = 'none';

          Object.keys(grouped[grade]).forEach(strand => {
            const strandLi = document.createElement('li');
            strandLi.className = 'strand-group collapsible';

            const strandBtn = document.createElement('button');
            strandBtn.className = 'strand-group-title dropdown-btn';
            strandBtn.textContent = strand;
            strandBtn.type = 'button';
            strandBtn.setAttribute('aria-expanded', 'false');
            strandLi.appendChild(strandBtn);

            const dashboardsUl = document.createElement('ul');
            dashboardsUl.className = 'dashboard-list-ul';
            dashboardsUl.style.display = 'none';

            grouped[grade][strand].forEach(dashboard => {
              dashboardsData[dashboard.dashboard_id] = [];
              const dashLi = document.createElement('li');
              dashLi.className = 'dashboard-list-item';
              const a = document.createElement('a');
              a.href = '#';
              a.textContent = dashboard.dashboard_name;
              a.dataset.dashboardId = dashboard.dashboard_id;
              a.dataset.dashboardName = dashboard.dashboard_name;
              a.dataset.gradeLevel = dashboard.grade_level;
              a.dataset.strand = dashboard.strand;
              a.addEventListener('click', changeDashboard);

              const deleteBtn = document.createElement('button');
              deleteBtn.className = 'delete-dashboard-btn';
              deleteBtn.title = 'Delete Dashboard';
              deleteBtn.innerHTML = '🗑️';
              deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                deleteDashboardConfirm(dashboard.dashboard_id, dashboard.dashboard_name);
              });
              dashLi.appendChild(a);
              dashLi.appendChild(deleteBtn);
              dashboardsUl.appendChild(dashLi);
            });

            strandLi.appendChild(dashboardsUl);
            strandsUl.appendChild(strandLi);

            strandBtn.addEventListener('click', function() {
              const expanded = strandBtn.getAttribute('aria-expanded') === 'true';
              strandBtn.setAttribute('aria-expanded', String(!expanded));
              dashboardsUl.style.display = expanded ? 'none' : '';
            });
          });

          gradeLi.appendChild(strandsUl);
          list.appendChild(gradeLi);

          gradeBtn.addEventListener('click', function() {
            const expanded = gradeBtn.getAttribute('aria-expanded') === 'true';
            gradeBtn.setAttribute('aria-expanded', String(!expanded));
            strandsUl.style.display = expanded ? 'none' : '';
          });
        });

        currentDashboard = null;
        document.getElementById('dashboardTitle').textContent = 'No Dashboard Selected';
        document.getElementById('tableBody').innerHTML = '';
      }
    })
    .catch(error => {
      console.error('Error loading dashboards:', error);
    });
}

function deleteDashboardConfirm(dashboardId, dashboardName) {
  if (!dashboardId) return;
  if (!confirm(`Are you sure you want to delete the dashboard "${dashboardName}"? This action cannot be undone.`)) return;
  deleteDashboard(dashboardId);
}

function changeDashboard(e) {
  e.preventDefault();
  if (hasUnsavedChanges) {
    if (!confirm("You have unsaved changes. Are you sure you want to switch dashboards and lose those changes?")) {
      return;
    }
  }
  const link = e.currentTarget;
  const id = link.dataset.dashboardId;
  document.querySelectorAll('#dashboardList a').forEach(a => a.classList.remove('active'));
  link.classList.add('active');
  currentDashboard = id;
  // Get info from data attributes
  const grade = link.dataset.gradeLevel || '';
  const name = link.dataset.dashboardName || link.textContent;
  const strand = link.dataset.strand || '';
  // Set the centered title
  document.getElementById('dashboardTitle').textContent =
    [grade, name, strand].filter(Boolean).join(' - ');
  setUnsavedChanges(false);
  loadDashboardData(id);
}

function loadDashboardData(id) {
  const loading = document.getElementById('loadingIndicator');
  const errorBox = document.getElementById('errorBox');
  if (loading) loading.style.display = 'block';
  if (errorBox) errorBox.style.display = 'none';

  fetch(`dashboard.php?action=loadData&dashboard_id=${id}`)
    .then(response => {
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      return response.text().then(text => {
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error('Server response:', text);
          throw new Error('Invalid JSON response from server');
        }
      });
    })
    .then(result => {
      if (!result.success) throw new Error(result.message || 'Failed to load data');
      dashboardsData[id] = result.data.map(row =>
        FIELD_NAMES.map(field => row[field] || '')
      );
      renderTable();
      setUnsavedChanges(false);
      if (loading) loading.style.display = 'none';
    })
    .catch(err => {
      console.error('Data fetch error:', err);
      if (loading) loading.style.display = 'none';
      if (errorBox) {
        errorBox.textContent = 'Failed to load dashboard data: ' + (err.message || 'Unknown error');
        errorBox.style.display = 'block';
      }
    });
}

async function deleteDashboard(dashboardId) {
  if (!dashboardId) {
    showToast("No dashboard selected", false);
    return;
  }
  try {
    const res = await fetch('delete_dashboard.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ dashboard_id: dashboardId })
    });
    const result = await res.json();
    if (result.success) {
      showToast("Dashboard deleted successfully.");
      delete dashboardsData[dashboardId];
      loadDashboardsSidebar();
      if (currentDashboard === dashboardId) {
        currentDashboard = null;
        document.getElementById('tableBody').innerHTML = '';
        document.getElementById('dashboardTitle').textContent = 'No Dashboard Selected';
      }
      setUnsavedChanges(false);
    } else {
      showToast(result.message || "Failed to delete dashboard", false);
    }
  } catch (err) {
    console.error('Delete error:', err);
    showToast("Error deleting dashboard", false);
  }
}

function showToast(msg, success = true) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.style.background = success ? 'var(--accent-color,#28a745)' : '#dc3545';
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

// Table filter
function filterTable() {
  const input = document.getElementById('tableSearchInput');
  if (!input) return;
  const query = input.value.toLowerCase();
  document.querySelectorAll('#tableBody tr').forEach(row => {
    const match = [...row.cells].some(cell =>
      cell.textContent.trim().toLowerCase().includes(query)
    );
    row.style.display = match ? '' : 'none';
  });
}

const filterTableDebounced = debounce(filterTable, 300);

// Sidebar filter with smart expand/collapse
function filterSidebarSmart() {
  const input = document.getElementById('sidebarSearchInput');
  if (!input) return;
  const query = input.value.toLowerCase();

  // 1. For each dashboard-list-item, show/hide if match
  document.querySelectorAll('.dashboard-list-item').forEach(item => {
    const a = item.querySelector('a');
    const match = a && a.textContent.toLowerCase().includes(query);
    item.style.display = match ? '' : 'none';
  });

  // 2. For each strand group, show if at least one visible item, else hide
  document.querySelectorAll('.strand-group').forEach(strandGroup => {
    const items = strandGroup.querySelectorAll('.dashboard-list-item');
    const anyVisible = Array.from(items).some(item => item.style.display !== 'none');
    strandGroup.style.display = anyVisible ? '' : 'none';
    // 3. Expand if any match, collapse otherwise
    const strandBtn = strandGroup.querySelector('.strand-group-title');
    const dashboardsUl = strandGroup.querySelector('.dashboard-list-ul');
    if (strandBtn && dashboardsUl) {
      if (anyVisible) {
        strandBtn.setAttribute('aria-expanded', 'true');
        dashboardsUl.style.display = '';
      } else {
        strandBtn.setAttribute('aria-expanded', 'false');
        dashboardsUl.style.display = 'none';
      }
    }
  });

  // 4. For each grade group, show if at least one visible item, else hide
  document.querySelectorAll('.grade-group').forEach(gradeGroup => {
    const items = gradeGroup.querySelectorAll('.dashboard-list-item');
    const anyVisible = Array.from(items).some(item => item.style.display !== 'none');
    gradeGroup.style.display = anyVisible ? '' : 'none';
    // 5. Expand if any match, collapse otherwise
    const gradeBtn = gradeGroup.querySelector('.grade-group-title');
    const strandsUl = gradeGroup.querySelector('.strand-group-list');
    if (gradeBtn && strandsUl) {
      if (anyVisible) {
        gradeBtn.setAttribute('aria-expanded', 'true');
        strandsUl.style.display = '';
      } else {
        gradeBtn.setAttribute('aria-expanded', 'false');
        strandsUl.style.display = 'none';
      }
    }
  });
}

const filterSidebarDebounced = debounce(filterSidebarSmart, 300);

// Sidebar and menu setup
function setupSidebarToggle() {
  const menuBtn = document.getElementById('menuBtn');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (!menuBtn || !sidebar || !overlay) return;

  const openSidebar = () => {
    sidebar.classList.add('active');
    overlay.classList.add('active');
  };

  const closeSidebar = () => {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
  };

  menuBtn.addEventListener('click', () => {
    const isOpen = sidebar.classList.contains('active');
    isOpen ? closeSidebar() : openSidebar();
  });

  overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
      closeSidebar();
    }
  });
}

function setupDropdownMenu() {
  const dropdownBtn = document.getElementById('dropdownBtn');
  const dropdownMenu = document.getElementById('dropdownMenu');
  if (!dropdownBtn || !dropdownMenu) return;

  dropdownBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    dropdownMenu.classList.toggle('show');
  });

  document.addEventListener('click', function(e) {
    if (!dropdownMenu.contains(e.target) && e.target !== dropdownBtn) {
      dropdownMenu.classList.remove('show');
    }
  });
}

function setupControls() {
  const addBtn = document.getElementById('addRowButton');
  const delBtn = document.getElementById('deleteRowsButton');
  if (addBtn) addBtn.style.display = (mode === 'edit' && showRowButtons) ? '' : 'none';
  if (delBtn) delBtn.style.display = (mode === 'edit' && showRowButtons) ? '' : 'none';

  const saveBtn = document.getElementById('saveChangesButton');
  if (saveBtn) saveBtn.style.display = (mode === 'edit') ? '' : 'none';

  if (mode === 'edit' && showRowButtons) {
    addBtn?.addEventListener('click', e => {
      e.preventDefault();
      if (!currentDashboard) return showToast("Select a dashboard", false);
      dashboardsData[currentDashboard].push(Array(FIELD_NAMES.length).fill(''));
      renderTable();
      setUnsavedChanges(true);
    });

    delBtn?.addEventListener('click', e => {
      e.preventDefault();
      if (!currentDashboard) return;
      dashboardsData[currentDashboard] = dashboardsData[currentDashboard].filter((_, i) => {
        const checkbox = document.querySelectorAll('.selectRowCheckbox')[i];
        return checkbox ? !checkbox.checked : true;
      });
      renderTable();
      setUnsavedChanges(true);
    });
  }

  if (mode === 'edit') {
    saveBtn?.addEventListener('click', e => {
      e.preventDefault();
      saveDashboardData();
    });
  }
}

function saveDashboardData() {
  if (!currentDashboard) {
    showToast("No dashboard selected", false);
    return;
  }
  const data = dashboardsData[currentDashboard].map(row => {
    const obj = {};
    FIELD_NAMES.forEach((f, i) => obj[f] = row[i] || '');
    return obj;
  });
  fetch('dashboard.php?action=saveData', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ dashboard_id: currentDashboard, data })
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        setUnsavedChanges(false);
        showToast("Changes saved successfully.");
        loadDashboardData(currentDashboard);
      } else {
        showToast(result.message || "Failed to save changes", false);
      }
    })
    .catch(err => {
      console.error('Save error:', err);
      showToast("Error saving changes", false);
    });
}

function bindMiscListeners() {
  const searchInput = document.getElementById('tableSearchInput');
  if (searchInput) searchInput.addEventListener('input', filterTableDebounced);

  const sidebarSearch = document.getElementById('sidebarSearchInput');
  if (sidebarSearch) sidebarSearch.addEventListener('input', filterSidebarDebounced);

  window.addEventListener('beforeunload', e => {
    if (hasUnsavedChanges) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
}

function init() {
  renderTableHeaders();
  loadDashboardsSidebar();
  setupControls();
  bindMiscListeners();
  setupSidebarToggle();
  setupDropdownMenu();
}

document.addEventListener('DOMContentLoaded', init);

// PHASE FIELDS MODAL LOGIC (admin only)
document.addEventListener('DOMContentLoaded', function() {
  const btn = document.getElementById('managePhaseFieldsBtn');
  const modal = document.getElementById('phaseFieldsModal');
  const closeBtn = document.getElementById('closePhaseFieldsModal');
  const contentDiv = document.getElementById('phaseFieldsModalContent');
  if (!btn || !modal) return;
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    modal.style.display = 'flex';
    contentDiv.innerHTML = "Loading...";
    fetch('phases_fields_api.php')
      .then(r => r.json())
      .then(data => {
        if (!data.success) return contentDiv.innerHTML = "Failed to load data.";
        // Build the modal content
        let html = '';
        data.phases.forEach(phase => {
          const phaseNum = phase.phase_number;
          html += `<div style="border:1px solid #ccc; margin-bottom:1em; border-radius:6px; padding:0.5em;">
            <strong>Phase ${phaseNum}:</strong>
            <form class="phase-fields-form" data-phase="${phaseNum}">
              <div style="margin-bottom:0.6em;">
                <label>
                  Start Date:
                  <input type="date" name="start_date" value="${phase.start_date}">
                </label>
                <label style="margin-left:1.5em;">
                  End Date:
                  <input type="date" name="end_date" value="${phase.end_date}">
                </label>
              </div>
              <div style="display:flex; flex-wrap:wrap; gap:0.5em;">`;
          data.fields.forEach(f => {
            const checked = (data.map[phaseNum] || []).includes(f) ? 'checked' : '';
            html += `<label style="display:inline-block; min-width:150px;"><input type="checkbox" name="field" value="${f}" ${checked}> ${f.replace(/_/g,' ')}</label>`;
          });
          html += `</div>
              <button type="submit" style="margin-top:0.5em;">Save</button>
            </form>
          </div>`;
        });
        contentDiv.innerHTML = html;
        // Attach submit handlers
        contentDiv.querySelectorAll('form.phase-fields-form').forEach(form => {
          form.addEventListener('submit', function(ev) {
            ev.preventDefault();
            const phase = this.getAttribute('data-phase');
            const fields = Array.from(this.querySelectorAll('input[name="field"]:checked')).map(i => i.value);
            const start_date = this.querySelector('input[name="start_date"]').value;
            const end_date = this.querySelector('input[name="end_date"]').value;
            fetch('phases_fields_api.php', {
              method: 'POST',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({ phase, fields, start_date, end_date })
            }).then(r=>r.json()).then(res=>{
              if (res.success) alert('Saved!');
              else alert('Failed: '+res.message);
            }).catch(()=>alert('Error'));
          });
        });
      });
  });
  closeBtn.addEventListener('click', ()=>{ modal.style.display = 'none'; });
  modal.addEventListener('click', function(e) {
    if (e.target === modal) modal.style.display = 'none';
  });
});

// ========================
// SUMMARY & PRINT BUTTONS
// ========================

document.addEventListener('DOMContentLoaded', function() {
  // SUMMARY BUTTON
  const genBtn = document.getElementById('generateSummaryBtn');
  const summaryDiv = document.getElementById('reportSummary');
  if (genBtn && summaryDiv) {
    genBtn.addEventListener('click', function() {
      if (!currentDashboard || !dashboardsData[currentDashboard]) {
        alert("No dashboard selected");
        return;
      }
      const data = dashboardsData[currentDashboard];
      let summaryHtml = `<h3>Summary</h3>`;
      summaryHtml += `<p><strong>Total Records:</strong> ${data.length}</p>`;
      // Example: Gender count
      const idx = FIELD_NAMES.indexOf('gender');
      if (idx !== -1) {
        const counts = {};
        data.forEach(row => {
          const v = (row[idx] || '').trim();
          if (v) counts[v] = (counts[v] || 0) + 1;
        });
        summaryHtml += `<p><strong>Gender:</strong> ` +
          Object.entries(counts).map(([k, v]) => `${k}: ${v}`).join(', ') +
          `</p>`;
      }
      // Add more summary logic as needed...

      summaryDiv.innerHTML = summaryHtml;
      summaryDiv.style.display = '';
    });
  }

  // PRINT TABLE BUTTON
  const printBtn = document.getElementById('printTableBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function() {
      // Print only the table (not the whole wrapper)
      const table = document.querySelector('.table-wrapper table');
      if (!table) {
        alert('Table not found!');
        return;
      }
  
      // Get the active dashboard link and its details
      const activeLink = document.querySelector('#dashboardList a.active');
      const grade = activeLink?.dataset.gradeLevel || '';
      const name = activeLink?.dataset.dashboardName || '';
      const strand = activeLink?.dataset.strand || '';
      const dashboardTitle = [grade, name, strand].filter(Boolean).join(' - ');
  
      const tableHTML = table.outerHTML;
      const printWindow = window.open('', '', 'width=900,height=700');
      printWindow.document.write(`
        <html>
          <head>
            <title>${dashboardTitle}</title>
            <style>
              body { font-family: Arial, sans-serif; margin: 2em; }
              table { border-collapse: collapse; width: 100%; }
              th, td { border: 1px solid #333; padding: 6px 10px; }
              th { background: #eee; }
            </style>
          </head>
          <body>
            <h2>${dashboardTitle}</h2>
            ${tableHTML}
          </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
      setTimeout(() => printWindow.close(), 1000);
    });
  }
});