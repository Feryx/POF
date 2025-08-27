<?php
/*-----------------------------------
  POST processing (checkbox)
-----------------------------------*/
add_action('admin_init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'feryx_results_settings')) {
            $value = isset($_POST['feryx_results_public']) ? intval($_POST['feryx_results_public']) : 0;
            update_option('feryx_results_public', $value);
            wp_redirect(admin_url('admin.php?page=po-results'));
            exit;
        }
    }
});

/*-----------------------------------
  RETRIEVING RESULTS
-----------------------------------*/
function feryx_get_results() {
    global $wpdb;

    $compos = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}_Feryx_compos ORDER BY name ASC");
    $results = [];

    foreach ($compos as $compo) {
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                p.id as entryid,
                p.compo_id,
                p.product_title,
                p.author,
                p.orderid,
                SUM(v.vote) as points
            FROM {$wpdb->prefix}_Feryx_prods p
            LEFT JOIN {$wpdb->prefix}_Feryx_votes v ON v.entryid = p.id
            WHERE p.compo_id = %d
            GROUP BY p.id
            ORDER BY points DESC
        ", $compo->id));

        $results[$compo->id] = $rows;
    }

    return $results;
}

/*-----------------------------------
  TXT GENERATION
-----------------------------------*/
function feryx_generate_results_txt() {
    global $wpdb;
    $results = feryx_get_results();

    // HEADER
    $header_file = plugin_dir_path(__FILE__) . '../header_results.txt';
    $header_content = '';
    if (file_exists($header_file)) {
        $header_content = file_get_contents($header_file);
    } else {
        $header_content = '### HEADER FILE NOT FOUND ###';
    }

    $output = $header_content . "\n\n"; // header + 2 empty lines

    foreach ($results as $compo_id => $rows) {
        // Get the compo name
        $compo_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}_Feryx_compos WHERE id = %d", $compo_id));
        $output .= $compo_name . "\n";
        $place = 1; // start from 1 for each compo
        foreach ($rows as $row) {
            $points = $row->points ?: 0;
            $output .= $place . ".\t" . $points . " pts\t#" . $row->orderid . "\t" .
                         $row->product_title . " - " . $row->author . "\n";
            $place++;
        }
        $output .= "\n";
    }

    // FOOTER
    $total_votes = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}_Feryx_votes");
    $unique_voters = $wpdb->get_var("SELECT COUNT(DISTINCT userid) FROM {$wpdb->prefix}_Feryx_votes");

    $output .= "===============================================================================\n\n";
    $output .= "         " . intval($total_votes) . " votes were cast by " . intval($unique_voters) . " registered voters.\n\n";
    $output .= "         Made possible by Feryx Party Organizer - https://partyorganizer.qbparty.hu\n";

    return $output;
}




/*-----------------------------------
  HTML TABLE GENERATION
-----------------------------------*/
function feryx_generate_results_html() {
    global $wpdb;
    ob_start();

    // Get compos with names
    $compos = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}_Feryx_compos ORDER BY `name` ASC");

    echo "<div class='feryx-results'>";
    foreach ($compos as $compo) {
        // Query productions for the given compo
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                p.id as entryid,
                p.compo_id,
                p.product_title,
                p.author,
                p.orderid,
                SUM(v.vote) as points
            FROM {$wpdb->prefix}_Feryx_prods p
            LEFT JOIN {$wpdb->prefix}_Feryx_votes v ON v.entryid = p.id
            WHERE p.compo_id = %d
            GROUP BY p.id
            ORDER BY points DESC
        ", $compo->id));

        echo "<h2>" . esc_html($compo->name) . "</h2>"; // now the name is displayed
        echo "<div style='overflow-x:auto;'>";
        echo "<table class='widefat striped'>";
        echo "<thead><tr>
                 <th>Place</th>
                 <th>Points</th>
                 <th>OrderID</th>
                 <th>Product</th>
                 <th>Author</th>
               </tr></thead><tbody>";
        $place = 1;
        foreach ($rows as $row) {
            $points = $row->points ?: 0;
            echo "<tr>";
            echo "<td>" . $place . ".</td>";
            echo "<td>" . intval($points) . " pts</td>";
            echo "<td>#" . intval($row->orderid) . "</td>";
            echo "<td>" . esc_html($row->product_title) . "</td>";
            echo "<td>" . esc_html($row->author) . "</td>";
            echo "</tr>";
            $place++;
        }
        echo "</tbody></table>";
        echo "</div>";
    }
    echo "</div>";

    return ob_get_clean();
}

