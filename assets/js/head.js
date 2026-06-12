let currentUser = null;
let currentHistoryData = [];
let currentLabs = [];
let currentHistoryPage = 1;

window.onload = function() {
    checkAuth();
    setupNavigation();
    loadDashboard();
};

function checkAuth() {
    fetch('api/check-session.php')
        .then(r => r.json())
        .then(data => {
            if (!data.logged_in || data.role !== 'head') {
                window.location.href = 'login.html';
                return;
            }
            currentUser = data;
            document.getElementById('userName').textContent = data.name;
            document.getElementById('welcomeName').textContent = data.name;
        })
        .catch(err => window.location.href = 'login.html');
}

function setupNavigation() {
    document.querySelectorAll('.nav-links a[data-page]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            showPage(page);
            document.querySelectorAll('.nav-links a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

function showPage(page) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(page + '-page').classList.add('active');
    if (page === 'dashboard') loadDashboard();
    if (page === 'pending') loadPendingBookings();
    if (page === 'history') {
        loadLabsForFilter().then(() => loadHistory(1));
    }
    if (page === 'support-status') loadMySupportRequests();
}

function loadLabsForFilter() {
    return fetch('api/head-labs.php')
        .then(r => r.json())
        .then(data => {
            currentLabs = data.labs || [];
            const labFilter = document.getElementById('labFilter');
            if (labFilter) {
                labFilter.innerHTML = '<option value="">All My Labs</option>' +
                    currentLabs.map(l => `<option value="${l.id}">${escapeHtml(l.lab_name)}</option>`).join('');
            }
        })
        .catch(err => console.error('Failed to load labs for filter:', err));
}

function loadDashboard() {
    const now = new Date();
    document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    fetch('api/head-stats.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('statLabs').textContent = data.labs || 0;
            document.getElementById('statPending').textContent = data.pending || 0;
            document.getElementById('statApproved').textContent = data.approved || 0;
        });

    fetch('api/head-bookings.php?filter=pending&limit=5')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('recentPending');
            if (!data.bookings || !data.bookings.length) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No pending bookings</h3></div>';
                return;
            }
            container.innerHTML = data.bookings.map(b => createBookingHTML(b)).join('');
        });
}

function loadPendingBookings() {
    const container = document.getElementById('pendingList');
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    fetch('api/head-bookings.php?filter=pending')
        .then(r => r.json())
        .then(data => {
            if (!data.bookings || !data.bookings.length) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No pending bookings</h3></div>';
                return;
            }
            container.innerHTML = data.bookings.map(b => createBookingHTML(b)).join('');
        });
}

