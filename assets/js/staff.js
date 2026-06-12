let currentUser = null;
let currentAvailabilityCheck = null;
let currentHistoryData = [];

window.onload = function() {
    checkAuth();
    setupNavigation();
    loadDashboard();
};

function checkAuth() {
    fetch('api/check-session.php')
        .then(r => r.json())
        .then(data => {
            if (!data.logged_in || data.role !== 'staff') {
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
    if (page === 'timetable') loadTimetableView();
    if (page === 'book') loadBookingForm();
    if (page === 'history') loadHistory();
    if (page === 'support-status') loadMySupportRequests();
}

function loadDashboard() {
    const now = new Date();
    const dateDisplay = document.getElementById('currentDate');
    if (dateDisplay) {
        dateDisplay.textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    const today = now.toISOString().split('T')[0];
    
    fetch('api/get-blocks.php?date=' + today)
        .then(r => r.json())
        .then(data => {
            const noticesDiv = document.getElementById('importantNotices');
            if (!noticesDiv) return;
            if (data.blocked && data.info) {
                noticesDiv.innerHTML = `<div class="alert alert-warning" style="background:#fef3c7; border-left:4px solid #f59e0b; padding:15px; border-radius:8px; margin-bottom:20px;"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i><strong>Notice:</strong> Today is <strong>${ucfirst(data.info.type)}</strong> - ${escapeHtml(data.info.block_reason || 'No bookings allowed')}</div>`;
            } else {
                noticesDiv.innerHTML = '';
            }
        }).catch(err => console.error('Failed to load blocks:', err));
    
    fetch('api/day-info.php?date=' + today)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const todayDate = document.getElementById('todayDate');
                const todayOrder = document.getElementById('todayOrder');
                const todayType = document.getElementById('todayType');
                const todayCard = document.getElementById('todayCard');
                if (todayDate) todayDate.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
                if (todayOrder) todayOrder.innerHTML = `<i class="fas fa-sync"></i> ${data.day_order}`;
                if (todayType) todayType.textContent = data.type.toUpperCase();
                if (todayCard) todayCard.className = 'today-card ' + data.type;
            }
        }).catch(err => console.error('Failed to load day info:', err));
    
    loadRecentBookings();
}

function loadRecentBookings() {
    const container = document.getElementById('recentBookings');
    if (!container) return;
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    fetch('api/staff-bookings.php?limit=5')
        .then(r => r.json())
        .then(data => {
            if (!data.bookings || data.bookings.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No bookings yet</h3></div>';
                return;
            }
            container.innerHTML = data.bookings.map(b => createBookingHTML(b)).join('');
        })
        .catch(err => container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Failed to load</h3></div>');
}

function createBookingHTML(booking) {
    const sessionDisplay = { 'FN': 'FN (8:10 AM - 12:40 PM)', 'AN': 'AN (12:50 PM - 5:20 PM)', 'Full Day': 'Full Day (8:10 AM - 5:20 PM)' };
    const statusClass = booking.status === 'pending' ? 'status-pending' : (booking.status === 'approved' ? 'status-approved' : 'status-rejected');
    const instantBadge = booking.is_instant ? '<span style="background:#8b5cf6;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;">⚡ INSTANT</span>' : '';
    const periodBadge = booking.time_slot && booking.time_slot.startsWith('Period') ? '<span style="background:#06b6d4;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;">📚 ' + escapeHtml(booking.time_slot) + '</span>' : '';
    const conflictBadge = booking.has_conflict ? '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;" title="' + escapeHtml(booking.conflict_reason || '') + '"><i class="fas fa-exclamation-triangle"></i> CONFLICT</span>' : '';
    const timeDisplay = booking.time_range || sessionDisplay[booking.time_slot] || booking.time_slot;
    return `<div class="booking-item ${booking.status}" style="border-left: 4px solid ${booking.is_instant ? '#8b5cf6' : (booking.has_conflict ? '#f59e0b' : '#e5e7eb')};"><div class="booking-info"><h4>${escapeHtml(booking.lab_name)} ${instantBadge} ${periodBadge} ${conflictBadge}</h4><div class="booking-meta"><span><i class="fas fa-calendar"></i> ${formatDate(booking.booking_date)}</span><span><i class="fas fa-clock"></i> ${escapeHtml(timeDisplay)}</span></div>${booking.admin_remarks ? `<div style="margin-top:8px; font-size:13px; color:#92400e;"><i class="fas fa-comment"></i> ${escapeHtml(booking.admin_remarks)}</div>` : ''}${booking.has_conflict && booking.status === 'pending' ? `<div style="margin-top:8px; font-size:13px; color:#92400e;"><i class="fas fa-clock"></i> Waiting for admin approval</div>` : ''}</div><span class="status-badge ${statusClass}">${booking.status}</span></div>`;
}

function loadTimetableView() {
    const select = document.getElementById('viewLabFilter');
    if (!select) return;
    select.innerHTML = '<option value="">Loading...</option>';
    fetch('api/labs.php')
        .then(r => r.json())
        .then(data => {
            if (!data.labs || data.labs.length === 0) { select.innerHTML = '<option value="">No labs</option>'; return; }
            select.innerHTML = '<option value="">Select Lab</option>' + data.labs.map(l => `<option value="${l.id}">${escapeHtml(l.lab_name)}</option>`).join('');
        }).catch(err => select.innerHTML = '<option value="">Error</option>');
    const today = new Date().toISOString().split('T')[0];
    fetch(`api/day-info.php?date=${today}`)
        .then(r => r.json())
        .then(data => {
            const viewDayFilter = document.getElementById('viewDayFilter');
            if (viewDayFilter && data.success && data.day_order_num) viewDayFilter.value = data.day_order_num;
        }).catch(() => { const viewDayFilter = document.getElementById('viewDayFilter'); if (viewDayFilter) viewDayFilter.value = 1; });
}

let currentTimetableData = { labId: null, day: null };

function loadLabSchedule() {
    const labId = document.getElementById('viewLabFilter')?.value;
    const dayOrderNum = document.getElementById('viewDayFilter')?.value;
    const container = document.getElementById('scheduleContainer');
    if (!container || !labId || !dayOrderNum) return;
    currentTimetableData = { labId, day: dayOrderNum };
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    Promise.all([
        fetch(`api/timetable.php?lab_id=${labId}&day_order=${dayOrderNum}`).then(r => r.json()),
        fetch(`api/labs.php`).then(r => r.json()),
        fetch(`api/staff-bookings.php?filter=all`).then(r => r.json())
    ]).then(([timetableData, labsData, bookingsData]) => {
        const lab = labsData.labs?.find(l => l.id == labId);
        const selectedDate = new Date().toISOString().split('T')[0];
        const bookedPeriods = {};
        if (bookingsData.bookings) {
            bookingsData.bookings.forEach(b => {
                if (b.lab_id == labId && b.booking_date === selectedDate && b.status !== 'rejected') {
                    if (b.time_slot.startsWith('Period')) bookedPeriods[b.time_slot] = b;
                    else if (b.time_slot === 'FN') for (let i=1;i<=5;i++) bookedPeriods['Period '+i]=b;
                    else if (b.time_slot === 'AN') for (let i=6;i<=10;i++) bookedPeriods['Period '+i]=b;
                    else if (b.time_slot === 'Full Day') for (let i=1;i<=10;i++) bookedPeriods['Period '+i]=b;
                }
            });
        }
        const classPeriods = {};
        if (timetableData.timetable) {
            timetableData.timetable.forEach(t => {
                const start = t.start_time.substring(0,5);
                const periodMap = {'08:10':1,'09:00':2,'10:10':3,'11:00':4,'11:50':5,'12:50':6,'13:40':7,'14:30':8,'15:40':9,'16:30':10};
                if (periodMap[start]) classPeriods[periodMap[start]] = t;
            });
        }
        let html = `<div style="background:white; padding:20px; border-radius:12px;"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;"><h4 style="color:#6b21a8; margin:0;">${escapeHtml(lab?.lab_name || 'Lab')} - Day ${dayOrderNum}</h4><span style="background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white; padding:5px 15px; border-radius:20px; font-size:12px;"><i class="fas fa-sync"></i> Academic Day Order ${dayOrderNum}</span></div>`;
        const periodTimes = [{period:1,start:'08:10',end:'09:00'},{period:2,start:'09:00',end:'09:50'},{period:3,start:'10:10',end:'11:00'},{period:4,start:'11:00',end:'11:50'},{period:5,start:'11:50',end:'12:40'},{period:6,start:'12:50',end:'13:40'},{period:7,start:'13:40',end:'14:30'},{period:8,start:'14:30',end:'15:20'},{period:9,start:'15:40',end:'16:30'},{period:10,start:'16:30',end:'17:20'}];
        html += '<h5 style="margin-top:20px;">Forenoon</h5>';
        for (let p=1;p<=5;p++) html += renderPeriodBlock(labId, selectedDate, p, periodTimes[p-1], classPeriods[p], bookedPeriods['Period '+p]);
        html += '<h5 style="margin-top:20px;">Afternoon</h5>';
        for (let p=6;p<=10;p++) html += renderPeriodBlock(labId, selectedDate, p, periodTimes[p-1], classPeriods[p], bookedPeriods['Period '+p]);
        html += '</div>';
        container.innerHTML = html;
    }).catch(err => container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Failed to load</h3></div>');
}

function renderPeriodBlock(labId, date, periodNum, timeObj, bookedClass, bookedSlot) {
    const timeRange = `${timeObj.start} - ${timeObj.end}`;
    let content = '', bgColor = '#f0fdf4';
    if (bookedClass) {
        bgColor = '#fee2e2';
        content = `<div style="background:#fee2e2; padding:10px; border-radius:6px;"><strong>${escapeHtml(bookedClass.class_name)}</strong><br><small>${escapeHtml(bookedClass.subject || '')} - ${escapeHtml(bookedClass.faculty_name || '')}</small></div>`;
    } else if (bookedSlot) {
        bgColor = '#dbeafe';
        const isMine = bookedSlot.staff_id === currentUser?.id;
        content = `<div style="background:#dbeafe; padding:10px; border-radius:6px; text-align:center;"><strong><i class="fas fa-check-circle"></i> ${isMine ? 'Your Booking' : 'Booked'}</strong><br><small>${bookedSlot.is_instant ? '⚡ Instant' : 'Pending'}</small></div>`;
    } else {
        content = `<button class="btn btn-sm btn-success" style="width:100%; background:linear-gradient(135deg,#059669,#047857); border:none;" onclick="openInstantBookingModal(${periodNum}, ${labId}, '${date}', '${timeObj.start}', '${timeObj.end}')">⚡ Book Period ${periodNum}</button>`;
    }
    return `<div style="background:${bgColor}; border:1px solid #e5e7eb; border-radius:8px; padding:15px; margin-bottom:10px;"><div style="font-weight:600; margin-bottom:8px; color:#374151;">Period ${periodNum}: ${timeRange}</div>${content}</div>`;
}

function openInstantBookingModal(periodNumber, labId, date, startTime, endTime) {
    let modal = document.getElementById('instantBookingModal');
    if (!modal) {
        const modalHTML = `<div id="instantBookingModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);"><div style="background:white; margin:10% auto; padding:30px; border-radius:12px; width:90%; max-width:500px;"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;"><h3 style="color:#6b21a8; margin:0;"><i class="fas fa-bolt"></i> Instant Booking</h3><button onclick="closeInstantModal()" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button></div><form id="instantBookingForm"><input type="hidden" id="instLabId" name="lab_id"><input type="hidden" id="instDate" name="booking_date"><input type="hidden" id="instPeriod" name="period_number"><div style="margin-bottom:15px;"><label style="display:block; margin-bottom:5px; font-weight:600;">Laboratory</label><input type="text" id="instLabName" readonly style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; background:#f3f4f6;"></div><div style="margin-bottom:15px;"><label style="display:block; margin-bottom:5px; font-weight:600;">Date</label><input type="text" id="instDateDisplay" readonly style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; background:#f3f4f6;"></div><div style="margin-bottom:15px;"><label style="display:block; margin-bottom:5px; font-weight:600;">Time</label><input type="text" id="instTimeDisplay" readonly style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; background:#f3f4f6;"></div><div style="margin-bottom:20px;"><label style="display:block; margin-bottom:5px; font-weight:600;">Purpose *</label><textarea id="instPurpose" name="purpose" required rows="3" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;"></textarea></div><div id="instError" style="margin-bottom:15px; color:#dc2626;"></div><div style="display:flex; gap:10px;"><button type="submit" id="instSubmitBtn" style="flex:1; background:#059669; color:white; border:none; padding:12px; border-radius:6px; font-weight:600; cursor:pointer;">Confirm</button><button type="button" onclick="closeInstantModal()" style="flex:1; background:#e5e7eb; border:none; padding:12px; border-radius:6px; font-weight:600; cursor:pointer;">Cancel</button></div></form></div></div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        modal = document.getElementById('instantBookingModal');
        document.getElementById('instantBookingForm').addEventListener('submit', submitInstantBooking);
    }
    document.getElementById('instLabId').value = labId;
    document.getElementById('instDate').value = date;
    document.getElementById('instPeriod').value = periodNumber;
    fetch('api/labs.php').then(r=>r.json()).then(data=>{ const lab=data.labs?.find(l=>l.id==labId); document.getElementById('instLabName').value=lab?lab.lab_name:'Lab '+labId; });
    document.getElementById('instDateDisplay').value = formatDate(date);
    document.getElementById('instTimeDisplay').value = `${startTime} - ${endTime} (Period ${periodNumber})`;
    document.getElementById('instPurpose').value = '';
    document.getElementById('instError').textContent = '';
    modal.style.display = 'block';
}

function closeInstantModal() { const modal = document.getElementById('instantBookingModal'); if(modal) modal.style.display = 'none'; }

function submitInstantBooking(e) {
    e.preventDefault();
    const form = document.getElementById('instantBookingForm');
    const errorDiv = document.getElementById('instError');
    const submitBtn = document.getElementById('instSubmitBtn');
    const purpose = document.getElementById('instPurpose').value.trim();
    if (!purpose) { errorDiv.textContent = 'Please enter a purpose'; return; }
    submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'; errorDiv.textContent = '';
    const formData = new FormData(form);
    fetch('api/instant-booking.php', { method:'POST', body:formData })
        .then(async response => { const text = await response.text(); try { return JSON.parse(text); } catch(e) { throw new Error('Server error'); } })
        .then(data => {
            submitBtn.disabled = false; submitBtn.innerHTML = 'Confirm';
            if (data.success) {
                closeInstantModal();
                if (data.requires_approval) showToast('⚡ Request sent to admin for approval!', 'warning');
                else showToast('⚡ Period ' + data.period + ' booked!', 'success');
                if (currentTimetableData.labId) loadLabSchedule();
                loadRecentBookings();
            } else { errorDiv.textContent = data.error || 'Booking failed'; }
        }).catch(err => { submitBtn.disabled = false; submitBtn.innerHTML = 'Confirm'; errorDiv.textContent = 'Network error'; });
}

function loadBookingForm() {
    const dateInput = document.getElementById('bookingDate');
    if (dateInput) { const today = new Date().toISOString().split('T')[0]; dateInput.min = today; dateInput.value = today; updateDayInfo(); }
    fetch('api/labs.php').then(r=>r.json()).then(data=>{ const labSelect=document.getElementById('labSelect'); if(labSelect) labSelect.innerHTML='<option value="">-- Choose Lab --</option>'+data.labs.map(l=>`<option value="${l.id}">${escapeHtml(l.lab_name)}</option>`).join(''); });
    const form = document.getElementById('bookingForm');
    if (form) form.onsubmit = function(e) { e.preventDefault(); submitRegularBooking(); };
}

function updateDayInfo() {
    const dateInput = document.getElementById('bookingDate'); const dayInfoDiv = document.getElementById('dayInfo');
    if (!dateInput || !dayInfoDiv || !dateInput.value) { dayInfoDiv.innerHTML = ''; return; }
    const date = dateInput.value;
    fetch(`api/day-info.php?date=${date}`).then(r=>r.json()).then(data=>{
        if (data.success) {
            let typeColor = data.type === 'normal' ? '#4f46e5' : '#f59e0b', typeBg = data.type === 'normal' ? '#e0e7ff' : '#fef3c7';
            let html = `<div style="padding:12px; background:${typeBg}; border-radius:6px; margin-top:10px; border-left:4px solid ${typeColor};"><div style="display:flex; justify-content:space-between; align-items:center;"><div><strong><i class="fas fa-calendar-day"></i> ${data.day_name}</strong><span style="color:${typeColor}; font-weight:600; margin-left:10px; padding:2px 10px; background:white; border-radius:15px; font-size:14px; border:2px solid ${typeColor};">📅 ${data.day_order}</span>${data.auto_generated ? '<span style="font-size:11px; color:#666; margin-left:5px;">(auto)</span>' : ''}</div>${data.type !== 'normal' ? '<span style="color:#92400e; font-size:12px; text-transform:uppercase; font-weight:600;">' + data.type + '</span>' : ''}</div>${data.block_reason ? `<div style="margin-top:5px; font-size:12px; color:#666;"><i class="fas fa-info-circle"></i> ${data.block_reason}</div>` : ''}</div>`;
            dayInfoDiv.innerHTML = html;
            const labId = document.getElementById('labSelect')?.value; const slot = document.querySelector('input[name="time_slot"]:checked')?.value;
            if (labId && slot) checkLabAvailability();
        }
    }).catch(err => dayInfoDiv.innerHTML = '');
}

function checkLabAvailability() {
    const labId = document.getElementById('labSelect')?.value, date = document.getElementById('bookingDate')?.value, slot = document.querySelector('input[name="time_slot"]:checked')?.value;
    if (!labId || !date || !slot) return;
    const warningDiv = document.getElementById('slotAvailabilityWarning');
    warningDiv.style.display = 'block';
    warningDiv.innerHTML = '<div style="padding:10px; background:#f3f4f6; border-radius:6px;"><i class="fas fa-spinner fa-spin"></i> Checking availability...</div>';
    fetch(`api/check-availability.php?lab_id=${labId}&date=${date}&slot=${encodeURIComponent(slot)}`)
        .then(r=>r.json()).then(data=>{
            currentAvailabilityCheck = data;
            if (data.error) { warningDiv.innerHTML = `<div style="padding:15px; background:#fee2e2; border-left:4px solid #dc2626; border-radius:6px; color:#dc2626;"><i class="fas fa-exclamation-circle"></i> ${data.error}</div>`; return; }
            let html = '';
            if (data.day_order_num) html += `<div style="margin-bottom:10px; padding:10px 15px; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white; border-radius:8px; font-size:14px;"><i class="fas fa-sync"></i> <strong>Academic Day Order: ${data.day_order}</strong> <span style="opacity:0.9; font-size:12px; margin-left:10px;">(Timetable Cycle Day ${data.day_order_num} of 6)</span></div>`;
            if (data.has_instant_booking && data.instant_bookings.length>0) {
                html += `<div style="padding:15px; background:#ede9fe; border-left:4px solid #8b5cf6; border-radius:6px; margin-bottom:10px;"><div style="display:flex; align-items:center; margin-bottom:10px;"><i class="fas fa-bolt" style="color:#8b5cf6; margin-right:8px;"></i><strong style="color:#6d28d9; font-size:16px;">⚡ Instant Bookings</strong><span style="margin-left:10px; background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:12px; font-size:11px;">Admin can override</span></div><div style="display:flex; flex-direction:column; gap:8px;">`;
                data.instant_bookings.forEach(b=>{
                    const statusColor = b.status === 'approved' ? '#059669' : '#f59e0b', statusBg = b.status === 'approved' ? '#d1fae5' : '#fef3c7';
                    html += `<div style="background:white; padding:12px; border-radius:8px; border:2px solid #8b5cf6;"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;"><strong style="color:#1f2937;">${escapeHtml(b.staff_name)}</strong><span style="background:${statusBg}; color:${statusColor}; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; text-transform:uppercase;">${b.status}</span></div><div style="font-size:13px; color:#6b7280; margin-bottom:5px;"><i class="fas fa-tag"></i> ${escapeHtml(b.purpose)}</div><div style="font-size:12px; color:#8b5cf6; font-weight:600;"><i class="fas fa-clock"></i> ${escapeHtml(b.time_slot)} | <i class="fas fa-envelope"></i> ${escapeHtml(b.staff_email)}</div></div>`;
                });
                html += `</div></div>`;
            }
            if (data.has_regular_booking && data.regular_bookings.length>0) {
                html += `<div style="padding:15px; background:#dbeafe; border-left:4px solid #3b82f6; border-radius:6px; margin-bottom:10px;"><div style="display:flex; align-items:center; margin-bottom:10px;"><i class="fas fa-calendar-check" style="color:#3b82f6; margin-right:8px;"></i><strong style="color:#1e40af; font-size:16px;">📋 Regular Bookings</strong><span style="margin-left:10px; background:#fee2e2; color:#dc2626; padding:3px 10px; border-radius:12px; font-size:11px;">Blocks approval</span></div><div style="display:flex; flex-direction:column; gap:8px;">`;
                data.regular_bookings.forEach(b=>{
                    const statusColor = b.status === 'approved' ? '#059669' : '#f59e0b', statusBg = b.status === 'approved' ? '#d1fae5' : '#fef3c7';
                    html += `<div style="background:white; padding:12px; border-radius:8px; border:1px solid #3b82f6;"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;"><strong style="color:#1f2937;">${escapeHtml(b.staff_name)}</strong><span style="background:${statusBg}; color:${statusColor}; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; text-transform:uppercase;">${b.status}</span></div><div style="font-size:13px; color:#6b7280; margin-bottom:5px;"><i class="fas fa-tag"></i> ${escapeHtml(b.purpose)}</div><div style="font-size:12px; color:#3b82f6;"><i class="fas fa-clock"></i> ${escapeHtml(b.time_slot)}</div></div>`;
                });
                html += `</div></div>`;
            }
            if (data.has_timetable_clash && data.conflicts) {
                const timetableConflicts = data.conflicts.filter(c=>c.type==='timetable');
                if (timetableConflicts.length>0) {
                    html += `<div style="padding:15px; background:#fef3c7; border-left:4px solid #f59e0b; border-radius:6px; margin-bottom:10px;"><div style="display:flex; align-items:center; margin-bottom:10px;"><i class="fas fa-exclamation-triangle" style="color:#f59e0b; margin-right:8px;"></i><strong style="color:#92400e; font-size:16px;">📚 Timetable Conflicts (Scheduled Classes)</strong></div><div style="display:flex; flex-direction:column; gap:8px;">`;
                    timetableConflicts.forEach(c=>{
                        html += `<div style="background:white; padding:12px; border-radius:8px; border:1px solid #f59e0b;"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;"><strong style="color:#1f2937;">${escapeHtml(c.class_name)}</strong><span style="background:#fee2e2; color:#dc2626; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">CONFLICT</span></div><div style="font-size:13px; color:#6b7280; margin-bottom:3px;"><i class="fas fa-book"></i> ${escapeHtml(c.subject || 'No subject')}</div><div style="font-size:13px; color:#6b7280; margin-bottom:3px;"><i class="fas fa-user"></i> ${escapeHtml(c.faculty || 'No faculty')}</div><div style="font-size:12px; color:#92400e; font-weight:600;"><i class="fas fa-clock"></i> ${c.start_time.substring(0,5)} - ${c.end_time.substring(0,5)}</div></div>`;
                    });
                    html += `</div></div>`;
                }
            }
            if (data.conflicts.some(c=>c.type==='blocked')) {
                const blockConflict = data.conflicts.find(c=>c.type==='blocked');
                html += `<div style="padding:15px; background:#fee2e2; border-left:4px solid #dc2626; border-radius:6px; color:#dc2626;"><i class="fas fa-ban"></i> <strong>Date Blocked:</strong> ${blockConflict.message}</div>`;
            } else if (data.has_pending_request) {
                html += `<div style="padding:15px; background:#fef3c7; border-left:4px solid #f59e0b; border-radius:6px; color:#92400e;"><i class="fas fa-lock"></i> <strong>Slot Locked:</strong> ${data.pending_request_by} has requested admin approval. Please wait.</div>`;
            } else if (data.has_regular_booking) {
                html += `<div style="padding:15px; background:#fee2e2; border-left:4px solid #dc2626; border-radius:6px; color:#dc2626;"><i class="fas fa-times-circle"></i> <strong>Not Available</strong><br><span style="font-size:14px;">This slot has a regular booking (see above). Cannot request approval.</span></div>`;
            } else if (data.can_request_approval) {
                const conflictType = data.has_timetable_clash && data.has_instant_booking ? 'timetable conflicts and instant booking' : (data.has_timetable_clash ? 'timetable conflicts' : 'instant booking');
                html += `<div style="padding:15px; background:#fef3c7; border-left:4px solid #f59e0b; border-radius:6px; color:#92400e;"><div style="display:flex; align-items:center; margin-bottom:10px;"><i class="fas fa-exclamation-triangle" style="font-size:24px; margin-right:10px;"></i><div><strong>Schedule Conflict Detected</strong><br><span style="font-size:14px;">This slot has ${conflictType}.</span></div></div><p style="margin:10px 0; font-size:13px;">${data.has_instant_booking ? '⚡ Admin can cancel the instant booking and approve your request. ' : ''}Class heads will be notified if approved.</p><button type="button" onclick="requestAdminApproval()" style="background:#f59e0b; color:white; border:none; padding:12px 24px; border-radius:6px; cursor:pointer; font-weight:600; width:100%; margin-top:10px;"><i class="fas fa-paper-plane"></i> Request Head and Admin Approval</button></div>`;
            } else if (data.is_available) {
                html += `<div style="padding:15px; background:#d1fae5; border-left:4px solid #059669; border-radius:6px; color:#065f46;"><i class="fas fa-check-circle"></i> <strong>Available!</strong><br><span style="font-size:14px;">No timetable conflicts. This slot is free for booking.</span></div>`;
            } else {
                html += `<div style="padding:15px; background:#fee2e2; border-left:4px solid #dc2626; border-radius:6px; color:#dc2626;"><i class="fas fa-times-circle"></i> <strong>Not Available</strong><br><span style="font-size:14px;">This slot cannot be booked.</span></div>`;
            }
            warningDiv.innerHTML = html;
        }).catch(err => { console.error('Availability check failed:', err); warningDiv.innerHTML = `<div style="padding:15px; background:#fee2e2; border-left:4px solid #dc2626; border-radius:6px; color:#dc2626;">Error checking availability</div>`; });
}

