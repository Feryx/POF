<?php
// Only accessible by admin
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
<?php
$enable_video_bg = get_option('po_enable_video_bg', 'no');
$video_bg_id     = get_option('po_video_bg', '');
$video_url       = $video_bg_id ? wp_get_attachment_url($video_bg_id) : '';
?>

<div id="background"></div>

<?php if ($enable_video_bg === 'yes' && $video_url): ?>
    <div id="video-background">
        <video autoplay muted loop playsinline>
            <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
        </video>
    </div>
<?php endif; ?>

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
		<div id="prizegiving-left-image"></div> <!-- left side prewiev image -->
        <div id="results-list"></div>
    </div>
</div>

<div id="gallery" class="hidden">
    <div class="slider-container">
        <div class="slider" id="gallery-slider"></div>
    </div>
</div>
<div id="timeline-bar" class="timeline-bar hidden">
    <div id="timeline-content"></div>
</div>
<div id="info-overlay">
    <div id="compo-name-overlay">Next event start:</div>
    <div id="compo-timer-overlay"></div>
</div>
<?php
$logo_url = get_option('po_slider_logo', '');
?>
<?php if (!empty($logo_url)): ?>
    <div id="logo-image">
        <img src="<?php echo esc_url($logo_url); ?>" alt="Slider Logo">
    </div>
<?php endif; ?>

<script src="script.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var enable = "<?php echo esc_js($enable_video_bg); ?>";
    if (enable !== "yes") {
        var vid = document.getElementById("video-background");
        if (vid) vid.remove(); // if not enabled, we hide it
    }
});
</script>

</body>
</html>