// jQuery version of RFID scanner
$(document).ready(function () {
    // Elements
    const $rfidInput = $("#rfidInput");
    const $studentInfoContainer = $("#studentInfoContainer");
    const $carouselItem = $("#carouselItem");
    const $digitalClock = $("#digitalClock");

    // Get CSRF token from meta tag
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr("content");

    // State
    let isProcessing = false;
    let lastScanTime = 0;
    const MIN_SCAN_INTERVAL = 2000; // 2 seconds between scans

    // Initialize
    $rfidInput.focus();

    // Process RFID scan
    async function processRFID(rfidCode) {
        // Check minimum interval
        const now = Date.now();
        if (now - lastScanTime < MIN_SCAN_INTERVAL) {
            return;
        }

        // Prevent multiple simultaneous scans
        if (isProcessing) return;

        // Update state
        isProcessing = true;
        lastScanTime = now;
        console.log("Processing RFID:", rfidCode);

        try {
            // Send to server using jQuery AJAX
            const response = await $.ajax({
                url: "/api/scan-rfid",
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": CSRF_TOKEN,
                    Accept: "application/json",
                },
                contentType: "application/json",
                data: JSON.stringify({ rfid: rfidCode }),
                dataType: "json",
            });

            if (response.success) {
                // Success
                displayStudentInfo(response.data);

                // Reset timer for this display (2 seconds)
                clearTimeout(window.studentInfoTimeout);
                window.studentInfoTimeout = setTimeout(() => {
                    hideStudentInfo();
                }, 2000);
            } else {
                // Error - display error message
                displayErrorMessage(
                    response.message ||
                        "RFID card not registered in the system",
                );

                // Auto-hide error after 2 seconds
                clearTimeout(window.errorTimeout);
                window.errorTimeout = setTimeout(() => {
                    hideStudentInfo();
                }, 2000);
            }
        } catch (error) {
            console.error(
                "Error:",
                error.responseJSON ? error.responseJSON.message : error,
            );

            // Handle server errors (404, 500, etc.)
            const errorMessage =
                error.responseJSON?.message ||
                "Connection error. Please try again.";
            displayErrorMessage(errorMessage);
        } finally {
            // Reset after delay
            setTimeout(() => {
                isProcessing = false;
            }, 1000);
        }
    }

    // Handle RFID input keydown
    $rfidInput.on("keydown", function (e) {
        if (e.key === "Enter" && $(this).val().trim().length > 0) {
            const rfidCode = $(this).val().trim();

            // Basic validation (10-digit number)
            if (!/^\d{10}$/.test(rfidCode)) {
                $(this).val("");
                return;
            }

            console.log("RFID Code:", rfidCode);

            // Process the RFID
            processRFID(rfidCode);

            // Clear input
            $(this).val("");
            e.preventDefault();
        }
    });

    // Handle RFID input change
    $rfidInput.on("input", function () {
        const value = $(this).val().trim();

        if (value.includes("\n") || value.includes("\r")) {
            const rfidCode = value.replace(/[\r\n]/g, "");

            if (rfidCode.length >= 8) {
                console.log("RFID Code (auto):", rfidCode);
                processRFID(rfidCode);
                $(this).val("");
            }
        }
    });

    // Refocus if input loses focus
    $rfidInput.on("blur", function () {
        setTimeout(() => {
            if (!isProcessing) {
                $rfidInput.focus();
            }
        }, 50);
    });

    // Prevent accidental form submission
    $(document).on("keydown", function (e) {
        if (e.key === "Enter" && !$(e.target).is($rfidInput)) {
            e.preventDefault();
        }
    });

    // Handle page visibility
    $(document).on("visibilitychange", function () {
        if (!document.hidden && !isProcessing) {
            setTimeout(() => $rfidInput.focus(), 100);
        }
    });

    // Auto-refocus safety
    setInterval(() => {
        if (!isProcessing && !$rfidInput.is(":focus")) {
            $rfidInput.focus();
        }
    }, 30000);

    // Display student info function
    function displayStudentInfo(personData) {
        // Create overlay if it doesn't exist
        let $overlay = $("#overlay");
        if ($overlay.length === 0) {
            $overlay = $('<div id="overlay"></div>');
            $("body").append($overlay);
        }

        // Add blackout class to carousel
        $carouselItem.addClass("carousel-blackout");
        $overlay.show();

        // Pause carousel
        if ($carouselItem.length > 0 && typeof bootstrap !== "undefined") {
            const bsCarousel = bootstrap.Carousel.getInstance($carouselItem[0]);
            if (bsCarousel) {
                bsCarousel.pause();
            }
        }

        // Create initials
        const initials =
            (personData.firstname?.charAt(0) || "") +
            (personData.lastname?.charAt(0) || "");

        // Check if it's a teacher
        const isTeacher =
            personData.person_type === "teacher" ||
            personData.label === "Teacher";

        // Create info HTML
        const studentInfoHTML = `
            <!-- Photo on top-left -->
            <div style="
                position: fixed;
                top: 30px;
                left: 50px;
                z-index: 10001;
            ">
                ${
                    personData.photo_url
                        ? `<img src="${personData.photo_url}" class="img-fluid" alt="Photo" style="
                            width: 56.25vh;
                            height: 90vh;
                            object-fit: fill;
                            position: fixed;
                            left: 20;
                            top: 50%;
                            transform: translateY(-50%);
                            z-index: 10001;
                            border-radius: 2%;
                        ">`
                        : `<div style="
                            height: 1365px;
                            border-radius: 20%;
                            background: linear-gradient(135deg, #ffffff, #cccccc);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: #0d6efd;
                            font-size: 48px;
                            font-weight: bold;
                            border: 5px solid white;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                        ">${initials}</div>`
                }
            </div>
            
            <!-- Person info on top-right -->
            <div style="
                position: fixed;
                top: 40px;
                right: 40px;
                text-align: right;
                z-index: 10001;
                max-width: 600px;
            ">
                <div style="font-size: 65px; font-weight: 700; color: #ffffff; margin-bottom: 15px; text-shadow: 3px 3px 6px rgba(0,0,0,0.7); line-height: 1.1;">
                    ${personData.fullname}
                </div>
                ${
                    isTeacher
                        ? `<div style="font-size: 40px; font-weight: 400; color: #ffffff; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">
                            Teacher
                        </div>`
                        : `<div style="font-size: 40px; font-weight: 400; color: #ffffff; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">
                            ${personData.lrn || ""}
                        </div>
                        <div style="font-size: 40px; font-weight: 400; color: #ffffff; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">
                            ${personData.level_section || ""}
                        </div>`
                }
            </div>
        `;

        // Get or create student info card container
        let $studentInfoCard = $("#studentInfoCard");
        if ($studentInfoCard.length === 0) {
            $studentInfoCard = $('<div id="studentInfoCard"></div>');
            $studentInfoContainer.append($studentInfoCard);
        }

        // Update and show container
        $studentInfoCard.html(studentInfoHTML);
        $studentInfoContainer.show();

        // Auto-hide after 2 seconds
        clearTimeout(window.studentInfoTimeout);
        window.studentInfoTimeout = setTimeout(() => {
            hideStudentInfo();
        }, 2000);
    }

    // Hide student info function
    function hideStudentInfo() {
        $studentInfoContainer.hide();
        $carouselItem.removeClass("carousel-blackout");
        $("#overlay").hide();

        // Resume carousel
        if ($carouselItem.length > 0 && typeof bootstrap !== "undefined") {
            const bsCarousel = bootstrap.Carousel.getInstance($carouselItem[0]);
            if (bsCarousel) {
                bsCarousel.cycle();
            }
        }
    }

    // Reset timer on click
    $(document).on("click", function () {
        if ($studentInfoContainer.is(":visible")) {
            clearTimeout(window.studentInfoTimeout);
            window.studentInfoTimeout = setTimeout(() => {
                hideStudentInfo();
            }, 2000);
        }
    });

    // Digital Clock Function
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        const ampm = hours >= 12 ? "PM" : "AM";

        // Convert to 12-hour format
        hours = hours % 12;
        hours = hours ? hours : 12; // Convert 0 to 12

        // Add leading zeros
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
        $digitalClock.text(timeString);
    }

    // Initialize and update clock every second
    updateClock();
    setInterval(updateClock, 1000);
});
