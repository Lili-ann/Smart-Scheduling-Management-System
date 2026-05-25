<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Our Database of Answers
    $faqDb = [
        "q_invite" => "Invitation codes are automatically sent to your registered email 24 hours before the event starts. Check your spam folder if you don't see it.",
        "q_contact" => "You can contact event staff by clicking the 'Support' ticket icon on your dashboard, or by emailing staff@events.com.",
        "q_password" => "To reset your password, log out and click on 'Forgot Password' on the main login screen.",
        "q_meeting" => "You can add a new meeting by clicking the dark purple '+ Add Meeting' button on the bottom right of your meetings list.",
        "q_export" => "To export data, click the 'Export as .csv' button at the bottom of the meetings section. It will download immediately."
    ];

    // Keywords to match if the user decides to type instead of click
    $keywordMap = [
        "invite" => "q_invite",
        "invitation" => "q_invite",
        "contact" => "q_contact",
        "staff" => "q_contact",
        "password" => "q_password",
        "meeting" => "q_meeting",
        "export" => "q_export",
        "csv" => "q_export"
    ];

    $response = "I'm sorry, I couldn't find an exact answer for that. Please try selecting one of the quick options above.";

    // Check if they clicked a button (sent an ID)
    if (!empty($input['id']) && isset($faqDb[$input['id']])) {
        $response = $faqDb[$input['id']];
    } 
    // Check if they typed a message
    elseif (!empty($input['message'])) {
        $userMessage = strtolower(trim($input['message']));
        foreach ($keywordMap as $keyword => $id) {
            if (strpos($userMessage, $keyword) !== false) {
                $response = $faqDb[$id];
                break; // Stop looking once we find a match
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode(["reply" => $response]);
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ Assistant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; }

        /* Chat Container */
        .chat-container {
            width: 400px;
            height: 650px;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
        .chat-header {
            background-color: #11062b; 
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
        }

        /* Message Area */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background-color: #f9f9fb;
            scroll-behavior: smooth;
        }

        /* Bubbles */
        .message {
            max-width: 85%;
            padding: 12px 16px;
            border-radius: 15px;
            font-size: 0.95rem;
            line-height: 1.4;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .bot-message {
            background-color: #e2e8f0;
            color: #333;
            align-self: flex-start;
            border-bottom-left-radius: 2px;
        }

        .user-message {
            background-color: #11062b;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }

        /* Quick Options Container */
        .options-area {
            background-color: #ffffff;
            padding: 10px 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            overflow-x: auto;
            white-space: nowrap;
        }

        /* Hide scrollbar for cleaner look */
        .options-area::-webkit-scrollbar { display: none; }
        
        .option-btn {
            background-color: #f0ecf9;
            border: 1px solid #d4c5f0;
            color: #11062b;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .option-btn:hover {
            background-color: #11062b;
            color: white;
        }

        /* Text Input Area */
        .chat-input-area {
            display: flex;
            padding: 15px;
            background-color: white;
            border-top: 1px solid #eee;
        }

        .chat-input-area input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 25px;
            outline: none;
            font-size: 0.95rem;
        }

        .chat-input-area button {
            background-color: #11062b;
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-left: 10px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: background 0.3s;
        }

        .chat-input-area button:hover {
            background-color: #2b1154;
        }
    </style>
</head>
<body>

    <div class="chat-container">
        <div class="chat-header">
            FAQ Assistant
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="message bot-message">Hello! Choose an option below or type your question.</div>
        </div>
        
        <div class="options-area" id="optionsArea">
            <button class="option-btn" data-id="q_invite">Get Invitation Code</button>
            <button class="option-btn" data-id="q_contact">Contact Staff</button>
            <button class="option-btn" data-id="q_password">Reset Password</button>
            <button class="option-btn" data-id="q_meeting">Add Meeting</button>
            <button class="option-btn" data-id="q_export">Export Data</button>
        </div>

        <form class="chat-input-area" id="chatForm">
            <input type="text" id="userInput" placeholder="Type your question..." autocomplete="off">
            <button type="submit"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
    </div>

    <script>
        const chatMessages = document.getElementById('chatMessages');
        const optionButtons = document.querySelectorAll('.option-btn');
        const chatForm = document.getElementById('chatForm');
        const userInput = document.getElementById('userInput');

        // Helper to add chat bubbles
        function appendMessage(text, sender) {
            const msgDiv = document.createElement('div');
            msgDiv.classList.add('message', sender === 'user' ? 'user-message' : 'bot-message');
            msgDiv.textContent = text;
            chatMessages.appendChild(msgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Helper to handle sending data to PHP
        async function sendMessageToServer(payload) {
            // Show "Typing..."
            const typingIndicator = document.createElement('div');
            typingIndicator.classList.add('message', 'bot-message');
            typingIndicator.textContent = "Typing...";
            chatMessages.appendChild(typingIndicator);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                const response = await fetch('faq-chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                chatMessages.removeChild(typingIndicator);
                appendMessage(data.reply, 'bot');
            } catch (error) {
                chatMessages.removeChild(typingIndicator);
                appendMessage("Sorry, I'm having trouble connecting to the server.", 'bot');
            }
        }

        // Handle Quick Option Clicks
        optionButtons.forEach(button => {
            button.addEventListener('click', function() {
                const questionText = this.textContent;
                const questionId = this.getAttribute('data-id');

                appendMessage(questionText, 'user'); // Show what they clicked as a chat bubble
                sendMessageToServer({ id: questionId }); // Send ID to backend
            });
        });

        // Handle Text Bar Submissions
        chatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const message = userInput.value.trim();
            if (!message) return;

            appendMessage(message, 'user'); // Show typed message
            userInput.value = ''; // Clear input
            
            sendMessageToServer({ message: message }); // Send text to backend
        });
    </script>

</body>
</html>