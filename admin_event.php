// this is the part that allows admin and staff roles to edit event details, upload images to the event gallery, and extend event times
// it includes security checks to ensure that staff can only edit events assigned to them, and it handles file uploads
// how? by validating file types and storing them in a designated directory which is in the dabatase

<?php
session_start();
require_once "db.php";

// Allow Admin and Staff
$role = $_SESSION['user_role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($role !== 'Admin' && $role !== 'Staff') {
    header("Location: login.php?error=Access Denied");
    exit();
}

$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($eventId <= 0) {
    die("Invalid Event ID.");
}

// 1. FETCH EVENT FIRST FOR SECURITY
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    die("Event not found.");
}

// 2. STRICT SECURITY CHECK FOR STAFF
if ($role === 'Staff' && (int)$event['assigned_staff_id'] !== $userId) {
    header("Location: user.php?error=Access Denied. You can only edit your own assigned events.");
    exit();
}

// Fetch session flash data if available across the redirect
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? '';

// Clear flash data immediately so it doesn't linger on subsequent refreshes
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Create uploads directory if it doesn't exist
if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

// 3. HANDLE FORM ACTIONS (Now fully secured)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_event') {
            $title = trim($_POST['title'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $room = trim($_POST['room'] ?? '');
            $date = trim($_POST['date'] ?? '');
            $imagePath = trim($_POST['image_path'] ?? ''); // <-- Grabs the new image URL
            
            $stmt = $conn->prepare("UPDATE events SET title=?, description=?, room=?, date=?, image_path=? WHERE id=?");
            $stmt->bind_param("sssssi", $title, $desc, $room, $date, $imagePath, $eventId);
            $stmt->execute();
        }
    elseif ($action === 'extend_time') {
        $extDate = trim($_POST['extended_date'] ?? '');
        $extStart = trim($_POST['extended_start'] ?? '');
        $extEnd = trim($_POST['extended_end'] ?? '');
        
        $stmt = $conn->prepare("UPDATE events SET date=?, start_time=?, end_time=? WHERE id=?");
        $stmt->bind_param("sssi", $extDate, $extStart, $extEnd, $eventId);
        $stmt->execute();
    } 
    elseif ($action === 'upload_image') {
        if (isset($_FILES['gallery_file']) && $_FILES['gallery_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['gallery_file']['tmp_name'];
            $origName = basename($_FILES['gallery_file']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowedExtensions)) {
                $fileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = "uploads/" . $fileName;
                
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmt = $conn->prepare("INSERT INTO event_gallery (event_id, image_path) VALUES (?, ?)");
                    $stmt->bind_param("is", $eventId, $targetPath);
                    $stmt->execute();
                }
            }
        }
    }
    elseif ($action === 'delete_image') {
        $imgId = (int)($_POST['image_id'] ?? 0);
        
        $stmt = $conn->prepare("SELECT image_path FROM event_gallery WHERE id = ? AND event_id = ?");
        $stmt->bind_param("ii", $imgId, $eventId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        
        if ($res && file_exists($res['image_path'])) {
            unlink($res['image_path']); 
        }
        
        $stmtDel = $conn->prepare("DELETE FROM event_gallery WHERE id = ? AND event_id = ?");
        $stmtDel->bind_param("ii", $imgId, $eventId);
        $stmtDel->execute();
    }
    
    header("Location: admin_event.php?id=" . $eventId);
    exit();
}

