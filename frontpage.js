const FIELD_NAMES = [
  'full_name', 'lrn', 'first_name', 'middle_name',
  'last_name', 'suffix', 'gender', 'birthdate',
  'attendance_status_15_days', 'lis_remarks',
  'attendance_status_30_days', 'billing_remarks_1',
  'peac_remarks', 'billing_remarks_2', 'billing_remarks_3',
  'id_picture_2x2', 'sf9_grade10_report_card_photocopy',
  'psa_birth_certificate_photocopy', 'sf9_grade10_report_card_original',
  'scanning_status_sf9', 'sf10_form137_original'
];

document.addEventListener('DOMContentLoaded', () => {
  const menuBtn = document.getElementById('menuBtn');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const dropdownBtn = document.getElementById('dropdownBtn');
  const dropdownMenu = document.getElementById('dropdownMenu');
  const sidebarSearchInput = document.getElementById('sidebarSearchInput');
  const sectionList = document.getElementById('sectionList');
  const studentsTableContainer = document.getElementById('studentsTableContainer');

  // Sidebar toggle
  menuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
  });

  // Overlay click closes sidebar
  sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
  });

  // Dropdown toggle
  dropdownBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdownMenu.classList.toggle('show');
    dropdownBtn.setAttribute('aria-expanded', dropdownMenu.classList.contains('show'));
  });

  // Hide dropdown on outside click
  document.addEventListener('click', () => {
    dropdownMenu.classList.remove('show');
    dropdownBtn.setAttribute('aria-expanded', false);
  });

  // Search filter for sidebar section list
  sidebarSearchInput.addEventListener('input', () => {
    const filter = sidebarSearchInput.value.toLowerCase();
    document.querySelectorAll('.grade-group').forEach(group => {
      let anyMatch = false;
      group.querySelectorAll('a.section-item').forEach(link => {
        const text = link.textContent.toLowerCase();
        const match = text.includes(filter);
        link.parentElement.style.display = match ? '' : 'none';
        if (match) anyMatch = true;
      });
      group.style.display = anyMatch ? '' : 'none';
    });
  });

  // Toast helper
  function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  // Show student details in a modal
  function showDetails(student) {
    let html = '<table style="width:100%; border-collapse: collapse;">';
    FIELD_NAMES.forEach(field => {
      const label = field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      const value = (student[field] ?? '').toString().trim();
      html += `
        <tr style="border-bottom:1px solid #ddd;">
          <th style="text-align:left; padding:8px; width:40%; background:#f7f7f7;">${label}</th>
          <td style="padding:8px;">${value}</td>
        </tr>
      `;
    });
    html += '</table>';

    document.getElementById('modalDetailsContent').innerHTML = html;
    document.getElementById('studentModal').style.display = 'block';
  }

  // Modal close logic
  const studentModal = document.getElementById('studentModal');
  const closeModal = document.getElementById('closeModal');
  closeModal.onclick = function() {
    studentModal.style.display = "none";
  };
  window.onclick = function(event) {
    if (event.target === studentModal) {
      studentModal.style.display = "none";
    }
  };

  // Render students table with status column
  function renderStudents(students) {
    studentsTableContainer.innerHTML = `
      <h2>Students List</h2>
      <table id="studentsTable" style="width: 100%; margin-top: 10px; border-collapse: collapse;">
        <thead>
          <tr>
            <th style="border-bottom:1px solid #ccc; text-align:left; padding:8px;">Student Name</th>
            <th style="border-bottom:1px solid #ccc; text-align:left; padding:8px;">Status</th>
            <th style="border-bottom:1px solid #ccc; text-align:left; padding:8px;">View</th>
          </tr>
        </thead>
        <tbody id="studentsTableBody"></tbody>
      </table>
    `;

    const tbody = document.getElementById('studentsTableBody');
    students.forEach(student => {
      const tr = document.createElement('tr');
      tr.style.borderBottom = '1px solid #eee';

      // Name
      const nameTd = document.createElement('td');
      nameTd.textContent = student.full_name || '[No Name]';
      nameTd.style.padding = '8px';
      tr.appendChild(nameTd);

      // Status
      const statusTd = document.createElement('td');
      statusTd.style.padding = '8px';

      let allFilled = true;
      FIELD_NAMES.forEach(field => {
        const value = (student[field] ?? '').toString().trim();
        if (!value) allFilled = false;
      });

      statusTd.textContent = allFilled ? '✅ Complete' : '❌ Incomplete';
      statusTd.style.color = allFilled ? '#28a745' : '#dc3545';
      tr.appendChild(statusTd);

      // View Button
      const actionTd = document.createElement('td');
      actionTd.style.padding = '8px';

      const viewBtn = document.createElement('button');
      viewBtn.textContent = '👁️ View';
      viewBtn.title = 'View Details';
      viewBtn.className = 'btn btn-info';
      viewBtn.style.cursor = 'pointer';
      viewBtn.onclick = () => showDetails(student);

      actionTd.appendChild(viewBtn);
      tr.appendChild(actionTd);

      tbody.appendChild(tr);
    });
  }

  // Load and render sections list, grouped by grade level
  async function loadSections() {
    try {
      const response = await fetch('get_dashboard.php'); // Or your actual backend endpoint
      if (!response.ok) throw new Error('Network response was not ok');
      const result = await response.json();
      const sections = result.dashboards ?? [];

      // Group sections by normalized grade_level
      const grouped = { "Grade 11": [], "Grade 12": [] };
      sections.forEach(section => {
        const gradeNorm = (section.grade_level ?? '').toString().trim().toLowerCase();
        if (gradeNorm === "grade 11" || gradeNorm === "11") {
          grouped["Grade 11"].push(section);
        } else if (gradeNorm === "grade 12" || gradeNorm === "12") {
          grouped["Grade 12"].push(section);
        }
      });

      // Defensive: Only render allowed grade (if set by backend)
      // window.ALLOWED_GRADE should be set by PHP: "Grade 11", "Grade 12", or "all"
      const allowedGrade = window.ALLOWED_GRADE || 'all';
      sectionList.innerHTML = '';
      ["Grade 11", "Grade 12"].forEach(grade => {
        if (allowedGrade !== 'all' && allowedGrade !== grade) return;
        if (!grouped[grade].length) return;
        const groupLi = document.createElement('li');
        groupLi.className = 'grade-group';

        // Grade label
        const gradeLabel = document.createElement('div');
        gradeLabel.textContent = grade;
        gradeLabel.className = 'grade-group-title';
        groupLi.appendChild(gradeLabel);

        // Sections under this grade
        const ul = document.createElement('ul');
        ul.className = 'grade-section-list';
        grouped[grade].forEach(section => {
          const li = document.createElement('li');
          const a = document.createElement('a');
          a.href = '#';
          a.textContent = section.dashboard_name;
          a.classList.add('section-item');
          a.tabIndex = 0;

          a.addEventListener('click', async (e) => {
            e.preventDefault();
            sectionList.querySelectorAll('a.section-item').forEach(link => link.classList.remove('active'));
            a.classList.add('active');
            try {
              const res = await fetch(`get_dashboard_data.php?id=${encodeURIComponent(section.dashboard_id)}`);
              const data = await res.json();
              if (data.success && Array.isArray(data.rows)) {
                renderStudents(data.rows);
                const sectionTitle = document.getElementById('sectionTitle');
                if (sectionTitle) {
                  sectionTitle.textContent = `Section: ${data.dashboard_name || data.dashboard_id}`;
                }
              } else {
                showToast(data.message || 'Failed to load students.');
              }
            } catch (err) {
              console.error(err);
              showToast('An error occurred while loading the students.');
            }
          });

          li.appendChild(a);
          ul.appendChild(li);
        });
        groupLi.appendChild(ul);
        sectionList.appendChild(groupLi);
      });

      // Auto-load the first section if any
      const firstSection = sectionList.querySelector('a.section-item');
      if (firstSection) {
        firstSection.click();
      }
    } catch (error) {
      console.error('Error loading sections:', error);
      sectionList.innerHTML = '<li class="error">Failed to load sections</li>';
    }
  }

  loadSections();
});