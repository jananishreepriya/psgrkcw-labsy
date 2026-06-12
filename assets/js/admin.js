let currentHistoryPage = 1;
let currentHistoryData = [];

// ============================================
// MOBILE MENU & BULK UPLOAD HANDLERS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (menuToggle && sidebar && overlay) {
        function toggleMobileMenu() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
        }
        menuToggle.addEventListener('click', toggleMobileMenu);
        overlay.addEventListener('click', toggleMobileMenu);
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) toggleMobileMenu();
            });
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
    }

    // Bulk upload staff form
    const staffForm = document.getElementById('bulkUploadStaffForm');
    if (staffForm) {
        staffForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const resultDiv = document.getElementById('bulkUploadStaffResult');
            resultDiv.innerHTML = '<div class="alert alert-info">Uploading... <i class="fas fa-spinner fa-spin"></i></div>';
            fetch('api/bulk-upload-staff.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> Upload complete!<br>Inserted: ${data.inserted}<br>Updated: ${data.updated}<br>Errors: ${data.errors}<br>${data.messages ? data.messages.map(m => escapeHtml(m)).join('<br>') : ''}</div>`;
                        loadStaff();
                    } else {
                        resultDiv.innerHTML = `<div class="alert alert-error">${data.error || 'Upload failed'}</div>`;
                    }
                })
                .catch(err => { resultDiv.innerHTML = '<div class="alert alert-error">Network error: ' + err.message + '</div>'; });
        });
    }

    // Bulk upload heads form
    const headsForm = document.getElementById('bulkUploadHeadsForm');
    if (headsForm) {
        headsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const resultDiv = document.getElementById('bulkUploadHeadsResult');
            resultDiv.innerHTML = '<div class="alert alert-info">Uploading... <i class="fas fa-spinner fa-spin"></i></div>';
            fetch('api/bulk-upload-heads.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> Upload complete!<br>Inserted: ${data.inserted}<br>Updated: ${data.updated}<br>Errors: ${data.errors}<br>${data.messages ? data.messages.map(m => escapeHtml(m)).join('<br>') : ''}</div>`;
                        loadHeads();
                    } else {
                        resultDiv.innerHTML = `<div class="alert alert-error">${data.error || 'Upload failed'}</div>`;
                    }
                })
                .catch(err => { resultDiv.innerHTML = '<div class="alert alert-error">Network error: ' + err.message + '</div>'; });
        });
    }

    // Bulk upload timetable form
    const timetableForm = document.getElementById('bulkUploadTimetableForm');
    if (timetableForm) {
        timetableForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const resultDiv = document.getElementById('bulkUploadTimetableResult');
            resultDiv.innerHTML = '<div class="alert alert-info">Uploading... <i class="fas fa-spinner fa-spin"></i></div>';
            fetch('api/bulk-upload-timetable.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> Upload complete!<br>Inserted: ${data.inserted}<br>Updated: ${data.updated}<br>Errors: ${data.errors}<br>${data.messages ? data.messages.map(m => escapeHtml(m)).join('<br>') : ''}</div>`;
                        loadTimetable();
                    } else {
                        resultDiv.innerHTML = `<div class="alert alert-error">${data.error || 'Upload failed'}</div>`;
                    }
                })
                .catch(err => { resultDiv.innerHTML = '<div class="alert alert-error">Network error: ' + err.message + '</div>'; });
        });
    }
});

window.onload = function() {
    checkAuth();
    setupNavigation();
    initCalendarDropdowns();
    loadDashboard();
};

// ============================================
// AUTH & NAVIGATION
// ============================================
function checkAuth() {
    fetch('api/check-session.php')
        .then(r => r.json())
        .then(data => {
            if (!data.logged_in || data.role !== 'admin') {
                window.location.href = 'login.html';
                return;
            }
            document.getElementById('adminName').textContent = data.name;
        })
        .catch(err => window.location.href = 'login.html');
}

function setupNavigation() {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            showPage(page);
            document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            if (window.innerWidth <= 768) {
                const toggle = document.getElementById('mobileMenuToggle');
                if (toggle) toggle.click();
            }
        });
    });
}

function showPage(page) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(page + '-page').classList.add('active');
    if (page === 'dashboard') loadDashboard();
    if (page === 'timetable') loadTimetablePage();
    if (page === 'blockday') { initBlockDay(); loadBlockedDays(); }
    if (page === 'staff') loadStaff();
    if (page === 'heads') loadHeads();
    if (page === 'labs') loadLabs();
    if (page === 'calendar') loadCalendar();
    if (page === 'bookings') loadAllBookings();
    if (page === 'history') loadHistoryPage();
    if (page === 'support') loadSupportQueries();
}

// ============================================
// DASHBOARD
// ============================================
function loadDashboard() {
    const now = new Date();
    const dateDisplay = document.getElementById('currentDate');
    if (dateDisplay) dateDisplay.textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    fetch('api/admin-stats.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('statLabs').textContent = data.labs || 0;
            document.getElementById('statStaff').textContent = data.staff || 0;
            document.getElementById('statHeads').textContent = data.heads || 0;
            document.getElementById('statToday').textContent = data.today_bookings || 0;
            document.getElementById('statPending').textContent = data.pending || 0;
        })
        .catch(err => console.error('Failed to load stats:', err));

    fetch('api/admin-bookings.php?limit=5')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('recentBookings');
            if (!container) return;
            if (!data.bookings || data.bookings.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No recent bookings</h3></div>';
                return;
            }
            container.innerHTML = data.bookings.map(b => createBookingRow(b)).join('');
        })
        .catch(err => console.error('Failed to load recent bookings:', err));

    const today = new Date().toISOString().split('T')[0];
    fetch('api/day-info.php?date=' + today)
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('todayStatus');
            if (!box) return;
            if (data.success) {
                box.className = 'today-box ' + data.type;
                box.innerHTML = `<h2>${data.day_name}</h2><div style="font-size:24px;margin:10px 0;"><i class="fas fa-sync"></i> ${data.day_order}</div><div style="text-transform:uppercase;font-weight:600;">${data.type}</div>${data.block_reason ? `<div style="margin-top:10px;padding:10px;background:rgba(0,0,0,0.1);border-radius:6px;">${escapeHtml(data.block_reason)}</div>` : ''}`;
            }
        })
        .catch(err => console.error('Failed to load day info:', err));
}

function getTimeRangeFromSlot(slot) {
    const map = { 'P1':'8:10 AM – 9:00 AM','P2':'9:00 AM – 9:50 AM','P3':'10:10 AM – 11:00 AM','P4':'11:00 AM – 11:50 AM','P5':'11:50 AM – 12:40 PM','P6':'12:50 PM – 1:40 PM','P7':'1:40 PM – 2:30 PM','P8':'2:30 PM – 3:20 PM','P9':'3:40 PM – 4:30 PM','P10':'4:30 PM – 5:20 PM' };
    if (map[slot]) return map[slot];
    if (slot === 'FN') return '8:10 AM – 12:40 PM (FN)';
    if (slot === 'AN') return '12:50 PM – 5:20 PM (AN)';
    if (slot === 'Full Day') return '8:10 AM – 5:20 PM (Full Day)';
    return slot;
}

function createBookingRow(b) {
    const staffName = b.staff_name || 'Unknown';
    const labName = b.lab_name || 'Unknown';
    const bookingDate = b.booking_date ? formatDate(b.booking_date) : '';
    const timeRange = b.time_range || getTimeRangeFromSlot(b.time_slot);
    const status = b.status || '';
    const instantBadge = b.is_instant ? '<span style="background:#8b5cf6;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;">⚡ Instant</span>' : '';
    return `<div class="booking-item ${status}"><div class="booking-info"><h4>${escapeHtml(staffName)} ${instantBadge} - ${escapeHtml(labName)}</h4><div class="booking-meta"><span><i class="fas fa-calendar"></i> ${bookingDate}</span><span><i class="fas fa-clock"></i> ${escapeHtml(timeRange)}</span></div></div><span class="status-badge status-${status}">${status}</span></div>`;
}

