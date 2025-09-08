<?php
function po_settings_page() {
    // Save settings
    if (isset($_POST['po_save_times'])) {
        update_option('po_party_start', sanitize_text_field($_POST['po_party_start']));
        update_option('po_doors_open', sanitize_text_field($_POST['po_doors_open']));
        update_option('po_doors_close', sanitize_text_field($_POST['po_doors_close']));
        update_option('po_opening_ceremony', sanitize_text_field($_POST['po_opening_ceremony']));
        update_option('po_previewSize', sanitize_text_field($_POST['po_previewSize']));

        // **Video Background enable**
        $enable_footertext = isset($_POST['po_enable_footertext']) ? 'yes' : 'no';
        update_option('po_enable_footertext', $enable_footertext);
        // Checkbox
        $enable_screenshots = isset($_POST['po_enable_screenshots']) ? 'yes' : 'no';
        update_option('po_enable_screenshots', $enable_screenshots);

        // Multiple images slider
        if (isset($_POST['po_slider_images'])) {
            $images = array_map('esc_url_raw', $_POST['po_slider_images']);
            update_option('po_slider_images', json_encode($images));
        }

        // **Slider Basic Background**
        if (isset($_POST['po_slider_bg'])) {
            update_option('po_slider_bg', esc_url_raw($_POST['po_slider_bg']));
        }

        // **Slider Logo**
        if (isset($_POST['po_slider_logo'])) {
            update_option('po_slider_logo', esc_url_raw($_POST['po_slider_logo']));
        }

        // **Video Background enable**
        $enable_video_bg = isset($_POST['po_enable_video_bg']) ? 'yes' : 'no';
        update_option('po_enable_video_bg', $enable_video_bg);

        // **Video Background (media id)**
        if (isset($_POST['po_video_bg'])) {
            update_option('po_video_bg', intval($_POST['po_video_bg']));
        }

        // **Extra Party Network Settings**
        update_option('po_partynetwork', sanitize_text_field($_POST['po_partynetwork']));
        update_option('po_partywifissid', sanitize_text_field($_POST['po_partywifissid']));
        update_option('po_partywificode', sanitize_text_field($_POST['po_partywificode']));

        echo '<div class="updated notice"><p>Settings saved!</p></div>';
    }

    // Load settings
    $party_start        = get_option('po_party_start', '');
    $doors_open         = get_option('po_doors_open', '');
    $doors_close        = get_option('po_doors_close', '');
    $opening_ceremony   = get_option('po_opening_ceremony', '');
    $preview_Size       = get_option('po_previewSize', '300');
    $enable_screenshots = get_option('po_enable_screenshots', 'yes');
    $slider_images      = json_decode(get_option('po_slider_images', '[]'), true);
    $slider_bg          = get_option('po_slider_bg', '');
    $slider_logo        = get_option('po_slider_logo', '');
    $enable_video_bg    = get_option('po_enable_video_bg', 'no');
    $video_bg_id        = get_option('po_video_bg', '');
    $enable_video_bg    = get_option('po_enable_video_bg', 'no');
    $enable_footertext  = get_option('po_enable_footertext', 'yes');
    // Extra Party Network Settings
    $partynetwork       = get_option('po_partynetwork', '');
    $partywifissid      = get_option('po_partywifissid', '');
    $partywificode      = get_option('po_partywificode', '');

    // Enqueue WP Media script
    wp_enqueue_media();

    echo '<div class="wrap">';
    echo '<h1>Basics Settings</h1>';
    echo '<form method="post">';

    // Preview size
    echo '<p><label><strong>Preview Size (300px):</strong></label><br>';
    echo '<input type="number" name="po_previewSize" value="' . esc_attr($preview_Size) . '"></p>';   

	echo '<p><label><strong>„Show attribution in footer: This party system is made possible by Feryx Party Organizer”:</strong></label><br>';
        echo '<input type="checkbox" name="po_enable_footertext" value="yes" ' . checked('yes', $enable_footertext, false) . '> Enable</p>';
    // Preview size
    echo '<p><label><strong>Preview Size (300px):</strong></label><br>';
    echo '<input type="number" name="po_previewSize" value="' . esc_attr($preview_Size) . '"></p>';
    // Preview size
    echo '<p><label><strong>Preview Size (300px):</strong></label><br>';
    echo '<input type="number" name="po_previewSize" value="' . esc_attr($preview_Size) . '"></p>';

    // Party start
    echo '<p><label><strong>Party start:</strong></label><br>';
    echo '<input type="datetime-local" name="po_party_start" value="' . esc_attr($party_start) . '"></p>';

    // Doors open
    echo '<p><label><strong>Doors open:</strong></label><br>';
    echo '<input type="datetime-local" name="po_doors_open" value="' . esc_attr($doors_open) . '"></p>';

    // Doors close
    echo '<p><label><strong>Doors close:</strong></label><br>';
    echo '<input type="datetime-local" name="po_doors_close" value="' . esc_attr($doors_close) . '"></p>';

    // Opening ceremony
    echo '<p><label><strong>Opening ceremony:</strong></label><br>';
    echo '<input type="datetime-local" name="po_opening_ceremony" value="' . esc_attr($opening_ceremony) . '"></p>';

    echo '<h1>Beamer Settings</h1>';
    $plugin_url = plugin_dir_url(__DIR__);
    echo "<p>For edit the sideviewer stylesheet: " . $plugin_url . 'slideviewer/style.css' . "</p>";

    // Enable screenshots checkbox
    echo '<p><label><strong>Enable Screenshots for Slider:</strong></label><br>';
    echo '<input type="checkbox" name="po_enable_screenshots" value="yes" ' . checked('yes', $enable_screenshots, false) . '> Enable</p>';

    // Multiple images slider
    echo '<p><label><strong>Marketing Slider Images:</strong></label><br>';
    echo '<button class="button" id="po_upload_images_button">Select Images</button></p>';
    echo '<ul id="po_selected_images" style="list-style-type: none; margin: 0; padding: 0;">';
    if ($slider_images) {
        foreach ($slider_images as $img) {
            echo '<li style="float: left;"><input type="hidden" name="po_slider_images[]" value="' . esc_url($img) . '"><img src="' . esc_url($img) . '" style="max-width:100px;margin:5px;"></li>';
        }
    }
    echo '</ul><br>';

    // **Slider Basic Background**
    echo '<br>';
    echo '<p style="padding-top:100px;"><hr><label><strong>Slider Basic Background:</strong></label><br>';
    echo '<button class="button" id="po_upload_bg_button">Select Background</button></p>';
    echo '<div id="po_selected_bg">';
    if ($slider_bg) {
        echo '<input type="hidden" name="po_slider_bg" value="' . esc_url($slider_bg) . '">';
        echo '<img src="' . esc_url($slider_bg) . '" style="max-width:200px;margin:5px;">';
    }
    echo '</div>';

    // **Slider Logo**
    echo '<hr>';
    echo '<p><label><strong>Slider Logo (PNG):</strong></label><br>';
    echo '<button class="button" id="po_upload_logo_button">Select Logo</button></p>';
    echo '<div id="po_selected_logo">';
    if ($slider_logo) {
        echo '<input type="hidden" name="po_slider_logo" value="' . esc_url($slider_logo) . '">';
        echo '<img src="' . esc_url($slider_logo) . '" style="max-width:150px;margin:5px;">';
    }
    echo '</div>';

    // **Video Background**
    echo '<hr>';
    echo '<p><label><strong>Enable Video Background for beamer:</strong></label><br>';
    echo '<input type="checkbox" name="po_enable_video_bg" value="yes" ' . checked('yes', $enable_video_bg, false) . '> Enable</p>';

    echo '<p><label><strong>Video Background:</strong></label><br>';
    echo '<button class="button" id="po_upload_video_button">Select Video</button></p>';
    echo '<div id="po_selected_video">';
    if ($video_bg_url) {
        echo '<input type="hidden" name="po_video_bg" value="' . intval($video_bg_id) . '">';
        echo '<video src="' . esc_url($video_bg_url) . '" style="max-width:300px;max-height:200px;" controls muted></video>';
    }
    echo '</div>';

    // **Extra Party Network Settings**
    echo '<hr>';
    echo '<h2>Party Network Settings</h2>';
    echo '<p><label><strong>Party Network:</strong></label><br>';
    echo '<input type="text" name="po_partynetwork" value="' . esc_attr($partynetwork) . '" style="width:300px;"></p>';

    echo '<p><label><strong>Party WiFi SSID:</strong></label><br>';
    echo '<input type="text" name="po_partywifissid" value="' . esc_attr($partywifissid) . '" style="width:300px;"></p>';

    echo '<p><label><strong>Party WiFi Code:</strong></label><br>';
    echo '<input type="text" name="po_partywificode" value="' . esc_attr($partywificode) . '" style="width:300px;"></p>';

    echo '<p class="submit"><button type="submit" name="po_save_times" class="button button-primary">Save</button></p>';
    echo '</form>';

    // JS for Media Uploader
    ?>
    <script>
    jQuery(document).ready(function($){
        // Multiple images slider
        var frame;
        $('#po_upload_images_button').on('click', function(e){
            e.preventDefault();
            if(frame){ frame.open(); return; }
            frame = wp.media({
                title: 'Select Images for Slider',
                button: { text: 'Use Selected Images' },
                multiple: true
            });
            frame.on('select', function(){
                var attachments = frame.state().get('selection').toArray();
                $('#po_selected_images').empty();
                attachments.forEach(function(attachment){
                    var url = attachment.attributes.url;
                    $('#po_selected_images').append('<li><input type="hidden" name="po_slider_images[]" value="'+url+'"><img src="'+url+'" style="max-width:100px;margin:5px;"></li>');
                });
            });
            frame.open();
        });

        // Slider Basic Background
        var frameBg;
        $('#po_upload_bg_button').on('click', function(e){
            e.preventDefault();
            if(frameBg){ frameBg.open(); return; }
            frameBg = wp.media({
                title: 'Select Background Image',
                button: { text: 'Use This Image' },
                multiple: false
            });
            frameBg.on('select', function(){
                var attachment = frameBg.state().get('selection').first().toJSON();
                $('#po_selected_bg').html('<input type="hidden" name="po_slider_bg" value="'+attachment.url+'"><img src="'+attachment.url+'" style="max-width:200px;margin:5px;">');
            });
            frameBg.open();
        });

        // Slider Logo (PNG only)
        var frameLogo;
        $('#po_upload_logo_button').on('click', function(e){
            e.preventDefault();
            if(frameLogo){ frameLogo.open(); return; }
            frameLogo = wp.media({
                title: 'Select Logo (PNG)',
                button: { text: 'Use This Logo' },
                library: { type: 'image' },
                multiple: false
            });
            frameLogo.on('select', function(){
                var attachment = frameLogo.state().get('selection').first().toJSON();
                if(attachment.subtype !== 'png'){ alert('Please select a PNG image.'); return; }
                $('#po_selected_logo').html('<input type="hidden" name="po_slider_logo" value="'+attachment.url+'"><img src="'+attachment.url+'" style="max-width:150px;margin:5px;">');
            });
            frameLogo.open();
        });

        // Video Background
        var frameVideo;
        $('#po_upload_video_button').on('click', function(e){
            e.preventDefault();
            if(frameVideo){ frameVideo.open(); return; }
            frameVideo = wp.media({
                title: 'Select Background Video',
                button: { text: 'Use This Video' },
                library: { type: 'video' },
                multiple: false
            });
            frameVideo.on('select', function(){
                var attachment = frameVideo.state().get('selection').first().toJSON();
                $('#po_selected_video').html('<input type="hidden" name="po_video_bg" value="'+attachment.id+'"><video src="'+attachment.url+'" style="max-width:300px;max-height:200px;" controls muted></video>');
            });
            frameVideo.open();
        });
    });
    </script>
    <?php
    echo '</div>';
}
