<?php
add_action('init', 'feryx_create_page_live');
function feryx_create_page_live() {
    $page = get_page_by_path('live-vote', OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title'   => 'Live Vote',
            'post_name'    => 'live-vote',
            'post_content' => '[feryx_live_vote]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'menu_order'   => 5
        ]);
    }
}
// Shortcode: [feryx_live_vote]
function feryx_live_vote_shortcode() {
$access = feryx_check_access('LiveVote');
if ($access !== true) {
    echo $access;
    return;
}
    global $wpdb;

    // Active compo (where live=1)
    $compo = $wpdb->get_row("SELECT * FROM wp__Feryx_compos WHERE live=1 ORDER BY id ASC");

    ob_start(); ?>
    <div class="feryx-livevote">
        <?php if ($compo): ?>
            <h2><?= esc_html($compo->name) ?></h2>
            <div id="feryx-livevote-list" data-compo="<?= $compo->id ?>"></div>
        <?php else: ?>
            <h2>No active live vote yet!</h2>
            <p>Come back soon.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('feryx_live_vote', 'feryx_live_vote_shortcode');

// ==== AJAX: PRODS LIST ====
add_action('wp_ajax_feryx_get_prods', 'feryx_get_prods');
add_action('wp_ajax_nopriv_feryx_get_prods', 'feryx_get_prods');

function feryx_get_prods() {
    global $wpdb;
    $compoid = intval($_POST['compoid']);
    $userid  = get_current_user_id();

$prods = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM wp__Feryx_prods
     WHERE compo_id=%d AND livevoteflag=1 AND status=1
     ORDER BY id ASC",
    $compoid
));


    $html = '';
    foreach ($prods as $prod) {
        // Check if the user has already voted
        $vote = $wpdb->get_var($wpdb->prepare(
            "SELECT vote FROM wp__Feryx_votes WHERE compoid=%d AND userid=%d AND entryid=%d",
            $compoid, $userid, $prod->id
        ));
$img_url = content_url('/uploads/prods_screens/' . $prod->screenshot);

$html .= '<div class="feryx-card">';
$html .= '  <div class="feryx-img"><img src="'.esc_url($img_url).'" alt="" /></div>';
$html .= '  <div class="feryx-info">';
$html .= '    <h3>'.esc_html($prod->product_title).'</h3>';
$html .= '    <p>'.esc_html($prod->author).'</p>';
$html .= '    <div class="stars" data-entry="'.$prod->id.'">';
for ($i=1; $i<=5; $i++) {
    $active = ($vote && $vote >= $i) ? 'active' : '';
    $html .= '<span class="star '.$active.'" data-vote="'.$i.'">&#9733;</span>';
}
$html .= '    </div>';
$html .= '  </div>';
$html .= '</div>';
    }

    echo $html;
    wp_die();
}

// ==== AJAX: VOTE ====
add_action('wp_ajax_feryx_vote', 'feryx_vote');
add_action('wp_ajax_nopriv_feryx_vote', 'feryx_vote');

function feryx_vote() {
    global $wpdb;
    $compoid = intval($_POST['compoid']);
    $entryid = intval($_POST['entryid']);
    $vote    = intval($_POST['vote']);
    $userid  = get_current_user_id();

    if (!$userid) { wp_die('not_logged_in'); }

    // Is there already a vote?
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM wp__Feryx_votes WHERE compoid=%d AND userid=%d AND entryid=%d",
        $compoid, $userid, $entryid
    ));

    if ($exists) {
        $wpdb->update("wp__Feryx_votes", [
            'vote' => $vote,
            'votetime' => current_time('mysql')
        ], ['id' => $exists]);
    } else {
        $wpdb->insert("wp__Feryx_votes", [
            'compoid'  => $compoid,
            'userid'   => $userid,
            'entryid'  => $entryid,
            'vote'     => $vote,
            'votetime' => current_time('mysql')
        ]);
    }

    wp_die('ok');
}

// ==== FRONTEND SCRIPT ====
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('feryx-livevote', plugin_dir_url(__FILE__) . 'feryx-livevote.js', ['jquery'], '1.0', true);
    wp_localize_script('feryx-livevote', 'feryxVote', ['ajaxurl' => admin_url('admin-ajax.php')]);
    wp_enqueue_style('feryx-livevote-style', plugin_dir_url(__FILE__) . '../css/feryx-livevote.css');
});