// ============================================
// TIMETABLE
// ============================================
function loadTimetablePage() {
    fetch('api/labs.php?admin=1')
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('timetableLabFilter');
            if (select) select.innerHTML = '<option value="">All Labs</option>' + (data.labs ? data.labs.map(l => `<option value="${l.id}">${escapeHtml(l.lab_name)}</option>`).join('') : '');
        })
        .catch(err => console.error('Failed to load labs:', err));
    loadTimetable();
}

function loadTimetable() {
    const labId = document.getElementById('timetableLabFilter')?.value || '';
    const dayOrder = document.getElementById('timetableDayFilter')?.value || '';
    let url = 'api/timetable.php';
    const params = [];
    if (labId) params.push('lab_id=' + labId);
    if (dayOrder) params.push('day_order=' + dayOrder);
    if (params.length) url += '?' + params.join('&');
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('timetableContainer');
            if (!container) return;
            if (!data.timetable || data.timetable.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-week"></i><h3>No timetable entries</h3></div>';
                return;
            }
            const grouped = {};
            data.timetable.forEach(t => { if (!grouped[t.day_order]) grouped[t.day_order] = []; grouped[t.day_order].push(t); });
            let html = '';
            for (let order = 1; order <= 6; order++) {
                if (!grouped[order]) continue;
                if (dayOrder && order != dayOrder) continue;
                html += `<div style="margin-bottom:25px;"><h4 style="color:#6b21a8;margin-bottom:15px;"><i class="fas fa-sync"></i> Day Order ${order}</h4>`;
                grouped[order].forEach(t => {
                    html += `<div style="background:#f9fafb;padding:15px;border-radius:8px;border-left:4px solid #dc2626;margin-bottom:10px;display:flex;justify-content:space-between;">
                        <div><strong>${escapeHtml(t.lab_name)}</strong> | ${t.start_time.substring(0,5)} - ${t.end_time.substring(0,5)}<br><small>${escapeHtml(t.class_name)} ${t.subject ? '| ' + escapeHtml(t.subject) : ''} ${t.faculty_name ? '| ' + escapeHtml(t.faculty_name) : ''}</small></div>
                        <div><button class="btn btn-sm btn-warning" onclick="editTimetable(${t.id})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" onclick="deleteTimetable(${t.id})"><i class="fas fa-trash"></i></button></div>
                    </div>`;
                });
                html += `</div>`;
            }
            container.innerHTML = html;
        })
        .catch(err => console.error('Failed to load timetable:', err));
}

function showAddTimetableModal() {
    const modal = document.getElementById('modal');
    const content = document.getElementById('modalContent');
    fetch('api/labs.php?admin=1')
        .then(r => r.json())
        .then(data => {
            content.innerHTML = `
                <div class="modal-header"><h3><i class="fas fa-plus-circle"></i> Add Class Schedule</h3><button class="close-modal" onclick="closeModal()">&times;</button></div>
                <form id="addTimetableForm" onsubmit="return saveTimetable(event)">
                    <div class="form-group"><label>Lab *</label><select name="lab_id" required>${data.labs ? data.labs.map(l => `<option value="${l.id}">${escapeHtml(l.lab_name)}</option>`).join('') : ''}</select></div>
                    <div class="form-group"><label>Day Order (Academic Cycle) *</label><select name="day_order" required><option value="1">Day 1</option><option value="2">Day 2</option><option value="3">Day 3</option><option value="4">Day 4</option><option value="5">Day 5</option><option value="6">Day 6</option></select><small style="color:#6b7280;display:block;margin-top:5px;"><i class="fas fa-info-circle"></i> This is the academic day order (1-6), not day of week</small></div>
                    <div class="form-group"><label>Select Period *</label><select name="period" id="periodSelect" required onchange="updateTimeFromPeriod()"><option value="">-- Choose Period --</option><option value="1">Period 1: 8:10 AM - 9:00 AM</option><option value="2">Period 2: 9:00 AM - 9:50 AM</option><option value="3">Period 3: 10:10 AM - 11:00 AM</option><option value="4">Period 4: 11:00 AM - 11:50 AM</option><option value="5">Period 5: 11:50 AM - 12:40 PM</option><option value="6">Period 6: 12:50 PM - 1:40 PM</option><option value="7">Period 7: 1:40 PM - 2:30 PM</option><option value="8">Period 8: 2:30 PM - 3:20 PM</option><option value="9">Period 9: 3:40 PM - 4:30 PM</option><option value="10">Period 10: 4:30 PM - 5:20 PM</option></select></div>
                    <input type="hidden" name="start_time" id="startTime"><input type="hidden" name="end_time" id="endTime">
                    <div class="form-group"><label>Class Name *</label><input type="text" name="class_name" required placeholder="e.g., II BSc CS"></div>
                    <div class="form-group"><label>Subject</label><input type="text" name="subject" placeholder="Data Structures"></div>
                    <div class="form-group"><label>Faculty Name</label><input type="text" name="faculty_name" placeholder="Dr. Smith"></div>
                    <div class="form-group"><label>Head Email</label><input type="email" name="head_email" placeholder="head@department.edu"></div>
                    <div class="form-group"><label>Semester</label><input type="text" name="semester" placeholder="Even 2025-2026"></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Schedule</button>
                </form>
                <div id="modalError" style="margin-top:15px;"></div>
            `;
            modal.classList.add('active');
        })
        .catch(err => { alert('Failed to load labs'); });
}

window.updateTimeFromPeriod = function() {
    const periodSelect = document.getElementById('periodSelect');
    const startTime = document.getElementById('startTime');
    const endTime = document.getElementById('endTime');
    if (!periodSelect || !startTime || !endTime) return;
    const period = periodSelect.value;
    const times = {
        '1': { start: '08:10:00', end: '09:00:00' }, '2': { start: '09:00:00', end: '09:50:00' },
        '3': { start: '10:10:00', end: '11:00:00' }, '4': { start: '11:00:00', end: '11:50:00' },
        '5': { start: '11:50:00', end: '12:40:00' }, '6': { start: '12:50:00', end: '13:40:00' },
        '7': { start: '13:40:00', end: '14:30:00' }, '8': { start: '14:30:00', end: '15:20:00' },
        '9': { start: '15:40:00', end: '16:30:00' }, '10': { start: '16:30:00', end: '17:20:00' }
    };
    if (period && times[period]) {
        startTime.value = times[period].start;
        endTime.value = times[period].end;
    }
};