function loadHistory(page = 1) {
    currentHistoryPage = page;
    const filter = document.getElementById('historyFilter')?.value || 'all';
    const labId = document.getElementById('labFilter')?.value || '';
    const search = document.getElementById('historySearch')?.value.trim() || '';
    const container = document.getElementById('historyList');
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    let url = `api/head-bookings.php?filter=${filter}&page=${page}&limit=20`;
    if (labId) url += `&lab_id=${labId}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            currentHistoryData = data.bookings || [];
            if (!data.bookings || !data.bookings.length) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No bookings found</h3></div>';
                document.getElementById('historyPagination').innerHTML = '';
                return;
            }
            container.innerHTML = data.bookings.map(b => createBookingHTML(b)).join('');
            displayPagination(data.pagination);
        })
        .catch(err => {
            container.innerHTML = '<div class="empty-state">Failed to load</div>';
        });
}

function displayPagination(pagination) {
    const container = document.getElementById('historyPagination');
    if (!container) return;
    if (!pagination || pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }
    let html = '';
    html += `<button onclick="loadHistory(${pagination.page - 1})" ${pagination.page <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= pagination.pages; i++) {
        if (i === pagination.page || (i >= pagination.page - 2 && i <= pagination.page + 2)) {
            html += `<button onclick="loadHistory(${i})" class="${i === pagination.page ? 'active' : ''}">${i}</button>`;
        } else if (i === pagination.page - 3 || i === pagination.page + 3) {
            html += `<span>...</span>`;
        }
    }
    html += `<button onclick="loadHistory(${pagination.page + 1})" ${pagination.page >= pagination.pages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
    container.innerHTML = html;
}

function createBookingHTML(b) {
    const timeRange = b.time_range || getTimeRangeFromSlot(b.time_slot);
    const statusClass = b.status === 'pending' ? 'status-pending' : (b.status === 'approved' ? 'status-approved' : 'status-rejected');
    const instantBadge = b.is_instant ? '<span style="background:#8b5cf6;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;">⚡ Instant</span>' : '';
    const headApprovedBadge = b.head_approved ? '<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;"><i class="fas fa-check-circle"></i> Head Approved</span>' : '';
    
    let actionButtons = '';
    if (b.status === 'pending' && !b.head_approved) {
        actionButtons = `
            <div style="margin-top:10px; display:flex; gap:8px;">
                <button class="btn btn-sm btn-success" onclick="approveBooking(${b.id})" style="flex:1;">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button class="btn btn-sm btn-danger" onclick="rejectBooking(${b.id})" style="flex:1;">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
        `;
    }
    
    return `
        <div class="booking-item ${b.status}">
            <div class="booking-info">
                <h4>${escapeHtml(b.lab_name)} ${instantBadge} ${headApprovedBadge}</h4>
                <div class="booking-meta">
                    <span><i class="fas fa-user"></i> ${escapeHtml(b.staff_name)}</span>
                    <span><i class="fas fa-calendar"></i> ${formatDate(b.booking_date)}</span>
                    <span><i class="fas fa-clock"></i> ${escapeHtml(timeRange)}</span>
                </div>
                <div><i class="fas fa-tag"></i> ${escapeHtml(b.purpose)}</div>
                ${b.conflict_reason ? `<div style="color:#f59e0b; font-size:12px;"><i class="fas fa-exclamation-triangle"></i> ${escapeHtml(b.conflict_reason)}</div>` : ''}
                ${actionButtons}
            </div>
            <span class="status-badge ${statusClass}">${b.status}</span>
        </div>
    `;
}

function getTimeRangeFromSlot(slot) {
    const map = {
        'FN': '8:10 AM – 12:40 PM',
        'AN': '12:50 PM – 5:20 PM',
        'Full Day': '8:10 AM – 5:20 PM'
    };
    if (map[slot]) return map[slot];
    if (slot.startsWith('Period')) return slot;
    return slot;
}

function formatDate(dateStr) {
    try {
        return new Date(dateStr).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
    } catch { return dateStr; }
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    try {
        return new Date(dateStr).toLocaleString('en-US', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
    } catch { return dateStr; }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function approveBooking(bookingId) {
    if (!confirm('Approve this booking? This will notify admin that you have approved it.')) return;
    fetch('api/head-approve.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + bookingId + '&action=approve'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Booking approved! Admin can now finalize.', 'success');
            loadPendingBookings();
            loadDashboard();
            if (document.getElementById('history-page').classList.contains('active')) {
                loadHistory(currentHistoryPage);
            }
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(err => showToast('Network error', 'error'));
}

function rejectBooking(bookingId) {
    const reason = prompt('Please enter the reason for rejection:');
    if (reason === null) return;
    if (!reason.trim()) {
        alert('Rejection reason cannot be empty.');
        return;
    }
    if (!confirm('Reject this booking? The staff member will be notified.')) return;
    fetch('api/head-approve.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${bookingId}&action=reject&remarks=${encodeURIComponent(reason)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Booking rejected! Staff notified.', 'success');
            loadPendingBookings();
            loadDashboard();
            if (document.getElementById('history-page').classList.contains('active')) {
                loadHistory(currentHistoryPage);
            }
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(err => showToast('Network error', 'error'));
}

function exportToPDF() {
    if (!currentHistoryData.length) { alert('No data to export.'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4');
    
    doc.setFontSize(18);
    doc.setTextColor(107,33,168);
    doc.text('PSGRKCW LABSY - Head Booking History', 14, 20);
    
    const tableData = currentHistoryData.map((b,i) => {
        const time = b.time_range || getTimeRangeFromSlot(b.time_slot);
        const mark = b.is_instant ? '⚡ ' : '';
        return [
            i+1,
            mark + (b.staff_name||''),
            b.lab_name||'',
            formatDate(b.booking_date),
            time,
            (b.purpose||'').substring(0,40),
            b.status.toUpperCase()
        ];
    });
    
    doc.autoTable({
        head: [['S.No','Staff Name','Lab Name','Date','Time','Purpose','Status']],
        body: tableData,
        startY: 30,
        styles: { fontSize:9, cellPadding:2 },
        headStyles: { fillColor: [107,33,168], textColor:255 }
    });
    doc.save(`head-booking-history-${new Date().toISOString().split('T')[0]}.pdf`);
}

function exportToExcel() {
    if (!currentHistoryData.length) { alert('No data to export.'); return; }
    const excelData = currentHistoryData.map((b,i) => {
        const time = b.time_range || getTimeRangeFromSlot(b.time_slot);
        return {
            'S.No': i+1,
            'Staff Name': b.staff_name,
            'Staff Email': b.staff_email || '',
            'Lab Name': b.lab_name,
            'Booking Date': formatDate(b.booking_date),
            'Time Slot': b.time_slot,
            'Time Range': time,
            'Purpose': b.purpose || '',
            'Status': b.status.toUpperCase(),
            'Has Conflict': b.has_conflict ? 'Yes' : 'No',
            'Is Instant': b.is_instant ? 'Yes' : 'No',
            'Head Approved': b.head_approved ? 'Yes' : 'No',
            'Created At': b.created_at ? new Date(b.created_at).toLocaleString() : ''
        };
    });
    const ws = XLSX.utils.json_to_sheet(excelData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Head Booking History');
    XLSX.writeFile(wb, `head-booking-history-${new Date().toISOString().split('T')[0]}.xlsx`);
}

function showToast(message, type = 'success') {
    let toast = document.getElementById('toast');
    if (!toast) {
        document.body.insertAdjacentHTML('beforeend', `
            <div id="toast" style="display:none; position:fixed; top:20px; right:20px; padding:15px 25px; border-radius:8px; color:white; font-weight:600; z-index:9999;"></div>
        `);
        toast = document.getElementById('toast');
    }
    toast.textContent = message;
    toast.style.background = type === 'success' ? '#059669' : (type === 'warning' ? '#f59e0b' : '#dc2626');
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}

// ============================================
// MY SUPPORT REQUESTS
// ============================================
function loadMySupportRequests() {
    const container = document.getElementById('myRequestsList');
    if (!container) return;
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('api/my-support-requests.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.requests || data.requests.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No support requests found</h3><p>Click "Report Issue" to submit a query.</p></div>';
                return;
            }
            let html = '<div class="requests-list">';
            data.requests.forEach(req => {
                const statusClass = req.status === 'pending' ? 'status-pending' : 'status-approved';
                const statusText = req.status === 'pending' ? 'Pending' : 'Resolved';
                const resolvedInfo = req.status === 'resolved' && req.resolved_at ? `<div class="resolved-date"><i class="fas fa-check-circle"></i> Resolved on ${formatDateTime(req.resolved_at)}</div>` : '';
                html += `
                    <div class="request-item ${req.status}">
                        <div class="request-header">
                            <span class="request-type"><i class="fas fa-tag"></i> ${escapeHtml(req.request_type)}</span>
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </div>
                        <div class="request-message">${escapeHtml(req.message)}</div>
                        <div class="request-meta">
                            <span><i class="fas fa-calendar-alt"></i> Submitted: ${formatDateTime(req.created_at)}</span>
                            ${resolvedInfo}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="empty-state">Failed to load requests</div>';
        });
}