/*-----------------------------------
  ADMIN PAGE
-----------------------------------*/
add_action('admin_menu', function() {
    add_submenu_page(
        'party-organizer',
        'Results',
        'Results',
        'manage_options',
        'po-results',
        'feryx_results_page'
    );
});

function feryx_results_page() {
    $enabled = get_option('feryx_results_public', false);
    ?>
    <div class='wrap'>
        <h1>Results</h1>
<p>These functions help you avoid tie situations, with questions like which product do you like better?</p>
        <button id="feryx-equality-check" class="button button-primary">Equality Check</button>
<p>Enabling public results view will disable the Votes menus and allow visitors to see the results.</p>
        <form method="post" action="">
            <?php wp_nonce_field('feryx_results_settings'); ?>
            <input type="hidden" name="feryx_results_public" value="0">
            <label>
                <input type="checkbox" name="feryx_results_public" value="1" <?php checked($enabled, true); ?>> Enable public results view
            </label>
            <p class="submit"><button type="submit" class="button button-primary">Save</button></p>
        </form>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="feryx_download_results">
            <button type="submit" class="button button-primary">Download results.txt</button>
        </form>

        <p><a href="<?php echo esc_url(home_url('/results/')); ?>" target="_blank" class="button">View online</a></p>

        <h2>Table view (Admin)</h2>
        <?php echo feryx_generate_results_html(); ?>
    </div>
    <?php
}

/*-----------------------------------
  DOWNLOAD admin_post hook
-----------------------------------*/
add_action('admin_post_feryx_download_results', function() {
    $txt = feryx_generate_results_txt();
    header("Content-Type: text/plain; charset=utf-8");
    header("Content-Disposition: attachment; filename=results.txt");
    echo $txt;
    exit;
});

/*-----------------------------------
  PUBLIC /results/ page
-----------------------------------*/
add_action('init', function() {
    add_rewrite_rule('^results/?$', 'index.php?feryx_results=1', 'top');
    add_rewrite_tag('%feryx_results%', '1');
});

add_action('template_redirect', function() {
    if (get_query_var('feryx_results') == 1) {
        $enabled = get_option('feryx_results_public', false);

        if (!$enabled && !current_user_can('manage_options')) {
            status_header(403);
            wp_die('Results are not public.');
        }

        if (isset($_GET['format']) && $_GET['format'] === 'txt') {
            header("Content-Type: text/plain; charset=utf-8");
            echo feryx_generate_results_txt();
        } else {
            echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Results</title>";
            echo "<style>
                body{font-family:sans-serif;max-width:900px;margin:20px auto;}
                table{border-collapse:collapse;width:100%;margin-bottom:20px;}
                th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;}
                th{background:#eee;}
                .results-footer{margin-top:30px;padding-top:10px;border-top:1px solid #ccc;font-size:0.9em;color:#555;}
            </style></head><body>";
            echo "<h1>Results</h1>";
            echo feryx_generate_results_html();
            echo "<p><a href='" . esc_url(home_url('/results/?format=txt')) . "' target='_blank'>Download TXT version</a></p>";

            // FOOTER HTML
            global $wpdb;
            $total_votes = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}_Feryx_votes");
            $unique_voters = $wpdb->get_var("SELECT COUNT(DISTINCT userid) FROM {$wpdb->prefix}_Feryx_votes");

            echo "<div class='results-footer'>";

            echo "<p>" . intval($total_votes) . " votes were cast by " . intval($unique_voters) . " registered voters.</p>";
            echo "<p>Made possible by Feryx Party Organizer - <a href='https://partyorganizer.qbparty.hu' target='_blank'>https://partyorganizer.qbparty.hu</a></p>";
            echo "</div>";

            echo "</body></html>";
        }
        exit;
    }
});


