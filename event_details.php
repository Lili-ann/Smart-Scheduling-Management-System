<?php
session_start();
require_once "db.php";

$isVisitor = !empty($_SESSION['visitor_access']);
$isAdmin = (($_SESSION['user_role'] ?? '') === 'Admin');
$isStaff = (($_SESSION['user_role'] ?? '') === 'User');
$userId = (int)($_SESSION['user_id'] ?? 0);

// Staff/Admin can use login. Visitors can view after entering an invitation code.
if (!$isStaff && !$isAdmin && !$isVisitor) {
    header("Location: login.php?error=Please log in or enter a visitor code to view event details");
    exit();
}

// 2. Get the Event ID from the URL
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventId <= 0) {
    die("Invalid Event ID.");
}

// 3. Fetch Event Details
$stmt = $conn->prepare(
    "SELECT e.*, u.fullname AS assigned_staff_name
     FROM events e
     LEFT JOIN users u ON u.id = e.assigned_staff_id
     WHERE e.id = ?"
);
$stmt->bind_param("i", $eventId);
$stmt->execute();
$eventResult = $stmt->get_result();

if ($eventResult->num_rows === 0) {
    die("Event not found.");
}
$event = $eventResult->fetch_assoc();
$stmt->close();

if ($isStaff && (int)($event['assigned_staff_id'] ?? 0) !== $userId) {
    header("Location: events.php?error=You can only view events assigned to you");
    exit();
}

// 4. Fetch Gallery Images for this event
$galleryStmt = $conn->prepare("SELECT image_path FROM event_gallery WHERE event_id = ? ORDER BY id ASC");
$galleryStmt->bind_param("i", $eventId);
$galleryStmt->execute();
$galleryResult = $galleryStmt->get_result();

$galleryImages = [];
while ($row = $galleryResult->fetch_assoc()) {
    $galleryImages[] = $row['image_path'];
}
$galleryStmt->close();

// Format the date for display
$dateObj = new DateTime($event['date']);
$formattedDate = $dateObj->format('M d, Y');
$startObj = new DateTime($event['start_time']);
$endObj = new DateTime($event['end_time']);
$formattedTime = $startObj->format('h:i A') . ' - ' . $endObj->format('h:i A');
$backUrl = $isVisitor ? 'events.php' : ($isAdmin ? 'admin.php' : 'events.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Recap</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Light UI styling */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        
        body { 
            background-color: #f8f9fa; 
            color: #333; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
        }

        /* Top Header Area matching main app */
        .header-container {
            position: relative;
            width: 100%;
            height: 140px;
            background-color: #11072b;
            color: white;
            padding: 30px 60px;
            display: flex;
            align-items: center;
            z-index: 2;
        }

        .header-wave {
            position: absolute;
            top: 99%;
            left: 0;
            width: 100%;
            height: 60px;
            z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 100' preserveAspectRatio='none'%3E%3Cpath d='M0,0 L1000,0 L1000,30 C750,10 250,120 0,80 Z' fill='%2311072b'/%3E%3C/svg%3E");
            background-size: 100% 100%;
        }

        .header-title-wrapper {
            z-index: 2;
        }

        .header-container h1 { 
            font-size: 2.2rem; 
            font-weight: 600; 
            margin: 0;
        }

        /* Main Content Container */
        .main-content {
            width: 100%;
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 40px;
            position: relative;
            z-index: 5;
        }

        .container-card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            padding: 35px;
        }
        
        /* Navigation / Back Button styling */
        .back-btn-wrapper {
            margin-bottom: 25px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #11062b;
            color: white;
            padding: 10px 24px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.85rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: background-color 0.2s, transform 0.2s;
        }
        
        .back-btn:hover {
            background-color: #1f0b4d;
            transform: translateY(-1px);
        }

        /* Two Column Grid layout */
        .details-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
            gap: 40px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .details-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Left Side Panel */
        .details-left {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .details-poster {
            width: 100%;
            max-height: 420px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .event-info-title {
            color: #11062b;
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .event-description {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        /* Meta Table styling */
        .event-meta-table {
            font-size: 0.9rem;
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: #fdfdfd;
            border-radius: 6px;
        }

        .event-meta-table td {
            padding: 10px 0;
            vertical-align: middle;
            color: #444;
            border-bottom: 1px solid #f0f0f0;
        }

        .event-meta-table tr:last-child td {
            border-bottom: none;
        }

        .event-meta-table td:first-child {
            width: 110px;
            font-weight: bold;
            color: #11062b;
        }

        .event-meta-table i {
            color: #11062b;
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        /* Right Side Gallery Panel */
        .details-right {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .gallery-section-label {
            color: #11062b;
            font-weight: 700;
            font-size: 1.2rem;
            border-bottom: 2px solid #11062b;
            padding-bottom: 8px;
            margin-bottom: 5px;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        @media (max-width: 480px) {
            .gallery-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .gallery-thumb {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 6px;
            object-fit: cover;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            border: 1px solid rgba(0,0,0,0.04);
        }

        .gallery-thumb:hover {
            transform: scale(1.04);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .no-highlights {
            color: #777;
            font-style: italic;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="header-container">
        <div class="header-title-wrapper">
            <h1>Event Recap & Highlights</h1>
        </div>
        <div class="header-wave"></div>
    </div>

    <main class="main-content">
        <div class="back-btn-wrapper">
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Events
            </a>
        </div>

        <div class="container-card">
            <div class="details-layout">
                
                <div class="details-left">
                    <?php if (!empty($event['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($event['image_path']); ?>" alt="Event Poster" class="details-poster">
                    <?php endif; ?>
                    
                    <h2 class="event-info-title"><?php echo htmlspecialchars($event['title']); ?></h2>
                    <p class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    
                    <table class="event-meta-table">
                        <tr>
                            <td><i class="fas fa-calendar-alt"></i> Date</td>
                            <td><?php echo $formattedDate; ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-clock"></i> Time</td>
                            <td><?php echo $formattedTime; ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-map-marker-alt"></i> Location</td>
                            <td><?php echo htmlspecialchars($event['room']); ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-user-tie"></i> Staff</td>
                            <td><?php echo htmlspecialchars($event['assigned_staff_name'] ?? 'Unassigned'); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="details-right">
                    <h3 class="gallery-section-label">Event Highlights</h3>
                    
                    <?php if (count($galleryImages) > 0): ?>
                        <div class="gallery-grid">
                            <?php foreach ($galleryImages as $img): ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="Recap Photo" class="gallery-thumb" onclick="window.open(this.src, '_blank');">
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-highlights">No highlights photos available for this event yet.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

</body>
</html>
