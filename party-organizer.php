<?php
/*
Plugin Name: POF+ Party Organizer By Feryx
Description: A wp plugin for managing Demoscene competitions and productions.
Version: 0.92
Author: Feryx
RequiresPlugins: woocommerce/woocommerce.php
Text Domain: party-organizer
License: NonCommercial Use Only
*/

if (!defined('ABSPATH')) exit;

// Create 'Visitor' role on activation
register_activation_hook(__FILE__, function () {
    add_role('visitor', 'Visitor', [
        'read' => true,
    ]);
});

add_action('init', function() {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_timeline';
    $wpdb->get_results("SELECT * FROM $table");
});


function feryx_check_access($page_name = 'Page') {
    if (!is_user_logged_in()) {
        return '<p>Please, <a href="' . esc_url(wp_login_url()) . '">login</a> for ' . esc_html($page_name) . '!</p>';
    }

    // Check user role: visitor or administrator
    $user = wp_get_current_user();
    if (!in_array('visitor', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
        return '<p>You are not authorized to ' . esc_html($page_name) . '! Register and enter your votekey.</p>';
    }

    return true; // Everything is okay
}

function po_admin_menu_color() {
    ?>
    <style>
    #toplevel_page_party-organizer > a {color: #00ff00!important;}
    </style>
    <?php
}
add_action('admin_head', 'po_admin_menu_color');

add_action('admin_menu', function () {
    // Main menu - Party Organizer
    add_menu_page(
        'Party Organizer',
        'Party Organizer',
        'manage_options',
        'party-organizer',
        'po_dashboard_page',
        'dashicons-groups',
        0
    );

    add_submenu_page(
        'party-organizer',
        'Edit Competitions',
        'Edit Competitions',
        'manage_options',
        'po_admin_page',
        'po_admin_page'
    );
    // OrderProds
    add_submenu_page(
        'party-organizer',
        'Organize Prods',
        'Organize Prods',
        'manage_options',
        'po_organize_page',
        'po_organize_page'
    );
    // Export menu item
    add_submenu_page(
        'party-organizer',
        'Entries Export',
        'Entries Export',
        'manage_options',
        'po-export',
        function() {
            if (isset($_POST['do_export'])) {
                echo '<div class="updated"><p>' . feryx_export_entries() . '</p></div>';
            }
            echo '<p>This button exports all entries to a specified folder structure.<br>wp-content/uploads/exported/<br>
    /CompoName/<br>
        /0001/<br>
            producttitle_author.zip<br>
        /0002/<br>
            producttitle_author.zip<br></p><br>';
            echo '<form method="post"><button type="submit" name="do_export" class="button button-primary">Export</button></form>';
        }
    );
    // Timeline
    add_submenu_page(
        'party-organizer',
        'TimeLine',
        'TimeLine',
        'manage_options',
        'po-TimeLine',
        'Feryx_TimeLine'
    );
    // Votekeys
    add_submenu_page(
        'party-organizer',
        'VoteKeys',
        'VoteKeys',
        'manage_options',
        'po-votekey',
        'po_votekey_page'
    );
    // Live vote
    add_submenu_page(
        'party-organizer',
        'LiveVote',
        'LiveVote',
        'manage_options',
        'po-livevote',
        'po_livevote_page'
    );
    // Settings
    add_submenu_page(
        'party-organizer',
        'Settings',
        'Settings',
        'manage_options',
        'po-settings',
        'po_settings_page'
    );
});

// Dashboard page (Usage Guide)
function po_dashboard_page() {
    ?>
<div class="wrap">
    <h1>Party Organizer – Usage Guide</h1>
    <p>Here's a step-by-step guide on how to use the plugin.</p>
    <ol id="pof-usage-list">
        <li><input type="checkbox" data-step="1" checked> Install WordPress and set up your party website (content, theme, etc.).</li>
        <li><input type="checkbox" data-step="2" checked> If you want to sell tickets and merchandise online, install WooCommerce (v10.x tested) and configure it.<br>After installed Woo enable the POF+ Ticket plugin for sell and check tickets.</li>
        <li><input type="checkbox" data-step="3" checked> Install the POF plugin.</li>
        <li><input type="checkbox" data-step="4"> After installation, run the Permalink refresh once:<br>(Dashboard → Settings → Permalinks → Save) to ensure everything works.</li>
        <li><input type="checkbox" data-step="5"> Organize the menu items on the frontend wherever you like.<br>(Dashboard → Appearance → Menus)</li>
        <li><input type="checkbox" data-step="6"> Fill the tables with test data if needed.<br>(Dashboard → POF Lorem Ipsum)</li>
        <li><input type="checkbox" data-step="7"> Go to the Competitions edit page.<br>(Dashboard → POF → Edit Competitions) and configure the competitions.</li>
        <li><input type="checkbox" data-step="8"> Replace the results file header:<br><code><?php echo plugin_dir_path(__FILE__) . 'header_results.txt'; ?></code></li>
        <li><input type="checkbox" data-step="9"> Go to the Settings page.<br>(Dashboard → POF → Settings)</li>
        <li><input type="checkbox" data-step="10"> Set up the competition dates and times.</li>
        <li><input type="checkbox" data-step="11"> Configure the beamer images and its behavior.</li>
        <li><input type="checkbox" data-step="12"> If you want to configure Slideviewer, easily modify the stylesheet:<br>
            <code><?php echo plugin_dir_path(__FILE__) . 'slideviewer/style.css'; ?></code>
        </li>
        <li><input type="checkbox" data-step="13"> Create VoteKeys!<br>(Dashboard → POF → VoteKeys)</li>
        <li><input type="checkbox" data-step="14"> Edit the timeline.<br>(Dashboard → POF → Timeline)</li>
        <li><input type="checkbox" data-step="15"> Enjoy the event!</li>
        <li><input type="checkbox" data-step="16"> After the prizegiving Enable the public results view! <br>It will disable the Votes menus and allow visitors to see the results! (Dashboard → POF → Results)</li>
    </ol>

	
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const checkboxes = document.querySelectorAll("#pof-usage-list input[type=checkbox]");

    checkboxes.forEach(cb => {
        const key = "pof_step_" + cb.dataset.step;

   
        if (localStorage.getItem(key) !== null) {
            cb.checked = (localStorage.getItem(key) === "true");
        }

        cb.addEventListener("change", function () {
            localStorage.setItem(key, cb.checked);
        });
    });
});
</script>


    <?php
}
require 'inc/visitors.php';
require 'inc/votekeys.php';
require 'inc/settings.php';
require 'inc/organize.php';
require 'inc/visitorsVote.php';
require 'inc/exporter.php';
require 'inc/slider.php';

