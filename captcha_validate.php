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
// Modify the array below to match which squares contain a man in captcha.png

$correctSquares = array(7, 12, 17, 22); // EXAMPLE - UPDATE THIS WITH ACTUAL CORRECT SQUARES

header('Content-Type: application/json');

// Get the selected squares from the request
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['selected']) || !is_array($input['selected'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit();
}

$selected = $input['selected'];

// Sort both arrays for comparison
sort($correctSquares);
sort($selected);

// Check if the selected squares match the correct squares exactly
if ($selected === $correctSquares) {
    $_SESSION['captcha_verified'] = true;
    echo json_encode([
        'success' => true,
        'message' => 'Verification successful'
    ]);
} else {
    // For security, don't reveal which squares are correct
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect selection. Please try again.'
    ]);
}
?>
