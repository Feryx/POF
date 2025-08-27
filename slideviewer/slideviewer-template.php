<?php
require_once __DIR__ . '/../../../../wp-load.php';
if ( ! current_user_can('manage_options') ) {
    wp_die('You do not have permission to access this page.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Party Organizer SlideViewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script>
        const ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
    </script>
    
</head>
<body>

<div id="background"></div>
<div id="content">
    <div id="announcement" class="slide-mode hidden">
        <div class="inner-text">
            <p id="announcement-text">Slide show is ready to start!</p>
        </div>
    </div>

    <div id="compo-countdown" class="slide-mode hidden">
        <h1 id="compo-name">Photo Compo</h1>
        <h2>Starts in:</h2>
        <h2 id="compo-timer">99:99</h2>
    </div>

    <div id="event-countdown" class="slide-mode hidden">
        <h1 id="event-name">Special Event</h1>
        <h2>Starts in:</h2>
        <h2 id="event-timer">99:99</h2>
    </div>

    <div id="compo-display" class="slide-mode hidden">
        <div class="slider-container">
            <div class="slider" id="prod-slider"></div>
        </div>
    </div>

    <div id="prizegiving" class="slide-mode hidden">
        <div class="main-title">
            <h1>Results</h1>
            <h2 id="prizegiving-compo-name">Compo Name</h2>
        </div>
        <div id="results-list"></div>
    </div>
</div>

<div id="gallery" class="hidden">
    <div class="slider-container">
        <div class="slider" id="gallery-slider"></div>
    </div>
</div>

<div id="info-overlay">
    <div id="compo-name-overlay">Photo Compo</div>
    <div id="compo-timer-overlay"></div>
</div>
<script>
let compoTime;
let eventTime;
let lastModeBeforeGallery = -1;

let compoTimerInterval, eventTimerInterval;
let galleryInterval;
let currentProd = 0, currentPrize = -1, currentGallery = 0;
let enableScreenshots = 'no';
let prods = [];
let results = [];
let maxPoints = 0;

let currentMode = -1;
let dataFromLastFetch = null;

async function fetchSliderData() {
    try {
        const response = await fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=get_party_slider_mode"
        });

        if (!response.ok) throw new Error("HTTP error " + response.status);

        const data = await response.json();
        console.log("🔄 New data:", data);
        dataFromLastFetch = data; // Save data for later use
        return data;
    } catch (error) {
        console.error("❌ Error fetching data:", error);
        return null;
    }
}

function showMode(data) {
    if (!data) return;

    const newMode = parseInt(data.mode);
    if (newMode !== currentMode) {
        lastModeBeforeGallery = currentMode;
        currentMode = newMode;
    }

    // The compo_time and event_time values must now be provided by PHP in the response
    if (data.event_time && data.event_time !== 'nodataERR') {
        eventTime = new Date(data.event_time);
    }
    if (data.compo_time && data.compo_time !== 'nodataERR') { // Assuming PHP sends the compo time
        compoTime = new Date(data.compo_time);
    }

    if (data.data && data.data.length > 0) {
        if (currentMode === 3) {
            prods = data.data;
        } else if (currentMode === 4) {
            results = data.data.reverse();
            if (results.length > 0) {
                maxPoints = results[0].total_points;
            }
        }
    }

    document.querySelectorAll(".slide-mode").forEach(m => m.classList.add("hidden"));
    document.getElementById("gallery").classList.add("hidden");
    document.getElementById("info-overlay").classList.remove("visible");
    document.getElementById("background").style.filter = "none";
    clearInterval(compoTimerInterval);
    clearInterval(eventTimerInterval);
    clearInterval(galleryInterval);
    if (data.enable_screenshots) {
        enableScreenshots = data.enable_screenshots;
    }
    switch (currentMode) {
        case 0:
            const announcementEl = document.getElementById("announcement");
            if (announcementEl) announcementEl.classList.remove("hidden");
            const announcementTextEl = document.getElementById("announcement-text");
            // Insert the data into the p tag here
            if (announcementTextEl) announcementTextEl.textContent = data.announcement_text;
            break;
        case 1:
            const compoCountdownEl = document.getElementById("compo-countdown");
            if (compoCountdownEl) compoCountdownEl.classList.remove("hidden");
            const compoNameEl = document.getElementById("compo-name");
            if (compoNameEl) compoNameEl.textContent = data.event_name;
            startCountdown("compo-timer", compoTime);
            break;
        case 2:
            const eventCountdownEl = document.getElementById("event-countdown");
            if (eventCountdownEl) eventCountdownEl.classList.remove("hidden");
            const eventNameEl = document.getElementById("event-name");
            if (eventNameEl) eventNameEl.textContent = data.event_name;
            startCountdown("event-timer", eventTime);
            break;
        case 3:
            const compoDisplayEl = document.getElementById("compo-display");
            if (compoDisplayEl) compoDisplayEl.classList.remove("hidden");
            buildProds();
            break;
        case 4:
            const prizegivingEl = document.getElementById("prizegiving");
            if (prizegivingEl) prizegivingEl.classList.remove("hidden");
            const prizegivingNameEl = document.getElementById("prizegiving-compo-name");
            if (prizegivingNameEl) prizegivingNameEl.textContent = data.event_name;
            buildPrizes();
            break;
        case 5:
            const galleryEl = document.getElementById("gallery");
            if (galleryEl) galleryEl.classList.remove("hidden");
            document.getElementById("info-overlay").classList.add("visible");
            document.getElementById("background").style.filter = "blur(5px) scale(1.05)";
            startCountdown("compo-timer-overlay", compoTime, true);
            buildGallery();
            startGalleryAutoSlide();
            break;
        default:
            console.error("❌ Unknown mode:", currentMode);
            break;
    }
}

