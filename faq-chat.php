// the like faux faq chat

<?php
// Make sure no extra spaces or HTML exist outside the PHP tags in this file!
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Our Database of Answers
    $faqDb = [
        "q_invite" => "Invitation codes are automatically sent to your registered email 24 hours before the event starts. Check your spam folder if you don't see it.",
        "q_contact" => "You can contact event staff by clicking the 'Support' ticket icon on your dashboard, or by emailing staff@events.com.",
        "q_password" => "To reset your password, log out and click on 'Forgot Password' on the main login screen.",
        "q_events" => "Staff can view assigned events from the staff dashboard. Admins can create, edit, and assign events from the admin dashboard."
    ];

    // Keywords to match if the user decides to type instead of click
    $keywordMap = [
        "invite" => "q_invite",
        "invitation" => "q_invite",
        "contact" => "q_contact",
        "staff" => "q_contact",
        "password" => "q_password",
        "event" => "q_events",
        "events" => "q_events"
    ];

    $response = "I'm sorry, I couldn't find an exact answer for that. Would you like to ask our staff directly?";
    $showContactBtn = true; // Default to showing the contact button for unknown queries

    // Check if they clicked a button (sent an ID)
    if (!empty($input['id']) && isset($faqDb[$input['id']])) {
        $response = $faqDb[$input['id']];
        $showContactBtn = false; // We found the answer, no need for the button
    } 
    // Check if they typed a message
    elseif (!empty($input['message'])) {
        $userMessage = strtolower(trim($input['message']));
        foreach ($keywordMap as $keyword => $id) {
            if (strpos($userMessage, $keyword) !== false) {
                $response = $faqDb[$id];
                $showContactBtn = false; // We found a keyword match
                break;
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        "reply" => $response,
        "showContactBtn" => $showContactBtn
    ]);
    exit; 
}
?>