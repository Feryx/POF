<?php
// Create page: Simple Vote
add_action('init', 'feryx_create_page_vote');
function feryx_create_page_vote() {
    $page = get_page_by_path('vote', OBJECT, 'page');
    if (!$page) {
        wp_insert_post([
            'post_title'   => 'Vote',
            'post_name'    => 'vote',
            'post_content' => '[feryx_vote_normal]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'menu_order'   => 6
        ]);
    }
}

// Shortcode: [feryx_vote_normal]
function feryx_vote_shortcode() {
$access = feryx_check_access('Vote');
if ($access !== true) {
    echo $access;
    return;
}
    global $wpdb;

    // Enabled compos (online=1)
    $compos = $wpdb->get_results("SELECT * FROM wp__Feryx_compos WHERE online=1 ORDER BY wp__Feryx_compos.id ASC");

    ob_start(); ?>
    <div class="feryx-vote">
        <?php if ($compos): ?>
            <?php foreach ($compos as $compo): ?>
                <h2><?= esc_html($compo->name) ?></h2>
                <div id="feryx-vote-list-<?= $compo->id ?>" class="feryx-vote-list" data-compo="<?= $compo->id ?>"></div>
            <?php endforeach; ?>
        <?php else: ?>
            <h2>No active votes yet!</h2>
            <p>Come back soon.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('feryx_vote_normal', 'feryx_vote_shortcode');

// ==== AJAX: PRODS LIST ====
// Same function, only the parent div ID changes
add_action('wp_ajax_feryx_get_prods_vote', 'feryx_get_prods_vote');
add_action('wp_ajax_nopriv_feryx_get_prods_vote', 'feryx_get_prods_vote');

function feryx_get_prods_vote() {
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
        $vote = $wpdb->get_var($wpdb->prepare(
            "SELECT vote FROM wp__Feryx_votes WHERE compoid=%d AND userid=%d AND entryid=%d",
            $compoid, $userid, $prod->id
        ));
        $img_url = content_url('/uploads/prods_screens/' . $prod->screenshot);

        $html .= '<div class="feryx-card">';
        $html .= '  <div class="feryx-img"><img src="'.esc_url($img_url).'" alt="" /></div>';
        $html .= '  <div class="feryx-info">';
        $html .= '      <h3>'.esc_html($prod->product_title).'</h3>';
        $html .= '      <p>'.esc_html($prod->author).'</p>';
        $html .= '      <div class="stars" data-entry="'.$prod->id.'">';
        for ($i=1; $i<=5; $i++) {
            $active = ($vote && $vote >= $i) ? 'active' : '';
            $html .= '<span class="star '.$active.'" data-vote="'.$i.'">&#9733;</span>';
        }
        $html .= '      </div>';
        $html .= '  </div>';
        $html .= '</div>';
    }

    echo $html;
    wp_die();
}

// ==== AJAX: VOTE ====
add_action('wp_ajax_feryx_vote', 'feryx_vote_normal');
add_action('wp_ajax_nopriv_feryx_vote', 'feryx_vote_normal');

function feryx_vote_normal() {
    global $wpdb;
    $compoid = intval($_POST['compoid']);
    $entryid = intval($_POST['entryid']);
    $vote    = intval($_POST['vote']);
    $userid  = get_current_user_id();

    if (!$userid) { wp_die('not_logged_in'); }

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
    wp_enqueue_script('feryx-vote', plugin_dir_url(__FILE__) . 'feryx-vote.js', ['jquery'], '1.0', true);
    wp_localize_script('feryx-vote', 'feryxVote', ['ajaxurl' => admin_url('admin-ajax.php')]);
    wp_enqueue_style('feryx-vote-style', plugin_dir_url(__FILE__) . '../css/feryx-livevote.css');
});