function saveTimetable(e) {
    e.preventDefault();
    const form = document.getElementById('addTimetableForm');
    if (!form) return false;
    const period = document.getElementById('periodSelect')?.value;
    if (!period) {
        document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Please select a period</div>';
        return false;
    }
    const fd = new FormData(form);
    fd.append('action', 'add');
    fd.delete('period');
    fetch('api/timetable.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadTimetable();
                showToast('Schedule added successfully!', 'success');
            } else {
                document.getElementById('modalError').innerHTML = `<div class="alert alert-error">${data.error || 'Failed to add schedule'}</div>`;
            }
        })
        .catch(err => { document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Network error occurred</div>'; });
    return false;
}

function deleteTimetable(id) {
    if (!confirm('Delete this schedule?')) return;
    fetch('api/timetable.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=delete&id=${id}` })
        .then(r => r.json())
        .then(data => { if (data.success) { loadTimetable(); showToast('Schedule deleted successfully!', 'success'); } })
        .catch(err => { showToast('Failed to delete schedule', 'error'); });
}

function editTimetable(id) {
    fetch('api/timetable.php?id=' + id)
        .then(r => r.json())
        .then(data => { if (data.entry) showEditTimetableModal(data.entry); else showToast('Entry not found', 'error'); })
        .catch(err => { showToast('Failed to load entry', 'error'); });
}

function showEditTimetableModal(entry) {
    const modal = document.getElementById('modal');
    const content = document.getElementById('modalContent');
    fetch('api/labs.php?admin=1')
        .then(r => r.json())
        .then(data => {
            const periodMap = { '08:10:00':1, '09:00:00':2, '10:10:00':3, '11:00:00':4, '11:50:00':5, '12:50:00':6, '13:40:00':7, '14:30:00':8, '15:40:00':9, '16:30:00':10 };
            const period = periodMap[entry.start_time] || '';
            content.innerHTML = `
                <div class="modal-header"><h3><i class="fas fa-edit"></i> Edit Class Schedule</h3><button class="close-modal" onclick="closeModal()">&times;</button></div>
                <form id="editTimetableForm" onsubmit="return updateTimetable(event)">
                    <input type="hidden" name="id" value="${entry.id}">
                    <div class="form-group"><label>Lab *</label><select name="lab_id" required>${data.labs ? data.labs.map(l => `<option value="${l.id}" ${l.id == entry.lab_id ? 'selected' : ''}>${escapeHtml(l.lab_name)}</option>`).join('') : ''}</select></div>
                    <div class="form-group"><label>Day Order (Academic Cycle) *</label><select name="day_order" required><option value="1" ${entry.day_order == 1 ? 'selected' : ''}>Day 1</option><option value="2" ${entry.day_order == 2 ? 'selected' : ''}>Day 2</option><option value="3" ${entry.day_order == 3 ? 'selected' : ''}>Day 3</option><option value="4" ${entry.day_order == 4 ? 'selected' : ''}>Day 4</option><option value="5" ${entry.day_order == 5 ? 'selected' : ''}>Day 5</option><option value="6" ${entry.day_order == 6 ? 'selected' : ''}>Day 6</option></select><small style="color:#6b7280;display:block;margin-top:5px;"><i class="fas fa-info-circle"></i> This is the academic day order (1-6), not day of week</small></div>
                    <div class="form-group"><label>Select Period *</label><select name="period" id="editPeriodSelect" required onchange="updateEditTimeFromPeriod()"><option value="">-- Choose Period --</option><option value="1" ${period == 1 ? 'selected' : ''}>Period 1: 8:10 AM - 9:00 AM</option><option value="2" ${period == 2 ? 'selected' : ''}>Period 2: 9:00 AM - 9:50 AM</option><option value="3" ${period == 3 ? 'selected' : ''}>Period 3: 10:10 AM - 11:00 AM</option><option value="4" ${period == 4 ? 'selected' : ''}>Period 4: 11:00 AM - 11:50 AM</option><option value="5" ${period == 5 ? 'selected' : ''}>Period 5: 11:50 AM - 12:40 PM</option><option value="6" ${period == 6 ? 'selected' : ''}>Period 6: 12:50 PM - 1:40 PM</option><option value="7" ${period == 7 ? 'selected' : ''}>Period 7: 1:40 PM - 2:30 PM</option><option value="8" ${period == 8 ? 'selected' : ''}>Period 8: 2:30 PM - 3:20 PM</option><option value="9" ${period == 9 ? 'selected' : ''}>Period 9: 3:40 PM - 4:30 PM</option><option value="10" ${period == 10 ? 'selected' : ''}>Period 10: 4:30 PM - 5:20 PM</option></select></div>
                    <input type="hidden" name="start_time" id="editStartTime" value="${entry.start_time}"><input type="hidden" name="end_time" id="editEndTime" value="${entry.end_time}">
                    <div class="form-group"><label>Class Name *</label><input type="text" name="class_name" required value="${escapeHtml(entry.class_name)}"></div>
                    <div class="form-group"><label>Subject</label><input type="text" name="subject" value="${escapeHtml(entry.subject || '')}"></div>
                    <div class="form-group"><label>Faculty Name</label><input type="text" name="faculty_name" value="${escapeHtml(entry.faculty_name || '')}"></div>
                    <div class="form-group"><label>Head Email</label><input type="email" name="head_email" value="${escapeHtml(entry.head_email || '')}"></div>
                    <div class="form-group"><label>Semester</label><input type="text" name="semester" value="${escapeHtml(entry.semester || '')}"></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Schedule</button>
                </form>
                <div id="modalError" style="margin-top:15px;"></div>
            `;
            modal.classList.add('active');
        })
        .catch(err => { alert('Failed to load labs'); });
}

function updateEditTimeFromPeriod() {
    const periodSelect = document.getElementById('editPeriodSelect');
    const startTime = document.getElementById('editStartTime');
    const endTime = document.getElementById('editEndTime');
    if (!periodSelect || !startTime || !endTime) return;
    const period = periodSelect.value;
    const times = {
        '1': { start: '08:10:00', end: '09:00:00' }, '2': { start: '09:00:00', end: '09:50:00' },
        '3': { start: '10:10:00', end: '11:00:00' }, '4': { start: '11:00:00', end: '11:50:00' },
        '5': { start: '11:50:00', end: '12:40:00' }, '6': { start: '12:50:00', end: '13:40:00' },
        '7': { start: '13:40:00', end: '14:30:00' }, '8': { start: '14:30:00', end: '15:20:00' },
        '9': { start: '15:40:00', end: '16:30:00' }, '10': { start: '16:30:00', end: '17:20:00' }
    };
    if (period && times[period]) {
        startTime.value = times[period].start;
        endTime.value = times[period].end;
    }
}

function updateTimetable(e) {
    e.preventDefault();
    const form = document.getElementById('editTimetableForm');
    if (!form) return false;
    const period = document.getElementById('editPeriodSelect')?.value;
    if (!period) {
        document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Please select a period</div>';
        return false;
    }
    const fd = new FormData(form);
    fd.append('action', 'update');
    fd.delete('period');
    fetch('api/timetable.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadTimetable();
                showToast('Schedule updated successfully!', 'success');
            } else {
                document.getElementById('modalError').innerHTML = `<div class="alert alert-error">${data.error || 'Failed to update schedule'}</div>`;
            }
        })
        .catch(err => { document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Network error occurred</div>'; });
    return false;
}

// ============================================
// BLOCK DAY
// ============================================
function initBlockDay() {
    const dateInput = document.getElementById('blockDate');
    if (dateInput && !dateInput.min) dateInput.min = new Date().toISOString().split('T')[0];
}

function blockDay() {
    const date = document.getElementById('blockDate')?.value;
    const type = document.getElementById('blockType')?.value;
    const reason = document.getElementById('blockReason')?.value;
    if (!date || !reason) { showMessage('blockMessage', 'Please fill all fields', 'error'); return; }
    fetch('api/block-day.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=block&date=${date}&type=${type}&reason=${encodeURIComponent(reason)}` })
        .then(r => r.json())
        .then(data => { if (data.success) { showMessage('blockMessage', data.message, 'success'); document.getElementById('blockReason').value = ''; loadBlockedDays(); } else { showMessage('blockMessage', data.error || 'Failed to block day', 'error'); } })
        .catch(err => { showMessage('blockMessage', 'Network error occurred', 'error'); });
}

function loadBlockedDays() {
    fetch('api/block-day.php')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('blockedDaysList');
            if (!container) return;
            if (!data.blocks || data.blocks.length === 0) { container.innerHTML = '<div class="empty-state">No blocked days</div>'; return; }
            container.innerHTML = data.blocks.map(b => `<div><div style="display:flex;justify-content:space-between;align-items:start;"><div><div style="font-weight:600;color:#991b1b;">${formatDate(b.calendar_date)} - ${ucfirst(b.type)}</div><div style="color:#7f1d1d;margin-top:5px;"><i class="fas fa-comment"></i> ${escapeHtml(b.block_reason)}</div></div><button class="btn btn-sm btn-success" onclick="unblockDay('${b.calendar_date}')"><i class="fas fa-unlock"></i></button></div></div>`).join('');
        })
        .catch(err => console.error('Failed to load blocked days:', err));
}

function unblockDay(date) {
    if (!confirm('Unblock this day?')) return;
    fetch('api/block-day.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=unblock&date=${date}` })
        .then(r => r.json())
        .then(data => { if (data.success) { loadBlockedDays(); showToast('Day unblocked successfully!', 'success'); } })
        .catch(err => { showToast('Failed to unblock day', 'error'); });
}

// ============================================
// MANAGE STAFF (FULL)
// ============================================
let allStaffData = [];

function loadStaff() {
    fetch('api/staff-list.php')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('staffTable');
            if (!tbody) return;
            if (!data.staff || data.staff.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No staff members</td></tr>';
                allStaffData = [];
                return;
            }
            allStaffData = data.staff;
            tbody.innerHTML = data.staff.map(s => `
                <tr>
                    <td>${escapeHtml(s.name)}</td>
                    <td>${escapeHtml(s.email)}</td>
                    <td><span class="status-badge status-${s.status}">${s.status}</span></td>
                    <td>${formatDate(s.created_at)}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editStaff(${s.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm ${s.status === 'active' ? 'btn-danger' : 'btn-success'}" onclick="toggleStaffStatus(${s.id}, '${s.status}')">
                            <i class="fas fa-${s.status === 'active' ? 'ban' : 'check'}"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
            filterStaff();
        })
        .catch(err => {
            const tbody = document.getElementById('staffTable');
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Failed to load staff</td></tr>';
        });
}

function showAddStaffModal() {
    const modal = document.getElementById('modal');
    const content = document.getElementById('modalContent');
    content.innerHTML = `
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New Staff</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <form id="addStaffForm" onsubmit="return saveStaff(event)">
            <div class="form-group"><label>Full Name</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Email Address</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="6"></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Staff</button>
        </form>
        <div id="modalError" style="margin-top:15px;"></div>
    `;
    modal.classList.add('active');
}

function editStaff(id) {
    const staff = allStaffData.find(s => s.id === id);
    if (!staff) { showToast('Staff not found', 'error'); return; }
    const modal = document.getElementById('modal');
    const content = document.getElementById('modalContent');
    content.innerHTML = `
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Staff</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <form id="editStaffForm" onsubmit="return updateStaff(event, ${id})">
            <input type="hidden" name="id" value="${id}">
            <div class="form-group"><label>Full Name</label><input type="text" name="name" value="${escapeHtml(staff.name)}" required></div>
            <div class="form-group"><label>Email Address</label><input type="email" name="email" value="${escapeHtml(staff.email)}" required></div>
            <div class="form-group"><label>New Password (leave blank to keep current)</label><input type="password" name="password" minlength="6" placeholder="Enter new password or leave blank"></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Staff</button>
        </form>
        <div id="modalError" style="margin-top:15px;"></div>
    `;
    modal.classList.add('active');
}

function updateStaff(e, id) {
    e.preventDefault();
    const form = document.getElementById('editStaffForm');
    const fd = new FormData(form);
    fd.append('action', 'update');
    fetch('api/update-staff.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadStaff();
                showToast('Staff updated successfully!', 'success');
            } else {
                document.getElementById('modalError').innerHTML = `<div class="alert alert-error">${data.error || 'Failed to update staff'}</div>`;
            }
        })
        .catch(err => { document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Network error occurred</div>'; });
    return false;
}

function saveStaff(e) {
    e.preventDefault();
    const form = document.getElementById('addStaffForm');
    const fd = new FormData(form);
    if (fd.get('password') !== fd.get('confirm_password')) {
        document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Passwords do not match</div>';
        return false;
    }
    fetch('api/add-staff.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadStaff();
                showToast('Staff added successfully!', 'success');
            } else {
                document.getElementById('modalError').innerHTML = `<div class="alert alert-error">${data.error || 'Failed to add staff'}</div>`;
            }
        })
        .catch(err => { document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Network error occurred</div>'; });
    return false;
}

function toggleStaffStatus(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this staff member?`)) return;
    fetch('api/toggle-staff.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${id}&status=${newStatus}` })
        .then(r => r.json())
        .then(data => { if (data.success) { loadStaff(); showToast(`Staff ${newStatus} successfully!`, 'success'); } })
        .catch(err => { showToast('Failed to update staff status', 'error'); });
}

function filterStaff() {
    const term = document.getElementById('staffSearch')?.value.toLowerCase() || '';
    const tbody = document.getElementById('staffTable');
    if (!tbody || !allStaffData.length) return;
    const filtered = term ? allStaffData.filter(s => s.name.toLowerCase().includes(term) || s.email.toLowerCase().includes(term)) : allStaffData;
    tbody.innerHTML = filtered.map(s => `
        <tr>
            <td>${escapeHtml(s.name)}</td>
            <td>${escapeHtml(s.email)}</td>
            <td><span class="status-badge status-${s.status}">${s.status}</span></td>
            <td>${formatDate(s.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-warning" onclick="editStaff(${s.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm ${s.status === 'active' ? 'btn-danger' : 'btn-success'}" onclick="toggleStaffStatus(${s.id}, '${s.status}')">
                    <i class="fas fa-${s.status === 'active' ? 'ban' : 'check'}"></i>
                </button>
            </td>
        </tr>
    `).join('');
    if (filtered.length === 0) tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No matching staff found</td></tr>';
}

function clearStaffSearch() { document.getElementById('staffSearch').value = ''; filterStaff(); }

// ============================================
// MANAGE HEADS (FULL)
// ============================================
let allHeadsData = [];

function loadHeads() {
    fetch('api/head-list.php')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('headsTable');
            if (!data.heads || data.heads.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No heads found</td></tr>';
                allHeadsData = [];
                return;
            }
            allHeadsData = data.heads;
            tbody.innerHTML = data.heads.map(h => `
                <tr>
                    <td>${escapeHtml(h.name)}</td>
                    <td>${escapeHtml(h.email)}</td>
                    <td>${escapeHtml(h.lab_names || 'None')}</td>
                    <td><span class="status-badge status-${h.status}">${h.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editHead(${h.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm ${h.status === 'active' ? 'btn-danger' : 'btn-success'}" onclick="toggleHeadStatus(${h.id}, '${h.status}')">
                            <i class="fas fa-${h.status === 'active' ? 'ban' : 'check'}"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
            filterHeads();
        })
        .catch(err => console.error('Failed to load heads:', err));
}

function showAddHeadModal() {
    fetch('api/labs.php?admin=1')
        .then(r => r.json())
        .then(data => {
            const labs = data.labs || [];
            const modalContent = document.getElementById('modalContent');
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h3><i class="fas fa-user-plus"></i> Add New Head</h3>
                    <button class="close-modal" onclick="closeModal()">&times;</button>
                </div>
                <form id="addHeadForm" onsubmit="return saveHead(event)">
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Email Address</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="6"></div>
                    <div class="form-group"><label>Assigned Labs (can select multiple)</label><select name="labs[]" multiple size="5" style="height:auto;">${labs.map(l => `<option value="${l.id}">${escapeHtml(l.lab_name)}</option>`).join('')}</select><small>Hold Ctrl/Cmd to select multiple</small></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Head</button>
                </form>
                <div id="modalError" style="margin-top:15px;"></div>
            `;
            document.getElementById('modal').classList.add('active');
        });
}

function saveHead(e) {
    e.preventDefault();
    const form = document.getElementById('addHeadForm');
    const fd = new FormData(form);
    fetch('api/add-head.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadHeads();
                showToast('Head added successfully!', 'success');
            } else {
                document.getElementById('modalError').innerHTML = `<div class="alert alert-error">${data.error || 'Failed to add head'}</div>`;
            }
        })
        .catch(err => { document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Network error</div>'; });
    return false;
}

function editHead(id) {
    const head = allHeadsData.find(h => h.id === id);
    if (!head) { showToast('Head not found', 'error'); return; }
    fetch('api/labs.php?admin=1')
        .then(r => r.json())
        .then(data => {
            const labs = data.labs || [];
            const assigned = head.assigned_labs ? head.assigned_labs.split(',').map(Number) : [];
            const modalContent = document.getElementById('modalContent');
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Edit Head</h3>
                    <button class="close-modal" onclick="closeModal()">&times;</button>
                </div>
                <form id="editHeadForm" onsubmit="return updateHead(event, ${id})">
                    <input type="hidden" name="id" value="${id}">
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" value="${escapeHtml(head.name)}" required></div>
                    <div class="form-group"><label>Email Address</label><input type="email" name="email" value="${escapeHtml(head.email)}" required></div>
                    <div class="form-group"><label>New Password (leave blank to keep current)</label><input type="password" name="password" minlength="6"></div>
                    <div class="form-group"><label>Assigned Labs</label><select name="labs[]" multiple size="5" style="height:auto;">${labs.map(l => `<option value="${l.id}" ${assigned.includes(l.id) ? 'selected' : ''}>${escapeHtml(l.lab_name)}</option>`).join('')}</select></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Head</button>
                </form>
                <div id="modalError" style="margin-top:15px;"></div>
            `;
            document.getElementById('modal').classList.add('active');
        });
}

function updateHead(e, id) {
    e.preventDefault();
    const form = document.getElementById('editHeadForm');
    const fd = new FormData(form);
    fetch('api/update-head.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadHeads();
                showToast('Head updated successfully!', 'success');
            } else {
                document.getElementById('modalError').innerHTML = `<div class="alert alert-error">${data.error || 'Failed to update head'}</div>`;
            }
        })
        .catch(err => { document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Network error</div>'; });
    return false;
}

function toggleHeadStatus(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this head?`)) return;
    fetch('api/toggle-head.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${id}&status=${newStatus}` })
        .then(r => r.json())
        .then(data => { if (data.success) { loadHeads(); showToast(`Head ${newStatus} successfully!`, 'success'); } })
        .catch(err => { showToast('Failed to update head status', 'error'); });
}

function filterHeads() {
    const term = document.getElementById('headSearch')?.value.toLowerCase() || '';
    const tbody = document.getElementById('headsTable');
    if (!tbody || !allHeadsData.length) return;
    const filtered = term ? allHeadsData.filter(h => h.name.toLowerCase().includes(term) || h.email.toLowerCase().includes(term) || (h.lab_names && h.lab_names.toLowerCase().includes(term))) : allHeadsData;
    tbody.innerHTML = filtered.map(h => `
        <tr>
            <td>${escapeHtml(h.name)}</td>
            <td>${escapeHtml(h.email)}</td>
            <td>${escapeHtml(h.lab_names || 'None')}</td>
            <td><span class="status-badge status-${h.status}">${h.status}</span></td>
            <td>
                <button class="btn btn-sm btn-warning" onclick="editHead(${h.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm ${h.status === 'active' ? 'btn-danger' : 'btn-success'}" onclick="toggleHeadStatus(${h.id}, '${h.status}')">
                    <i class="fas fa-${h.status === 'active' ? 'ban' : 'check'}"></i>
                </button>
            </td>
        </tr>
    `).join('');
    if (filtered.length === 0) tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No matching heads found</td></tr>';
}

function clearHeadSearch() { document.getElementById('headSearch').value = ''; filterHeads(); }

// ============================================
// MANAGE LABS (FULL)
// ============================================
let allLabsData = [];

function loadLabs() {
    fetch('api/labs.php?admin=1')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('labsGrid');
            if (!container) return;
            if (!data.labs || data.labs.length === 0) {
                container.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><h3>No Labs Added Yet</h3></div>';
                allLabsData = [];
                return;
            }
            allLabsData = data.labs;
            container.innerHTML = data.labs.map(l => `
                <div class="lab-card ${l.status}">
                    <div class="lab-header">
                        <span class="lab-title">${escapeHtml(l.lab_name)}</span>
                        <span class="status-badge status-${l.status}">${l.status}</span>
                    </div>
                    <div class="lab-desc">${escapeHtml(l.description || 'No description')}</div>
                    <div class="lab-meta">
                        <span><i class="fas fa-users"></i> Capacity: ${l.capacity}</span>
                        <span><i class="fas fa-book"></i> Bookings: ${l.booking_count || 0}</span>
                    </div>
                    <div class="lab-actions">
                        <button class="btn btn-sm btn-warning" onclick="editLab(${l.id})"><i class="fas fa-edit"></i> Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteLab(${l.id})"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
            `).join('');
            filterLabs();
        })
        .catch(err => console.error('Failed to load labs:', err));
}

