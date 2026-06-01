<?php
session_start();
require_once "db.php";

// Only admins are allowed to open this page.
if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: login.php?error=Please log in as an admin to access that page");
    exit();
}

$adminName = $_SESSION['user_name'] ?? "Admin";
$message = '';
$messageType = 'error';

// --- 1. HANDLE POST REQUESTS ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // --- EVENT ACTIONS ---
    if ($action === 'assign_event_staff') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $staffId = !empty($_POST['staff_id']) ? (int)$_POST['staff_id'] : null;
        if ($eventId > 0) {
            $stmt = $conn->prepare("UPDATE events SET assigned_staff_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $staffId, $eventId);
            $stmt->execute();
            header("Location: admin.php?success=Staff assigned to event successfully");
            exit();
        }
    }
    elseif ($action === 'edit_event') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $room = trim($_POST['room'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $start = trim($_POST['start_time'] ?? '');
        $end = trim($_POST['end_time'] ?? '');
        if ($eventId > 0 && $title !== '') {
            $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, room = ?, date = ?, start_time = ?, end_time = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $title, $desc, $room, $date, $start, $end, $eventId);
            $stmt->execute();
            header("Location: admin.php?success=Event updated successfully");
            exit();
        }
    }
    elseif ($action === 'create_event') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $imagePath = trim($_POST['image_path'] ?? '');
        $room = trim($_POST['room'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $start = trim($_POST['start_time'] ?? '');
        $end = trim($_POST['end_time'] ?? '');
        
        if ($imagePath === '') {
            $imagePath = 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80';
        }

        if ($title !== '' && $date !== '' && $start !== '' && $end !== '') {
            $stmt = $conn->prepare("INSERT INTO events (title, description, image_path, room, date, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $title, $desc, $imagePath, $room, $date, $start, $end);
            $stmt->execute();
            header("Location: admin.php?success=Event created successfully");
            exit();
        } else {
            $message = "Please fill in all required event fields.";
        }
    }

    // --- USER ACTIONS ---
    elseif ($action === 'add_user') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($fullname !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $password !== '') {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (fullname, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("ssss", $fullname, $email, $passwordHash, $role);
                $stmt->execute();
                header("Location: admin.php?success=User added successfully");
                exit();
            } catch (mysqli_sql_exception $e) { $message = "Could not add user. Email may exist."; }
        } else {
            $message = "Invalid user data provided.";
        }
    }
    elseif ($action === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');

        if ($userId > 0 && $fullname !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssi", $fullname, $email, $role, $userId);
                $stmt->execute();
                header("Location: admin.php?success=User updated successfully");
                exit();
            } catch (mysqli_sql_exception $e) { $message = "Could not update user."; }
        }
    }
    elseif ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int)$_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            header("Location: admin.php?success=User deleted successfully");
            exit();
        } else {
            $message = "You cannot delete your own account.";
        }
    }

    // --- MARK MESSAGE AS READ ---
    elseif ($action === 'mark_message_read') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId > 0) {
            $stmt = $conn->prepare("UPDATE admin_messages SET status = 'Read' WHERE id = ?");
            $stmt->bind_param("i", $msgId);
            $stmt->execute();
            header("Location: admin.php?success=Message marked as read");
            exit();
        }
    }
    elseif ($action === 'reply_message') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        $replyText = trim($_POST['reply_text'] ?? '');

        if ($msgId > 0 && $replyText !== '') {
            $stmt = $conn->prepare("UPDATE admin_messages SET status = 'Read' WHERE id = ?");
            $stmt->bind_param("i", $msgId);
            $stmt->execute();
            header("Location: admin.php?success=Reply sent and request marked as handled");
            exit();
        }

        $message = "Please type a reply before sending.";
    }
}

if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $messageType = 'success';
} elseif (isset($_GET['error'])) {
    $message = $_GET['error'];
}

// --- 2. FETCH ALL DATA ---

// Events
$events = [];
$resEvents = $conn->query("SELECT e.*, u.fullname AS assigned_staff_name FROM events e LEFT JOIN users u ON e.assigned_staff_id = u.id ORDER BY e.date ASC");
if ($resEvents) { while ($row = $resEvents->fetch_assoc()) $events[] = $row; }

// Users & Staff
$users = [];
$staffList = [];
$resUsers = $conn->query("SELECT id, fullname, email, role FROM users ORDER BY fullname ASC");
if ($resUsers) {
    while ($row = $resUsers->fetch_assoc()) {
        $users[] = $row;
        if ($row['role'] === 'Staff') $staffList[] = $row;
    }
}

