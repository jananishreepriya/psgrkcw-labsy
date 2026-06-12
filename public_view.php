<?php
require_once 'config.php';

$date = $_GET['date'] ?? date('Y-m-d');
$labId = $_GET['lab'] ?? '';

$labs = fetchAll("SELECT * FROM labs WHERE status = 'active' ORDER BY lab_name");

// Get ALL approved AND pending bookings for the date (not just approved)
$bookingsQuery = "
    SELECT b.*, l.lab_name, u.name as staff_name 
    FROM bookings b 
    JOIN labs l ON b.lab_id = l.id 
    JOIN users u ON b.staff_id = u.id 
    WHERE b.booking_date = ? AND b.status IN ('approved', 'pending')
";
$params = [$date];
if ($labId) {
    $bookingsQuery .= " AND b.lab_id = ?";
    $params[] = $labId;
}
$bookingsQuery .= " ORDER BY l.lab_name, b.time_slot";
$bookings = fetchAll($bookingsQuery, $params);

// Get day order from academic calendar (proper sync)
$dayInfo = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ?", [$date]);
$dayOrder = null;
$dayOrderDisplay = "Not Set";

if ($dayInfo && !empty($dayInfo['day_order'])) {
    if (preg_match('/\d+/', $dayInfo['day_order'], $matches)) {
        $dayOrder = (int)$matches[0];
        $dayOrderDisplay = $dayInfo['day_order'];
    }
} else {
    // Auto-generate day order if not exists
    $lastDayOrder = 6;
    $prevDay = fetchOne("
        SELECT day_order FROM academic_calendar 
        WHERE calendar_date < ? 
        AND type = 'normal'
        ORDER BY calendar_date DESC LIMIT 1
    ", [$date]);
    
    if ($prevDay && !empty($prevDay['day_order'])) {
        if (preg_match('/\d+/', $prevDay['day_order'], $matches)) {
            $lastDayOrder = (int)$matches[0];
        }
    }
    
    $dayOfWeek = date('w', strtotime($date));
    if ($dayOfWeek == 0) {
        $dayOrder = $lastDayOrder;
        $calType = 'holiday';
    } else {
        $dayOrder = ($lastDayOrder % 6) + 1;
        $calType = 'normal';
    }
    
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    query("INSERT INTO academic_calendar (calendar_date, day_name, day_order, type) VALUES (?, ?, ?, ?)",
        [$date, $dayNames[$dayOfWeek], "Day {$dayOrder}", $calType]);
    
    $dayOrderDisplay = "Day {$dayOrder}";
}

// Fetch timetable for the determined day order (1-6)
$timetable = [];
if ($dayOrder) {
    $timetable = fetchAll("
        SELECT t.*, l.lab_name 
        FROM timetable t 
        JOIN labs l ON t.lab_id = l.id 
        WHERE t.day_order = ? AND t.is_active = TRUE
        " . ($labId ? " AND t.lab_id = ?" : "") . "
        ORDER BY l.lab_name, t.start_time
    ", $labId ? [$dayOrder, $labId] : [$dayOrder]);
}

$blockInfo = fetchOne("SELECT * FROM academic_calendar WHERE calendar_date = ? AND type != 'normal'", [$date]);

// Check if user is logged in (for booking)
$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'staff';
$userId = $_SESSION['user_id'] ?? null;

// Define class timings as per your college schedule
function getClassTimings() {
    return [
        ['start' => '08:10', 'end' => '09:00', 'label' => '8:10 AM - 9:00 AM (Period 1)'],
        ['start' => '09:00', 'end' => '09:50', 'label' => '9:00 AM - 9:50 AM (Period 2)'],
        ['start' => '10:10', 'end' => '11:00', 'label' => '10:10 AM - 11:00 AM (Period 3)'],
        ['start' => '11:00', 'end' => '11:50', 'label' => '11:00 AM - 11:50 AM (Period 4)'],
        ['start' => '11:50', 'end' => '12:40', 'label' => '11:50 AM - 12:40 PM (Period 5)'],
        ['start' => '12:50', 'end' => '13:40', 'label' => '12:50 PM - 1:40 PM (Period 6)'],
        ['start' => '13:40', 'end' => '14:30', 'label' => '1:40 PM - 2:30 PM (Period 7)'],
        ['start' => '14:30', 'end' => '15:20', 'label' => '2:30 PM - 3:20 PM (Period 8)'],
        ['start' => '15:40', 'end' => '16:30', 'label' => '3:40 PM - 4:30 PM (Period 9)'],
        ['start' => '16:30', 'end' => '17:20', 'label' => '4:30 PM - 5:20 PM (Period 10)'],
    ];
}

// Check if a time slot has a class
function hasClassInSlot($labId, $startTime, $endTime, $timetable) {
    foreach ($timetable as $class) {
        if ($class['lab_id'] != $labId) continue;
        
        $classStart = substr($class['start_time'], 0, 5);
        $classEnd = substr($class['end_time'], 0, 5);
        
        if ($classStart <= $startTime && $classEnd >= $endTime) {
            return $class;
        }
        if (!($endTime <= $classStart || $startTime >= $classEnd)) {
            return $class;
        }
    }
    return false;
}

// Check if a time slot is booked
function isSlotBooked($labId, $startTime, $endTime, $bookings) {
    foreach ($bookings as $booking) {
        if ($booking['lab_id'] != $labId) continue;
        
        $bookingStart = '';
        $bookingEnd = '';
        $slot = $booking['time_slot'];
        
        if ($slot == 'FN') {
            $bookingStart = '08:10';
            $bookingEnd = '12:40';
        } elseif ($slot == 'AN') {
            $bookingStart = '12:50';
            $bookingEnd = '17:20';
        } elseif ($slot == 'Full Day') {
            $bookingStart = '08:10';
            $bookingEnd = '17:20';
        } elseif (strpos($slot, 'Period') === 0) {
            $periodTimes = [
                1 => ['start' => '08:10', 'end' => '09:00'],
                2 => ['start' => '09:00', 'end' => '09:50'],
                3 => ['start' => '10:10', 'end' => '11:00'],
                4 => ['start' => '11:00', 'end' => '11:50'],
                5 => ['start' => '11:50', 'end' => '12:40'],
                6 => ['start' => '12:50', 'end' => '13:40'],
                7 => ['start' => '13:40', 'end' => '14:30'],
                8 => ['start' => '14:30', 'end' => '15:20'],
                9 => ['start' => '15:40', 'end' => '16:30'],
                10 => ['start' => '16:30', 'end' => '17:20']
            ];
            $periodNum = intval(str_replace('Period ', '', $slot));
            if (isset($periodTimes[$periodNum])) {
                $bookingStart = $periodTimes[$periodNum]['start'];
                $bookingEnd = $periodTimes[$periodNum]['end'];
            }
        }
        
        if ($bookingStart && $bookingEnd) {
            if ($startTime >= $bookingStart && $endTime <= $bookingEnd) {
                return $booking;
            }
        }
    }
    return false;
}

// Get the status of a time slot
function getSlotStatus($labId, $startTime, $endTime, $timetable, $bookings) {
    $class = hasClassInSlot($labId, $startTime, $endTime, $timetable);
    if ($class) {
        return ['status' => 'class', 'data' => $class];
    }
    
    $booking = isSlotBooked($labId, $startTime, $endTime, $bookings);
    if ($booking) {
        return ['status' => 'booked', 'data' => $booking];
    }
    
    return ['status' => 'free', 'data' => null];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Schedule - PSGRKCW</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; color: #1f2937; }
        .header { background: linear-gradient(135deg, #6b21a8 0%, #4c1d95 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: white; text-decoration: none; margin-top: 15px; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 20px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px; }
        .filters { background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; display: flex; gap: 20px; flex-wrap: wrap; align-items: end; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        input, select { width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px; }
        .btn { padding: 12px 24px; background: #6b21a8; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(107,33,168,0.3); }
        .btn-book { background: #059669; }
        .btn-book:hover { background: #047857; }
        .btn-print { background: #4b5563; }
        .alert { padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #059669; }
        .info-box { background: #dbeafe; color: #1e40af; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3b82f6; display: flex; align-items: center; gap: 10px; }
        .schedule-grid { display: grid; gap: 20px; }
        .lab-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .lab-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f3f4f6; }
        .lab-title { font-size: 20px; color: #6b21a8; font-weight: 700; }
        .time-slots { display: grid; gap: 8px; }
        .slot { padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; }
        .slot.class { background: #fee2e2; border-left: 4px solid #dc2626; }
        .slot.booked { background: #dbeafe; border-left: 4px solid #2563eb; }
        .slot.free { background: #d1fae5; border-left: 4px solid #059669; cursor: pointer; }
        .slot.free:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(5, 150, 105, 0.3); }
        .slot-time { font-weight: 600; font-size: 15px; }
        .slot-info { color: #6b7280; font-size: 13px; margin-top: 4px; }
        .slot-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-class { background: #fecaca; color: #991b1b; }
        .badge-booked { background: #bfdbfe; color: #1e40af; }
        .badge-free { background: #a7f3d0; color: #065f46; }
        .legend { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; background: white; padding: 15px; border-radius: 8px; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .legend-color { width: 20px; height: 20px; border-radius: 4px; }
        .staff-note { text-align: center; margin-top: 30px; padding: 20px; background: white; border-radius: 12px; }
        .staff-note a { color: #6b21a8; text-decoration: none; font-weight: 600; }
        
        .day-order-badge { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            background: linear-gradient(135deg, #4f46e5, #7c3aed); 
            color: white; 
            padding: 8px 20px; 
            border-radius: 25px; 
            font-weight: 600; 
            margin-left: 15px;
        }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .close-modal { background: none; border: none; font-size: 24px; color: #6b7280; cursor: pointer; }
        .booking-form .form-group { margin-bottom: 20px; }
        .booking-form label { display: block; margin-bottom: 8px; font-weight: 500; color: #374151; }
        .booking-form input, .booking-form textarea { width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
        .booking-form textarea { min-height: 100px; resize: vertical; }
        .booking-form input:focus, .booking-form textarea:focus { outline: none; border-color: #6b21a8; }
        .booking-actions { display: flex; gap: 15px; margin-top: 25px; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        .login-prompt { text-align: center; padding: 20px; background: #fef3c7; border-radius: 8px; margin: 20px 0; }
        .login-prompt a { color: #6b21a8; font-weight: 600; text-decoration: none; }
        @media print { .filters, .staff-note, .back-link, .slot.free { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-flask"></i> PSGRKCW Lab Schedule</h1>
        <p>View Lab Availability - Click Green Slots to Book Instantly</p>
        <a href="staff.html" class="back-link"><i class="fas fa-arrow-left"></i> Back to Staff Portal</a>
    </div>
    
    <div class="container">
        <?php if ($blockInfo): ?>
            <div class="alert alert-danger">
                <i class="fas fa-ban"></i> 
                <strong><?php echo ucfirst($blockInfo['type']); ?>:</strong> 
                <?php echo htmlspecialchars($blockInfo['block_reason'] ?? 'No bookings allowed'); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <i class="fas fa-info-circle fa-2x"></i>
            <div>
                <strong>🟢 Green slots are available for instant booking!</strong><br>
                <small>Click on any green time slot to book immediately. Red slots have classes, Blue slots are already booked.</small>
            </div>
        </div>
        
        <form class="filters" method="GET">
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Date</label>
                <input type="date" name="date" value="<?php echo $date; ?>" min="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
            </div>
            <div class="form-group">
                <label><i class="fas fa-door-open"></i> Lab</label>
                <select name="lab" onchange="this.form.submit()">
                    <option value="">All Labs</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?php echo $lab['id']; ?>" <?php echo $labId == $lab['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lab['lab_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn"><i class="fas fa-search"></i> View</button>
            <button type="button" class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        </form>
        
        <div class="legend">
            <div class="legend-item"><div class="legend-color" style="background: #fee2e2;"></div><span><i class="fas fa-graduation-cap"></i> Class Session</span></div>
            <div class="legend-item"><div class="legend-color" style="background: #dbeafe;"></div><span><i class="fas fa-user-check"></i> Already Booked</span></div>
            <div class="legend-item"><div class="legend-color" style="background: #d1fae5;"></div><span><i class="fas fa-check-circle"></i> Available (Click to Book)</span></div>
        </div>
        
        <h2 style="margin-bottom: 20px;">
            <i class="fas fa-calendar-day"></i> 
            <?php echo date('l, d F Y', strtotime($date)); ?>
            <span class="day-order-badge">
                <i class="fas fa-sync"></i> <?php echo htmlspecialchars($dayOrderDisplay); ?>
            </span>
        </h2>
        
        <div class="schedule-grid">
            <?php 
            $displayLabs = $labId ? array_filter($labs, fn($l) => $l['id'] == $labId) : $labs;
            $classTimings = getClassTimings();
            
            foreach ($displayLabs as $lab): 
                $labTimetable = array_filter($timetable, fn($t) => $t['lab_id'] == $lab['id']);
                $labBookings = array_filter($bookings, fn($b) => $b['lab_id'] == $lab['id']);
            ?>
                <div class="lab-card">
                    <div class="lab-header">
                        <span class="lab-title"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($lab['lab_name']); ?></span>
                        <span style="color: #6b7280; font-size: 14px;"><i class="fas fa-users"></i> Capacity: <?php echo $lab['capacity']; ?></span>
                    </div>
                    <div class="time-slots">
                        <?php foreach ($classTimings as $timing): 
                            $slotStatus = getSlotStatus($lab['id'], $timing['start'], $timing['end'], $labTimetable, $labBookings);
                        ?>
                            <div class="slot <?php echo $slotStatus['status']; ?>" 
                                 <?php if ($slotStatus['status'] === 'free' && $isLoggedIn): ?> 
                                     onclick="openBookingModal(<?php echo $lab['id']; ?>, '<?php echo $date; ?>', '<?php echo $timing['start']; ?>', '<?php echo $timing['end']; ?>', '<?php echo htmlspecialchars($lab['lab_name']); ?>', '<?php echo $timing['label']; ?>')"
                                 <?php endif; ?>>
                                
                                <div>
                                    <div class="slot-time"><i class="fas fa-clock"></i> <?php echo $timing['label']; ?></div>
                                    <div class="slot-info">
                                        <?php if ($slotStatus['status'] === 'class'): ?>
                                            <i class="fas fa-graduation-cap"></i> 
                                            <strong>Class:</strong> <?php echo htmlspecialchars($slotStatus['data']['class_name']); ?>
                                            <?php if (!empty($slotStatus['data']['faculty_name'])): ?>
                                                <br><small>Faculty: <?php echo htmlspecialchars($slotStatus['data']['faculty_name']); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($slotStatus['data']['subject'])): ?>
                                                <br><small>Subject: <?php echo htmlspecialchars($slotStatus['data']['subject']); ?></small>
                                            <?php endif; ?>
                                        
                                        <?php elseif ($slotStatus['status'] === 'booked'): ?>
                                            <i class="fas fa-user"></i> 
                                            <strong>Booked by:</strong> <?php echo htmlspecialchars($slotStatus['data']['staff_name']); ?>
                                            <br><small>Purpose: <?php echo htmlspecialchars($slotStatus['data']['purpose']); ?></small>
                                        
                                        <?php else: ?>
                                            <i class="fas fa-check-circle"></i> 
                                            <strong>Available for instant booking</strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <span class="slot-badge badge-<?php echo $slotStatus['status']; ?>">
                                    <?php 
                                        if ($slotStatus['status'] === 'class') echo 'CLASS';
                                        elseif ($slotStatus['status'] === 'booked') echo 'BOOKED';
                                        else echo 'FREE';
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!$isLoggedIn): ?>
        <div class="login-prompt">
            <i class="fas fa-info-circle"></i> 
            <strong>Want to book a free slot?</strong> 
            <a href="login.html">Login as Staff</a> to book instantly.
        </div>
        <?php endif; ?>
        
        <div class="staff-note">
            <p><i class="fas fa-info-circle"></i> Need to request a slot during class time? <a href="staff.html"><i class="fas fa-arrow-right"></i> Go to Staff Portal for approval-based booking</a></p>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus"></i> Instant Booking</h3>
                <button class="close-modal" onclick="closeBookingModal()">&times;</button>
            </div>
            <form id="instantBookingForm" class="booking-form">
                <input type="hidden" name="lab_id" id="modalLabId">
                <input type="hidden" name="booking_date" id="modalDate">
                <input type="hidden" name="start_time" id="modalStartTime">
                <input type="hidden" name="end_time" id="modalEndTime">
                <input type="hidden" name="period_number" id="modalPeriodNumber">
                
                <div class="form-group">
                    <label><i class="fas fa-flask"></i> Laboratory</label>
                    <input type="text" id="modalLabName" readonly class="readonly-input">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Date</label>
                    <input type="text" id="modalDateDisplay" readonly class="readonly-input">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Time Slot</label>
                    <input type="text" id="modalTimeDisplay" readonly class="readonly-input">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Purpose <span style="color:#dc2626">*</span></label>
                    <textarea name="purpose" id="modalPurpose" required placeholder="Enter purpose for booking..."></textarea>
                </div>
                
                <div class="booking-actions">
                    <button type="submit" class="btn btn-book" style="flex:2"><i class="fas fa-check"></i> Confirm Booking</button>
                    <button type="button" class="btn btn-secondary" style="flex:1" onclick="closeBookingModal()">Cancel</button>
                </div>
            </form>
            <div id="bookingMessage" style="margin-top: 15px;"></div>
        </div>
    </div>

    <script>
        function openBookingModal(labId, date, startTime, endTime, labName, timeLabel) {
            // Extract period number from timeLabel (e.g., "8:10 AM - 9:00 AM (Period 1)")
            let periodNumber = 0;
            const periodMatch = timeLabel.match(/Period (\d+)/);
            if (periodMatch) {
                periodNumber = parseInt(periodMatch[1]);
            }
            
            document.getElementById('modalLabId').value = labId;
            document.getElementById('modalDate').value = date;
            document.getElementById('modalStartTime').value = startTime;
            document.getElementById('modalEndTime').value = endTime;
            document.getElementById('modalPeriodNumber').value = periodNumber;
            document.getElementById('modalLabName').value = labName;
            
            const dateObj = new Date(date);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
            });
            document.getElementById('modalDateDisplay').value = formattedDate;
            document.getElementById('modalTimeDisplay').value = timeLabel;
            
            document.getElementById('bookingModal').classList.add('active');
        }
        
        function closeBookingModal() {
            document.getElementById('bookingModal').classList.remove('active');
            document.getElementById('modalPurpose').value = '';
            document.getElementById('bookingMessage').innerHTML = '';
        }
        
        document.getElementById('instantBookingForm').onsubmit = function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const msgDiv = document.getElementById('bookingMessage');
            
            if (!formData.get('purpose').trim()) {
                msgDiv.innerHTML = '<div class="alert alert-danger">Please enter a purpose</div>';
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            submitBtn.disabled = true;
            
            fetch('api/instant-booking.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msgDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    setTimeout(() => {
                        closeBookingModal();
                        location.reload();
                    }, 2000);
                } else {
                    msgDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + data.error + '</div>';
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(err => {
                console.error('Error:', err);
                msgDiv.innerHTML = '<div class="alert alert-danger">Network error occurred</div>';
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        };
        
        window.onclick = function(e) {
            const modal = document.getElementById('bookingModal');
            if (e.target === modal) {
                closeBookingModal();
            }
        };
    </script>
</body>
</html>