function showAddLabModal() {
    const modal = document.getElementById('modal');
    const content = document.getElementById('modalContent');
    content.innerHTML = `
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Lab</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <form id="addLabForm" onsubmit="return saveLab(event)">
            <div class="form-group"><label>Lab Name</label><input type="text" name="lab_name" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
            <div class="form-group"><label>Capacity</label><input type="number" name="capacity" value="30" min="1" required></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Lab</button>
        </form>
        <div id="modalError" style="margin-top:15px;"></div>
    `;
    modal.classList.add('active');
}

function editLab(id) {
    const lab = allLabsData.find(l => l.id === id);
    if (!lab) { showToast('Lab not found', 'error'); return; }
    const modal = document.getElementById('modal');
    const content = document.getElementById('modalContent');
    content.innerHTML = `
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Lab</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <form id="editLabForm" onsubmit="return updateLab(event, ${id})">
            <input type="hidden" name="id" value="${id}">
            <div class="form-group"><label>Lab Name</label><input type="text" name="lab_name" value="${escapeHtml(lab.lab_name)}" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="3">${escapeHtml(lab.description || '')}</textarea></div>
            <div class="form-group"><label>Capacity</label><input type="number" name="capacity" value="${lab.capacity}" min="1" required></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Lab</button>
        </form>
        <div id="modalError" style="margin-top:15px;"></div>
    `;
    modal.classList.add('active');
}

