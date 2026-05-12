<?php
// Mock Data for Upcoming Meetings
$upcomingMeetings = [
    ["title" => "Meeting 1", "officers" => 43, "managers" => 2, "venue" => "Disneyland", "date" => "02 May 2087", "time" => "12:30"],
    ["title" => "Meeting 2", "officers" => 43, "managers" => 2, "venue" => "Disneyland", "date" => "02 May 2087", "time" => "14:30"],
    ["title" => "Meeting 1", "officers" => 43, "managers" => 2, "venue" => "Disneyland", "date" => "02 May 2087", "time" => "12:30"]
];

// Mock Data for Ended Meetings
$endedMeetings = [
    ["title" => "Meeting 1", "officers" => 43, "managers" => 2, "venue" => "Disneyland", "date" => "02 May 2087", "time" => "12:30"],
    ["title" => "Meeting 2", "officers" => 43, "managers" => 2, "venue" => "Disneyland", "date" => "02 May 2087", "time" => "14:30"],
    ["title" => "Meeting 2", "officers" => 43, "managers" => 2, "venue" => "Disneyland", "date" => "02 May 2087", "time" => "14:30"]
];

// Days that have dots under them in the calendar
$eventDays = [3, 12, 14, 16, 18, 21, 23, 25, 30];
$userName = "User";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <style>
        /* Base Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #ffffff; color: #333; overflow-x: hidden; }

        /* --- Header & Wave Design --- */
        .header-container {
            position: relative;
            width: 100%;
            height: 120px; /* Height of the dark blue area */
            background-color: #11072b;
            color: white;
            padding: 40px 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        /* The smooth bottom wave using SVG */
        .header-wave {
            position: absolute;
            top: 80%; /* Positions the wave exactly below the header */
            left: 0;
            width: 100%;
            height: 100px;
            z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 100' preserveAspectRatio='none'%3E%3Cpath d='M0,0 L1000,0 L1000,30 C750,10 250,120 0,80 Z' fill='%2311072b'/%3E%3C/svg%3E");
            background-size: 100% 100%;
        }

        .header-container h1 {
            font-size: 2.5rem;
            font-weight: 400;
            z-index: 2;
        }
        
        .logout-btn {
            background-color: white;
            color: #11062b;
            padding: 10px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            z-index: 2;
        }

        /* --- Main Layout --- */
        .main-content {
            display: flex;
            padding: 50px 60px;
            gap: 40px;
            margin-top: 50px;
        }

        .left-panel { flex: 2; }
        
        .divider { width: 1px; background-color: #d1d1d1; margin: 0 10px; }
        
        .right-panel { flex: 1; display: flex; justify-content: center; align-items: flex-start; }

        /* --- Meeting Cards --- */
        .section-title { font-size: 1.4rem; margin-bottom: 20px; margin-top: 10px; color: #000; }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .meeting-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            gap: 5px;
            border-top: 5px solid #11062b;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .meeting-card:hover {
            transform: translateY(-5px);
        }

        .meeting-card h3 { font-size: 1.2rem; margin-bottom: 8px; color: #000; }
        .meeting-card p { font-size: 0.8rem; color: #444; line-height: 1.4; }

        /* --- Calendar --- */
        .calendar-container {
            background-color: #3f517e;
            width: 100%;
            max-width: 380px;
            border-radius: 5px;
            padding: 25px 30px;
            color: white;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
            margin: auto;
        }

        .calendar-header { font-size: 2rem; font-weight: bold; margin-bottom: 30px; }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px 5px;
            text-align: center;
        }

        .day-name { color: #8fa2c9; font-size: 0.8rem; font-weight: bold; margin-bottom: 10px; }

        .calendar-day {
            position: relative;
            font-size: 1rem;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
            cursor: pointer;
        }

        .calendar-day.dimmed { color: rgba(255, 255, 255, 0.4); }

        .calendar-day.active {
            background-color: #627bff;
            border-radius: 50%;
            font-weight: bold;
        }

        /* The small dot indicator under dates */
        .calendar-day.has-event::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            background-color: #a4b5d6;
            border-radius: 50%;
        }

        /* Active day has a white dot */
        .calendar-day.active.has-event::after { background-color: white; }

        /* --- Footer --- */
        .footer { padding: 0 60px 40px 60px; }
        .footer a { color: #11062b; font-size: 1rem; text-decoration: underline; }

        /* --- Modal Styles --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            position: relative;
        }

        .close-modal { position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 1.5rem; color: #333; }
        .close-modal:hover { color: #d9534f; }

        .modal-content h2 { margin-bottom: 20px; color: #11072b; }
        
        .modal-meeting-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .modal-actions { display: flex; gap: 15px; justify-content: space-between; }
        .modal-actions button { flex: 1; padding: 12px; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; color: white; transition: 0.3s; }
        .btn-attended { background-color: #5cf25c; color: #000 !important; font-weight: bold; }
        .btn-attended:hover { background-color: #4ade4a; }
        .btn-not-attended { background-color: #d9534f; }
        .btn-not-attended:hover { background-color: #c9302c; }

    </style>
</head>
<body>

    <header class="header-container">
        <h1>Hello, {<?php echo $userName; ?>}</h1>
        <a href="login.php" class="logout-btn">Logout</a>
        <div class="header-wave"></div>
    </header>

    <main class="main-content">
        
        <section class="left-panel">
            
            <h2 class="section-title">Upcoming</h2>
            <div class="cards-grid">
                <?php foreach ($upcomingMeetings as $meeting): ?>
                    <div class="meeting-card" onclick="openMeetingModal('<?php echo htmlspecialchars($meeting['title'], ENT_QUOTES); ?>', '<?php echo $meeting['officers']; ?>', '<?php echo $meeting['managers']; ?>', '<?php echo htmlspecialchars($meeting['venue'], ENT_QUOTES); ?>', '<?php echo $meeting['date']; ?>', '<?php echo $meeting['time']; ?>')">
                        <h3><?php echo $meeting['title']; ?></h3>
                        <p><strong>Officers:</strong> <?php echo $meeting['officers']; ?></p>
                        <p><strong>Managers:</strong> <?php echo $meeting['managers']; ?></p>
                        <p><strong>Venue:</strong> <?php echo $meeting['venue']; ?></p>
                        <p><strong>Date:</strong> <?php echo $meeting['date']; ?></p>
                        <p><strong>Time:</strong> <?php echo $meeting['time']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2 class="section-title">Ended</h2>
            <div class="cards-grid">
                <?php foreach ($endedMeetings as $meeting): ?>
                    <div class="meeting-card" onclick="openMeetingModal('<?php echo htmlspecialchars($meeting['title'], ENT_QUOTES); ?>', '<?php echo $meeting['officers']; ?>', '<?php echo $meeting['managers']; ?>', '<?php echo htmlspecialchars($meeting['venue'], ENT_QUOTES); ?>', '<?php echo $meeting['date']; ?>', '<?php echo $meeting['time']; ?>')">
                        <h3><?php echo $meeting['title']; ?></h3>
                        <p><strong>Officers:</strong> <?php echo $meeting['officers']; ?></p>
                        <p><strong>Managers:</strong> <?php echo $meeting['managers']; ?></p>
                        <p><strong>Venue:</strong> <?php echo $meeting['venue']; ?></p>
                        <p><strong>Date:</strong> <?php echo $meeting['date']; ?></p>
                        <p><strong>Time:</strong> <?php echo $meeting['time']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

        </section>

        <div class="divider"></div>

        <section class="right-panel">
            <div class="calendar-container">
                <div class="calendar-header">March</div>
                <div class="calendar-grid">
                    <div class="day-name">M</div><div class="day-name">T</div>
                    <div class="day-name">W</div><div class="day-name">T</div>
                    <div class="day-name">F</div><div class="day-name">S</div><div class="day-name">S</div>

                    <div class="calendar-day dimmed">28</div>

                    <?php for ($i = 1; $i <= 31; $i++): 
                        $classes = "calendar-day";
                        if ($i == 16) $classes .= " active"; // Highlight the 16th
                        if (in_array($i, $eventDays)) $classes .= " has-event"; // Add dot
                    ?>
                        <div class="<?php echo $classes; ?>"><?php echo $i; ?></div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

    </main>

    <footer class="footer">
    </footer>

    <!-- Meeting Details Modal -->
    <div id="meetingDetailsModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeMeetingDetailsModal">&times;</span>
            <h2 id="modalMeetingTitle">Meeting Details</h2>
            <div id="modalMeetingInfo" class="modal-meeting-info">
                <p><strong>Officers:</strong> <span id="modalOfficers"></span></p>
                <p><strong>Managers:</strong> <span id="modalManagers"></span></p>
                <p><strong>Venue:</strong> <span id="modalVenue"></span></p>
                <p><strong>Date:</strong> <span id="modalDate"></span></p>
                <p><strong>Time:</strong> <span id="modalTime"></span></p>
            </div>
            <div class="modal-actions">
                <button class="btn-attended">Attended</button>
                <button class="btn-not-attended">Not Attended</button>
            </div>
        </div>
    </div>

    <script>
        const meetingModal = document.getElementById('meetingDetailsModal');
        const closeModalBtn = document.getElementById('closeMeetingDetailsModal');

        function openMeetingModal(title, officers, managers, venue, date, time) {
            document.getElementById('modalMeetingTitle').innerText = title;
            document.getElementById('modalOfficers').innerText = officers;
            document.getElementById('modalManagers').innerText = managers;
            document.getElementById('modalVenue').innerText = venue;
            document.getElementById('modalDate').innerText = date;
            document.getElementById('modalTime').innerText = time;
            meetingModal.style.display = 'flex';
        }

        closeModalBtn.addEventListener('click', () => meetingModal.style.display = 'none');
        window.addEventListener('click', (e) => {
            if (e.target === meetingModal) meetingModal.style.display = 'none';
        });
    </script>
</body>
</html>