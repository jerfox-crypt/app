<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RFID Scanner System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Only include CSS files that exist -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/carousel.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components/scanning.css') }}" rel="stylesheet">
</head>

<body>
    <!-- Scanning Animation -->
    <div class="scanning-animation" id="scanningAnimation"></div>

    <!-- Carousel -->
    <div id="carouselItem" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="10000"
        data-bs-wrap="true" data-bs-pause="false">
        <div class="carousel-inner">
            @for($i = 1; $i <= 5; $i++)
                <!-- carousel images -->
                <div class="carousel-item {{ $i === 1 ? 'active' : '' }}">
                    <img src="{{ asset('images/carousel/img' . $i . '.jpg') }}" class="d-block w-100 carousel-img"
                        alt="Carousel Image {{ $i }}">
                </div>
            @endfor
        </div>
    </div>


    <div id="studentInfoContainer" style="display: none;">
        <div id="studentInfoCard" style="
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 20px;
        max-width: 1000px;
        z-index: 10000;
    ">
            <!-- Student info will be inserted here -->
        </div>
    </div>

    <!-- Digital Clock -->
    <div id="digitalClock" style="
    position: fixed;
    bottom: 20px;
    right: 30px;
    font-size: 100px;
    font-weight: bold;
    color: white;
    padding: 10px 20px;
    border-radius: 15px;
    font-family: 'Segoe UI', monospace;
    z-index: 10000;
">
    </div>

    <script>
        // Digital Clock Function
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';

            // Convert to 12-hour format
            hours = hours % 12;
            hours = hours ? hours : 12; // Convert 0 to 12

            // Add leading zeros
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
            document.getElementById('digitalClock').textContent = timeString;
        }

        // Initialize and update clock every second
        updateClock();
        setInterval(updateClock, 1000);
    </script>

    <!-- RFID Input Field (Hidden) -->
    <input type="text" id="rfidInput" class="rfid-input-hidden" autofocus data-csrf="{{ csrf_token() }}" />

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    @vite(['resources/js/rfid-scanner.js'])
</body>

</html>