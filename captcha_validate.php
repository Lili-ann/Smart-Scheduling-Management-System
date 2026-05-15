<?php
session_start();

// CONFIGURE THIS ARRAY WITH THE CORRECT SQUARES (0-24)
// The grid is numbered as follows:
// 0  1  2  3  4
// 5  6  7  8  9
// 10 11 12 13 14
// 15 16 17 18 19
// 20 21 22 23 24
// 
// If you want you can change the correct squares to be whatever you want depending on which squares contain a man (Kaveh from genshin impact)

$correctSquares = array(7, 12, 17, 22); // This here shows where Kaveh is standing

header('Content-Type: application/json');

// Get the squares selected by the user from the POST request in the login page
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['selected']) || !is_array($input['selected'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit();
}

$selected = $input['selected'];

// Sort both arrays so we can compare them
sort($correctSquares);
sort($selected);

// Check if the selected squares match the correct squares exactly, and if it works, then we throw a success.
if ($selected === $correctSquares) {
    $_SESSION['captcha_verified'] = true;
    echo json_encode([
        'success' => true,
        'message' => 'Verification successful'
    ]);
} else {
    // For security, don't reveal which squares are correct when the person gets it wrong.
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect selection. Please try again.'
    ]);
}
?>
