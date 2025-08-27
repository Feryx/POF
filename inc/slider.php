<?php
// Add admin menu
add_action('admin_menu', function () {
    add_submenu_page(
        'party-organizer',
        'Beamer',
        'Beamer',
        'manage_options',
        'party-slider',
        'party_slider_admin_page'
    );
});

// Admin page
function party_slider_admin_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . '_Feryx_compos';

    // Load current values
    $mode           = get_option('party_slider_mode', -1);
    $announcement = get_option('party_slider_announcement_text', '');
    $compo_id       = get_option('party_slider_compo_id', '');
    $event_name     = get_option('party_slider_event_name', '');
    $event_time     = get_option('party_slider_event_time', '');
    $prize_id       = get_option('party_slider_prizegiving_id', '');
if (empty($event_time)) {
    $event_time = current_time('Y-m-d\TH:i');
}
    // Form processing
    if (isset($_POST['party_slider_action'])) {
        check_admin_referer('party_slider_nonce');

        switch ($_POST['party_slider_action']) {
            case 'announcement':
                update_option('party_slider_mode', 0);
                update_option('party_slider_announcement_text', sanitize_text_field($_POST['announcement_text']));
                break;

            case 'compo_countdown':
                update_option('party_slider_mode', 1);
                update_option('party_slider_compo_id', intval($_POST['compo_id']));
                break;

            case 'event_countdown':
                update_option('party_slider_mode', 2);
                update_option('party_slider_event_name', sanitize_text_field($_POST['event_name']));
                update_option('party_slider_event_time', sanitize_text_field($_POST['event_time']));
                break;

            case 'compo_display':
                update_option('party_slider_mode', 3);
                update_option('party_slider_compo_id', intval($_POST['compo_id2']));
                break;

            case 'prizegiving':
                update_option('party_slider_mode', 4);
                update_option('party_slider_prizegiving_id', intval($_POST['prize_id']));
                break;
        }

        echo '<div class="updated"><p>Settings saved!</p></div>';

        // Update variables with new values
        $mode           = get_option('party_slider_mode', -1);
        $announcement = get_option('party_slider_announcement_text', '');
        $compo_id       = get_option('party_slider_compo_id', '');
        $event_name     = get_option('party_slider_event_name', '');
        $event_time     = get_option('party_slider_event_time', '');
        $prize_id       = get_option('party_slider_prizegiving_id', '');
    }

    // Get compo list
    $compos = $wpdb->get_results("SELECT id, name FROM $table_name ORDER BY name");

    ?>
    <div class="wrap">
        <h1>Party Slider</h1>

                <p>
                    <a class="button button-primary" target="_blank"
                       href="<?php echo plugins_url('/../slideviewer/slideviewer.php', __FILE__); ?>">
                        Open slideviewer
                    </a>
                </p>
<p>
<ul>
<li><strong>LEFT ARROW - previous slide</strong></li>
<li><strong>RIGHT ARROW - next slide</strong></li>
<li><strong>UP ARROW - plus one minute in countdown mode</strong></li>
<li><strong>DOWN ARROW - minus one minute in countdown mode</strong></li>
<li><strong>S - partyslide rotation mode</strong></li>
<li><strong>SPACE - reload datas from server (and quit partyslide mode)</strong></li>
</ul>
</p>
        <form method="post">
            <?php wp_nonce_field('party_slider_nonce'); ?>

<h1>Announcement</h1>
<?php
wp_editor(
    $announcement,                           // default value
    'announcement_text',                     // field ID (will also be the name attribute)
    array(
        'textarea_name' => 'announcement_text', // this is needed for the correct name in the POST form
        'textarea_rows' => 3,                    // number of rows
        'media_buttons' => false,                // no "Add Media" button
        'teeny' => true,                         // simplified toolbar
        'quicktags' => true,                     // enable HTML mode toggle
    )
);
?>
<p>
    <button type="submit" name="party_slider_action" value="announcement" class="button">
        Switch to Announcement mode
    </button>
</p>

            <h1>Compo countdown</h1>
            <select name="compo_id">
                <?php foreach ($compos as $c): ?>
                    <option value="<?php echo esc_attr($c->id); ?>" <?php selected($c->id, $compo_id); ?>>
                        <?php echo esc_html($c->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p>
                <button type="submit" name="party_slider_action" value="compo_countdown" class="button">
                    Switch Compo Countdown mode
                </button>
            </p>

            <h1>Event countdown</h1>
            <input type="text" name="event_name" value="<?php echo esc_attr($event_name); ?>" placeholder="Event name" class="regular-text" />
            <input type="datetime-local" name="event_time" value="<?php echo esc_attr($event_time); ?>" />
            <p>
                <button type="submit" name="party_slider_action" value="event_countdown" class="button">
                    Switch Event Countdown mode
                </button>
            </p>

            <h1>Compo display</h1>
            <select name="compo_id2">
                <?php foreach ($compos as $c): ?>
                    <option value="<?php echo esc_attr($c->id); ?>" <?php selected($c->id, $compo_id); ?>>
                        <?php echo esc_html($c->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p>
                <button type="submit" name="party_slider_action" value="compo_display" class="button">
                    Switch Compo Display mode
                </button>
            </p>

            <h1>Prizegiving</h1>
            <select name="prize_id">
                <?php foreach ($compos as $c): ?>
                    <option value="<?php echo esc_attr($c->id); ?>" <?php selected($c->id, $prize_id); ?>>
                        <?php echo esc_html($c->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p>
                <button type="submit" name="party_slider_action" value="prizegiving" class="button">
                    Switch Prizegiving mode
                </button>
            </p>
        </form>
    </div>
    <?php
}