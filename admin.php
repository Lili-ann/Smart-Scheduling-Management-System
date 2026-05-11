<?php
// Dummy data for Meetings (In a real app, you'd fetch this from a database)
$meetings = [
    [
        "status" => "Upcoming",
        "title" => "Student Organization",
        "pic" => "Rafdah",
        "attendees" => 20,
        "room" => "5.03",
        "date" => "02 May 2027",
        "duration" => "12:30pm - 2:00pm"
    ],
    [
        "status" => "Upcoming",
        "title" => "Media Club Disbanding",
        "pic" => "Rafdah",
        "attendees" => 20,
        "room" => "5.03",
        "date" => "02 May 2027",
        "duration" => "12:30pm - 2:00pm"
    ],
    [
        "status" => "Ended",
        "title" => "IT Lecturers Meeting",
        "pic" => "Rafdah",
        "attendees" => 20,
        "room" => "5.03",
        "date" => "02 May 2027",
        "duration" => "12:30pm - 2:00pm"
    ],
    [
        "status" => "Ended",
        "title" => "All Lecturers Meeting",
        "pic" => "Rafdah",
        "attendees" => 20,
        "room" => "5.03",
        "date" => "02 May 2027",
        "duration" => "12:30pm - 2:00pm"
    ]
];

// Dummy data for Users
$users = [
    ["role" => "Officer", "name" => "Michael Lee"],
    ["role" => "Officer", "name" => "John What"],
    ["role" => "Manager", "name" => "Emily Carter"]
];

