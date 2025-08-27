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

let overlayEvent = null;

async function fetchSliderData() {
    try {
        const response = await fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=get_party_slider_mode"
        });

        if (!response.ok) throw new Error("HTTP error " + response.status);

        const data = await response.json();
        //console.log("🔄 New data:", data);
        dataFromLastFetch = data; // Save the data for later use

        if (currentMode === 1) {console.log(data.compo_time); startCountdown("compo-timer", compoTime); overlayEvent=compoTime; }
        if (currentMode === 2) {startCountdown("event-timer", eventTime); overlayEvent=eventTime;}
        return data;
    } catch (error) {
        console.error("❌ Error fetching data:", error);
        return null;
    }
}

function showMode(data) {
    if (!data) return;
    // --- Background refresh ---
    const backgroundEl = document.getElementById("background");
    if (backgroundEl && data.background) {
        backgroundEl.style.backgroundImage = `url(${data.background})`;
    }
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
                        overlayEvent = compoTime;
            break;
        case 2:
            const eventCountdownEl = document.getElementById("event-countdown");
            if (eventCountdownEl) eventCountdownEl.classList.remove("hidden");
            const eventNameEl = document.getElementById("event-name");
            if (eventNameEl) eventNameEl.textContent = data.event_name;
            startCountdown("event-timer", eventTime);
            overlayEvent = compoTime;
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
            startCountdown("compo-timer-overlay", overlayEvent, true);
            buildGallery();
            startGalleryAutoSlide();
            break;
        default:
            console.error("❌ Unknown mode:", currentMode);
            break;
    }
    if (currentMode === 5) {
    const timelineBar = document.getElementById("timeline-bar");
    const timelineContent = document.getElementById("timeline-content");
    if (timelineBar && timelineContent) {
        timelineBar.classList.remove("hidden");
        timelineContent.innerHTML = "";

        // Add the new network data from the AJAX response
        if (data.party_network) {
            const networkDiv = document.createElement("div");
            networkDiv.className = "timeline-item";
            networkDiv.textContent = `Party Network: ${data.party_network}`;
            timelineContent.appendChild(networkDiv);
        }
        if (data.party_wifi_ssid) {
            const wifiSsidDiv = document.createElement("div");
            wifiSsidDiv.className = "timeline-item";
            wifiSsidDiv.textContent = `WiFi SSID: ${data.party_wifi_ssid}`;
            timelineContent.appendChild(wifiSsidDiv);
        }
        if (data.party_wifi_code) {
            const wifiCodeDiv = document.createElement("div");
            wifiCodeDiv.className = "timeline-item";
            wifiCodeDiv.textContent = `WiFi Password: ${data.party_wifi_code}`;
            timelineContent.appendChild(wifiCodeDiv);
        }

        if (data.timeline && data.timeline.length > 0) {
            data.timeline.forEach(item => {
                const div = document.createElement("div");
                div.className = "timeline-item";
                div.textContent = `${item.eventname}: ${item.time}`;
                timelineContent.appendChild(div);
            });
        }
        if (data.party_network) {
            const networkDiv = document.createElement("div");
            networkDiv.className = "timeline-item";
            networkDiv.textContent = `VOTE: ${data.party_network}`;
            timelineContent.appendChild(networkDiv);
        }
        if (data.party_wifi_ssid) {
            const wifiSsidDiv = document.createElement("div");
            wifiSsidDiv.className = "timeline-item";
            wifiSsidDiv.textContent = `WiFi SSID: ${data.party_wifi_ssid}`;
            timelineContent.appendChild(wifiSsidDiv);
        }
        if (data.party_wifi_code) {
            const wifiCodeDiv = document.createElement("div");
            wifiCodeDiv.className = "timeline-item";
            wifiCodeDiv.textContent = `WiFi Password: ${data.party_wifi_code}`;
            timelineContent.appendChild(wifiCodeDiv);
        }
		

        // Restart scroll animation (if it refreshes)
        timelineContent.style.animation = "none";
        timelineContent.offsetHeight; // force reflow
        timelineContent.style.animation = "scroll-timeline 30s linear infinite";
    }
} else {
    // Hide outside of mode 5
    const timelineBar = document.getElementById("timeline-bar");
    if (timelineBar) timelineBar.classList.add("hidden");
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

    // Use images sent by PHP, if any
    const galleryImages = dataFromLastFetch.galleryImages && dataFromLastFetch.galleryImages.length > 0
                              ? dataFromLastFetch.galleryImages
                              : ["https://picsum.photos/1000/600?random=2", "https://picsum.photos/1000/600?random=3"]; // fallback

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
    const slider = document.getElementById("gallery-slider");
    const slides = slider ? slider.querySelectorAll(".slide") : [];
    const totalSlides = slides.length;

    galleryInterval = setInterval(() => {
        if (totalSlides === 0) return;
        currentGallery = (currentGallery + 1) % totalSlides;
        update3DSlider(slider, currentGallery);
    }, 5000);
}