// Fetch gallery data
$galleryRes = $conn->query("SELECT * FROM event_gallery WHERE event_id = $eventId ORDER BY uploaded_at DESC");
$galleries = $galleryRes->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - <?= htmlspecialchars($event['title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        body { background-color: #ffffff; color: #000; overflow-x: hidden; min-height: 100vh; position: relative; }

        :root {
            --primary-color: #11062B;
            --primary-hover: #1f0b4d;
        }

        .top-bg-curve {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 200px;
            z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320' preserveAspectRatio='none'%3E%3Cpath fill='%2311062B' d='M0,192 C360,240 720,160 1080,96 C1260,64 1350,48 1440,48 L1440,0 L0,0 Z'%3E%3C/path%3E%3C/svg%3E");
            background-size: 100% 100%;
            background-repeat: no-repeat;
        }

        .header-content {
            position: relative; 
            z-index: 10; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            padding: 40px 60px 0 60px;
            height: 100px;
        }
        .header-content a.back-link {
            color: #fff; 
            text-decoration: underline; 
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Main Layout */
        .container {
            position: relative; 
            z-index: 10; 
            max-width: 1300px; 
            margin: 40px auto;
            display: grid; 
            grid-template-columns: 1.1fr 0.9fr; 
            gap: 60px; 
            padding: 80px 40px;;
            align-items: start;
        }

        /* Left Side Column Elements */
        .event-poster {
            width: 100%; 
            max-height: 420px; 
            object-fit: cover;
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); 
        }
        
        .edit-block {
            background: #E5E5E5; 
            padding: 15px 20px; 
            border-radius: 12px 12px 0 0;
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); 
        }
        .edit-block input {
            font-size: 1.3rem; 
            font-weight: bold; 
            background: transparent; 
            border: none; 
            outline: none; 
            width: 90%; 
            color: #11062B;
        }
        .desc-block {
            background: #DDDDDD; 
            padding: 15px 20px; 
            border-radius: 0 0 12px 12px; 
            margin-bottom: 30px;
        }
        .desc-block textarea {
            width: 100%; 
            background: transparent; 
            border: none; 
            outline: none; 
            resize: none; 
            color: #555; 
            height: 60px;
            font-size: 0.95rem;
        }

        .form-row { 
            display: flex; 
            align-items: center; 
            margin-bottom: 20px; 
        }
        .form-row > label { 
            width: 80px; 
            font-weight: bold; 
            font-size: 0.95rem; 
            color: #333;
        }
        
        .input-pill {
            border: 1px solid #11062B; 
            border-radius: 20px; 
            padding: 8px 20px; 
            flex: 1; 
            max-width: 280px;
            font-size: 0.9rem; 
            text-align: center; 
            outline: none;
            background-color: #fff;
        }
        
        .time-box {
            background: #E5E5E5; 
            border: none; 
            border-radius: 20px; 
            padding: 8px 15px;
            width: 110px; 
            text-align: center; 
            margin-right: 10px; 
            color: #666;
            font-size: 0.85rem;
            border: 1px solid #11062B;
        }
        
        .btn-extend {
            background: #7B3232; 
            color: #fff; 
            border: none; 
            border-radius: 20px;
            padding: 8px 20px; 
            cursor: pointer; 
            font-weight: bold; 
            display: flex; 
            align-items: center; 
            gap: 5px;
            font-size: 0.9rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
        }

        /* Right Side Column Layout Components */
        .right-title { 
            font-size: 1.8rem; 
            font-weight: bold; 
            color: #11062B; 
            margin-bottom: 25px; 
        }
        
        .upload-zone {
            background: #F4F4F4; 
            border-radius: 16px; 
            border: 1px solid #E0E0E0;
            padding: 45px 20px; 
            text-align: center; 
            margin-bottom: 35px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
        }
        .upload-icon { 
            font-size: 3.5rem; 
            color: #A0A0A0; 
            margin-bottom: 12px; 
            display: block; 
        }
        .upload-zone p { 
            color: #555; 
            margin-bottom: 8px; 
            font-size: 1rem; 
        }
        .upload-zone a { 
            color: #0066cc; 
            font-size: 0.85rem; 
            text-decoration: underline; 
            margin-bottom: 20px; 
            display: block; 
        }

        .btn-upload-img {
            background: #11062B; 
            color: #fff; 
            border: none; 
            border-radius: 20px;
            padding: 10px 35px; 
            font-size: 0.95rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); 
            cursor: pointer; 
            font-weight: bold;
        }

        .gallery-list { 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
        }
        .gallery-item {
            background: #fff; 
            border-radius: 12px; 
            padding: 12px 20px;
            display: flex; 
            align-items: center; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); 
            border: 1px solid #eee;
        }
        .gallery-item img {
            width: 55px; 
            height: 55px; 
            object-fit: cover; 
            border-radius: 10px; 
            margin-right: 20px;
        }
        .gallery-item span { 
            font-size: 1.05rem; 
            color: #333; 
            font-weight: 500;
        }

        .btn-save {
            background: #11062B; 
            color: #fff; 
            border: none; 
            border-radius: 20px;
            padding: 10px 50px; 
            font-weight: bold; 
            cursor: pointer; 
            display: block; 
            margin: 40px auto 0;
            font-size: 1rem;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            
        }

        /* Modal Elements */
        .modal-overlay {
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100vw; 
            height: 100vh;
            background: rgba(0, 0, 0, 0.6); 
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-box {
            position: absolute; 
            border-radius: 20px; 
            padding: 30px; 
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); 
            width: 450px; 
            cursor: grab;
        }
        .modal-box:active { cursor: grabbing; }
        .modal-box h2 { text-align: center; margin-bottom: 25px; font-size: 1.4rem; }
        
        #modalTimeBox { background: #8C3A3A; }
        .modal-row { display: flex; align-items: center; margin-bottom: 20px; justify-content: space-between;}
        .modal-row label { font-size: 0.9rem; flex-shrink: 0; width: 110px; }
        .modal-input {
            background: transparent; border: 1px solid rgba(255,255,255,0.7); border-radius: 20px;
            color: white; padding: 8px 15px; outline: none; width: 100%; text-align: center;
        }
        .modal-input::placeholder { color: rgba(255,255,255,0.6); }
        .time-split { display: flex; gap: 10px; width: 100%; }
        
        .btn-modal-submit {
            background: #fff; color: #000; border: none; border-radius: 25px;
            padding: 10px 30px; font-weight: bold; display: block; margin: 25px auto 0; cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="top-bg-curve"></div>

    <div class="header-content">
        <a href="<?= $role === 'Admin' ? 'admin.php' : 'user.php' ?>" class="back-link">Back</a>
    </div>

    <form id="mainSaveForm" method="POST" action="admin_event.php?id=<?= $eventId ?>"></form>
    <input type="hidden" name="action" value="save_event" form="mainSaveForm">

    <div class="container">
        <div class="left-col">
            <img src="<?= htmlspecialchars($event['image_path'] ?? 'https://via.placeholder.com/800x600') ?>" alt="Event Poster" class="event-poster">
            
            <div class="edit-block">
                <input type="text" name="title" value="<?= htmlspecialchars($event['title']) ?>" form="mainSaveForm">
                <span><i class="fa-solid fa-pen-to-square" style="color: #11062B;"></i></span>
            </div>
            <div class="desc-block">
                <textarea name="description" placeholder="Description" form="mainSaveForm"><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <label>Poster URL:</label>
                <input type="text" name="image_path" value="<?= htmlspecialchars($event['image_path'] ?? '') ?>" class="input-pill" form="mainSaveForm" placeholder="Paste image URL here">
            </div>

            <div class="form-row">
                <label>Room:</label>
                <input type="text" name="room" value="<?= htmlspecialchars($event['room'] ?? '') ?>" class="input-pill" form="mainSaveForm" placeholder="Room 606">
            </div>

            <div class="form-row">
                <label>Date:</label>
                <input type="date" name="date" value="<?= htmlspecialchars($event['date']) ?>" class="input-pill" form="mainSaveForm">
            </div>

            <div class="form-row">
                <label>Time:</label>
                <input type="text" value="<?= htmlspecialchars(substr($event['start_time'], 0, 5)) ?>" class="time-box" readonly>
                <input type="text" value="<?= htmlspecialchars(substr($event['end_time'], 0, 5)) ?>" class="time-box" readonly>
                <button type="button" class="btn-extend" onclick="openModal('timeModal')">Extend <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.8rem;"></i></button>
            </div>
        </div>

        <div class="right-col">
            <h2 class="right-title">Event Recap</h2>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm" action="admin_event.php?id=<?= $eventId ?>">
                <input type="hidden" name="action" value="upload_image">
                <div class="upload-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <span class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                    <p>Drag and drop files here to Upload</p>
                    <a href="#">Select Image from device</a>
                    <input type="file" name="gallery_file" id="fileInput" style="display: none;" onchange="document.getElementById('uploadForm').submit()">
                    <button type="button" class="btn-upload-img">Upload Image</button>
                </div>
            </form>

            <div class="gallery-list">
                <?php foreach($galleries as $img): ?>
                    <div class="gallery-item">
                        <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Gallery Image">
                        <span><?= htmlspecialchars(basename($img['image_path'])) ?></span>
                        
                        <form method="POST" action="admin_event.php?id=<?= $eventId ?>" style="margin-left: auto;">
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                            <button type="submit" style="background: none; border: none; cursor: pointer; color: #7B3232;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" form="mainSaveForm" class="btn-save">SAVE</button>
        </div>
    </div>


    <div id="timeModal" class="modal-overlay">
        <div class="modal-box" id="modalTimeBox">
            <h2>Time Extension</h2>
            <form method="POST" action="admin_event.php?id=<?= $eventId ?>">
                <input type="hidden" name="action" value="extend_time">
                <div class="modal-row">
                    <label>Extended Date:</label>
                    <input type="date" name="extended_date" class="modal-input" value="<?= htmlspecialchars($event['date']) ?>">
                </div>
                <div class="modal-row">
                    <label>Extended Time:</label>
                    <div class="time-split">
                        <input type="time" name="extended_start" class="modal-input" value="<?= htmlspecialchars($event['start_time']) ?>">
                        <input type="time" name="extended_end" class="modal-input" value="<?= htmlspecialchars($event['end_time']) ?>">
                    </div>
                </div>
                <button type="submit" class="btn-modal-submit">Save</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            const overlay = document.getElementById(id);
            overlay.style.display = 'flex';
            
            const box = overlay.querySelector('.modal-box');
            box.style.top = (window.innerHeight / 2 - box.offsetHeight / 2) + 'px';
            box.style.left = (window.innerWidth / 2 - box.offsetWidth / 2) + 'px';
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('mousedown', function(e) {
                if(e.target === this) this.style.display = 'none';
            });
        });

        function makeDraggable(boxId) {
            const el = document.getElementById(boxId);
            let isDragging = false, startX, startY, initialX, initialY;

            el.addEventListener('mousedown', (e) => {
                if(['INPUT', 'BUTTON'].includes(e.target.tagName)) return;
                
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                initialX = el.offsetLeft;
                initialY = el.offsetTop;
            });

            document.addEventListener('mousemove', (e) => {
                if(!isDragging) return;
                e.preventDefault();
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                el.style.left = (initialX + dx) + 'px';
                el.style.top = (initialY + dy) + 'px';
            });

            document.addEventListener('mouseup', () => {
                isDragging = false;
            });
        }

        makeDraggable('modalTimeBox');

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>