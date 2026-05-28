<?php
session_start();
require_once "db.php";

// Only admins are allowed to open this page.
// If a normal user tries to access it, send them back to login.
if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    header("Location: login.php?error=Please log in as an admin to access that page");
    exit();
}

// Basic values used by the page and feedback popups.
$adminName = $_SESSION['user_name'] ?? "Admin";
$message = '';
$messageType = 'error';

// Download all meetings as a CSV file when the admin clicks "Export as .csv".
if (($_GET['action'] ?? '') === 'export_meetings') {
    $filename = 'meetings-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Status', 'Title', 'PIC', 'Attendees', 'Room', 'Date', 'Start Time', 'End Time', 'Created By', 'Created At']);

    try {
        $sql = "SELECT m.id, m.status, m.title, m.pic, m.attendees, m.room, m.date, m.start_time, m.end_time, u.fullname AS created_by, m.created_at
                FROM meetings m
                LEFT JOIN users u ON u.id = m.created_by
                ORDER BY m.date ASC, m.start_time ASC";
        $result = $conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'],
                    $row['status'],
                    $row['title'],
                    $row['pic'],
                    $row['attendees'],
                    $row['room'],
                    $row['date'],
                    $row['start_time'],
                    $row['end_time'],
                    $row['created_by'] ?? '',
                    $row['created_at']
                ]);
            }
        }
    } catch (mysqli_sql_exception $e) {
        fputcsv($output, ['Export failed']);
    }

    fclose($output);
    $conn->close();
    exit();
}

// Create a new meeting after the admin fills in meeting details and chooses a room.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'add_meeting') {
    $title = trim($_POST['meeting_title'] ?? '');
    $pic = trim($_POST['meeting_pic'] ?? '');
    $attendees = (int)($_POST['meeting_attendees'] ?? 0);
    $date = trim($_POST['meeting_date'] ?? '');
    $startTime = trim($_POST['meeting_start_time'] ?? '');
    $endTime = trim($_POST['meeting_end_time'] ?? '');
    $room = trim($_POST['meeting_room'] ?? '');
    $createdBy = $_SESSION['user_id'] ?? null;

    if ($title === '' || $pic === '' || $attendees <= 0 || $date === '' || $startTime === '' || $endTime === '' || $room === '') {
        $message = "Please fill in all meeting fields.";
    } elseif ($endTime <= $startTime) {
        $message = "End time must be after start time.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO meetings (status, title, pic, attendees, room, date, start_time, end_time, created_by) VALUES ('Upcoming', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssi", $title, $pic, $attendees, $room, $date, $startTime, $endTime, $createdBy);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?success=Meeting created successfully");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not create meeting. Please check the meeting table.";
        }
    }
}

// Mark an existing meeting as ended.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'end_meeting') {
    $meetingId = (int)($_POST['meeting_id'] ?? 0);

    if ($meetingId <= 0) {
        $message = "Invalid meeting selected.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE meetings SET status = 'Ended' WHERE id = ?");
            $stmt->bind_param("i", $meetingId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?success=Meeting marked as ended");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not update the meeting status.";
        }
    }
}

// Delete a meeting after the admin confirms the delete popup.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'delete_meeting') {
    $meetingId = (int)($_POST['meeting_id'] ?? 0);

    if ($meetingId <= 0) {
        $message = "Invalid meeting selected.";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM meetings WHERE id = ?");
            $stmt->bind_param("i", $meetingId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?success=Meeting deleted successfully");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not delete the meeting.";
        }
    }
}

// Save changes made from the edit meeting popup.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'update_meeting') {
    $meetingId = (int)($_POST['meeting_id'] ?? 0);
    $status = trim($_POST['meeting_status'] ?? '');
    $title = trim($_POST['meeting_title'] ?? '');
    $pic = trim($_POST['meeting_pic'] ?? '');
    $attendees = (int)($_POST['meeting_attendees'] ?? 0);
    $date = trim($_POST['meeting_date'] ?? '');
    $startTime = trim($_POST['meeting_start_time'] ?? '');
    $endTime = trim($_POST['meeting_end_time'] ?? '');
    $room = trim($_POST['meeting_room'] ?? '');
    $validStatuses = ['Upcoming', 'Ended', 'Cancelled'];

    if ($meetingId <= 0) {
        $message = "Invalid meeting selected.";
    } elseif (!in_array($status, $validStatuses, true) || $title === '' || $pic === '' || $attendees <= 0 || $date === '' || $startTime === '' || $endTime === '' || $room === '') {
        $message = "Please fill in all meeting fields.";
    } elseif ($endTime <= $startTime) {
        $message = "End time must be after start time.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE meetings SET status = ?, title = ?, pic = ?, attendees = ?, room = ?, date = ?, start_time = ?, end_time = ? WHERE id = ?");
            $stmt->bind_param("sssissssi", $status, $title, $pic, $attendees, $room, $date, $startTime, $endTime, $meetingId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?success=Meeting updated successfully");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not update the meeting.";
        }
    }
}

