<?php
// Admin Page
function po_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . '_Feryx_compos';


    $compos = $wpdb->get_results("SELECT * FROM $table_name ORDER BY start_time ASC");
    ?>
    <div class="wrap">
        <h1>Party Organizer</h1><h2>Edit Competitions</h2>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="text-align:left">ID</th>
                    <th style="text-align:left">Name</th>
                    <th style="text-align:center">BeamerBackgr.</th>
                    <th style="text-align:left">Timer</th>
                    <th style="text-align:center">Vote</th>
                    <th style="text-align:center">LiveVote</th>
                    <th style="text-align:center">Upload</th>
                    <th style="text-align:center">Editing</th>
                    <th style="text-align:left">Entry status</th>
                    <th style="text-align:left">Pcs</th>
                    <th style="text-align:left">Edit</th>
                </tr>
            </thead>
            <tbody id="po-compo-list">
    <?php foreach ($compos as $c): ?>
        <?php
    // Get the number of products for the given compo
    $prod_count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}_Feryx_prods WHERE compo_id = %d",
            $c->id
        )
    );
    ?>
        <tr data-id="<?php echo $c->id; ?>">
            <td><?php echo $c->id; ?></td>
            <td class="po-name"><?php echo esc_html($c->name); ?></td>

            <td class="po-fanpic" style="text-align:center; cursor:pointer;">
                <?php if ($c->backscreen): ?>
                    <?php echo wp_get_attachment_image($c->backscreen, [70,70]); ?>
                <?php else: ?>
                    <img src="<?php echo esc_url(wp_get_attachment_image_url(0, [70,70])); ?>"
                         alt="" style="width:70px; height:auto; opacity:0.5;">
                <?php endif; ?>
            </td>

            <td class="po-start"><?php echo esc_html($c->start_time); ?></td>

            <?php foreach (['online','live','upload','editing'] as $field): ?>
                <td class="po-toggle" data-field="<?php echo $field; ?>" style="text-align:center;cursor:pointer;">
                    <?php echo $c->$field ? '✅' : '❌'; ?>
                </td>
            <?php endforeach; ?>
<?php
$prod_statuses = $wpdb->get_col($wpdb->prepare("
    SELECT DISTINCT status
    FROM {$wpdb->prefix}_Feryx_prods
    WHERE compo_id = %d
", $c->id));
?>
<td style="text-align:left">
    <?php
    $colors = [
        0 => '#00bfff', // light blue
        1 => '#00ff00', // green
        2 => '#ffc107', // yellow
        3 => '#ff0000', // red
    ];
    for ($i = 0; $i < 4; $i++) {
        $active = in_array($i, $prod_statuses);
        $color = $active ? $colors[$i] : 'rgba(1,1,1,0.1)'; // gray if not active
        $pulse_class = ($i === 0 && $active) ? ' feryx-pulse' : '';
$icons = [
    0 => ['active' => '🤪', 'inactive' => '🎶'],
    1 => ['active' => '🥰', 'inactive' => ''],
    2 => ['active' => '😑', 'inactive' => ''],
    3 => ['active' => '🤬', 'inactive' => '']
];

$clean_class = isset($icons[$i])
    ? ($active ? $icons[$i]['active'] : $icons[$i]['inactive'])
    : '';
        $opacity = $active ? '1' : '0.2';
    echo '<span class="feryx-status-box' . $pulse_class . '" style="
        background-color:' . $color . ';
        opacity:' . $opacity . ';
    ">'.$clean_class.'</span>';
    }
    ?>
</td>

            <td><?php echo intval($prod_count); ?></td>
            <td><button class="button po-edit">Edit</button></td>
        </tr>
    <?php endforeach; ?>
</tbody>

        </table>

        <br>
        <button id="po-toggle-add" class="button">+ Add competition</button>
<?php
$party_start = get_option('po_party_start', '');
?>
        <div id="po-add-form" style="display:none;margin-top:10px;">
            <input type="text" id="po-new-name" placeholder="Name">
            <input type="datetime-local" id="po-new-start" value="<?php echo esc_attr($party_start); ?>">
            <button id="po-add" class="button button-primary">Add</button>
        </div>
    </div>
    <?php
}

// AJAX - new compo
add_action('wp_ajax_po_add_compo', function () {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_compos';
    $name = sanitize_text_field($_POST['name']);
    $start = sanitize_text_field($_POST['start']);
    $wpdb->insert($table, ['name' => $name, 'start_time' => $start]);
    wp_send_json_success(['id' => $wpdb->insert_id, 'name' => $name, 'start_time' => $start]);
});

// Ajax - picSave
add_action('wp_ajax_po_update_backscreen', function() {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_compos';
    $id = intval($_POST['id']);
    $backscreen = intval($_POST['backscreen']);

    $wpdb->update($table, ['backscreen' => $backscreen], ['id' => $id], ['%d'], ['%d']);
    wp_send_json_success();
});

// AJAX - toggle fields
add_action('wp_ajax_po_toggle_field', function () {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_compos';
    $id = intval($_POST['id']);
    $field = sanitize_key($_POST['field']);

    // Get current value from DB
    $current = intval($wpdb->get_var(
        $wpdb->prepare("SELECT `$field`, `online` FROM `$table` WHERE id = %d", $id)
    ));

    if ($field === 'online') {
        // Toggle the online field
        $newVal = $current ? 0 : 1;
        $wpdb->update($table, [$field => $newVal], ['id' => $id], ['%d'], ['%d']);

        // if online = 1, live is always 0
        if ($newVal === 1) {
            $wpdb->update($table, ['live' => 0], ['id' => $id], ['%d'], ['%d']);
        }
    } elseif ($field === 'live') {
        // if online is enabled, don't allow live toggle
        $online = intval($wpdb->get_var(
            $wpdb->prepare("SELECT `online` FROM `$table` WHERE id = %d", $id)
        ));
        if ($online) {
            $newVal = 0;
        } else {
            $newVal = $current ? 0 : 1;
        }
        $wpdb->update($table, ['live' => $newVal], ['id' => $id], ['%d'], ['%d']);
    } else {
        // toggle other fields
        $newVal = $current ? 0 : 1;
        $wpdb->update($table, [$field => $newVal], ['id' => $id], ['%d'], ['%d']);
    }

    wp_send_json_success(['new' => $newVal]);
});

// AJAX - save edit
add_action('wp_ajax_po_save_edit', function () {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_compos';
    $id = intval($_POST['id']);
    $name = sanitize_text_field($_POST['name']);
    $start = sanitize_text_field($_POST['start']);
    $wpdb->update($table, ['name' => $name, 'start_time' => $start], ['id' => $id]);
    wp_send_json_success();
});

// AJAX - delete
add_action('wp_ajax_po_delete_compo', function () {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_compos';
    $id = intval($_POST['id']);
    if ($wpdb->delete($table, ['id' => $id]) !== false) {
        wp_send_json_success();
    }
    wp_send_json_error('Error: cannot delete it.');
});

// Admin JS/CSS load
add_action('admin_enqueue_scripts', function ($hook) {
    // Debug: check the current hook value
    // error_log($hook);

    if (
        $hook !== 'toplevel_page_party-organizer' &&
        $hook !== 'party-organizer_page_po_admin_page'
    ) return;

    wp_enqueue_media();
    wp_enqueue_script('po-admin', plugin_dir_url(__FILE__) . '../partyorganizer.js', ['jquery'], false, true);
    wp_enqueue_style('po-admin-style', plugin_dir_url(__FILE__) . '../po-admin.css');
});