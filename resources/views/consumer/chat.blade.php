<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MedFind - Chat with {{ $pharmacy->pharmacy_name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        
        .chat-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header h2 { font-size: 1.2em; }
        .chat-header .back-btn {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
        
        .chat-messages {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background: #f7fafc;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .message.sent { align-items: flex-end; }
        .message.received { align-items: flex-start; }
        
        .message-bubble {
            max-width: 70%;
            padding: 12px 18px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message.sent .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-bubble {
            background: white;
            color: #1a1a2e;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-time {
            font-size: 0.65em;
            color: #999;
            margin-top: 4px;
        }
        
        .prescription-img {
            max-width: 150px;
            margin-top: 5px;
            border-radius: 8px;
        }
        
        .chat-input-area {
            display: flex;
            padding: 15px;
            gap: 10px;
            border-top: 1px solid #e0e0e0;
            background: white;
        }
        
        .chat-input-area input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-family: inherit;
        }
        
        .chat-input-area input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-upload {
            background: #48bb78;
            padding: 12px 16px;
        }
        
        #fileInput { display: none; }
        
        .prescription-preview {
            max-width: 100px;
            margin: 5px 0;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h2>💬 {{ $pharmacy->pharmacy_name }}</h2>
            <a href="/" class="back-btn">← Back</a>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            @foreach($messages as $message)
                <div class="message {{ $message->sender_id === auth()->id() ? 'sent' : 'received' }}">
                    <div class="message-bubble">
                        {{ $message->message }}
                        @if($message->prescription_image)
                            <br><img src="/storage/{{ $message->prescription_image }}" class="prescription-img">
                        @endif
                    </div>
                    <div class="message-time">{{ $message->created_at->format('h:i A') }}</div>
                </div>
            @endforeach
        </div>
        
        <div class="chat-input-area">
            <input type="text" id="messageInput" placeholder="Type your message...">
            <button class="btn btn-upload" onclick="document.getElementById('fileInput').click()">📎</button>
            <input type="file" id="fileInput" accept="image/*" onchange="previewImage(event)">
            <button class="btn" onclick="sendMessage()">Send</button>
        </div>
    </div>
    
    <script>
        let selectedFile = null;
        
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                selectedFile = file;
                const reader = new FileReader();
                reader.onload = function(e) {
                    const chatDiv = document.getElementById('chatMessages');
                    chatDiv.innerHTML += `
                        <div class="message sent">
                            <div class="message-bubble">
                                <img src="${e.target.result}" class="prescription-preview">
                                <br><small>📋 Prescription attached</small>
                            </div>
                            <div class="message-time">Just now</div>
                        </div>
                    `;
                    chatDiv.scrollTop = chatDiv.scrollHeight;
                };
                reader.readAsDataURL(file);
            }
        }
        
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            if (!message && !selectedFile) return;
            
            const formData = new FormData();
            formData.append('pharmacy_id', {{ $pharmacy->id }});
            formData.append('message', message);
            if (selectedFile) {
                formData.append('prescription_image', selectedFile);
            }
            
            try {
                const response = await fetch('/api/chat/send', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    input.value = '';
                    selectedFile = null;
                    document.getElementById('fileInput').value = '';
                    loadMessages();
                }
            } catch (error) {
                console.error('Error sending message:', error);
            }
        }
        
        async function loadMessages() {
            try {
                const response = await fetch('/api/chat/messages/{{ $pharmacy->id }}');
                const messages = await response.json();
                const chatDiv = document.getElementById('chatMessages');
                chatDiv.innerHTML = '';
                messages.forEach(msg => {
                    chatDiv.innerHTML += `
                        <div class="message ${msg.sender_id === {{ auth()->id() }} ? 'sent' : 'received'}">
                            <div class="message-bubble">
                                ${msg.message}
                                ${msg.prescription_image ? `<br><img src="/storage/${msg.prescription_image}" class="prescription-img">` : ''}
                            </div>
                            <div class="message-time">${new Date(msg.created_at).toLocaleTimeString()}</div>
                        </div>
                    `;
                });
                chatDiv.scrollTop = chatDiv.scrollHeight;
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }
        
        // Auto-refresh messages every 10 seconds
        setInterval(loadMessages, 10000);
        
        // Send on Enter key
        document.getElementById('messageInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });
        
        // Scroll to bottom on load
        document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
    </script>
</body>
</html>