// Approve a user's room request.
// This creates a real meeting and then marks the request as approved.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'approve_request') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $reviewedBy = $_SESSION['user_id'] ?? null;

    if ($requestId <= 0) {
        $message = "Invalid request selected.";
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("SELECT requester_id, title, pic, attendees, room, date, start_time, end_time FROM meeting_requests WHERE id = ? AND status = 'Pending'");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$request) {
                throw new mysqli_sql_exception("Request not found or already reviewed.");
            }

            $stmt = $conn->prepare("INSERT INTO meetings (status, title, pic, attendees, room, date, start_time, end_time, created_by) VALUES ('Upcoming', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "ssissssi",
                $request['title'],
                $request['pic'],
                $request['attendees'],
                $request['room'],
                $request['date'],
                $request['start_time'],
                $request['end_time'],
                $request['requester_id']
            );
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE meeting_requests SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $reviewedBy, $requestId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: admin.php?success=Meeting request approved");
            exit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Could not approve the meeting request.";
        }
    }
}

// Reject a user's room request without creating a meeting.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'reject_request') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $reviewedBy = $_SESSION['user_id'] ?? null;

    if ($requestId <= 0) {
        $message = "Invalid request selected.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE meeting_requests SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'Pending'");
            $stmt->bind_param("ii", $reviewedBy, $requestId);
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();

            if ($affectedRows < 1) {
                $message = "This request has already been reviewed.";
            } else {
                header("Location: admin.php?success=Meeting request rejected");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $message = "Could not reject the meeting request.";
        }
    }
}

// Add a new user account from the admin dashboard.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'add_user') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $validRoles = ['Admin', 'User'];
    $passwordErrors = [];

    if (strlen($password) < 8) $passwordErrors[] = "at least 8 characters";
    if (!preg_match('/[A-Z]/', $password)) $passwordErrors[] = "one uppercase letter";
    if (!preg_match('/[a-z]/', $password)) $passwordErrors[] = "one lowercase letter";
    if (!preg_match('/[0-9]/', $password)) $passwordErrors[] = "one number";
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) $passwordErrors[] = "one special character";

    if ($fullname === '' || $email === '' || $role === '' || $password === '') {
        $message = "Please fill in all user fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (!in_array($role, $validRoles, true)) {
        $message = "Please select a valid role.";
    } elseif (!empty($passwordErrors)) {
        $message = "Password must include: " . implode(", ", $passwordErrors) . ".";
    } else {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssss", $fullname, $email, $passwordHash, $role);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?success=User added successfully");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not add user. The email may already be registered.";
        }
    }
}

// Update an existing user's name, email, or role.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'update_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $validRoles = ['Admin', 'User'];

    if ($userId <= 0) {
        $message = "Invalid user selected.";
    } elseif ($fullname === '' || $email === '' || $role === '') {
        $message = "Please fill in all user fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (!in_array($role, $validRoles, true)) {
        $message = "Please select a valid role.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $fullname, $email, $role, $userId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?success=User updated successfully");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not update user. The email may already be registered.";
        }
    }
}

// Delete a user account.
// The current admin cannot delete their own account while logged in.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'delete_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        $message = "Invalid user selected.";
    } elseif ($userId === $currentUserId) {
        $message = "You cannot delete your own account while logged in.";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin.php?success=User deleted successfully");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not delete user.";
        }
    }
}

// Show a success message after redirects like add, edit, approve, or delete.
if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $messageType = 'success';
}