/*-----------------------------------
  MENU FILTERING
-----------------------------------*/
add_filter('wp_nav_menu_objects', function($items, $args) {
    $enabled = get_option('feryx_results_public', false);

    $page_results = get_page_by_path('results');
    $page_vote    = get_page_by_path('vote');
    $page_live    = get_page_by_path('live-vote');

    $results_id = $page_results ? $page_results->ID : 0;
    $vote_id    = $page_vote ? $page_vote->ID : 0;
    $live_id    = $page_live ? $page_live->ID : 0;

    foreach ($items as $key => $item) {
        if ($enabled && ($item->object_id == $vote_id || $item->object_id == $live_id)) {
            unset($items[$key]);
        }
        if (!$enabled && $item->object_id == $results_id) {
            unset($items[$key]);
        }
    }

    return array_values($items);
}, 10, 2);

/*-----------------------------------
  MENU JS
-----------------------------------*/
add_action('wp_footer', function() {
    $enabled = get_option('feryx_results_public', false) ? 1 : 0;
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var enabled = <?php echo intval($enabled); ?>;
        var vote    = document.querySelector('a[href*="/vote"]')?.closest('li');
        var live    = document.querySelector('a[href*="/live-vote"]')?.closest('li');
        var results = document.querySelector('a[href*="/results"]')?.closest('li');

        if (enabled) {
            if (vote)    vote.style.display = "none";
            if (live)    live.style.display = "none";
            if (results) results.style.display = "list-item";
        } else {
            if (results) results.style.display = "none";
            if (vote)    vote.style.display = "list-item";
            if (live)    live.style.display = "list-item";
        }
    });
    </script>
    <?php
});

/*-----------------------------------
  AJAX: Equality Check
-----------------------------------*/
add_action('wp_ajax_feryx_equality_check', function() {
    $results = feryx_get_results();
    $ties = [];

    foreach ($results as $compo_id => $rows) {
        $top3 = array_slice($rows, 0, 5);
        for ($i=0; $i<count($top3)-1; $i++) {
            if ($top3[$i]->points == $top3[$i+1]->points) {
                $ties[] = [
                    'compo_id' => $compo_id,
                    'entry1_id' => $top3[$i]->entryid,
                    'entry1' => $top3[$i]->product_title,
                    'entry2_id' => $top3[$i+1]->entryid,
                    'entry2' => $top3[$i+1]->product_title
                ];
            }
        }
    }

    wp_send_json_success($ties);
});

/*-----------------------------------
  AJAX: Resolve Tie
-----------------------------------*/
add_action('wp_ajax_feryx_resolve_tie', function() {
    global $wpdb;
    $votes_table = $wpdb->prefix . '_Feryx_votes';

    $winner = intval($_POST['winner']);
    $compo = intval($_POST['compo']);
    $userid = get_current_user_id();

    $wpdb->insert($votes_table, [
        'compoid' => $compo,
        'userid' => $userid ?: 0,
        'entryid' => $winner,
        'vote' => 1
    ]);

    wp_send_json_success(['message' => 'Tie resolved, vote recorded ✅']);
});

/*-----------------------------------
  SCRIPT LOADING
-----------------------------------*/
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'party-organizer_page_po-results') return;
    wp_enqueue_script('feryx-results-js', plugin_dir_url(__FILE__).'results.js', ['jquery'], false, true);
    wp_localize_script('feryx-results-js', 'feryx_ajax', ['ajaxurl' => admin_url('admin-ajax.php')]);
});