// Create tables on activation
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // wp_compos table
    $table_name = $wpdb->prefix . '_Feryx_compos';
    $sql_compos = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        backscreen varchar(255) DEFAULT '0',
        start_time datetime DEFAULT NULL,
        online tinyint(1) DEFAULT 0,
        live tinyint(1) DEFAULT 0,
        upload tinyint(1) DEFAULT 1,
        editing tinyint(1) DEFAULT 1,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // wp_votekeys table
    $votekey_table = $wpdb->prefix . '_Feryx_votekeys';
    $sql_vote = "CREATE TABLE $votekey_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        votekey varchar(255) NOT NULL,
        used tinyint(1) NOT NULL DEFAULT 0,
        online tinyint(1) NOT NULL DEFAULT 0,
        token VARCHAR(64) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // wp_prods table
$prods_table = $wpdb->prefix . '_Feryx_prods';
$sql_prods = "CREATE TABLE $prods_table (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id_uploader bigint(20) UNSIGNED NOT NULL DEFAULT 0,
    compo_id mediumint(9) UNSIGNED NOT NULL DEFAULT 0,
    product_title varchar(150) NOT NULL,
    author varchar(100) NOT NULL,
    comment_public text NOT NULL,
    comment_private text NOT NULL,
    uploaded_file varchar(30) NOT NULL,
    screenshot varchar(30) NOT NULL,
    status tinyint(1) NOT NULL DEFAULT 0,
    uploadTime datetime DEFAULT NULL,
    version smallint(5) UNSIGNED NOT NULL DEFAULT 0,
    livevoteflag TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    orderid TINYINT(2) UNSIGNED NOT NULL,
    PRIMARY KEY (id)
) $charset_collate;";

    // Timeline table
    $timeline_table = $wpdb->prefix . '_Feryx_timeline';
    $sql_timeline = "CREATE TABLE $timeline_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        list_id mediumint(20) UNSIGNED NOT NULL DEFAULT 0,
        eventname text NOT NULL,
        mainevent tinyint(1) NOT NULL DEFAULT 0,
        compo_id mediumint(9) UNSIGNED NOT NULL,
        time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Votes table
    $votes_table = $wpdb->prefix . '_Feryx_votes';
    $sql_votes = "CREATE TABLE $votes_table (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    compo_id mediumint(4) NOT NULL,
    user_id mediumint(4) NOT NULL,
    entry_id mediumint(4) NOT NULL,
    vote mediumint(4) NOT NULL,
    votetime datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_compos);
    dbDelta($sql_vote);
    dbDelta($sql_prods);
    dbDelta($sql_timeline);
    dbDelta($sql_votes);

    // Create 'Visitor' role
    add_role('visitor', 'Visitor', [
        'read' => true,
        'level_0' => true,
    ]);

    // Set 'visitor' as the default role for registration
    //update_option('default_role', 'visitor');
});