document.addEventListener("keydown", async e => {
    if (e.code === "Space") {
        e.preventDefault();
        //console.log("⏩ SPACE pressed → fetching new data...");
        const newData = await fetchSliderData();
        showMode(newData);
        if (currentMode === 1) {startCountdown("compo-timer", compoTime); overlayEvent=compoTime;}
        if (currentMode === 2) {startCountdown("event-timer", eventTime); overlayEvent=eventTime;}
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
    const totalSlides = slider.querySelectorAll(".slide").length;

    if (e.code === "ArrowRight") {
        currentGallery = (currentGallery + 1) % totalSlides;
        update3DSlider(slider, currentGallery);
        startGalleryAutoSlide();
    }
    if (e.code === "ArrowLeft") {
        currentGallery = (currentGallery - 1 + totalSlides) % totalSlides;
        update3DSlider(slider, currentGallery);
        startGalleryAutoSlide();
    }
}

    if (e.code === "ArrowUp") {
        if (compoTime) compoTime = new Date(compoTime.getTime() + 60 * 1000);
        if (eventTime) eventTime = new Date(eventTime.getTime() + 60 * 1000);
        if (currentMode === 1) {startCountdown("compo-timer", compoTime); overlayEvent=compoTime;}
        if (currentMode === 2) {startCountdown("event-timer", eventTime); overlayEvent=eventTime;}
        if (currentMode === 5) startCountdown("compo-timer-overlay", overlayEvent, true);
    }
    if (e.code === "ArrowDown") {
        if (compoTime) compoTime = new Date(compoTime.getTime() - 60 * 1000);
        if (eventTime) eventTime = new Date(eventTime.getTime() - 60 * 1000);
        if (currentMode === 1) {startCountdown("compo-timer", compoTime); overlayEvent=compoTime;}
        if (currentMode === 2) {startCountdown("event-timer", eventTime); overlayEvent=eventTime;}
        if (currentMode === 5) startCountdown("compo-timer-overlay", overlayEvent, true);
    }

    if (e.key.toLowerCase() === "s") {
        if (currentMode === 5) {
            const backToMode = lastModeBeforeGallery !== -1 ? lastModeBeforeGallery : 0;
            compoTime=overlayEvent; startCountdown("compo-timer", compoTime);
            eventTime=overlayEvent; startCountdown("event-timer", eventTime);
            if (dataFromLastFetch) showMode({ ...dataFromLastFetch, mode: backToMode });
        } else {
            if (dataFromLastFetch) showMode({ ...dataFromLastFetch, mode: 5 });
        }
        if (currentMode === 1) {startCountdown("compo-timer", compoTime); overlayEvent=compoTime;}
        if (currentMode === 2) {startCountdown("event-timer", eventTime); overlayEvent=eventTime;}
    }
});

async function initialLoad() {
    const newData = await fetchSliderData();
    showMode(newData);
}

initialLoad();