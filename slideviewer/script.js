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
        dataFromLastFetch = data;
        console.log("📡 fetchSliderData →", data); // <-- SPACE check datas

        if (currentMode === 1) { startCountdown("compo-timer", compoTime); overlayEvent = compoTime; }
        if (currentMode === 2) { startCountdown("event-timer", eventTime); overlayEvent = eventTime; }
        return data;
    } catch (error) {
        console.error("❌ Error fetching data:", error);
        return null;
    }
}

function showMode(data) {
    if (!data) return;

    const backgroundEl = document.getElementById("background");
    if (backgroundEl && data.background) {
        backgroundEl.style.backgroundImage = `url(${data.background})`;
    }
    const newMode = parseInt(data.mode);
    if (newMode !== currentMode) {
        lastModeBeforeGallery = currentMode;
        currentMode = newMode;
    }

    if (data.event_time && data.event_time !== 'nodataERR') eventTime = new Date(data.event_time);
    if (data.compo_time && data.compo_time !== 'nodataERR') compoTime = new Date(data.compo_time);

    if (data.data && data.data.length > 0) {
        if (currentMode === 3) {
            prods = data.data;
        } else if (currentMode === 4) {
            // Prizegiving: calculating results and max points
            results = data.data.slice().reverse();
            maxPoints = results.length ? Math.max(...results.map(r => r.total_points)) : 0;
        }
    }

    document.querySelectorAll(".slide-mode").forEach(m => m.classList.add("hidden"));
    document.getElementById("gallery").classList.add("hidden");
    document.getElementById("info-overlay").classList.remove("visible");
    document.getElementById("background").style.filter = "none";
    clearInterval(compoTimerInterval);
    clearInterval(eventTimerInterval);
    clearInterval(galleryInterval);

    if (data.enable_screenshots) enableScreenshots = data.enable_screenshots;

    switch (currentMode) {
        case 0:
            document.getElementById("announcement")?.classList.remove("hidden");
            document.getElementById("announcement-text").textContent = data.announcement_text;
            break;
        case 1:
            document.getElementById("compo-countdown")?.classList.remove("hidden");
            document.getElementById("compo-name").textContent = data.event_name;
            startCountdown("compo-timer", compoTime);
            overlayEvent = compoTime;
            break;
        case 2:
            document.getElementById("event-countdown")?.classList.remove("hidden");
            document.getElementById("event-name").textContent = data.event_name;
            startCountdown("event-timer", eventTime);
            overlayEvent = compoTime;
            break;
        case 3:
            document.getElementById("compo-display")?.classList.remove("hidden");
            buildProds();
            break;
        case 4:
            document.getElementById("prizegiving")?.classList.remove("hidden");
            document.getElementById("prizegiving-compo-name").textContent = data.event_name || "—";
            buildPrizes();
            break;
        case 5:
            document.getElementById("gallery")?.classList.remove("hidden");
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
}

function startCountdown(elId, targetTime, isOverlay = false) {
    function update() {
        if (!targetTime || isNaN(targetTime.getTime())) return;
        const now = new Date();
        let diff = Math.floor((targetTime.getTime() - now.getTime()) / 1000);
        let text = "";
        if (diff <= 0) text = "SOON";
        else if (diff > 5999) text = "∞";
        else {
            let m = Math.floor(diff / 60);
            let s = diff % 60;
            text = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
        }
        const el = isOverlay ? document.getElementById("compo-timer-overlay") : document.getElementById(elId);
        if (el) el.textContent = text;
    }
    update();
    if (elId.includes("compo-timer")) { clearInterval(compoTimerInterval); compoTimerInterval = setInterval(update, 1000); }
    if (elId.includes("event-timer")) { clearInterval(eventTimerInterval); eventTimerInterval = setInterval(update, 1000); }
}

function buildProds() {
    const slider = document.getElementById("prod-slider");
    if (!slider) return;
    slider.innerHTML = "";

    // Event name + Competition Start
    const eventName = dataFromLastFetch?.event_name || "—";
    slider.appendChild(createSlide(`${eventName}<br>Competition Start`, "Get ready!"));

    prods.forEach((p, index) => {
        const imageHtml = (enableScreenshots === 'yes') ? `<img class="prod-prew-image" src="${p.screenshot}" width="299" height="299">` : '';
        const slideContent = `<div class="prod-number">#${index + 1}</div>${imageHtml}<h1>${p.product_title}</h1><h2>by ${p.author}</h2><p>${p.comment_public}</p>`;
        slider.appendChild(createSlide(null, slideContent));
    });
    slider.appendChild(createSlide("Competition End", "Thank you for participating!"));
    currentProd = 0;
    update3DSlider(slider, currentProd);
}


function buildPrizes() {
    const compoName = dataFromLastFetch?.event_name || "—";
    document.getElementById("prizegiving-compo-name").textContent = compoName;
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

    // draw the prew img
const imgDiv = document.getElementById("prizegiving-left-image");
if (imgDiv) {
    imgDiv.innerHTML = ""; 
    if (newResult.screenshot) {
        const img = document.createElement("img");
        img.src = newResult.screenshot;
        imgDiv.appendChild(img);
        // fade-in anim
        requestAnimationFrame(() => {
            img.classList.add("visible");
        });
    }
}

    setTimeout(() => {
        resultItem.classList.add("active");
        setTimeout(() => {
            const widthPercentage = maxPoints ? (newResult.total_points / maxPoints) * 100 : 0;
            pointsBarFill.style.width = `${widthPercentage}%`;
        }, 800);
    }, 10);
}


function createSlide(title, content) {
    const div = document.createElement("div");
    div.className = "slide";
    if (title) div.innerHTML += `<h1>${title}</h1>`;
    if (content) div.innerHTML += `<div class="inner-text">${content}</div>`;
    return div;
}

function update3DSlider(slider, index) {
    if (!slider) return;
    const slides = slider.querySelectorAll(".slide");
    slides.forEach((s, i) => {
        s.classList.remove("active", "prev", "next");
        if (i === index) s.classList.add("active");
        else if (i < index) s.classList.add("prev");
        else s.classList.add("next");
    });
}

function buildGallery() {
    const slider = document.getElementById("gallery-slider");
    if (!slider) return;
    slider.innerHTML = "";
    const galleryImages = dataFromLastFetch.galleryImages?.length
        ? dataFromLastFetch.galleryImages
        : ["https://picsum.photos/1000/600?random=2", "https://picsum.photos/1000/600?random=3"];
    galleryImages.forEach(src => {
        const div = document.createElement("div");
        div.className = "slide gallery-slide";
        const img = document.createElement("img");
        img.src = src;
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
        const newData = await fetchSliderData();
        console.log("➡️ After SPACE:", newData); 
        showMode(newData);
    }

    if (currentMode === 3) {
        const slider = document.getElementById("prod-slider");
        if (e.code === "ArrowRight") { currentProd = Math.min(currentProd + 1, prods.length + 1); update3DSlider(slider, currentProd); }
        if (e.code === "ArrowLeft") { currentProd = Math.max(currentProd - 1, 0); update3DSlider(slider, currentProd); }
    }

    if (currentMode === 4) {
        if (e.code === "ArrowRight") { if (currentPrize < results.length - 1) { currentPrize++; updatePrizes(); } }
if (e.code === "ArrowLeft") {
    const list = document.getElementById("results-list");
    if (list.children.length > 0) { 
        list.firstChild.remove(); 
        currentPrize--; 

        const imgDiv = document.getElementById("prizegiving-left-image");
if (imgDiv) {
    imgDiv.innerHTML = "";
    if (currentPrize >= 0 && currentPrize < results.length) {
        const prevResult = results[currentPrize];
        if (prevResult.screenshot) {
            const img = document.createElement("img");
            img.src = prevResult.screenshot;
            imgDiv.appendChild(img);
            // fade-in anim
            requestAnimationFrame(() => {
                img.classList.add("visible");
            });
        }
    }
}

    }
}
    }

    if (currentMode === 5) {
        const slider = document.getElementById("gallery-slider");
        const totalSlides = slider.querySelectorAll(".slide").length;
        if (e.code === "ArrowRight") { currentGallery = (currentGallery + 1) % totalSlides; update3DSlider(slider, currentGallery); startGalleryAutoSlide(); }
        if (e.code === "ArrowLeft") { currentGallery = (currentGallery - 1 + totalSlides) % totalSlides; update3DSlider(slider, currentGallery); startGalleryAutoSlide(); }
    }
});

async function initialLoad() {
    const newData = await fetchSliderData();
    showMode(newData);
}
initialLoad();