// Upload menu
require 'inc/UploadProds.php';
register_activation_hook(__FILE__, 'feryx_create_pages');
// Edit menu
require 'inc/product-edit.php';
register_activation_hook(__FILE__, 'feryx_create_pages_edit');
// Timeline menu
require 'inc/timeline.php';
//register_activation_hook(__FILE__, 'Feryx_TimeLine');

// Admin menu
require 'inc/adminMenu.php';
// Vote
require 'inc/vote.php';
// Visitors Live Vote
require 'inc/livevoteVisitors.php';
// results
require 'inc/results.php';

function feryx_enqueue_admin_scripts($hook) {
    // Only on our subpage
    if($hook != 'party-organizer_page_po-TimeLine') return;

    wp_enqueue_script('jquery-ui-sortable'); // required for sortable
    wp_enqueue_script('jquery'); // just to be sure jQuery is there
}
add_action('admin_enqueue_scripts', 'feryx_enqueue_admin_scripts');

add_action('wp_footer', function() {
	$enable_footertext  = get_option('po_enable_footertext', 'no');
	if($enable_footertext==='yes'){
    echo '<p class="has-text-align-center feryx-footer-credit">
    This party & ticket system is made possible by <a href="https://feryx.hu" target="_blank">Feryx </a><a href="https://github.com/Feryx/POF" target="_blank">Party Organizer+</a>
</p>
	';}
});

add_action('wp_ajax_update_timeline_order', 'update_timeline_order');
function update_timeline_order() {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_timeline';
    if (isset($_POST['order']) && is_array($_POST['order'])) {
        foreach($_POST['order'] as $index => $id) {
            $wpdb->update($table, ['list_id' => $index], ['id' => intval($id)]);
        }
    }
    wp_send_json_success();
}

// Frontend AJAX
add_action('wp_ajax_nopriv_party_slider_mode', function() {
    $response = [
        'mode' => get_option('party_slider_mode', -1),
        'announcement_text' => get_option('party_slider_announcement_text', ''),
        'event_name' => get_option('party_slider_event_name', ''),
        'event_time' => get_option('party_slider_event_time', ''),
        'compo_id' => get_option('party_slider_compo_id', ''),
		'party_network'     => get_option('po_party_network', ''),
		'party_wifi_ssid'   => get_option('po_party_wifissid', ''),
		'party_wifi_code'   => get_option('po_party_wificode', ''),
        'prize_id' => get_option('party_slider_prizegiving_id', ''),
    ];
    wp_send_json($response);
});

// Admin AJAX
add_action('wp_ajax_party_slider_mode', function() {
    $response = [
        'mode' => get_option('party_slider_mode', -1),
        'announcement_text' => get_option('party_slider_announcement_text', ''),
        'event_name' => get_option('party_slider_event_name', ''),
        'event_time' => get_option('party_slider_event_time', ''),
        'compo_id' => get_option('party_slider_compo_id', ''),
        'prize_id' => get_option('party_slider_prizegiving_id', ''),
    ];
    wp_send_json($response);
});

add_action('wp_ajax_get_party_slider_mode', function() {
    global $wpdb;

    // Default settings
    $mode               = get_option('party_slider_mode', -1);
    $announcement_text  = get_option('party_slider_announcement_text', 'nodataERR');
    $event_name         = get_option('party_slider_event_name', 'nodataERR');
    $event_time         = get_option('party_slider_event_time', 'nodataERR');
    $compo_id           = get_option('party_slider_compo_id', 'nodataERR');
    $prize_id           = get_option('party_slider_prizegiving_id', 'nodataERR');
    $enable_screenshots = get_option('po_enable_screenshots', 'yes');  
	$party_network   = get_option('po_partynetwork', 'ERRs');
    $party_wifi_ssid = get_option('po_partywifissid', 'ERRs');
    $party_wifi_code = get_option('po_partywificode', 'ERRs');
    $compo_time         = 'nodataERR';
    $basic_bg           = get_option('po_slider_bg', ''); // default background

    $background_url = $basic_bg; // fallback

    // If compo_id is set, get the compo time and backscreen image
    if ($compo_id !== 'nodataERR') {
        $compos_table = $wpdb->prefix . '_Feryx_compos'; error_log('compo_id: ' . $compo_id);
        $compo_data = $wpdb->get_row($wpdb->prepare("SELECT name, start_time, backscreen FROM $compos_table WHERE id = %d", intval($compo_id)));
        if ($compo_data) {
            $compo_time = $compo_data->start_time;
            // If mode is 3 or 4 and there is a backscreen
            if (in_array($mode, [3,4]) && !empty($compo_data->backscreen)) {
                $attachment_url = wp_get_attachment_url($compo_data->backscreen);
                if ($attachment_url) $background_url = $attachment_url;
            }
        }
    }
    if ($mode == 1){$event_name=$compo_data->name; $compo_time ->start_time;}
    // Get slider images from admin settings
    $slider_images = json_decode(get_option('po_slider_images', '[]'), true);
    if (!is_array($slider_images)) $slider_images = [];

    $response = [
        'mode'              => $mode,
        'announcement_text' => $announcement_text,
        'event_name'        => $event_name,
        'event_time'        => $event_time,
        'compo_id'          => $compo_id,
        'prize_id'          => $prize_id,
		'party_network'   => $party_network,
		'party_wifi_ssid' => $party_wifi_ssid,
		'party_wifi_code' => $party_wifi_code,
        'compo_time'        => $compo_time,
        'enable_screenshots'=> $enable_screenshots,
        'galleryImages'     => $slider_images, // gallery images go here
        'background'        => $background_url, // background image goes here
        'data'              => []
    ];

    if ($compo_id !== 'nodataERR') {
        $prods_table = $wpdb->prefix . '_Feryx_prods';
        $votes_table = $wpdb->prefix . '_Feryx_votes';

        // --- Mode 3: Compo display ---
        if ($mode == 3) {
            $sql = $wpdb->prepare("
                SELECT
                    id,
                    screenshot,
                    product_title,
                    author,
                    comment_public,
                    orderid
                FROM $prods_table
                WHERE compo_id = %d AND status = 1
                ORDER BY orderid ASC
            ", intval($compo_id));

            $entries = $wpdb->get_results($sql, ARRAY_A);
            $processed_entries = [];
            $upload_dir = wp_upload_dir();
            $base_url = $upload_dir['baseurl'] . '/prods_screens/';

            foreach ($entries as $entry) {
                $entry['screenshot'] = $base_url . $entry['screenshot'];
                $processed_entries[] = $entry;
            }

            $response['data'] = $processed_entries;
        }

        // --- Mode 4: Prizegiving ---
        if ($mode == 4) {
            $sql = $wpdb->prepare("
                SELECT
                    p.id,
                    p.product_title,
                    p.author,
                    SUM(v.vote) as total_points
                FROM $prods_table p
                INNER JOIN $votes_table v ON p.id = v.entryid
                WHERE p.compo_id = %d
                GROUP BY p.id, p.product_title, p.author
                ORDER BY total_points DESC
            ", intval($compo_id));

            $ranking = $wpdb->get_results($sql, ARRAY_A);
            $response['data'] = $ranking;
        }

        // --- Timeline ---
        $timeline_table = $wpdb->prefix . '_Feryx_timeline';
        $timeline_items = $wpdb->get_results(
            "SELECT * FROM $timeline_table ORDER BY list_id ASC, time ASC",
            ARRAY_A
        );

        $timeline_formatted = [];
        foreach ($timeline_items as $item) {
            $time_formatted = date('D H:i', strtotime($item['time'])); // e.g., "Fri 15:00"
            $timeline_formatted[] = [
                'eventname' => $item['eventname'],
                'time'      => $time_formatted,
                'list_id'   => $item['list_id']
            ];
        }

        $response['timeline'] = $timeline_formatted;
    }

    wp_send_json($response);
});

// Add button to Admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'party-organizer',       // Main menu slug
        'Party Slide Viewer',    // Page title
        'Party Slide Viewer',    // Menu title
        'manage_options',        // Capability
        'party-slide-viewer',    // Submenu slug
        function() {             // Callback function
            ?>
            <div class="wrap">
                <h1>Party Slide Viewer</h1>
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
                <p>
                    <a class="button button-primary" target="_blank"
                       href="<?php echo plugins_url('slideviewer/slideviewer.php', __FILE__); ?>">
                        Open slideviewer
                    </a>
                </p>
            </div>
            <?php
        }
    );
});