// Forwarded Admin Messages
$forwardedMessages = [];
$resMessages = $conn->query("SELECT * FROM admin_messages WHERE subject LIKE '[FORWARDED by %' AND status = 'Unread' ORDER BY created_at DESC");
if ($resMessages) {
    while ($row = $resMessages->fetch_assoc()) {
        $row['formatted_date'] = (new DateTime($row['created_at']))->format('d M Y, g:ia');
        
        // Extract the Staff Name and Clean Subject using regex
        $row['forwarded_by'] = "Staff";
        $row['clean_subject'] = $row['subject'];
        if (preg_match('/\[FORWARDED by (.*?)\] (.*)/', $row['subject'], $matches)) {
            $row['forwarded_by'] = $matches[1];
            $row['clean_subject'] = $matches[2];
        }
        
        $forwardedMessages[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8f9fa; color: #333; overflow-x: hidden; }

        /* Header */
        .header-container { position: relative; width: 100%; height: 120px; background-color: #11072b; color: white; padding: 40px 60px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-wave { position: absolute; top: 100%; left: 0; width: 100%; height: 100px; z-index: 1; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 100' preserveAspectRatio='none'%3E%3Cpath d='M0,0 L1000,0 L1000,30 C750,10 250,120 0,80 Z' fill='%2311072b'/%3E%3C/svg%3E"); background-size: 100% 100%; }
        .header-container h1 { font-size: 2.5rem; font-weight: 400; z-index: 2; }
        .logout-btn { background-color: white; color: #11072b; padding: 10px 30px; border-radius: 25px; text-decoration: none; font-weight: bold; font-size: 1rem; z-index: 2; }

        /* Container & Nav Tabs */
        .main-container { padding: 40px 60px; margin-top: 90px; position: relative; z-index: 2; max-width: 1400px; margin-left: auto; margin-right: auto; }
        .admin-nav { display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 15px; }
        .nav-btn { background: transparent; border: none; font-size: 1.1rem; font-weight: bold; color: #666; cursor: pointer; padding: 10px 24px; border-radius: 25px; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .nav-btn:hover { color: #11072b; background: #eee; }
        .nav-btn.active { background: #11072b; color: white; }

        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Alerts */
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .alert-success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .alert-error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        /* Action Bar */
        .action-bar { display: flex; justify-content: flex-end; gap: 15px; margin-bottom: 20px; }
        .action-bar button, .action-bar a { background-color: #11072b; color: white; border: none; padding: 10px 24px; border-radius: 25px; text-decoration: none; font-weight: bold; cursor: pointer; }

        /* --- Events Tab --- */
        .events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; }
        .event-card { background-color: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); overflow: hidden; display: flex; flex-direction: column; border: 1px solid #eee; }
        .event-card-image-wrapper { position: relative; width: 100%; padding-top: 70%; }
        .event-card-image-wrapper img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .event-card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .event-card-body h3 { font-size: 1.25rem; color: #11062b; }
        .event-card-body p { font-size: 0.85rem; color: #555; }
        .event-staff { font-size: 0.85rem; font-weight: bold; color: #198754; background: #d1e7dd; padding: 4px 10px; border-radius: 12px; display: inline-block; width: fit-content; }
        .event-staff.unassigned { color: #842029; background: #f8d7da; }
        
        .event-actions { display: flex; border-top: 1px solid #eee; background: #fdfdfd; }
        .event-action-btn { flex: 1; padding: 15px; border: none; background: transparent; cursor: pointer; font-size: 0.95rem; font-weight: bold; color: #11062b; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .event-action-btn:hover { background: #eee; }
        .event-action-btn:first-child { border-right: 1px solid #eee; }

        .card-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
        .icon-btn { width: 35px; height: 35px; border-radius: 5px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: white; }
        .btn-check { background-color: #198754; }
        .btn-trash { background-color: #b02a37; }

        /* --- Users Tab --- */
        .admin-split-layout { display: flex; gap: 40px; }
        .admin-column { flex: 1; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 6px 15px rgba(0,0,0,0.3); }
        .admin-column h2 { font-size: 1.5rem; color: #11072b; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        .list-item { border: 1px solid #eee; padding: 15px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .list-item-info h3 { font-size: 1.1rem; margin-bottom: 5px; }
        .list-item-info p { font-size: 0.85rem; color: #666; }
        .list-actions { display: flex; gap: 10px; }
        .btn-edit { background: #f6d365; color: #000; width: 35px; height: 35px; border-radius: 5px; border:none; cursor: pointer;}
        
        /* Modals */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; width: 100%; max-width: 400px; position: relative; }
        .modal-content.wide { max-width: 500px; }
        .close-modal { position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 1.5rem; }
        .modal-content form { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        .modal-content input, .modal-content select, .modal-content textarea { padding: 12px; border: 1px solid #ccc; border-radius: 5px; }
        .modal-content button[type="submit"] { background-color: #11072b; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .modal-content button[type="submit"]:hover { background-color: #2b1154; }

        .message-badge {
            background: #b02a37;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .message-list { display: flex; flex-direction: column; gap: 15px; }
        
        .message-item { background: white; border-radius: 10px; padding: 15px; color: #333; position: relative; }
        .message-item-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .message-item-forwarder { font-size: 0.75rem; color: #198754; background: #d1e7dd; padding: 3px 8px; border-radius: 8px; font-weight: bold; }
        .message-item-date { font-size: 0.75rem; color: #888; }
        .message-item-name { font-weight: bold; font-size: 1rem; color: #11062b; margin-bottom: 2px; display: block; }
        .message-item-email { font-size: 0.8rem; color: #666; margin-bottom: 8px; display: block; }
        .message-item-subject { font-size: 0.9rem; font-weight: bold; color: #333; margin-bottom: 5px; }
        .message-item-content { font-size: 0.85rem; color: #555; background: #f9f9fb; padding: 10px; border-radius: 5px; font-style: italic; border-left: 3px solid #11062b; }
        
        .message-action-bar { display: flex; justify-content: flex-end; margin-top: 10px; }
        .btn-mark-read { background: #11062b; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-size: 0.8rem; cursor: pointer; transition: 0.2s; }
        .btn-mark-read:hover { background: #2b1154; }
        .btn-reply { background: #f6d365; color: #000; border: none; padding: 8px 15px; border-radius: 5px; font-size: 0.8rem; font-weight: bold; cursor: pointer; }

    </style>
</head>
<body>

    <header class="header-container">
        <h1>Hello, {<?php echo htmlspecialchars($adminName); ?>}</h1>
        <a href="login.php" class="logout-btn">Logout</a>
        <div class="header-wave"></div>
    </header>

    <div class="main-container">
        
        <?php if(!empty($message)): ?>
            <div class="alert-box <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="admin-nav">
            <button class="nav-btn active" onclick="switchTab('tabEvents', this)">Events</button>
            <button class="nav-btn" onclick="switchTab('tabUsers', this)">Users</button>
            <button class="nav-btn" onclick="switchTab('tabRequests', this)">
                Requests
                <?php if (count($forwardedMessages) > 0): ?>
                    <span class="message-badge"><?php echo count($forwardedMessages); ?></span>
                <?php endif; ?>
            </button>
        </div>

        <div id="tabEvents" class="tab-content active">
            <div class="action-bar">
                <button onclick="document.getElementById('addEventModal').style.display='flex'">+ Create Event</button>
            </div>
            <div class="events-grid">
                <?php if (empty($events)): ?>
                    <p style="color: #666; font-style: italic;">No events found in the database.</p>
                <?php else: ?>
                    <?php foreach ($events as $evt): 
                        $isAssigned = !empty($evt['assigned_staff_id']);
                        $staffName = $isAssigned ? $evt['assigned_staff_name'] : "Unassigned";
                        $badgeClass = $isAssigned ? "event-staff" : "event-staff unassigned";
                    ?>
                        <div class="event-card">
                            <div class="event-card-image-wrapper">
                                <img src="<?php echo htmlspecialchars($evt['image_path']); ?>" alt="Poster">
                            </div>
                            <div class="event-card-body">
                                <h3><?php echo htmlspecialchars($evt['title']); ?></h3>
                                <p><i class="fa-regular fa-calendar"></i> <?php echo (new DateTime($evt['date']))->format('M d, Y'); ?> | <?php echo (new DateTime($evt['start_time']))->format('h:i A'); ?></p>
                                <p><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($evt['room']); ?></p>
                                <span class="<?php echo $badgeClass; ?>"><i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($staffName); ?></span>
                            </div>
                            <div class="event-actions">
                                <a href="admin_event.php?id=<?php echo (int)$evt['id']; ?>" class="event-action-btn" style="text-decoration:none;">
                                    <i class="fa-regular fa-pen-to-square"></i> Edit
                                </a>

                                <button class="event-action-btn assign-staff-btn" 
                                    data-id="<?php echo $evt['id']; ?>" 
                                    data-title="<?php echo htmlspecialchars($evt['title'], ENT_QUOTES); ?>"
                                    data-staff="<?php echo $evt['assigned_staff_id']; ?>">
                                    <i class="fa-solid fa-user-plus"></i> Assign
                                </button>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="tabUsers" class="tab-content">
            <div class="admin-split-layout">
                <div class="admin-column">
                    <h2>Staff & Users</h2>
                    <div class="action-bar" style="justify-content: flex-start; margin-top:-10px;">
                        <button onclick="document.getElementById('addUserModal').style.display='flex'">+ Add User</button>
                    </div>
                    <?php foreach ($users as $u): ?>
                        <div class="list-item">
                            <div class="list-item-info">
                                <h3><?php echo htmlspecialchars($u['fullname']); ?></h3>
                                <p><?php echo htmlspecialchars($u['role']); ?> | <?php echo htmlspecialchars($u['email']); ?></p>
                            </div>
                            <div class="list-actions">
                                <button class="btn-edit" 
                                    onclick="openEditUserModal('<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['fullname'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>', '<?php echo $u['role']; ?>')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form action="admin.php" method="POST" style="margin:0;" onsubmit="return confirm('Delete user?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="icon-btn btn-trash"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="tabRequests" class="tab-content">
            <div class="admin-column">
                <h2>Message Requests</h2>
                <div class="message-list">
                    <?php if (empty($forwardedMessages)): ?>
                        <p style="color: #666; font-style: italic;">No forwarded messages to review.</p>
                    <?php else: ?>
                        <?php foreach ($forwardedMessages as $msg): ?>
                            <div class="message-item">
                                <div class="message-item-header">
                                    <span class="message-item-forwarder"><i class="fa-solid fa-share"></i> Fwd by: <?php echo htmlspecialchars($msg['forwarded_by']); ?></span>
                                    <span class="message-item-date"><?php echo htmlspecialchars($msg['formatted_date']); ?></span>
                                </div>
                                <span class="message-item-name"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                <span class="message-item-email"><?php echo htmlspecialchars($msg['sender_email']); ?></span>
                                <div class="message-item-subject"><?php echo htmlspecialchars($msg['clean_subject']); ?></div>
                                <div class="message-item-content">"<?php echo nl2br(htmlspecialchars($msg['content'])); ?>"</div>
                                <div class="message-action-bar">
                                    <button
                                        type="button"
                                        class="btn-reply"
                                        onclick='openReplyMessageModal(<?php echo htmlspecialchars(json_encode($msg), ENT_QUOTES); ?>)'
                                    >
                                        <i class="fa-solid fa-reply"></i> Reply
                                    </button>
                                    <form action="admin.php" method="POST" style="margin-left: 10px;">
                                        <input type="hidden" name="action" value="mark_message_read">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <button type="submit" class="btn-mark-read"><i class="fa-solid fa-check"></i> Mark as Handled</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <div id="addEventModal" class="modal-overlay">
        <div class="modal-content wide">
            <span class="close-modal" onclick="this.closest('.modal-overlay').style.display='none'">&times;</span>
            <h2>Create New Event</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="create_event">
                <input type="text" name="title" placeholder="Event Title" required>
                <textarea name="description" rows="3" placeholder="Event Description" required></textarea>
                <input type="text" name="image_path" placeholder="Image URL (Optional)">
                <input type="text" name="room" placeholder="Room/Location" required>
                <input type="date" name="date" required>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label style="font-size: 0.8rem; color:#666; font-weight:bold;">Start Time</label>
                        <input type="time" name="start_time" style="width:100%;" required>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size: 0.8rem; color:#666; font-weight:bold;">End Time</label>
                        <input type="time" name="end_time" style="width:100%;" required>
                    </div>
                </div>
                <button type="submit">Create Event</button>
            </form>
        </div>
    </div>

    <div id="assignStaffModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="this.closest('.modal-overlay').style.display='none'">&times;</span>
            <h2>Assign Staff</h2>
            <p style="margin-bottom: 10px; color: #555;">Assigning staff for: <strong id="assignEventTitle"></strong></p>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="assign_event_staff">
                <input type="hidden" name="event_id" id="assignEventId">
                <select name="staff_id" id="assignStaffSelect" required>
                    <option value="" disabled selected>-- Select Staff Member --</option>
                    <option value="">(Unassign Staff)</option>
                    <?php foreach ($staffList as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['fullname']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Save Assignment</button>
            </form>
        </div>
    </div>

    <div id="editEventModal" class="modal-overlay">
        <div class="modal-content wide">
            <span class="close-modal" onclick="this.closest('.modal-overlay').style.display='none'">&times;</span>
            <h2>Edit Event Details</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="edit_event">
                <input type="hidden" name="event_id" id="editEventId">
                <input type="text" name="title" id="editEventTitle" placeholder="Event Title" required>
                <textarea name="description" id="editEventDesc" rows="4" placeholder="Event Description" required></textarea>
                <input type="text" name="room" id="editEventRoom" placeholder="Room/Location" required>
                <input type="date" name="date" id="editEventDate" required>
                <div style="display:flex; gap:10px;">
                    <input type="time" name="start_time" id="editEventStart" style="flex:1;" required>
                    <input type="time" name="end_time" id="editEventEnd" style="flex:1;" required>
                </div>
                <button type="submit">Update Event</button>
            </form>
        </div>
    </div>

    <div id="addUserModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="this.closest('.modal-overlay').style.display='none'">&times;</span>
            <h2>Add New User</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="add_user">
                <input type="text" name="fullname" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <select name="role" required>
                    <option value="" disabled selected>Select Role</option>
                    <option value="Admin">Admin</option>
                    <option value="Staff">Staff</option>
                </select>
                <input type="password" name="password" placeholder="Create Password" required>
                <button type="submit">Save User</button>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="this.closest('.modal-overlay').style.display='none'">&times;</span>
            <h2>Edit User</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="editUserId">
                <input type="text" name="fullname" id="editUserName" required>
                <input type="email" name="email" id="editUserEmail" required>
                <select name="role" id="editUserRole" required>
                    <option value="Admin">Admin</option>
                    <option value="Staff">Staff</option>
                </select>
                <button type="submit">Update User</button>
            </form>
        </div>
    </div>

    <div id="replyMessageModal" class="modal-overlay">
        <div class="modal-content wide">
            <span class="close-modal" onclick="this.closest('.modal-overlay').style.display='none'">&times;</span>
            <h2>Reply to Message</h2>
            <div style="margin-bottom: 15px; font-size:0.9rem; color:#444;">
                <p><strong>From:</strong> <span id="replyMsgFrom"></span></p>
                <p><strong>Subject:</strong> <span id="replyMsgSubject"></span></p>
                <div id="replyMsgContent" style="background:#f0f0f0; padding:10px; border-radius:5px; margin-top:10px; font-style:italic;"></div>
            </div>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="reply_message">
                <input type="hidden" name="message_id" id="replyMsgId">
                <textarea name="reply_text" id="replyTextarea" rows="5" placeholder="Type your reply here..." required></textarea>
                <button type="submit">Send Reply</button>
            </form>
        </div>
    </div>

    <script>
        // Tab Switching Logic
        function switchTab(tabId, btnElement) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btnElement.classList.add('active');
        }

        // Open Edit User Modal
        function openEditUserModal(id, name, email, role) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editUserName').value = name;
            document.getElementById('editUserEmail').value = email;
            document.getElementById('editUserRole').value = role;
            document.getElementById('editUserModal').style.display = 'flex';
        }

        function openReplyMessageModal(msg) {
            document.getElementById('replyMsgId').value = msg.id;
            document.getElementById('replyMsgFrom').innerText = `${msg.sender_name} (${msg.sender_email})`;
            document.getElementById('replyMsgSubject').innerText = msg.clean_subject || msg.subject;
            document.getElementById('replyMsgContent').innerText = `"${msg.content}"`;
            document.getElementById('replyTextarea').value = '';
            document.getElementById('replyMessageModal').style.display = 'flex';
        }

        // Open Edit Event Modal
        document.querySelectorAll('.edit-event-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('editEventId').value = btn.dataset.id;
                document.getElementById('editEventTitle').value = btn.dataset.title;
                document.getElementById('editEventDesc').value = btn.dataset.desc;
                document.getElementById('editEventRoom').value = btn.dataset.room;
                document.getElementById('editEventDate').value = btn.dataset.date;
                document.getElementById('editEventStart').value = btn.dataset.start;
                document.getElementById('editEventEnd').value = btn.dataset.end;
                document.getElementById('editEventModal').style.display = 'flex';
            });
        });

        // Open Assign Staff Modal
        document.querySelectorAll('.assign-staff-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('assignEventId').value = btn.dataset.id;
                document.getElementById('assignEventTitle').innerText = btn.dataset.title;
                let select = document.getElementById('assignStaffSelect');
                select.value = btn.dataset.staff ? btn.dataset.staff : "";
                document.getElementById('assignStaffModal').style.display = 'flex';
            });
        });

        // Close Modals on outside click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.style.display = 'none';
            }
        });
        
        // Clean URL after success/error alerts
        if (window.history.replaceState) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.has('success') || currentUrl.searchParams.has('error')) {
                currentUrl.searchParams.delete('success');
                currentUrl.searchParams.delete('error');
                window.history.replaceState({}, '', currentUrl.pathname);
            }
        }
    </script>
</body>
</html>
