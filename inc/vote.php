<?php
function po_livevote_page() {
    global $wpdb;

    // Retrieve all competitions
    $competitions = $wpdb->get_results("SELECT * FROM wp__Feryx_compos ORDER BY id ASC");

    // Selected competition ID (default: first one)
    $selected_competition = isset($_POST['competition_id']) ? intval($_POST['competition_id']) : ($competitions[0]->id ?? 0);

    ?>
    <div class="livevote-container">

        <div class="livevote-header">
            <select id="competition_id">
                <?php foreach ($competitions as $comp): ?>
                    <option value="<?= $comp->id ?>" <?= ($comp->id == $selected_competition) ? 'selected' : '' ?>>
                        <?= esc_html($comp->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
<label>
    <input type="checkbox" id="livevote-enable" <?= ($selected_competition && $wpdb->get_var("SELECT live FROM wp__Feryx_compos WHERE id=$selected_competition") ? 'checked' : '') ?> />
    Enable Live Vote
</label>
<p>Shortcode: [feryx_live_vote]</p>
<script>
jQuery(document).ready(function($){
    $('#livevote-enable').on('change', function(){
        var comp_id = <?= $selected_competition ?>;
        var enable = $(this).is(':checked') ? 1 : 0;

        $.post(ajaxurl, {
            action: 'toggle_livevote_comp',
            comp_id: comp_id,
            enable: enable
        }, function(resp){
            if(resp.success){
                console.log('Live vote updated:', resp.data.live);
            } else {
                alert('An error occurred while saving.');
            }
        });
    });
});
</script>


        </div>

        <div id="livevote-prod-list"></div>

    </div>

    <style>
    .livevote-container { max-width: 600px; margin: 20px; font-family: Arial; }
    .livevote-header { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; }
    #livevote-prod-list ul { list-style: none; padding: 0; }
    #livevote-prod-list li { display: flex; align-items: center; gap: 10px; padding: 5px 0; border-bottom: 1px solid #ddd; }
    .status-dot { width: 15px; height: 15px; border-radius: 50%; display: inline-block; margin-left: 5px; }
    .status-0 { background: gray; }
    .status-1 { background: green; }
    .status-2 { background: orange; }
    .status-3 { background: red; }
    .active { border: 2px solid black; }
    </style>
<button id="enable-vote-btn">Enable Vote</button>

<script>
jQuery(document).ready(function($){
    $('#enable-vote-btn').on('click', function(){
        var comp_id = $('#competition_id').val();
        $.post(ajaxurl, {
            action: 'enable_vote',
            comp_id: comp_id
        }, function(resp){
            if(resp.success){
                alert('Vote enabled for this competition!');
                // Refresh list
                loadLivevoteList(comp_id);
            } else {
                alert('An error occurred!');
            }
        });
    });
});
</script>

    <script>
    jQuery(document).ready(function($){
        function loadLivevoteList(comp_id){
            $.post(ajaxurl, { action: 'get_livevote_list', comp_id: comp_id }, function(resp){
                $('#livevote-prod-list').html(resp);
            });
        }

        // Load on page load
        loadLivevoteList($('#competition_id').val());

        // On competition change
        $('#competition_id').on('change', function(){
            loadLivevoteList($(this).val());
        });

        // Live vote enable/disable
        $('#livevote-enable').on('change', function(){
            var comp_id = $('#competition_id').val();
            var enable = $(this).is(':checked') ? 1 : 0;
            $.post(ajaxurl, { action: 'toggle_livevote', comp_id: comp_id, enable: enable }, function(resp){
                if(resp.success) loadLivevoteList(comp_id);
            });
        });

        // Product checkbox change
        $(document).on('change', '.livevote-prod', function(){
            var prod_id = $(this).data('prod-id');
            var enable = $(this).is(':checked') ? 1 : 0;
            $.post(ajaxurl, { action: 'toggle_livevote_prod', prod_id: prod_id, enable: enable }, function(resp){
                if(resp.success){
                    var comp_id = $('#competition_id').val();
                    loadLivevoteList(comp_id);
                }
            });
        });
    });
    </script>
    <?php
}
// AJAX enable simple vote
add_action('wp_ajax_enable_vote', function(){
    global $wpdb;
    $comp_id = intval($_POST['comp_id']);

    // Set all competitions online = 0
    $wpdb->query("UPDATE wp__Feryx_compos SET live = 0");

    // Set the current competition online = 1
    $wpdb->update(
        'wp__Feryx_compos',
        ['online' => 1],
        ['id' => $comp_id],
        ['%d'],
        ['%d']
    );

    wp_send_json_success();
});


// Live vote enable/disable
add_action('wp_ajax_toggle_livevote', function(){
    global $wpdb;
    $comp_id = intval($_POST['comp_id']);
    $enable = intval($_POST['enable']);

    // Set all livevoteflag to 0
    $wpdb->query("UPDATE wp__Feryx_compos SET livevoteflag=0");
    if($enable) $wpdb->update('wp__Feryx_compos', ['livevoteflag'=>1], ['id'=>$comp_id]);

    wp_send_json_success();
});
// AJAX handler for live vote toggle
add_action('wp_ajax_toggle_livevote_comp', function() {
    global $wpdb;

    $comp_id = intval($_POST['comp_id']);
    $enable  = intval($_POST['enable']); // 1 or 0

    // If enabling, set all others to 0
    if ($enable) {
        $wpdb->query("UPDATE wp__Feryx_compos SET live = 0");
    }

    // Set the current competition's live value
    $wpdb->update(
        'wp__Feryx_compos',
        ['live' => $enable],
        ['id' => $comp_id],
        ['%d'],
        ['%d']
    );

    wp_send_json_success(['live' => $enable]);
});

// Get product list
function po_get_livevote_products($comp_id){
    global $wpdb;
$products = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM wp__Feryx_prods
     WHERE compo_id=%d AND status=1
     ORDER BY orderid ASC",
    $comp_id
));


    if(!$products) return '<p>No products found.</p>';

    $html = '<ul>';
    $i = 1;
    foreach($products as $prod){
        $checked = $prod->livevoteflag ? 'checked' : '';
        $html .= '<li>#'. $i .' <input type="checkbox" class="livevote-prod" data-prod-id="'. $prod->id .'" '. $checked .' /> '. esc_html($prod->product_title) .'</li>';
        $i++;
    }
    $html .= '</ul>';
    return $html;
}

add_action('wp_ajax_get_livevote_list', function(){
    $comp_id = intval($_POST['comp_id']);
    echo po_get_livevote_products($comp_id);
    wp_die();
});

// Product livevoteflag toggle
add_action('wp_ajax_toggle_livevote_prod', function(){
    global $wpdb;
    $prod_id = intval($_POST['prod_id']);
    $enable = intval($_POST['enable']);
    $wpdb->update('wp__Feryx_prods', ['livevoteflag'=>$enable], ['id'=>$prod_id]);
    wp_send_json_success();
});