add_action('init', function() {
    // If WooCommerce is not present, we automatically create a menu if none exists
    if ( ! has_nav_menu('primary') ) {
        register_nav_menu('primary', __('Primary Menu', 'party-organizer'));
    }
});

add_action('wp_footer', function() {
    // Check if the primary menu exists
    if ( has_nav_menu('primary') ) {
        $menu_items = wp_get_nav_menu_items('primary');
        // If there's no Login/Register link yet
        $exists = false;
        if ($menu_items) {
            foreach ($menu_items as $item) {
                if ($item->title === 'Login / Register') $exists = true;
            }
        }
        if (!$exists) {
            echo '<style>
            .login-register-btn {
                position: fixed; top: 10px; right: 10px; z-index: 9999;
            }
            </style>';
            echo '<a class="button login-register-btn" href="' . esc_url( wp_registration_url() ) . '">Login / Register</a>';
        }
    } else {
        // If no menu exists, we still display the button
        echo '<style>
        .login-register-btn {
            position: fixed; top: 10px; right: 10px; z-index: 9999;
        }
        </style>';
        echo '<a class="button login-register-btn" href="' . esc_url( wp_registration_url() ) . '">Login / Register</a>';
    }
});

// -----------------------
// VOTEKEY ON PROFILE PAGE
// -----------------------
add_action('show_user_profile', 'po_votekey_profile_field');
add_action('edit_user_profile', 'po_votekey_profile_field');
function po_votekey_profile_field($user) {
    ?>
    <p>💕POF - PartyOrganizer By Feryx💕</p>
    <?php
}