function updateLab(e, id) {
    e.preventDefault();
    const form = document.getElementById('editLabForm');
    const fd = new FormData(form);
    fd.append('action', 'update');
    fetch('api/update-lab.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadLabs();
                showToast('Lab updated successfully!', 'success');
            } else {
                document.getElementById('modalError').innerHTML = `<div class="alert alert-error">${data.error || 'Failed to update lab'}</div>`;
            }
        })
        .catch(err => { document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Network error occurred</div>'; });
    return false;
}

function saveLab(e) {
    e.preventDefault();
    const form = document.getElementById('addLabForm');
    const fd = new FormData(form);
    fetch('api/add-lab.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadLabs();
                showToast('Lab added successfully!', 'success');
            } else {
                document.getElementById('modalError').innerHTML = `<div class="alert alert-error">${data.error || 'Failed to add lab'}</div>`;
            }
        })
        .catch(err => { document.getElementById('modalError').innerHTML = '<div class="alert alert-error">Network error occurred</div>'; });
    return false;
}

function deleteLab(id) {
    if (!confirm('Delete this lab permanently?')) return;
    fetch('api/delete-lab.php?id=' + id)
        .then(r => r.json())
        .then(data => { if (data.success) { loadLabs(); showToast('Lab deleted successfully!', 'success'); } })
        .catch(err => { showToast('Failed to delete lab', 'error'); });
}

function filterLabs() {
    const term = document.getElementById('labSearch')?.value.toLowerCase() || '';
    const grid = document.getElementById('labsGrid');
    if (!grid || !allLabsData.length) return;
    const filtered = term ? allLabsData.filter(l => l.lab_name.toLowerCase().includes(term) || (l.description && l.description.toLowerCase().includes(term))) : allLabsData;
    grid.innerHTML = filtered.map(l => `
        <div class="lab-card ${l.status}">
            <div class="lab-header">
                <span class="lab-title">${escapeHtml(l.lab_name)}</span>
                <span class="status-badge status-${l.status}">${l.status}</span>
            </div>
            <div class="lab-desc">${escapeHtml(l.description || 'No description')}</div>
            <div class="lab-meta">
                <span><i class="fas fa-users"></i> Capacity: ${l.capacity}</span>
                <span><i class="fas fa-book"></i> Bookings: ${l.booking_count || 0}</span>
            </div>
            <div class="lab-actions">
                <button class="btn btn-sm btn-warning" onclick="editLab(${l.id})"><i class="fas fa-edit"></i> Edit</button>
                <button class="btn btn-sm btn-danger" onclick="deleteLab(${l.id})"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
    `).join('');
    if (filtered.length === 0) grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;">No matching labs found</div>';
}

function clearLabSearch() { document.getElementById('labSearch').value = ''; filterLabs(); }

// ============================================
// ACADEMIC CALENDAR (FIXED - 2026 START, ENDLESS FUTURE)
// ============================================
function initCalendarDropdowns() {
    const monthSelect = document.getElementById('calendarMonth');
    const yearSelect = document.getElementById('calendarYear');
    if (!monthSelect || !yearSelect) return;
    
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    monthSelect.innerHTML = months.map((m,i) => `<option value="${i+1}" ${i===new Date().getMonth()?'selected':''}>${m}</option>`).join('');
    
    // Start year from 2026, extend 50 years into the future from the current year
    const currentYear = new Date().getFullYear();
    const startYear = 2026;                     // fixed start
    const endYear = Math.max(currentYear + 50, 2100); // at least up to 2100
    
    yearSelect.innerHTML = '';
    for (let y = startYear; y <= endYear; y++) {
        yearSelect.innerHTML += `<option value="${y}" ${y === currentYear ? 'selected' : ''}>${y}</option>`;
    }
}

