<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to Push Notifications</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        button {
            padding: 10px 20px;
            background: #4a5568;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #2d3748;
        }
        .status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Subscribe to Push Notifications</h1>
    <p>Click the button below to allow notifications from this application.</p>
    
    <button id="subscribe-btn">Subscribe to Notifications</button>
    <button id="test-btn" style="display: none;">Test Notification</button>
    
    <div id="status-container"></div>

    <div class="info">
        <p>
            <strong>Current User:</strong> 
            @auth
                {{ Auth::user()->firstname ?? 'Unknown' }} {{ Auth::user()->lastname ?? '' }}
                (ID: {{ Auth::user()->sisi_id ?? Auth::user()->id ?? 'Unknown' }})
            @else
                Not logged in
            @endauth
        </p>
    </div>
    
    <script>
        const subscribeBtn = document.getElementById('subscribe-btn');
        const testBtn = document.getElementById('test-btn');
        const statusContainer = document.getElementById('status-container');
        
        // Check if service worker and push API are supported
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            showStatus('error', 'Push notifications are not supported in your browser. Please use Chrome, Firefox, or Edge.');
            subscribeBtn.disabled = true;
        }
        
        // Public VAPID Key - Replace with your actual public key
        const publicVapidKey = 'BOX9QCTD-ZAYpfpSqZ8fBJD-rh_osK2xnuX7c_3ABZ8PDZ9DOCcvy2qE861-OJDe4iuPRiSjRcYHPRTlAPGC4uw'; // Replace this with your actual key
        
        function showStatus(type, message, details = null) {
            const statusDiv = document.createElement('div');
            statusDiv.className = `status ${type}`;
            statusDiv.textContent = message;
            
            if (details) {
                const pre = document.createElement('pre');
                pre.textContent = typeof details === 'object' ? JSON.stringify(details, null, 2) : details;
                statusDiv.appendChild(pre);
            }
            
            statusContainer.appendChild(statusDiv);
        }
        
        async function subscribeUserToPush() {
            try {
                // Register service worker
                console.log('Registering service worker...');
                const registration = await navigator.serviceWorker.register('/service-worker.js');
                console.log('Service worker registered:', registration);
                
                // Request permission
                console.log('Requesting notification permission...');
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    showStatus('error', 'Notification permission denied');
                    return false;
                }
                
                // Convert base64 string to Uint8Array
                function urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding)
                        .replace(/-/g, '+')
                        .replace(/_/g, '/');
                    
                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);
                    
                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }
                    
                    return outputArray;
                }
                
                // Subscribe to push notifications
                console.log('Subscribing to push notifications...');
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
                });
                
                // Send subscription to server
                console.log('Sending subscription to server...');
                const response = await fetch('/api/notifications/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        ...subscription,
                        user_id: '{{ Auth::user()->sisi_id ?? Auth::user()->id ?? "guest" }}' // Explicitly send the user ID
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showStatus('success', 'Successfully subscribed to push notifications!');
                    testBtn.style.display = 'inline-block';
                    subscribeBtn.disabled = true;
                    return true;
                } else {
                    showStatus('error', 'Failed to subscribe', data);
                    return false;
                }
            } catch (error) {
                console.error('Error subscribing to push notifications:', error);
                showStatus('error', 'Error subscribing to push notifications', error.message);
                return false;
            }
        }
        
        // Event listeners
        subscribeBtn.addEventListener('click', subscribeUserToPush);
        
        testBtn.addEventListener('click', async () => {
            try {
                const response = await fetch('/test-push');
                const text = await response.text();
                showStatus('success', text);
            } catch (error) {
                showStatus('error', 'Error sending test notification', error.message);
            }
        });
        
        // Check if already registered
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(async (registration) => {
                try {
                    const subscription = await registration.pushManager.getSubscription();
                    if (subscription) {
                        showStatus('success', 'Already subscribed to push notifications');
                        testBtn.style.display = 'inline-block';
                        subscribeBtn.disabled = true;
                    }
                } catch (e) {
                    console.error('Error checking subscription:', e);
                }
            });
        }
    </script>
</body>
</html>