add_action('personal_options_update', 'po_save_votekey_profile');
add_action('edit_user_profile_update', 'po_save_votekey_profile');
function po_save_votekey_profile($user_id) {
    if (!current_user_can('edit_user', $user_id)) return false;

    if (isset($_POST['votekey'])) {
        $votekey = sanitize_text_field($_POST['votekey']);
        update_user_meta($user_id, 'votekey', $votekey);

        // For example, if there's a votekey and no visitor role, we update it
        if ($votekey) {
            $user = new WP_User($user_id);
            if (!in_array('visitor', $user->roles)) {
                $user->set_role('visitor');
            }
        }
    }
}

register_activation_hook(__FILE__, function() {
    // Flush rewrite rules on activation
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Add JS to the frontend
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'po-menu-roles',
        plugin_dir_url(__FILE__) . 'inc/menu-roles.js',
        ['jquery'],
        '1.0',
        true
    );

    // Pass user role to JS
    $current_user = wp_get_current_user();
    $role = (!empty($current_user->roles)) ? $current_user->roles[0] : 'guest';
    wp_localize_script('po-menu-roles', 'poUserRole', ['role' => $role]);
});



// Csak admin felületen fusson
if ( is_admin() ) {

    // Hozzáadjuk az admin menüpontot
    add_action('admin_menu', function() {
        add_menu_page(
            'Demo Data Manager',
            'POF Lorem Ipsum',
            'manage_options',
            'demo-data',
            'render_demo_data_page'
        );
    });

    // Oldal tartalma
    function render_demo_data_page() {
        ?>
        <div class="wrap">
            <h1>Demo Data Manager</h1>
            <p>Fill the database with test data or delete it. be careful, deleting will delete everything from the database (competition_db)!</p>

            <form method="post">
                <?php wp_nonce_field('demo_data_action','demo_data_nonce'); ?>
                <button type="submit" name="demo_data_action" value="insert" class="button button-primary">
                    Insert Demo Data
                </button>
                <button type="submit" name="demo_data_action" value="clear" class="button button-secondary">
                    Clear Demo Data
                </button>
            </form>
        </div>
        <?php
    }

    // Űrlap feldolgozása
    add_action('admin_init', function() {
        global $wpdb;

        if (isset($_POST['demo_data_action']) && check_admin_referer('demo_data_action','demo_data_nonce')) {
            $table = $wpdb->prefix . '_Feryx_compos';

            if ($_POST['demo_data_action'] === 'insert') {
                // előbb töröljük a régi adatokat hogy ne duplázódjon
                $wpdb->query("TRUNCATE TABLE $table");

                $wpdb->query("INSERT INTO $table (id, name, backscreen, start_time, online, live, upload, editing) VALUES
                (1, 'Wild/Animation', '0', '2025-08-25 15:30:00', 0, 0, 1, 1),
				(2, 'Demo', '0', '2025-09-07 22:38:00', 0, 0, 1, 1),
				(3, 'Music', '0', '2025-08-30 22:38:00', 0, 0, 1, 1),
				(4, 'Photo', '0', '2026-08-30 22:38:00', 0, 0, 1, 1),
				(5, 'Graphics', '0', '2026-08-30 22:38:00', 0, 0, 1, 1),
				(6, '256 byte Intro', '0', '2026-08-31 22:38:00', 0, 0, 1, 1),
				(7, 'Oldschool Demo', '0', '2026-08-27 22:38:00', 0, 0, 1, 1)
                ");
                add_action('admin_notices', function(){
                    echo '<div class="notice notice-success"><p>Demo data inserted successfully.</p></div>';
                });
            }

            if ($_POST['demo_data_action'] === 'clear') {
                $wpdb->query("TRUNCATE TABLE $table");
                add_action('admin_notices', function(){
                    echo '<div class="notice notice-warning"><p>Demo data cleared.</p></div>';
                });
            }
        }
    });
}
add_action('admin_footer', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'party-organizer') {
        ?>
        <div style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            padding: 15px;
            border: 2px dashed #ccc;
            border-radius: 10px;
            background: #f9f9f9;
            font-size: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            z-index: 9999;
            text-align: left;
        ">
            <p style="margin: 0; font-weight: bold;">⚠️ Disclaimer</p>
            <p style="margin: 5px 0 0 0;">
                I take absolutely no responsibility for the use of this plugin <br>– you’re using it entirely at your own risk.<br>
                If something breaks, crashes, or spontaneously combusts,<br> that’s not on me.<br>
                But hey, feel free to ask questions anyway 😉<br>
				feryx@feryx.hu
            </p>
            <p style="margin-top: 10px; font-style: italic;">Have a great party,<br><strong>Feryx</strong></p>
        </div>
        <?php
    }
});