function loadCalendar() {
    const month = document.getElementById('calendarMonth')?.value;
    const year = document.getElementById('calendarYear')?.value;
    if (!month || !year) {
        initCalendarDropdowns();
        return loadCalendar();
    }
    fetch(`api/calendar.php?month=${month}&year=${year}`)
        .then(r => r.json())
        .then(data => {
            const statsContainer = document.getElementById('calendarStats');
            if (statsContainer && data.stats) {
                statsContainer.innerHTML = `<div class="stat-pill working">Working: ${data.stats.working || 0}</div><div class="stat-pill holiday">Holidays: ${data.stats.holiday || 0}</div><div class="stat-pill exam">Exams: ${data.stats.exam || 0}</div>`;
            }
            const grid = document.getElementById('calendarGrid');
            if (!grid) return;
            const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            let html = days.map(d => `<div class="calendar-header">${d}</div>`).join('');
            const firstDay = new Date(year, month-1, 1).getDay();
            for (let i = 0; i < firstDay; i++) html += '<div class="calendar-day empty"></div>';
            const daysInMonth = new Date(year, month, 0).getDate();
            const today = new Date().toISOString().split('T')[0];
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                const dayData = data.days ? data.days[dateStr] : null;
                if (dayData) {
                    const isToday = dateStr === today;
                    html += `<div class="calendar-day ${dayData.type} ${isToday ? 'today' : ''}" onclick="editDay('${dateStr}', '${dayData.type}', '${dayData.day_order}')" title="${escapeHtml(dayData.block_reason || '')}">
                        <div class="day-number">${d}</div>
                        <div class="day-order">${dayData.day_order || ''}</div>
                        ${dayData.type !== 'normal' ? '<div class="day-badge"></div>' : ''}
                    </div>`;
                } else {
                    // Should not happen after generation, but fallback
                    const dayOfWeek = new Date(year, month-1, d).getDay();
                    const defaultOrder = `Day ${dayOfWeek === 0 ? 7 : dayOfWeek}`;
                    html += `<div class="calendar-day normal" onclick="editDay('${dateStr}', 'normal', '${defaultOrder}')">
                        <div class="day-number">${d}</div>
                        <div class="day-order">${defaultOrder}</div>
                    </div>`;
                }
            }
            grid.innerHTML = html;
        })
        .catch(err => console.error('Calendar load error:', err));
}

function generateCalendar() {
    const month = document.getElementById('calendarMonth')?.value;
    const year = document.getElementById('calendarYear')?.value;
    if (!month || !year) return;
    
    // Show loading indicator
    const grid = document.getElementById('calendarGrid');
    if (grid) grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin"></i> Generating calendar...</div>';
    
    fetch(`api/generate-calendar.php?month=${month}&year=${year}`)
        .then(r => r.json())
        .then(data => { 
            if (data.success) { 
                loadCalendar(); 
                showToast('Calendar generated successfully!', 'success'); 
            } else {
                showToast('Failed to generate calendar: ' + (data.error || 'unknown'), 'error');
                loadCalendar(); // Reload anyway
            }
        })
        .catch(err => { 
            showToast('Network error generating calendar', 'error'); 
            loadCalendar();
        });
}

function editDay(date, currentType, currentDayOrder) {
    const newType = prompt(`Change ${date} type (normal, holiday, exam, cultural, maintenance, other):`, currentType);
    if (newType === null) return;
    if (!['normal','holiday','exam','cultural','maintenance','other'].includes(newType.toLowerCase())) {
        alert('Invalid type. Please use one of: normal, holiday, exam, cultural, maintenance, other');
        return;
    }
    const newOrder = prompt(`Enter day order for ${date} (e.g., "Day 1", "Day 2", ... or leave empty to keep current):`, currentDayOrder);
    if (newOrder === null) return;
    
    const fd = new URLSearchParams();
    fd.append('date', date);
    fd.append('type', newType.toLowerCase());
    if (newOrder.trim() !== '') fd.append('day_order', newOrder.trim());
    
    fetch('api/update-day.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadCalendar();
            showToast('Day updated successfully!', 'success');
        } else {
            showToast('Failed to update day: ' + (data.error || 'unknown error'), 'error');
        }
    })
    .catch(err => { showToast('Network error updating day', 'error'); });
}

// ============================================
// ALL BOOKINGS
// ============================================
function loadAllBookings() {
    const filter = document.getElementById('bookingFilter')?.value || 'all';
    const tbody = document.getElementById('bookingsTable');
    tbody.innerHTML = '<tr><td colspan="7" class="loading-cell"><div class="loading-spinner"><div class="spinner"></div> Loading...</div></td></tr>';
    fetch('api/admin-bookings.php?filter=' + filter)
        .then(r => r.json())
        .then(data => {
            if (!data.bookings || data.bookings.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No bookings found</td></tr>';
                return;
            }
            tbody.innerHTML = data.bookings.map(b => {
                const conflictIcon = b.has_conflict ? '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:12px;font-size:11px;" title="' + escapeHtml(b.conflict_reason || '') + '"><i class="fas fa-exclamation-triangle"></i> Conflict</span>' : '';
                const instantBadge = b.is_instant ? '<span style="background:#8b5cf6;color:white;padding:2px 8px;border-radius:12px;font-size:11px;">⚡ Instant</span>' : '';
                const headApprovedBadge = b.head_approved ? '<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-size:11px;"><i class="fas fa-check-circle"></i> Head OK</span>' : '';
                const timeDisplay = b.time_range || getTimeRangeFromSlot(b.time_slot);
                let actions = '-';
                if (b.status !== 'approved' && b.status !== 'rejected') {
                    if (b.head_approved) {
                        actions = `<button class="btn btn-sm btn-success" onclick="updateBooking(event, ${b.id}, 'approved')"><i class="fas fa-check"></i> Approve</button> <button class="btn btn-sm btn-danger" onclick="updateBooking(event, ${b.id}, 'rejected')"><i class="fas fa-times"></i> Reject</button>`;
                    } else {
                        actions = '<span style="color:#f59e0b;"><i class="fas fa-clock"></i> Awaiting Head Approval</span>';
                    }
                }
                return `<tr>
                    <td>${escapeHtml(b.staff_name)} ${conflictIcon} ${instantBadge} ${headApprovedBadge}</td>
                    <td>${escapeHtml(b.lab_name)}</td>
                    <td>${formatDate(b.booking_date)}</td>
                    <td>${escapeHtml(timeDisplay)}</td>
                    <td>${escapeHtml(b.purpose || '')}</td>
                    <td><span class="status-badge status-${b.status}">${b.status}</span></td>
                    <td>${actions}</td>
                </tr>`;
            }).join('');
        })
        .catch(err => { tbody.innerHTML = `<tr><td colspan="7" class="empty-state">Error: ${escapeHtml(err.message)}</td></tr>`; });
}