// Load messages sent by users from the user dashboard.
$adminMessages = [];
try {
    $sqlMessages = "SELECT id, sender_name, sender_email, subject, content, status, created_at
                    FROM admin_messages
                    ORDER BY created_at DESC
                    LIMIT 25";
    $resultMessages = $conn->query($sqlMessages);
    if ($resultMessages && $resultMessages->num_rows > 0) {
        while ($row = $resultMessages->fetch_assoc()) {
            $dateObj = new DateTime($row['created_at']);
            $row['formatted_date'] = $dateObj->format('d M Y, g:ia');
            $adminMessages[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    $adminMessages = [];
}

// Load pending room requests so the admin can approve or reject them.
$meetingRequests = [];
try {
    $sqlRequests = "SELECT mr.*, u.fullname AS requester_name
                    FROM meeting_requests mr
                    INNER JOIN users u ON u.id = mr.requester_id
                    WHERE mr.status = 'Pending'
                    ORDER BY mr.created_at ASC";
    $resultRequests = $conn->query($sqlRequests);
    if ($resultRequests && $resultRequests->num_rows > 0) {
        while($row = $resultRequests->fetch_assoc()) {
            $dateObj = new DateTime($row['date']);
            $startObj = new DateTime($row['start_time']);
            $endObj = new DateTime($row['end_time']);

            $row['formatted_date'] = $dateObj->format('d M Y');
            $row['formatted_time'] = $startObj->format('g:ia') . ' - ' . $endObj->format('g:ia');
            $meetingRequests[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    $meetingRequests = [];
}

// Load upcoming room reservations for the "Reserved Room" list.
$reservedRooms = [];
try {
    $sqlRooms = "SELECT room, date, start_time, end_time FROM meetings WHERE status = 'Upcoming' ORDER BY date ASC, start_time ASC";
    $resultRooms = $conn->query($sqlRooms);
    if ($resultRooms && $resultRooms->num_rows > 0) {
        while($row = $resultRooms->fetch_assoc()) {
            $dateObj = new DateTime($row['date']);
            $startObj = new DateTime($row['start_time']);
            $endObj = new DateTime($row['end_time']);
            
            $row['formatted_time'] = $dateObj->format('d M') . ' | ' . $startObj->format('g:ia') . '-' . $endObj->format('g:ia');
            $reservedRooms[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    $reservedRooms = [];
}

// Load all meetings shown as cards on the left side.
$meetings = [];
try {
    $sqlMeetings = "SELECT * FROM meetings ORDER BY date ASC, start_time ASC";
    $resultMeetings = $conn->query($sqlMeetings);
    if ($resultMeetings && $resultMeetings->num_rows > 0) {
        while($row = $resultMeetings->fetch_assoc()) {
            // Format date and time to match the original design
            $dateObj = new DateTime($row['date']);
            $startObj = new DateTime($row['start_time']);
            $endObj = new DateTime($row['end_time']);

            $row['raw_date'] = $row['date'];
            $row['raw_start_time'] = substr($row['start_time'], 0, 5);
            $row['raw_end_time'] = substr($row['end_time'], 0, 5);
            $row['date'] = $dateObj->format('d M Y');
            $row['duration'] = $startObj->format('g:ia') . ' - ' . $endObj->format('g:ia');

            $meetings[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    $meetings = [];
}

// Load all users shown in the user list on the right side.
$users = [];
try {
    $sqlUsers = "SELECT id, fullname, email, role FROM users ORDER BY fullname ASC";
    $resultUsers = $conn->query($sqlUsers);
    if ($resultUsers && $resultUsers->num_rows > 0) {
        while($row = $resultUsers->fetch_assoc()) {
            $users[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    $users = [];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#11072b">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="captcha.png">
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
            height: 120px;
            background-color: #11072b;
            color: white;
            padding: 40px 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .header-wave {
            position: absolute;
            top: 100%;
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
            margin-top: 90px;
        }

        /* --- Left Column: Meetings --- */
        .meetings-section {
            flex: 2;
        }

        .meetings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        /* Individual Meeting Card */
        .meeting-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
            border-top: 5px solid #11072b;
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


        /* Card Action Buttons */
        .card-actions {
            display: flex;
            flex-direction: row;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .card-actions form {
            margin: 0;
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
        .btn-edit-meeting { background-color: #f6d365; color: #000; }
        .btn-trash { background-color: #9d8bb8; color: #000; }

        .pagination {
            text-align: center;
            margin-top: 20px;
            font-size: 1.5rem;
            color: #333;
            letter-spacing: 20px;
            cursor: pointer;
        }

        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            margin-bottom: 24px;
        }

        .action-buttons button,
        .action-buttons a {
            background-color: #11072b;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
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
            padding: 30px 0 20px 0;
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
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }

        .user-info p { font-size: 0.7rem; color: #ccc; margin-bottom: 3px; }
        .user-info h3 { font-size: 1.2rem; font-weight: normal; }

        .request-card {
            background: white;
            color: #333;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.18);
            border-top: 5px solid #11072b;
        }

        .request-card h3 {
            color: #11072b;
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .request-card p {
            font-size: 0.78rem;
            line-height: 1.4;
            margin-bottom: 4px;
        }

        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }

        .request-actions form {
            flex: 1;
        }

        .request-actions button {
            width: 100%;
            border: none;
            padding: 9px 10px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-approve-request { background: #198754; }
        .btn-reject-request { background: #b02a37; }

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

        .user-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-delete-user {
            background: #b02a37;
            color: white;
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

        .btn-add-user {
            background: transparent;
            color: #11072b;
            border: 2px solid #11072b;
            padding: 8px 30px;
            border-radius: 25px;
            font-size: 0.9rem;
            cursor: pointer;
            font-weight: bold;
            margin-top: 18px;
        }

        /* --- Footer --- */
        .footer { padding: 0 60px 40px 60px; }
        .footer a { color: #11072b; font-size: 1.1rem; }

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
        
        .message-modal-content { text-align: center; max-width: 360px; }
        .message-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.5rem;
        }
        .message-icon.success { background: #d1e7dd; color: #0f5132; }
        .message-icon.error { background: #f8d7da; color: #842029; }
        .message-modal-content p { color: #444; line-height: 1.5; margin-bottom: 22px; }
        
        /* --- Floating Icon for Messages --- */
        .message-request-icon {
            position: fixed;
            bottom: 1rem;
            left: 1rem;
            z-index: 1000;
            cursor: pointer;
            transition: transform 0.2s;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            /* Adds a slight dark background so the icon stands out clearly */
            background: #11072b; 
            padding: 10px;
        }
        .message-request-icon:hover {
            transform: scale(1.05);
        }

        /* --- Message Request Modal styling --- */
        .message-request-content {
            background: #11062b; /* Match screenshot dark purple */
            color: white;
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 450px;
            position: relative;
        }
        .message-request-content h2 {
            margin-bottom: 5px;
            color: white;
            text-transform: uppercase;
            font-size: 1.3rem;
            letter-spacing: 0.5px;
        }
        .message-request-divider {
            height: 4px;
            background: white;
            width: 100%;
            margin-bottom: 25px;
            border-radius: 2px;
        }
        .message-request-content .close-modal {
            color: white;
        }
        .message-request-content .close-modal:hover {
            color: #ccc;
        }

        .message-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        .message-list::-webkit-scrollbar {
            width: 6px;
        }
        .message-list::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 10px;
        }

        .message-item {
            background: #d9d9d9;
            border-radius: 20px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #11072b;
            cursor: pointer;
            transition: background 0.2s;
        }
        .message-item:hover {
            background: #c0c0c0;
        }
        .message-item-icon {
            width: 35px;
            height: 35px;
            flex-shrink: 0;
            filter: invert(10%) sepia(45%) saturate(3027%) hue-rotate(249deg) brightness(88%) contrast(105%); /* Adjust icon to match dark text color */
        }
        .message-item-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .message-item-name {
            font-weight: bold;
            font-size: 0.95rem;
        }
        .message-item-title {
            font-size: 0.85rem;
            color: #444;
            overflow-wrap: anywhere;
        }
        .message-item-meta {
            font-size: 0.75rem;
            color: #666;
        }

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
            <div class="action-buttons">
                <a href="admin.php?action=export_meetings">Export as .csv</a>
                <button id="addMeetingBtn">+ Add Meeting</button>
            </div>

            <div class="meetings-box">
                <div class="meetings-grid">
                    
                    <?php foreach ($meetings as $meeting): ?>
                        <div class="meeting-card">
                            <div class="meeting-info">
                                <h4><?php echo htmlspecialchars($meeting['status']); ?></h4>
                                <h3><?php echo htmlspecialchars($meeting['title']); ?></h3>
                                <p><strong>PIC:</strong> <?php echo htmlspecialchars($meeting['pic']); ?></p>
                                <p><strong>Expected Attendees:</strong> <?php echo htmlspecialchars($meeting['attendees']); ?></p>
                                <p><strong>Room:</strong> <?php echo htmlspecialchars($meeting['room']); ?></p>
                                <p><strong>Date:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                                <p><strong>Duration:</strong> <?php echo htmlspecialchars($meeting['duration']); ?></p>
                            </div>
                            <div class="card-actions">
                                <form action="admin.php" method="POST">
                                    <input type="hidden" name="action" value="end_meeting">
                                    <input type="hidden" name="meeting_id" value="<?php echo (int)$meeting['id']; ?>">
                                    <button type="submit" class="icon-btn btn-check" title="Mark meeting as ended" aria-label="Mark meeting as ended">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    class="icon-btn btn-edit-meeting edit-meeting-btn"
                                    title="Edit meeting"
                                    aria-label="Edit meeting"
                                    data-meeting-id="<?php echo (int)$meeting['id']; ?>"
                                    data-meeting-status="<?php echo htmlspecialchars($meeting['status'], ENT_QUOTES); ?>"
                                    data-meeting-title="<?php echo htmlspecialchars($meeting['title'], ENT_QUOTES); ?>"
                                    data-meeting-pic="<?php echo htmlspecialchars($meeting['pic'], ENT_QUOTES); ?>"
                                    data-meeting-attendees="<?php echo (int)$meeting['attendees']; ?>"
                                    data-meeting-room="<?php echo htmlspecialchars($meeting['room'], ENT_QUOTES); ?>"
                                    data-meeting-date="<?php echo htmlspecialchars($meeting['raw_date'], ENT_QUOTES); ?>"
                                    data-meeting-start-time="<?php echo htmlspecialchars($meeting['raw_start_time'], ENT_QUOTES); ?>"
                                    data-meeting-end-time="<?php echo htmlspecialchars($meeting['raw_end_time'], ENT_QUOTES); ?>"
                                >
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <button
                                    type="button"
                                    class="icon-btn btn-trash delete-meeting-btn"
                                    title="Delete meeting"
                                    aria-label="Delete meeting"
                                    data-meeting-id="<?php echo (int)$meeting['id']; ?>"
                                    data-meeting-title="<?php echo htmlspecialchars($meeting['title'], ENT_QUOTES); ?>"
                                >
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
                <div class="pagination">
                    <span>&lt;</span> <span>&gt;</span>
                </div>
            </div>

        </section>

        <div class="divider"></div>

        <section class="users-section">
            <h2>Meeting Requests</h2>
            <div class="users-list">
                <?php if (empty($meetingRequests)): ?>
                    <p style="color: #666; font-style: italic; text-align: center;">No pending requests.</p>
                <?php else: ?>
                    <?php foreach ($meetingRequests as $request): ?>
                        <div class="request-card">
                            <h3><?php echo htmlspecialchars($request['title']); ?></h3>
                            <p><strong>Requested by:</strong> <?php echo htmlspecialchars($request['requester_name']); ?></p>
                            <p><strong>PIC:</strong> <?php echo htmlspecialchars($request['pic']); ?></p>
                            <p><strong>Attendees:</strong> <?php echo htmlspecialchars($request['attendees']); ?></p>
                            <p><strong>Room:</strong> <?php echo htmlspecialchars($request['room']); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($request['formatted_date']); ?></p>
                            <p><strong>Time:</strong> <?php echo htmlspecialchars($request['formatted_time']); ?></p>
                            <div class="request-actions">
                                <form action="admin.php" method="POST">
                                    <input type="hidden" name="action" value="approve_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                    <button type="submit" class="btn-approve-request">Approve</button>
                                </form>
                                <form action="admin.php" method="POST">
                                    <input type="hidden" name="action" value="reject_request">
                                    <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                    <button type="submit" class="btn-reject-request">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2>Reserved Rooms</h2>
            <div class="users-list">
                <?php if (empty($reservedRooms)): ?>
                    <p style="color: #666; font-style: italic; text-align: center;">No rooms reserved.</p>
                <?php else: ?>
                    <?php foreach ($reservedRooms as $room): ?>
                        <div class="user-card">
                            <div class="user-info">
                                <p><?php echo htmlspecialchars($room['formatted_time']); ?></p>
                                <h3><?php echo htmlspecialchars($room['room']); ?></h3>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2>Users List</h2>
            <div class="users-list">
                <?php if (empty($users)): ?>
                    <p style="color: #666; font-style: italic; text-align: center;">No users found.</p>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-card">
                            <div class="user-info">
                                <p><?php echo htmlspecialchars($user['role']); ?></p>
                                <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
                            </div>
                            <div class="user-actions">
                                <button
                                    type="button"
                                    class="btn-edit"
                                    onclick="openEditModal('<?php echo (int)$user['id']; ?>', '<?php echo htmlspecialchars($user['fullname'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['role'], ENT_QUOTES); ?>')"
                                    title="Edit user"
                                    aria-label="Edit user"
                                >
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <form action="admin.php" method="POST" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                    <button type="submit" class="btn-delete-user" title="Delete user" aria-label="Delete user">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button id="addUserBtn" class="btn-add-user">+ Add User</button>
                        
        </section>

        <input class="message-request-icon" id="messageRequestBtn" type="image" src="smsicon.svg" width="60" height="60" alt="View Messages">

    </main>

    <footer class="footer">
    </footer>

    <?php if (!empty($message)): ?>
        <div id="messageModal" class="modal-overlay" style="display: flex;">
            <div class="modal-content message-modal-content">
                <span class="close-modal" id="closeMessageModal">&times;</span>
                <div class="message-icon <?php echo htmlspecialchars($messageType); ?>">
                    <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-check' : 'fa-triangle-exclamation'; ?>"></i>
                </div>
                <h2><?php echo $messageType === 'success' ? 'Success' : 'Notice'; ?></h2>
                <p><?php echo htmlspecialchars($message); ?></p>
                <button type="button" id="messageModalOkBtn">OK</button>
            </div>
        </div>
    <?php endif; ?>

    <div id="deleteMeetingModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center;">
            <span class="close-modal" id="closeDeleteMeetingModal">&times;</span>
            <div class="message-icon error">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h2>Delete Meeting</h2>
            <p style="color: #444; line-height: 1.5; margin-bottom: 20px;">
                Are you sure you want to delete <strong id="deleteMeetingTitle">this meeting</strong>? This action cannot be undone.
            </p>
            <form action="admin.php" method="POST" id="deleteMeetingForm">
                <input type="hidden" name="action" value="delete_meeting">
                <input type="hidden" name="meeting_id" id="deleteMeetingId">
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" id="cancelDeleteMeetingBtn" style="background: #e9ecef; color: #11072b;">Cancel</button>
                    <button type="submit" style="background: #b02a37;">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editMeetingModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeEditMeetingModal">&times;</span>
            <h2>Edit Meeting</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="update_meeting">
                <input type="hidden" id="edit_meeting_id" name="meeting_id">
                <select id="edit_meeting_status" name="meeting_status" required>
                    <option value="Upcoming">Upcoming</option>
                    <option value="Ended">Ended</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <input type="text" id="edit_meeting_title" name="meeting_title" placeholder="Meeting Title" required>
                <input type="text" id="edit_meeting_pic" name="meeting_pic" placeholder="Person In Charge (PIC)" required>
                <input type="number" id="edit_meeting_attendees" name="meeting_attendees" placeholder="Expected Attendees" min="1" required>
                <input type="date" id="edit_meeting_date" name="meeting_date" required>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label for="edit_meeting_start_time" style="flex-shrink: 0;">From:</label>
                    <input id="edit_meeting_start_time" type="time" name="meeting_start_time" required style="width: 100%;">
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label for="edit_meeting_end_time" style="flex-shrink: 0;">To:</label>
                    <input id="edit_meeting_end_time" type="time" name="meeting_end_time" required style="width: 100%;">
                </div>
                <input type="text" id="edit_meeting_room" name="meeting_room" placeholder="Room Number" required>
                <button type="submit">Update Meeting</button>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h2>Edit User</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" id="editUserId" name="user_id">
                <input type="text" id="editUserName" name="fullname" placeholder="Full Name" required>
                <input type="email" id="editUserEmail" name="email" placeholder="Email" required>
                <select id="editUserRole" name="role" required>
                    <option value="Admin">Admin</option>
                    <option value="User">User</option>
                </select>
                <button type="submit">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="addUserModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeAddUserModal">&times;</span>
            <h2>Add New User</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="add_user">
                <input type="text" name="fullname" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Enter email" required>
                <select name="role" required>
                    <option value="" disabled selected hidden>Select Role</option>
                    <option value="Admin">Admin</option>
                    <option value="User">User</option>
                </select>
                <input type="password" name="password" placeholder="Create Password" required>
                <button type="submit">Add User</button>
            </form>
        </div>
    </div>

    <div id="addMeetingModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeAddMeetingModal">&times;</span>
            <h2>Add New Meeting</h2>
            <form id="meetingDetailsForm">
                <input type="text" id="meeting_title" name="meeting_title" placeholder="Meeting Title" required>
                <input type="text" id="meeting_pic" name="meeting_pic" placeholder="Person In Charge (PIC)" required>
                <input type="number" id="meeting_attendees" name="meeting_attendees" placeholder="Expected Attendees" required>
                <input type="date" id="meeting_date" name="meeting_date" required>
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

    <div id="reserveRoomModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeReserveRoomModal">&times;</span>
            <h2>Reserve Room</h2>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="add_meeting">
                <input type="hidden" id="hidden_meeting_title" name="meeting_title">
                <input type="hidden" id="hidden_meeting_pic" name="meeting_pic">
                <input type="hidden" id="hidden_meeting_attendees" name="meeting_attendees">
                <input type="hidden" id="hidden_meeting_date" name="meeting_date">
                <input type="hidden" id="hidden_meeting_start_time" name="meeting_start_time">
                <input type="hidden" id="hidden_meeting_end_time" name="meeting_end_time">
                <input type="text" name="meeting_room" placeholder="Enter Room Number" required>
                <button type="submit">Confirm Reservation</button>
            </form>
        </div>
    </div>

    <div id="messageRequestModal" class="modal-overlay">
        <div class="message-request-content">
            <span class="close-modal" id="closeMessageRequestModal">&times;</span>
            <h2>MESSAGE REQUEST</h2>
            <div class="message-request-divider"></div>
            
            <div class="message-list">
                <?php if (empty($adminMessages)): ?>
                    <p style="color: #ccc; font-style: italic; text-align: center;">No new message requests.</p>
                <?php else: ?>
                    <?php foreach ($adminMessages as $msg): ?>
                        <?php
                            $alertMessage = "Message from " . $msg['sender_name'] . " <" . $msg['sender_email'] . ">\n";
                            $alertMessage .= "Sent: " . $msg['formatted_date'] . "\n\n";
                            $alertMessage .= $msg['content'];
                        ?>
                        <div class="message-item" onclick="alert(<?php echo htmlspecialchars(json_encode($alertMessage), ENT_QUOTES, 'UTF-8'); ?>)">
                            <img src="smsicon.svg" class="message-item-icon" alt="Message Icon">
                            <div class="message-item-text">
                                <span class="message-item-name"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                <span class="message-item-title">"<?php echo htmlspecialchars($msg['subject']); ?>"</span>
                                <span class="message-item-meta"><?php echo htmlspecialchars($msg['sender_email']); ?> | <?php echo htmlspecialchars($msg['formatted_date']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Opens the edit user popup and fills it with the selected user's data.
        const editModal = document.getElementById('editUserModal');
        const closeModalBtn = document.getElementById('closeModal');

        function openEditModal(id, name, email, role) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editUserName').value = name;
            document.getElementById('editUserEmail').value = email;
            document.getElementById('editUserRole').value = role;
            editModal.style.display = 'flex';
        }

        closeModalBtn.addEventListener('click', () => editModal.style.display = 'none');

        // Opens and closes the add user popup.
        const addUserModal = document.getElementById('addUserModal');
        const closeAddUserModalBtn = document.getElementById('closeAddUserModal');
        const addUserBtn = document.getElementById('addUserBtn');

        addUserBtn.addEventListener('click', () => {
            addUserModal.style.display = 'flex';
        });

        closeAddUserModalBtn.addEventListener('click', () => addUserModal.style.display = 'none');

        // Opens and closes the first step of adding a meeting.
        const addMeetingModal = document.getElementById('addMeetingModal');
        const closeAddMeetingModalBtn = document.getElementById('closeAddMeetingModal');
        const addMeetingBtn = document.getElementById('addMeetingBtn');

        addMeetingBtn.addEventListener('click', () => {
            addMeetingModal.style.display = 'flex';
        });

        closeAddMeetingModalBtn.addEventListener('click', () => addMeetingModal.style.display = 'none');

        // Moves meeting details from step 1 into hidden fields for the room step.
        const proceedToRoomBtn = document.getElementById('proceedToRoomBtn');
        const reserveRoomModal = document.getElementById('reserveRoomModal');
        const closeReserveRoomModalBtn = document.getElementById('closeReserveRoomModal');

        proceedToRoomBtn.addEventListener('click', () => {
            const meetingDetailsForm = document.getElementById('meetingDetailsForm');
            if (!meetingDetailsForm.reportValidity()) {
                return;
            }

            document.getElementById('hidden_meeting_title').value = document.getElementById('meeting_title').value;
            document.getElementById('hidden_meeting_pic').value = document.getElementById('meeting_pic').value;
            document.getElementById('hidden_meeting_attendees').value = document.getElementById('meeting_attendees').value;
            document.getElementById('hidden_meeting_date').value = document.getElementById('meeting_date').value;
            document.getElementById('hidden_meeting_start_time').value = document.getElementById('start_time').value;
            document.getElementById('hidden_meeting_end_time').value = document.getElementById('end_time').value;

            addMeetingModal.style.display = 'none';
            reserveRoomModal.style.display = 'flex';
        });

        closeReserveRoomModalBtn.addEventListener('click', () => reserveRoomModal.style.display = 'none');

        const messageModal = document.getElementById('messageModal');
        const closeMessageModalBtn = document.getElementById('closeMessageModal');
        const messageModalOkBtn = document.getElementById('messageModalOkBtn');

        // Remove the success value from the URL so refreshing does not show the popup again.
        if (messageModal && window.history.replaceState) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.has('success')) {
                currentUrl.searchParams.delete('success');
                window.history.replaceState({}, '', currentUrl.pathname + currentUrl.search + currentUrl.hash);
            }
        }

        function closeMessageModal() {
            if (messageModal) {
                messageModal.style.display = 'none';
            }
        }

        if (closeMessageModalBtn) {
            closeMessageModalBtn.addEventListener('click', closeMessageModal);
        }
        if (messageModalOkBtn) {
            messageModalOkBtn.addEventListener('click', closeMessageModal);
        }

        // Opens the delete meeting confirmation popup with the selected meeting name.
        const deleteMeetingModal = document.getElementById('deleteMeetingModal');
        const closeDeleteMeetingModalBtn = document.getElementById('closeDeleteMeetingModal');
        const cancelDeleteMeetingBtn = document.getElementById('cancelDeleteMeetingBtn');
        const deleteMeetingIdInput = document.getElementById('deleteMeetingId');
        const deleteMeetingTitle = document.getElementById('deleteMeetingTitle');

        document.querySelectorAll('.delete-meeting-btn').forEach((button) => {
            button.addEventListener('click', () => {
                deleteMeetingIdInput.value = button.dataset.meetingId;
                deleteMeetingTitle.innerText = button.dataset.meetingTitle || 'this meeting';
                deleteMeetingModal.style.display = 'flex';
            });
        });

        function closeDeleteMeetingModal() {
            deleteMeetingModal.style.display = 'none';
            deleteMeetingIdInput.value = '';
            deleteMeetingTitle.innerText = 'this meeting';
        }

        closeDeleteMeetingModalBtn.addEventListener('click', closeDeleteMeetingModal);
        cancelDeleteMeetingBtn.addEventListener('click', closeDeleteMeetingModal);

        const editMeetingModal = document.getElementById('editMeetingModal');
        const closeEditMeetingModalBtn = document.getElementById('closeEditMeetingModal');

        // Opens the edit meeting popup and copies the card data into the form fields.
        document.querySelectorAll('.edit-meeting-btn').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('edit_meeting_id').value = button.dataset.meetingId;
                document.getElementById('edit_meeting_status').value = button.dataset.meetingStatus;
                document.getElementById('edit_meeting_title').value = button.dataset.meetingTitle;
                document.getElementById('edit_meeting_pic').value = button.dataset.meetingPic;
                document.getElementById('edit_meeting_attendees').value = button.dataset.meetingAttendees;
                document.getElementById('edit_meeting_room').value = button.dataset.meetingRoom;
                document.getElementById('edit_meeting_date').value = button.dataset.meetingDate;
                document.getElementById('edit_meeting_start_time').value = button.dataset.meetingStartTime;
                document.getElementById('edit_meeting_end_time').value = button.dataset.meetingEndTime;
                editMeetingModal.style.display = 'flex';
            });
        });

        closeEditMeetingModalBtn.addEventListener('click', () => editMeetingModal.style.display = 'none');

        // View Message Requests Logic
        const messageRequestModal = document.getElementById('messageRequestModal');
        const messageRequestBtn = document.getElementById('messageRequestBtn');
        const closeMessageRequestModalBtn = document.getElementById('closeMessageRequestModal');

        messageRequestBtn.addEventListener('click', () => {
            messageRequestModal.style.display = 'flex';
        });

        closeMessageRequestModalBtn.addEventListener('click', () => {
            messageRequestModal.style.display = 'none';
        });

        // Clicking the dark background outside a popup closes that popup.
        window.addEventListener('click', (e) => {
            if (e.target === messageModal) closeMessageModal();
            if (e.target === deleteMeetingModal) closeDeleteMeetingModal();
            if (e.target === editMeetingModal) editMeetingModal.style.display = 'none';
            if (e.target === editModal) editModal.style.display = 'none';
            if (e.target === addUserModal) addUserModal.style.display = 'none';
            if (e.target === addMeetingModal) addMeetingModal.style.display = 'none';
            if (e.target === reserveRoomModal) reserveRoomModal.style.display = 'none';
            if (e.target === messageRequestModal) messageRequestModal.style.display = 'none';
        });
    </script>
    <script>
        // Registers the service worker so the app can cache basic files for PWA support.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch((error) => {
                    console.error('Service worker registration failed:', error);
                });
            });
        }
    </script>

</body>
</html>