function startCountdown(elId, targetTime, isOverlay = false) {
    function update() {
        if (!targetTime || isNaN(targetTime.getTime())) return;
        
        const now = new Date();
        let diff = Math.floor((targetTime.getTime() - now.getTime()) / 1000);
        let text = "";

        if (diff <= 0) {
            text = "SOON";
        } else if (diff > 5999) {
            text = "∞";
        } else {
            let m = Math.floor(diff / 60);
            let s = diff % 60;
            text = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
        }
        
        if (!isOverlay) {
            const el = document.getElementById(elId);
            if (el) el.textContent = text;
        } else {
            const el = document.getElementById("compo-timer-overlay");
            if (el) el.textContent = text;
        }
    }
    update();
    if (elId.includes("compo-timer")) {
        clearInterval(compoTimerInterval);
        compoTimerInterval = setInterval(update, 1000);
    }
    if (elId.includes("event-timer")) {
        clearInterval(eventTimerInterval);
        eventTimerInterval = setInterval(update, 1000);
    }
}

function buildProds() {
    const slider = document.getElementById("prod-slider");
    if (!slider) return;
    slider.innerHTML = "";
    const startSlide = createSlide("Competition Start", "Get ready!");
    slider.appendChild(startSlide);
    
    // We'll use the 'index' argument of forEach to create the number
    prods.forEach((p, index) => {
        // Conditionally generate the image
        const imageHtml = (enableScreenshots === 'yes')
                             ? `<img class="prod-prew-image" src="${p.screenshot}" width="299" height="299">`
                             : '';

        const slideContent = `<div class="prod-number">#${index + 1}</div>
                              ${imageHtml}
                              <h1>${p.product_title}</h1>
                              <h2>by ${p.author}</h2>
                              <p>${p.comment_public}</p>`;
        slider.appendChild(createSlide(null, slideContent));
    });
    
    const endSlide = createSlide("Competition End", "Thank you for participating!");
    slider.appendChild(endSlide);
    currentProd = 0;
    update3DSlider(slider, currentProd);
}

function buildPrizes() {
    const prizegivingNameEl = document.getElementById("prizegiving-compo-name");
    if (prizegivingNameEl) prizegivingNameEl.textContent = dataFromLastFetch.event_name;
    const resultsList = document.getElementById("results-list");
    if (resultsList) resultsList.innerHTML = "";
    currentPrize = -1;
}

function updatePrizes() {
    if (currentPrize === -1 || currentPrize >= results.length) return;

    const resultsList = document.getElementById("results-list");
    if (!resultsList) return;
    const newResult = results[currentPrize];

    const resultItem = document.createElement("div");
    resultItem.classList.add("result-item");

    const place = results.length - currentPrize;
    if (place === 1) resultItem.classList.add("first-place");
    if (place === 2) resultItem.classList.add("second-place");
    if (place === 3) resultItem.classList.add("third-place");
    
    const placeDiv = document.createElement("div");
    placeDiv.classList.add("place");
    placeDiv.textContent = `#${place}.`;
    
    const pointsBarContainer = document.createElement("div");
    pointsBarContainer.classList.add("points-bar-container");
    
    const pointsBarFill = document.createElement("div");
    pointsBarFill.classList.add("points-bar-fill");
    
    const pointsText = document.createElement("div");
    pointsText.classList.add("points-text");
    pointsText.innerHTML = `<span>${newResult.product_title} by ${newResult.author}</span><span>${newResult.total_points} pts.</span>`;
    
    pointsBarContainer.appendChild(pointsBarFill);
    pointsBarContainer.appendChild(pointsText);
    
    resultItem.appendChild(placeDiv);
    resultItem.appendChild(pointsBarContainer);
    
    resultsList.prepend(resultItem);

    setTimeout(() => {
        resultItem.classList.add("active");
        setTimeout(() => {
            const widthPercentage = (newResult.total_points / maxPoints) * 100;
            pointsBarFill.style.width = `${widthPercentage}%`;
        }, 800);
    }, 10);
}