$adminName = "Admin";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            color: #11072b;
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
            padding: 60px;
            gap: 50px;
            margin-top: 60px;
        }

        /* --- Left Column: Meetings --- */
        .meetings-section {
            flex: 2;
        }

        .meetings-box {
            background-color: #d9d9d9;
            padding: 30px;
            border-radius: 5px;
        }

        .meetings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Individual Meeting Card */
        .meeting-card {
            background: white;
            padding: 15px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .meeting-info h4 {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: normal;
        }

        .meeting-info h3 {
            font-size: 1.1rem;
            color: #000;
            margin-bottom: 10px;
        }

        .meeting-info p {
            font-size: 0.8rem;
            margin-bottom: 3px;
        }

        .meeting-info p strong {
            font-weight: bold;
            color: #000;
        }


        /* Card Action Buttons (Check/Trash) */
        .card-actions {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 15px;
        }

        .icon-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .btn-check { background-color: #5cf25c; color: #000; }
        .btn-trash { background-color: #9d8bb8; color: #000; }

        /* Pagination inside grey box */
        .pagination {
            text-align: center;
            margin-top: 20px;
            font-size: 1.5rem;
            color: #333;
            letter-spacing: 20px;
            cursor: pointer;
        }

        /* Bottom Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .action-buttons button {
            background-color: #11072b;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
        }

        /* --- Right Column: Users --- */
        .divider {
            width: 1px;
            background-color: #ccc;
        }

        .users-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .users-section h2 {
            font-size: 1.8rem;
            color: #11072b;
            padding: 50px 0;
        }

        .users-list {
            width: 100%;
            max-width: 300px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .user-card {
            background-color: #11072b;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info p { font-size: 0.7rem; color: #ccc; margin-bottom: 3px; }
        .user-info h3 { font-size: 1.2rem; font-weight: normal; }

        .btn-edit {
            background: white;
            color: #11072b;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .more-users {
            color: #999;
            font-size: 1.5rem;
            margin: 15px 0;
        }

        .btn-add-user {
            background: transparent;
            color: #11072b;
            border: 2px solid #11072b;
            padding: 8px 30px;
            border-radius: 25px;
            font-size: 0.9rem;
            cursor: pointer;
            font-weight: bold;
        }

        /* --- Footer --- */
        .footer {
            padding: 0 60px 40px 60px;
        }
        .footer a {
            color: #11072b;
            font-size: 1.1rem;
        }

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
        .modal-content form { display: flex; flex-direction: column; gap: 15px; }
        .modal-content input, .modal-content select { padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        .modal-content button { background-color: #11072b; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-size: 1rem; transition: 0.3s; }
        .modal-content button:hover { background-color: #2b1154; }
    </style>
</head>
<body>

    <header class="header-container">
        <h1>Hello, {<?php echo $adminName; ?>}</h1>
        <a href="login.php" class="logout-btn">Logout</a>
        <div class="header-wave"></div>
    </header>

    <main class="main-content">
        
        <section class="meetings-section">
            <div class="meetings-box">
                <div class="meetings-grid">
                    
                    <?php foreach ($meetings as $meeting): ?>
                        <div class="meeting-card">
                            <div class="meeting-info">
                                <h4><?php echo $meeting['status']; ?></h4>
                                <h3><?php echo $meeting['title']; ?></h3>
                                <p><strong>PIC:</strong> <?php echo $meeting['pic']; ?></p>
                                <p><strong>Expected Attendees:</strong> <?php echo $meeting['attendees']; ?></p>
                                <p><strong>Room:</strong> <?php echo $meeting['room']; ?></p>
                                <p><strong>Date:</strong> <?php echo $meeting['date']; ?></p>
                                <p><strong>Duration:</strong> <?php echo $meeting['duration']; ?></p>
                            </div>
                            <div class="card-actions">
                                <button class="icon-btn btn-check"><i class="fa-solid fa-check"></i></button>
                                <button class="icon-btn btn-trash"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
                <div class="pagination">
                    <span>&lt;</span> <span>&gt;</span>
                </div>
            </div>

            <div class="action-buttons">
                <button>Export as .csv</button>
                <button id="addMeetingBtn">+ Add Meeting</button>
            </div>
        </section>

        <div class="divider"></div>

        <section class="users-section">
            <h2>Users List</h2>
            <div class="users-list">
                
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="user-info">
                            <p><?php echo $user['role']; ?></p>
                            <h3><?php echo $user['name']; ?></h3>
                        </div>
                        <button class="btn-edit" onclick="openEditModal('<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['role'], ENT_QUOTES); ?>')"><i class="fa-regular fa-pen-to-square"></i></button>
                    </div>
                <?php endforeach; ?>

            </div>
            
            <i class="fa-solid fa-chevron-down more-users"></i>
            
            <button id="addUserBtn" class="btn-add-user">+ Add User</button>
        </section>

    </main>

    <footer class="footer">
    </footer>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h2>Edit User</h2>
            <form action="#" method="POST">
                <input type="text" id="editUserName" name="user_name" placeholder="Name" required>
                <select id="editUserRole" name="user_role" required>
                    <option value="Officer">Officer</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
                <button type="submit">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Add Meeting Modal -->
    <div id="addMeetingModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeAddMeetingModal">&times;</span>
            <h2>Add New Meeting</h2>
            <form action="#" method="POST">
                <input type="text" name="meeting_title" placeholder="Meeting Title" required>
                <input type="text" name="meeting_pic" placeholder="Person In Charge (PIC)" required>
                <input type="number" name="meeting_attendees" placeholder="Expected Attendees" required>
                <input type="date" name="meeting_date" required>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label for="start_time" style="flex-shrink: 0;">From:</label>
                    <input id="start_time" type="time" name="meeting_start_time" required style="width: 100%;">
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label for="end_time" style="flex-shrink: 0;">To:</label>
                    <input id="end_time" type="time" name="meeting_end_time" required style="width: 100%;">
                </div>
                <button type="button" id="proceedToRoomBtn">Create Meeting</button>
            </form>
        </div>
    </div>

    <!-- Reserve Room Modal -->
    <div id="reserveRoomModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeReserveRoomModal">&times;</span>
            <h2>Reserve Room</h2>
            <form action="#" method="POST">
                <!-- You can later add hidden inputs here to pass data from the first modal to the backend -->
                <input type="text" name="meeting_room" placeholder="Enter Room Number" required>
                <button type="submit">Confirm Reservation</button>
            </form>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeAddUserModal">&times;</span>
            <h2>Add New User</h2>
            <form action="#" method="POST">
                <input type="text" name="user_name" placeholder="Full Name" required>
                <select name="user_role" required>
                    <option value="" disabled selected>Select Role</option>
                    <option value="Officer">Officer</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
                <button type="submit">Add User</button>
            </form>
        </div>
    </div>

    <script>
        const editModal = document.getElementById('editUserModal');
        const closeModalBtn = document.getElementById('closeModal');

        function openEditModal(name, role) {
            document.getElementById('editUserName').value = name;
            document.getElementById('editUserRole').value = role;
            editModal.style.display = 'flex';
        }

        closeModalBtn.addEventListener('click', () => editModal.style.display = 'none');

        const addMeetingModal = document.getElementById('addMeetingModal');
        const closeAddMeetingModalBtn = document.getElementById('closeAddMeetingModal');
        const addMeetingBtn = document.getElementById('addMeetingBtn');

        addMeetingBtn.addEventListener('click', () => {
            addMeetingModal.style.display = 'flex';
        });

        closeAddMeetingModalBtn.addEventListener('click', () => addMeetingModal.style.display = 'none');

        const proceedToRoomBtn = document.getElementById('proceedToRoomBtn');
        const reserveRoomModal = document.getElementById('reserveRoomModal');
        const closeReserveRoomModalBtn = document.getElementById('closeReserveRoomModal');

        proceedToRoomBtn.addEventListener('click', () => {
            addMeetingModal.style.display = 'none';
            reserveRoomModal.style.display = 'flex';
        });

        closeReserveRoomModalBtn.addEventListener('click', () => reserveRoomModal.style.display = 'none');

        const addUserModal = document.getElementById('addUserModal');
        const closeAddUserModalBtn = document.getElementById('closeAddUserModal');
        const addUserBtn = document.getElementById('addUserBtn');

        addUserBtn.addEventListener('click', () => {
            addUserModal.style.display = 'flex';
        });

        closeAddUserModalBtn.addEventListener('click', () => addUserModal.style.display = 'none');

        window.addEventListener('click', (e) => {
            if (e.target === editModal) {
                editModal.style.display = 'none';
            }
            if (e.target === addMeetingModal) {
                addMeetingModal.style.display = 'none';
            }
            if (e.target === reserveRoomModal) {
                reserveRoomModal.style.display = 'none';
            }
            if (e.target === addUserModal) {
                addUserModal.style.display = 'none';
            }
        });
    </script>

</body>
</html>