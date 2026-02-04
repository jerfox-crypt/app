<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RFID Scanner System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://getbootstrap.com/docs/5.3/assets/css/docs.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .carousel-img {
            height: 100vh;
            width: 100%;
            object-fit: cover;
        }
        
        .rfid-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border: none;
        }
        
        .student-info-display {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.98);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
            z-index: 10000;
            min-width: 380px;
            max-width: 500px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
            backdrop-filter: blur(10px);
        }
        
        .student-photo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #0d6efd;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .student-initials {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .close-btn:hover {
            background: #f8f9fa;
            color: #dc3545;
        }
        
        .scan-status {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 14px;
            z-index: 9998;
            backdrop-filter: blur(10px);
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -45%) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            to {
                opacity: 0;
                transform: translate(-50%, -45%) scale(0.95);
            }
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0); }
            100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
        }
        
        .scanning-animation {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(13, 110, 253, 0.1);
            animation: pulse 2s infinite;
            z-index: 9997;
            display: none;
        }
        
        .student-name {
            color: #0d6efd;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 24px;
        }
        
        .student-details {
            color: #6c757d;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .student-lrn {
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .timestamp {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            font-size: 13px;
            color: #6c757d;
        }
        
        .notification-icon {
            font-size: 20px;
            margin-right: 10px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1e7dd, #a3cfbb);
            color: #0f5132;
            border: none;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f1aeb5);
            color: #842029;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Scanning Animation -->
    <div class="scanning-animation" id="scanningAnimation"></div>
    
    <!-- Carousel -->
    <div id="carouselItem" class="carousel slide" data-bs-ride="carousel" data-bs-interval="10000" data-bs-wrap="true" data-bs-pause="false">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="{{asset('images/carousel/img1.jpg')}}" class="d-block w-100 carousel-img">
            </div>
            <div class="carousel-item">
                <img src="{{asset('images/carousel/img2.jpg')}}" class="d-block w-100 carousel-img">
            </div>
            <div class="carousel-item">
                <img src="{{asset('images/carousel/img3.jpg')}}" class="d-block w-100 carousel-img">
            </div>
            <div class="carousel-item">
                <img src="{{asset('images/carousel/img4.jpg')}}" class="d-block w-100 carousel-img">
            </div>
            <div class="carousel-item">
                <img src="{{asset('images/carousel/img5.jpg')}}" class="d-block w-100 carousel-img">
            </div>
        </div>
    </div>
    
    <!-- Scan Status -->
    <div class="scan-status" id="scanStatus">
        <i class="fas fa-rss"></i> Ready to scan...
    </div>
    
    <!-- RFID Input Field (Hidden) -->
    <input type="text" id="rfidInput" autofocus style="position:absolute;left:-9999px;" />
    
    <!-- Font Awesome for Icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Elements
        const rfidInput = document.getElementById('rfidInput');
        const scanStatus = document.getElementById('scanStatus');
        const scanningAnimation = document.getElementById('scanningAnimation');
        
        // State
        let isProcessing = false;
        let lastScanTime = 0;
        const MIN_SCAN_INTERVAL = 2000; // 2 seconds between scans
        
        // Initialize
        rfidInput.focus();
        updateScanStatus('ready', 'Ready to scan...');
        
        // Update scan status display
        function updateScanStatus(status, message) {
            const icon = status === 'ready' ? 'fa-rss' :
                        status === 'scanning' ? 'fa-sync fa-spin' :
                        status === 'success' ? 'fa-check-circle' :
                        'fa-exclamation-circle';
            
            scanStatus.innerHTML = `<i class="fas ${icon} me-2"></i>${message}`;
            
            if (status === 'scanning') {
                scanningAnimation.style.display = 'block';
            } else {
                scanningAnimation.style.display = 'none';
            }
        }
        
        // Play sound (using built-in audio context for better compatibility)
        function playSound(type) {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                if (type === 'success') {
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.5);
                } else {
                    oscillator.frequency.value = 300;
                    oscillator.type = 'sawtooth';
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.8);
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.8);
                }
            } catch (e) {
                console.log('Web Audio API not supported');
            }
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existing = document.querySelector('.rfid-notification');
            if (existing) {
                existing.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => existing.remove(), 300);
            }
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `rfid-notification alert alert-${type === 'success' ? 'success' : 'danger'}`;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <span class="notification-icon">${type === 'success' ? '✓' : '✗'}</span>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOut 0.3s ease-out';
                    setTimeout(() => notification.remove(), 300);
                }
            }, type === 'success' ? 5000 : 8000);
        }
        
        // Display student info
        function displayStudentInfo(studentData) {
            // Remove existing display
            const existing = document.getElementById('studentInfoDisplay');
            if (existing) existing.remove();
            
            // Create initials for photo fallback
            const initials = (studentData.firstname?.charAt(0) || '') + (studentData.lastname?.charAt(0) || '');
            
            // Format contact info
            const contactInfo = studentData.primary_contact ? 
                `<div class="student-details">
                    <i class="fas fa-phone me-1"></i> ${studentData.primary_contact}
                </div>` : '';
            
            // Create display
            const display = document.createElement('div');
            display.id = 'studentInfoDisplay';
            display.className = 'student-info-display';
            display.innerHTML = `
                <div style="margin-bottom: 20px;">
                    ${studentData.photo_url ? 
                        `<img src="${studentData.photo_url}" alt="Student Photo" class="student-photo">` : 
                        `<div class="student-initials">${initials}</div>`
                    }
                </div>
                <div class="student-name">${studentData.fullname}</div>
                <div class="student-details">
                    <i class="fas fa-graduation-cap me-1"></i> ${studentData.level_section}
                </div>
                ${studentData.lrn ? 
                    `<div class="student-lrn">
                        <i class="fas fa-id-card me-1"></i> LRN: ${studentData.lrn}
                    </div>` : ''
                }
                ${studentData.sid ? 
                    `<div class="student-details">
                        <i class="fas fa-hashtag me-1"></i> ID: ${studentData.sid}
                    </div>` : ''
                }
                ${contactInfo}
                <div class="timestamp">
                    <i class="far fa-clock me-1"></i> ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                </div>
                <button class="close-btn" onclick="this.parentElement.remove()">
                    ×
                </button>
            `;
            
            document.body.appendChild(display);
            
            // Auto remove after 10 seconds
            setTimeout(() => {
                if (display.parentNode) {
                    display.style.animation = 'fadeOut 0.5s ease-out';
                    setTimeout(() => display.remove(), 500);
                }
            }, 10000);
        }
        
        // Process RFID scan
        async function processRFID(rfidCode) {
            // Check minimum interval
            const now = Date.now();
            if (now - lastScanTime < MIN_SCAN_INTERVAL) {
                showNotification('Please wait before scanning again', 'error');
                return;
            }
            
            // Prevent multiple simultaneous scans
            if (isProcessing) return;
            
            // Update state
            isProcessing = true;
            lastScanTime = now;
            updateScanStatus('scanning', 'Processing scan...');
            console.log('Processing RFID:', rfidCode);
            
            try {
                // Send to server
                const response = await fetch('/api/scan-rfid', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        rfid: rfidCode,
                        location: 'main_gate' // Change this per scanner location
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Success
                    playSound('success');
                    updateScanStatus('success', `Welcome ${data.data.firstname}!`);
                    showNotification(data.message, 'success');
                    displayStudentInfo(data.data);
                } else {
                    // Error
                    playSound('error');
                    updateScanStatus('error', 'Scan failed');
                    showNotification(data.message || 'Scan failed', 'error');
                }
                
            } catch (error) {
                console.error('Error:', error);
                playSound('error');
                updateScanStatus('error', 'Connection error');
                showNotification('Network error. Please try again.', 'error');
            } finally {
                // Reset after delay
                setTimeout(() => {
                    isProcessing = false;
                    updateScanStatus('ready', 'Ready to scan...');
                }, 1000);
            }
        }
        
        // Handle RFID input
        rfidInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && rfidInput.value.trim().length > 0) {
                const rfidCode = rfidInput.value.trim();
                
                // Basic validation (10-digit number)
                if (!/^\d{10}$/.test(rfidCode)) {
                    showNotification('Invalid RFID format. Expected 10 digits.', 'error');
                    rfidInput.value = '';
                    return;
                }
                
                console.log('RFID Code:', rfidCode);
                
                // Process the RFID
                processRFID(rfidCode);
                
                // Clear input
                rfidInput.value = '';
                e.preventDefault();
            }
        });
        
        // Handle RFID input change (for readers that don't send Enter)
        rfidInput.addEventListener('input', function(e) {
            const value = rfidInput.value.trim();
            
            // Some RFID readers append Enter automatically
            if (value.includes('\n') || value.includes('\r')) {
                const rfidCode = value.replace(/[\r\n]/g, '');
                
                if (rfidCode.length >= 8) { // Minimum RFID length
                    console.log('RFID Code (auto):', rfidCode);
                    processRFID(rfidCode);
                    rfidInput.value = '';
                }
            }
        });
        
        // Refocus if input loses focus
        rfidInput.addEventListener('blur', function() {
            setTimeout(() => {
                if (!isProcessing) {
                    rfidInput.focus();
                }
            }, 50);
        });
        
        // Prevent accidental form submission
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target !== rfidInput) {
                e.preventDefault();
            }
        });
        
        // Handle page visibility (refocus when tab becomes active)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && !isProcessing) {
                setTimeout(() => rfidInput.focus(), 100);
            }
        });
        
        // Optional: Auto-refocus every 30 seconds as safety
        setInterval(() => {
            if (!isProcessing && document.activeElement !== rfidInput) {
                rfidInput.focus();
            }
        }, 30000);
    </script>
</body>
</html>