function createSlide(title, content) {
    const div = document.createElement("div");
    div.className = "slide";
    if (title) { div.innerHTML += `<h1>${title}</h1>`; }
    if (content) { div.innerHTML += `<div class="inner-text">${content}</div>`; }
    return div;
}

function update3DSlider(slider, index) {
    if (!slider) return;
    const slides = slider.querySelectorAll(".slide");
    slides.forEach((s, i) => {
        s.classList.remove("active", "prev", "next");
        if (i === index) {
            s.classList.add("active");
        } else if (i < index) {
            s.classList.add("prev");
        } else {
            s.classList.add("next");
        }
    });
}

function buildGallery() {
    const slider = document.getElementById("gallery-slider");
    if (!slider) return;
    slider.innerHTML = "";
    const galleryImages = ["https://picsum.photos/1000/600?random=2", "https://picsum.photos/1000/600?random=3", "https://picsum.photos/1000/600?random=4"];
    galleryImages.forEach(imgSrc => {
        const div = document.createElement("div");
        div.className = "slide gallery-slide";
        const img = document.createElement("img");
        img.src = imgSrc;
        div.appendChild(img);
        slider.appendChild(div);
    });
    currentGallery = 0;
    update3DSlider(slider, currentGallery);
}

function startGalleryAutoSlide() {
    clearInterval(galleryInterval);
    galleryInterval = setInterval(() => {
        const slider = document.getElementById("gallery-slider");
        currentGallery = (currentGallery + 1) % 3;
        update3DSlider(slider, currentGallery);
    }, 5000);
}

document.addEventListener("keydown", async e => {
    if (e.code === "Space") {
        e.preventDefault();
        console.log("⏩ SPACE pressed → fetching new data...");
        const newData = await fetchSliderData();
        showMode(newData);
    }
    
    if (currentMode === 3) {
        const slider = document.getElementById("prod-slider");
        if (e.code === "ArrowRight") {
            currentProd = Math.min(currentProd + 1, prods.length+1);
            update3DSlider(slider, currentProd);
        }
        if (e.code === "ArrowLeft") {
            currentProd = Math.max(currentProd - 1, 0);
            update3DSlider(slider, currentProd);
        }
    }

    if (currentMode === 4) {
        if (e.code === "ArrowRight") {
            if (currentPrize < results.length - 1) {
                currentPrize++;
                updatePrizes();
            }
        }
        if (e.code === "ArrowLeft") {
            const resultsList = document.getElementById("results-list");
            if (resultsList.children.length > 0) {
                resultsList.firstChild.remove();
                currentPrize--;
            }
        }
    }
    
    if (currentMode === 5) {
        const slider = document.getElementById("gallery-slider");
        if (e.code === "ArrowRight") {
            currentGallery = (currentGallery + 1) % 3;
            update3DSlider(slider, currentGallery);
            startGalleryAutoSlide();
        }
        if (e.code === "ArrowLeft") {
            currentGallery = (currentGallery - 1 + 3) % 3;
            update3DSlider(slider, currentGallery);
            startGalleryAutoSlide();
        }
    }

    if (e.code === "ArrowUp") {
        if (compoTime) compoTime = new Date(compoTime.getTime() + 60 * 1000);
        if (eventTime) eventTime = new Date(eventTime.getTime() + 60 * 1000);
        if (currentMode === 1) startCountdown("compo-timer", compoTime);
        if (currentMode === 2) startCountdown("event-timer", eventTime);
        if (currentMode === 5) startCountdown("compo-timer-overlay", compoTime, true);
    }
    if (e.code === "ArrowDown") {
        if (compoTime) compoTime = new Date(compoTime.getTime() - 60 * 1000);
        if (eventTime) eventTime = new Date(eventTime.getTime() - 60 * 1000);
        if (currentMode === 1) startCountdown("compo-timer", compoTime);
        if (currentMode === 2) startCountdown("event-timer", eventTime);
        if (currentMode === 5) startCountdown("compo-timer-overlay", compoTime, true);
    }

    if (e.key.toLowerCase() === "s") {
        if (currentMode === 5) {
            const backToMode = lastModeBeforeGallery !== -1 ? lastModeBeforeGallery : 0;
            if (dataFromLastFetch) showMode({ ...dataFromLastFetch, mode: backToMode });
        } else {
            if (dataFromLastFetch) showMode({ ...dataFromLastFetch, mode: 5 });
        }
    }
});

async function initialLoad() {
    const newData = await fetchSliderData();
    showMode(newData);
}

initialLoad();
</script>

</body>
</html>