function updateBooking(event, id, status) {
    const btn = event.currentTarget;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    const remarks = status === 'rejected' ? prompt('Enter rejection reason:') : '';
    fetch('api/update-booking.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${id}&status=${status}&remarks=${encodeURIComponent(remarks || '')}` })
        .then(r => r.json())
        .then(data => {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            if (data.success) {
                let message = `Booking ${status}!`;
                if (data.staff_email_sent) message += ' 📧 Staff notified.';
                if (data.heads_notified > 0) message += ` 📧 ${data.heads_notified} class head(s) notified.`;
                if (data.instant_bookings_cancelled > 0) message += ` ❌ ${data.instant_bookings_cancelled} instant booking(s) cancelled & notified.`;
                showToast(message, 'success');
                loadAllBookings();
                loadDashboard();
            } else { showToast('Error: ' + (data.error || 'Unknown error'), 'error'); }
        })
        .catch(err => { btn.innerHTML = originalHTML; btn.disabled = false; showToast('Network error', 'error'); });
}

// ============================================
// BOOKING HISTORY (with filters, pagination, export)
// ============================================
function loadHistoryPage() {
    const yearSelect = document.getElementById('filterYear');
    if (yearSelect) {
        yearSelect.innerHTML = '';
        for (let y = 2026; y <= 2050; y++) yearSelect.innerHTML += `<option value="${y}" ${y===new Date().getFullYear()?'selected':''}>${y}</option>`;
    }
    const monthSelect = document.getElementById('filterMonth');
    if (monthSelect) monthSelect.value = new Date().getMonth() + 1;
    Promise.all([fetch('api/staff-list.php').then(r=>r.json()).catch(()=>({staff:[]})), fetch('api/labs.php?admin=1').then(r=>r.json()).catch(()=>({labs:[]}))])
        .then(([staffData,labsData])=>{
            const staffSelect = document.getElementById('filterStaff');
            if(staffSelect) staffSelect.innerHTML = '<option value="">Select Staff</option>'+(staffData.staff?staffData.staff.map(s=>`<option value="${s.id}">${escapeHtml(s.name)}</option>`).join(''):'');
            const labSelect = document.getElementById('filterLab');
            if(labSelect) labSelect.innerHTML = '<option value="">Select Lab</option>'+(labsData.labs?labsData.labs.map(l=>`<option value="${l.id}">${escapeHtml(l.lab_name)}</option>`).join(''):'');
        }).catch(err=>console.error('Failed to load filter options:',err));
    loadBookingHistory();
}
function updateHistoryFilters() {
    const filterType = document.getElementById('historyFilterType')?.value;
    if(!filterType) return;
    document.querySelectorAll('.filter-input').forEach(el=>el.style.display='none');
    switch(filterType){
        case 'date': document.getElementById('dateFilter').style.display='block'; break;
        case 'month': document.getElementById('monthFilter').style.display='block'; break;
        case 'year': document.getElementById('yearFilter').style.display='block'; break;
        case 'staff': document.getElementById('staffFilter').style.display='block'; break;
        case 'lab': document.getElementById('labFilter').style.display='block'; break;
    }
}
function getFilterValue() {
    const filterType = document.getElementById('historyFilterType')?.value||'all';
    let filterValue = '';
    switch(filterType){
        case 'date': filterValue = document.getElementById('filterDate')?.value||''; break;
        case 'month': filterValue = document.getElementById('filterMonth')?.value||''; break;
        case 'year': filterValue = document.getElementById('filterYear')?.value||''; break;
        case 'staff': filterValue = document.getElementById('filterStaff')?.value||''; break;
        case 'lab': filterValue = document.getElementById('filterLab')?.value||''; break;
    }
    return {type:filterType, value:filterValue};
}
function loadBookingHistory(page=1) {
    currentHistoryPage = page;
    const filter = getFilterValue();
    let url = `api/booking-history.php?filter_type=${filter.type}&page=${page}`;
    if(filter.value) url += `&filter_value=${encodeURIComponent(filter.value)}`;
    fetch(url).then(r=>r.json()).then(data=>{
        if(data.success){
            currentHistoryData = data.bookings||[];
            displayHistoryTable(data.bookings||[]);
            displayHistoryStats(data.stats||{});
            displayPagination(data.pagination||{});
        }
    }).catch(err=>console.error('Failed to load history:',err));
}
function displayHistoryTable(bookings) {
    const tbody = document.getElementById('historyTableBody');
    if(!tbody) return;
    if(bookings.length===0){ tbody.innerHTML='<tr><td colspan="8" class="empty-state">No bookings found</td></tr>'; return; }
    tbody.innerHTML = bookings.map((b,index)=>{
        const instantBadge = b.is_instant?'<span style="background:#8b5cf6;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;">⚡ Instant</span>':'';
        const timeDisplay = b.time_range||getTimeRangeFromSlot(b.time_slot);
        return `<tr>
            <td>${(currentHistoryPage-1)*50+index+1}</td>
            <td>${escapeHtml(b.staff_name)} ${instantBadge}</td>
            <td>${escapeHtml(b.lab_name)}</td>
            <td>${formatDate(b.booking_date)}</td>
            <td>${escapeHtml(timeDisplay)}</td>
            <td>${escapeHtml(b.purpose||'')}</td>
            <td><span class="status-badge status-${b.status}">${b.status}</span></td>
            <td>${formatDateTime(b.created_at)}</td>
        </tr>`;
    }).join('');
}
function displayHistoryStats(stats) {
    document.getElementById('totalBookings').textContent = stats.total||0;
    document.getElementById('approvedBookings').textContent = stats.approved||0;
    document.getElementById('pendingBookings').textContent = stats.pending||0;
    document.getElementById('rejectedBookings').textContent = stats.rejected||0;
}
function displayPagination(pagination) {
    const container = document.getElementById('historyPagination');
    if(!container) return;
    container.innerHTML = `<button onclick="loadBookingHistory(${pagination.current_page-1})" ${pagination.current_page<=1?'disabled':''}><i class="fas fa-chevron-left"></i> Previous</button> <span class="page-info">Page ${pagination.current_page} of ${pagination.total_pages} (${pagination.total_records} records)</span> <button onclick="loadBookingHistory(${pagination.current_page+1})" ${pagination.current_page>=pagination.total_pages?'disabled':''}>Next <i class="fas fa-chevron-right"></i></button>`;
}
function resetHistoryFilters() {
    document.getElementById('historyFilterType').value='all';
    document.getElementById('filterDate').value='';
    document.getElementById('filterMonth').value=new Date().getMonth()+1;
    document.getElementById('filterYear').value=new Date().getFullYear();
    document.getElementById('filterStaff').value='';
    document.getElementById('filterLab').value='';
    updateHistoryFilters();
    loadBookingHistory(1);
}

// ============================================
// EXPORT FUNCTIONS
// ============================================
function htmlDecode(text){ if(!text) return ''; const txt=document.createElement('textarea'); txt.innerHTML=text; return txt.value; }
function exportToPDF() {
    if(currentHistoryData.length===0){ alert('No data to export. Please search first.'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l','mm','a4');
    doc.setFontSize(18); doc.setTextColor(107,33,168); doc.text('PSGRKCW LABSY - Booking History Report',14,20);
    const tableData = currentHistoryData.map((b,index)=>{
        const timeDisplay = b.time_range||getTimeRangeFromSlot(b.time_slot);
        const instantMark = b.is_instant?'⚡ ':'';
        return [index+1, instantMark+htmlDecode(b.staff_name||''), htmlDecode(b.lab_name||''), formatDate(b.booking_date), timeDisplay, htmlDecode((b.purpose||'').substring(0,30)), b.status.toUpperCase()];
    });
    doc.autoTable({ head:[['S.No','Staff Name','Lab Name','Date','Time','Purpose','Status']], body:tableData, startY:45, styles:{fontSize:9,cellPadding:2}, headStyles:{fillColor:[107,33,168],textColor:255} });
    doc.save(`booking-history-${new Date().toISOString().split('T')[0]}.pdf`);
}
function exportToExcel() {
    if(currentHistoryData.length===0){ alert('No data to export. Please search first.'); return; }
    const excelData = currentHistoryData.map((b,index)=>{
        const timeDisplay = b.time_range||getTimeRangeFromSlot(b.time_slot);
        return {'S.No':index+1,'Staff Name':htmlDecode(b.staff_name||''),'Staff Email':b.staff_email||'','Lab Name':htmlDecode(b.lab_name||''),'Booking Date':formatDate(b.booking_date),'Time Slot':b.time_slot,'Time Range':timeDisplay,'Purpose':htmlDecode(b.purpose||''),'Status':b.status.toUpperCase(),'Admin Remarks':b.admin_remarks||'','Has Conflict':b.has_conflict?'Yes':'No','Is Instant':b.is_instant?'Yes':'No','Created At':formatDateTime(b.created_at)};
    });
    const ws = XLSX.utils.json_to_sheet(excelData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Booking History');
    XLSX.writeFile(wb, `booking-history-${new Date().toISOString().split('T')[0]}.xlsx`);
}

// ============================================
// SUPPORT QUERIES
// ============================================
function loadSupportQueries() {
    const status = document.getElementById('supportStatusFilter')?.value||'all';
    const source = document.getElementById('supportSourceFilter')?.value||'all';
    const dateFrom = document.getElementById('supportDateFrom')?.value||'';
    const dateTo = document.getElementById('supportDateTo')?.value||'';
    const search = document.getElementById('supportSearch')?.value.trim()||'';
    let url = `api/support-requests.php?status=${status}&source=${source}&search=${encodeURIComponent(search)}`;
    if(dateFrom) url += `&date_from=${dateFrom}`;
    if(dateTo) url += `&date_to=${dateTo}`;
    fetch(url).then(r=>r.json()).then(data=>{
        const tbody = document.getElementById('supportTable');
        if(!tbody) return;
        if(!data.requests||data.requests.length===0){ tbody.innerHTML='<tr><td colspan="9" class="empty-state">No support requests found</td></tr>'; return; }
        tbody.innerHTML = data.requests.map(req=>{
            let sourceBadge = '';
            if(req.source==='login') sourceBadge='<span class="badge" style="background:#fef3c7;color:#92400e;">🔓 Login</span>';
            else if(req.source==='staff') sourceBadge='<span class="badge" style="background:#dbeafe;color:#1e40af;">👥 Staff</span>';
            else if(req.source==='head') sourceBadge='<span class="badge" style="background:#ede9fe;color:#6b21a8;">👑 Head</span>';
            else sourceBadge='<span class="badge">'+escapeHtml(req.source)+'</span>';
            return `<tr>
                <td>${req.id}</td>
                <td>${escapeHtml(req.name)}<br><small>${req.user_role?'('+req.user_role+')':''}</small></td>
                <td>${escapeHtml(req.email)}</td>
                <td>${sourceBadge}</td>
                <td>${escapeHtml(req.request_type)}</td>
                <td>${escapeHtml(req.message.substring(0,100))}${req.message.length>100?'...':''}</td>
                <td><span class="status-badge ${req.status==='pending'?'status-pending':'status-approved'}">${req.status}</span></td>
                <td>${formatDate(req.created_at)}</td>
                <td>${req.status==='pending'?`<button class="btn btn-sm btn-success" onclick="resolveSupport(${req.id})"><i class="fas fa-check"></i> Complete</button>`:'<i class="fas fa-check-circle" style="color:#059669;"></i> Resolved'}</td>
            </tr>`;
        }).join('');
    }).catch(err=>{ console.error(err); document.getElementById('supportTable').innerHTML='<tr><td colspan="9" class="empty-state">Failed to load</td></tr>'; });
}
function resetSupportFilters() {
    document.getElementById('supportStatusFilter').value='all';
    document.getElementById('supportSourceFilter').value='all';
    document.getElementById('supportDateFrom').value='';
    document.getElementById('supportDateTo').value='';
    document.getElementById('supportSearch').value='';
    loadSupportQueries();
}
function resolveSupport(id) {
    if(!confirm('Mark this request as completed? The user will receive an email notification.')) return;
    fetch('api/support-requests.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${id}&action=resolve`})
        .then(r=>r.json()).then(data=>{ if(data.success){ showToast('Request marked as completed. Email sent to user.','success'); loadSupportQueries(); } else { showToast('Error: '+(data.error||'Unknown'),'error'); } })
        .catch(err=>{ showToast('Network error','error'); });
}

// ============================================
// BULK UPLOAD MODAL CONTROLS
// ============================================
function showBulkUploadStaffModal() { document.getElementById('bulkUploadStaffModal').style.display = 'flex'; }
function showBulkUploadHeadsModal() { document.getElementById('bulkUploadHeadsModal').style.display = 'flex'; }
function showBulkUploadTimetableModal() { document.getElementById('bulkUploadTimetableModal').style.display = 'flex'; }
function closeBulkModal(type) {
    const modal = document.getElementById(`bulkUpload${type.charAt(0).toUpperCase()+type.slice(1)}Modal`);
    if(modal) modal.style.display = 'none';
    const resultDiv = document.getElementById(`bulkUpload${type.charAt(0).toUpperCase()+type.slice(1)}Result`);
    if(resultDiv) resultDiv.innerHTML = '';
}
function downloadStaffTemplate() {
    const csv = `name,email,password\nJohn Doe,john.doe@example.com,password123\nJane Smith,jane.smith@example.com,secure456`;
    const blob = new Blob([csv],{type:'text/csv'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'staff_template.csv'; a.click(); URL.revokeObjectURL(a.href);
}
function downloadHeadsTemplate() {
    const labNames = ['Lab 01 - M block - PG','Lab 02 - M block - UG','Lab 03 - M block - MM','Lab 04 - M block - CA','Lab 05 - SMS block - Lang','Lab 08 - P block - Data Analytics','Lab 07 - L block - Fitech','Lab 09 - P block - Bio Informatics','Lab 10 - C block - IT','Lab 11 - C block - IT','Lab 12 - E block - IT'];
    const csv = `name,email,password,labs\nDr. Alice Johnson,alice.johnson@example.com,alice123,"${labNames[0]}, ${labNames[1]}"\nProf. Bob Smith,bob.smith@example.com,bob456,"${labNames[2]}"`;
    const blob = new Blob([csv],{type:'text/csv'}); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'heads_template.csv'; a.click(); URL.revokeObjectURL(a.href);
}
function downloadTimetableTemplate() {
    const labNames = ['Lab 01 - M block - PG','Lab 02 - M block - UG','Lab 03 - M block - MM','Lab 04 - M block - CA','Lab 05 - SMS block - Lang','Lab 08 - P block - Data Analytics','Lab 07 - L block - Fitech','Lab 09 - P block - Bio Informatics','Lab 10 - C block - IT','Lab 11 - C block - IT','Lab 12 - E block - IT'];
    const csv = `lab,day_order,period,class_name,subject,faculty_name,head_email,semester\n${labNames[0]},1,1,II BSc CS,Data Structures,Dr. A. Kumar,head.cs@example.com,Even 2025\n${labNames[1]},2,2,I BSc Maths,Calculus,Prof. S. Sharma,head.maths@example.com,Odd 2025\n${labNames[3]},3,3,III BSc Physics,Quantum Mechanics,Dr. P. Gupta,head.physics@example.com,Even 2025`;
    const blob = new Blob([csv],{type:'text/csv'}); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'timetable_template.csv'; a.click(); URL.revokeObjectURL(a.href);
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function closeModal() { const modal = document.getElementById('modal'); if(modal) modal.classList.remove('active'); }
function showMessage(elementId,message,type) { const el=document.getElementById(elementId); if(!el) return; el.innerHTML=`<div class="alert alert-${type}"><i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i> ${message}</div>`; setTimeout(()=>el.innerHTML='',5000); }
function formatDate(dateStr) { if(!dateStr) return ''; try{ return new Date(dateStr).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}); }catch(e){ return dateStr; } }
function formatDateTime(dateStr) { if(!dateStr) return ''; try{ return new Date(dateStr).toLocaleString('en-US',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}); }catch(e){ return dateStr; } }
function escapeHtml(text) { if(!text) return ''; const div=document.createElement('div'); div.textContent=text; return div.innerHTML; }
function ucfirst(str) { if(!str) return ''; return str.charAt(0).toUpperCase()+str.slice(1); }
function showToast(message,type='success') { const toast=document.getElementById('toast'); const toastMessage=document.getElementById('toastMessage'); if(!toast||!toastMessage) return; toast.className='toast show'; toast.style.borderLeftColor=type==='success'?'#059669':type==='warning'?'#f59e0b':'#dc2626'; const icon=toast.querySelector('i'); if(icon){ icon.className=type==='success'?'fas fa-check-circle':type==='warning'?'fas fa-exclamation-triangle':'fas fa-exclamation-circle'; icon.style.color=type==='success'?'#059669':type==='warning'?'#f59e0b':'#dc2626'; } toastMessage.textContent=message; setTimeout(()=>{ toast.classList.remove('show'); },5000); }

function closeAdminHelpModal() { const modal = document.getElementById('adminHelpModal'); if(modal) modal.style.display = 'none'; }