function checkSlotAvailability() { checkLabAvailability(); }

function requestAdminApproval() {
    const labId = document.getElementById('labSelect')?.value, date = document.getElementById('bookingDate')?.value, slot = document.querySelector('input[name="time_slot"]:checked')?.value, purpose = document.querySelector('textarea[name="purpose"]')?.value, msgDiv = document.getElementById('bookMessage');
    if (!labId || !date || !slot) { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;">Please select lab, date, and time slot first</div>'; return; }
    if (!purpose || !purpose.trim()) { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;">Please enter a purpose for the booking</div>'; return; }
    msgDiv.innerHTML = '<div style="color:#1e40af; padding:10px;"><i class="fas fa-spinner fa-spin"></i> Submitting request...</div>';
    const formData = new FormData(); formData.append('lab_id', labId); formData.append('booking_date', date); formData.append('time_slot', slot); formData.append('purpose', purpose);
    fetch('api/request-approval.php', { method:'POST', body:formData }).then(r=>r.json()).then(data=>{
        if (data.success) {
            msgDiv.innerHTML = `<div style="color:#059669; padding:20px; text-align:center; background:#d1fae5; border-radius:8px;"><i class="fas fa-check-circle" style="font-size:48px;"></i><h3>Request Submitted!</h3><p>Admin will review your request. Class heads will be automatically notified if approved.</p><p>Booking ID: #${data.booking_id}</p></div>`;
            document.getElementById('bookingForm').reset(); setTimeout(()=>{ showPage('history'); loadHistory(); },3000);
        } else { msgDiv.innerHTML = `<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;"><i class="fas fa-exclamation-circle"></i> ${data.error}</div>`; }
    }).catch(err => msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;">Network error. Please try again.</div>');
}

function submitRegularBooking() {
    const form = document.getElementById('bookingForm'), msgDiv = document.getElementById('bookMessage'), submitBtn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form), labId = formData.get('lab_id'), bookingDate = formData.get('booking_date'), timeSlot = formData.get('time_slot'), purpose = formData.get('purpose');
    if (!labId) { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;"><i class="fas fa-exclamation-circle"></i> Please select a lab</div>'; return; }
    if (!bookingDate) { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;"><i class="fas fa-exclamation-circle"></i> Please select a date</div>'; return; }
    if (!timeSlot) { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;"><i class="fas fa-exclamation-circle"></i> Please select a session (FN, AN, or Full Day)</div>'; return; }
    if (!purpose || !purpose.trim()) { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;"><i class="fas fa-exclamation-circle"></i> Please enter a purpose</div>'; return; }
    if (currentAvailabilityCheck) {
        if (currentAvailabilityCheck.has_regular_booking) { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;">This slot has a regular booking. Cannot submit.</div>'; return; }
        if (currentAvailabilityCheck.has_pending_request) { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;">Another staff has already requested this slot</div>'; return; }
        if (currentAvailabilityCheck.has_conflict || currentAvailabilityCheck.has_instant_booking) { msgDiv.innerHTML = '<div style="color:#92400e; padding:10px; background:#fef3c7; border-radius:6px;">This slot has conflicts. Please click "Request Admin Approval" button above.</div>'; return; }
    }
    submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...'; msgDiv.innerHTML = '<div style="color:#1e40af;"><i class="fas fa-spinner fa-spin"></i> Submitting...</div>';
    fetch('api/create-booking.php', { method:'POST', body:formData }).then(r=>r.json()).then(data=>{
        submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Booking';
        if (data.success) {
            msgDiv.innerHTML = '<div style="color:#059669; padding:20px; text-align:center; background:#d1fae5; border-radius:8px;"><i class="fas fa-check-circle" style="font-size:48px;"></i><h3>Submitted!</h3><p>Admin will review your request.</p></div>';
            form.reset(); const dateInput = document.getElementById('bookingDate'); if (dateInput) { dateInput.value = new Date().toISOString().split('T')[0]; updateDayInfo(); }
            document.getElementById('slotAvailabilityWarning').style.display = 'none'; setTimeout(()=>{ showPage('history'); loadHistory(); },2000);
        } else { msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;"><i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed to submit booking') + '</div>'; }
    }).catch(err => { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Booking'; msgDiv.innerHTML = '<div style="color:#dc2626; padding:10px; background:#fee2e2; border-radius:6px;"><i class="fas fa-exclamation-circle"></i> Network error. Please try again.</div>'; console.error('Booking error:', err); });
}

function loadHistory() {
    const filter = document.getElementById('historyFilter')?.value || 'all';
    const container = document.getElementById('historyList');
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    fetch('api/staff-bookings.php?filter=' + filter).then(r=>r.json()).then(data=>{
        currentHistoryData = data.bookings || [];
        if (!data.bookings || data.bookings.length === 0) { container.innerHTML = '<div class="empty-state">No bookings found</div>'; return; }
        container.innerHTML = data.bookings.map(b => createBookingHTML(b)).join('');
    }).catch(err => container.innerHTML = '<div class="empty-state">Failed to load</div>');
}

function exportToPDF() {
    if (currentHistoryData.length === 0) { alert('No data to export.'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4');
    doc.setFontSize(18); doc.setTextColor(107,33,168); doc.text('PSGRKCW LABSY - My Booking History', 14, 20);
    const tableData = currentHistoryData.map((b,index)=>{ const timeDisplay = b.time_range || getTimeRangeFromSlot(b.time_slot); const instantMark = b.is_instant ? '⚡ ' : ''; return [index+1, instantMark+(b.lab_name||''), formatDate(b.booking_date), timeDisplay, (b.purpose||'').substring(0,40), b.status.toUpperCase()]; });
    doc.autoTable({ head:[['S.No','Lab Name','Date','Time','Purpose','Status']], body:tableData, startY:30, styles:{ fontSize:9, cellPadding:2 }, headStyles:{ fillColor:[107,33,168], textColor:255 } });
    doc.save(`my-bookings-${new Date().toISOString().split('T')[0]}.pdf`);
}

function exportToExcel() {
    if (currentHistoryData.length === 0) { alert('No data to export.'); return; }
    const excelData = currentHistoryData.map((b,index)=>{ const timeDisplay = b.time_range || getTimeRangeFromSlot(b.time_slot); return { 'S.No':index+1, 'Lab Name':b.lab_name, 'Booking Date':formatDate(b.booking_date), 'Time Slot':b.time_slot, 'Time Range':timeDisplay, 'Purpose':b.purpose||'', 'Status':b.status.toUpperCase(), 'Admin Remarks':b.admin_remarks||'', 'Has Conflict':b.has_conflict?'Yes':'No', 'Is Instant':b.is_instant?'Yes':'No', 'Created At':b.created_at?new Date(b.created_at).toLocaleString():'' }; });
    const ws = XLSX.utils.json_to_sheet(excelData); const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'My Bookings'); XLSX.writeFile(wb, `my-bookings-${new Date().toISOString().split('T')[0]}.xlsx`);
}

function getTimeRangeFromSlot(slot) { const map = { 'FN':'8:10 AM – 12:40 PM', 'AN':'12:50 PM – 5:20 PM', 'Full Day':'8:10 AM – 5:20 PM' }; if(map[slot]) return map[slot]; if(slot.startsWith('Period')) return slot.replace('Period','Period '); return slot; }

function loadMySupportRequests() {
    const container = document.getElementById('myRequestsList');
    if (!container) return;
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    fetch('api/my-support-requests.php').then(r=>r.json()).then(data=>{
        if (!data.success || !data.requests || data.requests.length === 0) { container.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No support requests found</h3><p>Click "Report Issue" to submit a query.</p></div>'; return; }
        let html = '<div class="requests-list">';
        data.requests.forEach(req => {
            const statusClass = req.status === 'pending' ? 'status-pending' : 'status-approved';
            const statusText = req.status === 'pending' ? 'Pending' : 'Resolved';
            const resolvedInfo = req.status === 'resolved' && req.resolved_at ? `<div class="resolved-date"><i class="fas fa-check-circle"></i> Resolved on ${formatDateTime(req.resolved_at)}</div>` : '';
            html += `<div class="request-item ${req.status}"><div class="request-header"><span class="request-type"><i class="fas fa-tag"></i> ${escapeHtml(req.request_type)}</span><span class="status-badge ${statusClass}">${statusText}</span></div><div class="request-message">${escapeHtml(req.message)}</div><div class="request-meta"><span><i class="fas fa-calendar-alt"></i> Submitted: ${formatDateTime(req.created_at)}</span>${resolvedInfo}</div></div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }).catch(err => container.innerHTML = '<div class="empty-state">Failed to load requests</div>');
}

function formatDate(dateStr) { if(!dateStr) return ''; try{ return new Date(dateStr).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}); }catch(e){ return dateStr; } }
function formatDateTime(dateStr) { if(!dateStr) return ''; try{ return new Date(dateStr).toLocaleString('en-US',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}); }catch(e){ return dateStr; } }
function escapeHtml(text) { if(!text) return ''; const div=document.createElement('div'); div.textContent=text; return div.innerHTML; }
function ucfirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }
function showToast(message, type='success') { let toast=document.getElementById('toast'); if(!toast){ document.body.insertAdjacentHTML('beforeend','<div id="toast" style="display:none; position:fixed; top:20px; right:20px; padding:15px 25px; border-radius:8px; color:white; font-weight:600; z-index:9999;"></div>'); toast=document.getElementById('toast'); } toast.textContent=message; toast.style.background=type==='success'?'#059669':(type==='warning'?'#f59e0b':'#dc2626'); toast.style.display='block'; setTimeout(()=>toast.style.display='none',3000); }