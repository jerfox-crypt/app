// Elements
const rfidInput = document.getElementById("rfidInput");

// Get CSRF token from data attribute
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;

// State
let isProcessing = false;
let lastScanTime = 0;
const MIN_SCAN_INTERVAL = 2000; // 2 seconds between scans

// Initialize
rfidInput.focus();

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
        // Send to server
        const response = await fetch("/api/scan-rfid", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF_TOKEN, // Use the variable here!
                Accept: "application/json",
            },
            body: JSON.stringify({
                rfid: rfidCode,
            }),
        });

        const data = await response.json();

        if (data.success) {
            // Success
            displayStudentInfo(data.data);

            // Reset timer for this display (2 seconds)
            clearTimeout(window.studentInfoTimeout);
            window.studentInfoTimeout = setTimeout(() => {
                hideStudentInfo();
            }, 2000);
        } else {
            // Error
            console.log("Error:", data.message);
        }
    } catch (error) {
        console.error("Error:", error);
    } finally {
        // Reset after delay
        setTimeout(() => {
            isProcessing = false;
        }, 1000);
    }
}

// Handle RFID input
rfidInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && rfidInput.value.trim().length > 0) {
        const rfidCode = rfidInput.value.trim();

        // Basic validation (10-digit number)
        if (!/^\d{10}$/.test(rfidCode)) {
            rfidInput.value = "";
            return;
        }

        console.log("RFID Code:", rfidCode);

        // Process the RFID
        processRFID(rfidCode);

        // Clear input
        rfidInput.value = "";
        e.preventDefault();
    }
});

// Handle RFID input change
rfidInput.addEventListener("input", function (e) {
    const value = rfidInput.value.trim();

    if (value.includes("\n") || value.includes("\r")) {
        const rfidCode = value.replace(/[\r\n]/g, "");

        if (rfidCode.length >= 8) {
            console.log("RFID Code (auto):", rfidCode);
            processRFID(rfidCode);
            rfidInput.value = "";
        }
    }
});

// Refocus if input loses focus
rfidInput.addEventListener("blur", function () {
    setTimeout(() => {
        if (!isProcessing) {
            rfidInput.focus();
        }
    }, 50);
});

// Prevent accidental form submission
document.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && e.target !== rfidInput) {
        e.preventDefault();
    }
});

// Handle page visibility
document.addEventListener("visibilitychange", function () {
    if (!document.hidden && !isProcessing) {
        setTimeout(() => rfidInput.focus(), 100);
    }
});

// Auto-refocus safety
setInterval(() => {
    if (!isProcessing && document.activeElement !== rfidInput) {
        rfidInput.focus();
    }
}, 30000);

// Display student info function
function displayStudentInfo(personData) {
    // Create overlay
    const existingOverlay = document.getElementById("overlay");
    if (!existingOverlay) {
        const overlay = document.createElement("div");
        overlay.id = "overlay";
        document.body.appendChild(overlay);
    }

    // Add blackout class to carousel
    document.getElementById("carouselItem")?.classList.add("carousel-blackout");
    document.getElementById("overlay").style.display = "block";

    const carousel = document.getElementById("carouselItem");
    if (carousel && typeof bootstrap !== "undefined") {
        const bsCarousel = bootstrap.Carousel.getInstance(carousel);
        if (bsCarousel) {
            bsCarousel.pause();
        }
    }

    // Create initials - use personData
    const initials =
        (personData.firstname?.charAt(0) || "") +
        (personData.lastname?.charAt(0) || "");

    // Check if it's a teacher
    const isTeacher =
        personData.person_type === "teacher" || personData.label === "Teacher";

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

    // Get or create container
    let container = document.getElementById("studentInfoContainer");
    if (!container) {
        container = document.createElement("div");
        container.id = "studentInfoContainer";
        container.style.display = "none";
        document.body.appendChild(container);
    }

    // Update and show container
    container.style.display = "block";
    document.getElementById("studentInfoCard").innerHTML = studentInfoHTML;

    // Auto-hide after 2 seconds
    clearTimeout(window.studentInfoTimeout);
    window.studentInfoTimeout = setTimeout(() => {
        hideStudentInfo();
    }, 2000);
}

// Hide student info function
function hideStudentInfo() {
    document.getElementById("studentInfoContainer").style.display = "none";
    document
        .getElementById("carouselItem")
        ?.classList.remove("carousel-blackout");
    document.getElementById("overlay").style.display = "none";

    // Resume carousel
    const carousel = document.getElementById("carouselItem");
    if (carousel && typeof bootstrap !== "undefined") {
        const bsCarousel = bootstrap.Carousel.getInstance(carousel);
        if (bsCarousel) {
            bsCarousel.cycle();
        }
    }
}

document.addEventListener("click", function () {
    if (
        document.getElementById("studentInfoContainer")?.style.display ===
        "block"
    ) {
        // Reset the 10-second timer
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
    document.getElementById("digitalClock").textContent = timeString;
}

// Initialize and update clock every second
updateClock();
setInterval(updateClock, 1000);
