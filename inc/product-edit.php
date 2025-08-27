<?php
// On activation, create the Edit page
function feryx_create_pages_edit() {
    if (!get_page_by_path('product-edit')) {
        wp_insert_post([
            'post_title'   => 'Edit Products',
            'post_name'    => 'product-edit',
            'post_content' => '[feryx_edit_page]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'menu_order'   => 3
        ]);
    }
}

// Shortcode for the editing page
add_shortcode('feryx_edit_page', function() {
    if (!is_page('product-edit')) return '';
$access = feryx_check_access('Edit');
if ($access !== true) {
    echo $access;
    return;
}
    global $wpdb;
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '<p>PLS Login!</p>';
    }

    $prod_table  = $wpdb->prefix . '_Feryx_prods';
    $compo_table = $wpdb->prefix . '_Feryx_compos';

    // Edit mode
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        $prod = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $prod_table WHERE id=%d AND user_id_uploader=%d",
            $edit_id, $user_id
        ));
        if (!$prod) {
            return '<p>No prods.</p>';
        }
        return feryx_edit_form_html($prod);
    }

    // List user's own productions
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, c.name as compo_name
         FROM $prod_table p
         LEFT JOIN $compo_table c ON p.compo_id = c.id
         WHERE p.user_id_uploader=%d",
         $user_id
    ));
$status_map = [
    0 => ['label' => 'New', 'color' => '#007bff', 'icon' => '🔵'],
    1 => ['label' => 'Qualified', 'color' => '#28a745', 'icon' => '✅'],
    2 => ['label' => 'Not qualified', 'color' => '#ffc107', 'icon' => '❌'],
    3 => ['label' => 'Disqualified', 'color' => 'rgba(255,50,50,0.6)', 'icon' => '❌'],
];
    ob_start();
    if (isset($_GET['edited']) && $_GET['edited'] == '1') {
        echo '<div class="feryx-success-msg"><span class="feryx-checkmark">&#10004;</span> Success!</div>';
    }

    echo '<div class="feryx-card-grid">';
foreach ($rows as $row) {
    $st = isset($status_map[$row->status]) ? $status_map[$row->status] : $status_map[0];

        $screenshot_url = content_url('uploads/prods_screens/' . $row->screenshot);
        echo '<div class="feryx-card"><div class="feryx-card-imgdiv">';
        echo '<img src="' . esc_url($screenshot_url) . '" alt="' . esc_attr($row->product_title) . '" /></div>';
        echo '<h3>' . esc_html($row->product_title) . '</h3>';
        echo '<p>Compo: ' . esc_html($row->compo_name) . '</p>';
        echo '<div class="feryx-entry-status">
        <strong>Entry status:</strong>
        <span class="feryx_status_label" style="  background: '.$st['color'].'; color:white">'.$st['icon'].' '.$st['label'].'</span>
      </div>';

        echo '<a class="feryx-edit-btn" href="' . esc_url(add_query_arg('edit_id', $row->id)) . '">EDIT</a>';
        echo '</div>';
    }
    echo '</div>';
    return ob_get_clean();
});

// Editing form HTML
function feryx_edit_form_html($prod) {
    global $wpdb;
    $compo_table = $wpdb->prefix . '_Feryx_compos';
    $compos = $wpdb->get_results("SELECT id, name FROM $compo_table");

    ob_start();
    $compo = $wpdb->get_row(
    $wpdb->prepare("SELECT name, editing FROM {$compo_table} WHERE id = %d", $prod->compo_id)
);
if (!$compo || intval($compo->editing) === 0) {
        echo '<div class="feryx-error-msg">Editing is closed for this competition! <a href="' . site_url('/product-edit') . '" class="feryx-btn-back">← Back</a></div>';
    return;
}
    ?>
    <form class="feryx-upload-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="edit_id" value="<?php echo intval($prod->id); ?>">

        <label>Compo:</label>
        <select name="compo_id" required>
            <?php foreach ($compos as $c): ?>
                <option value="<?php echo esc_attr($c->id); ?>" <?php selected($prod->compo_id, $c->id); ?>>
                    <?php echo esc_html($c->name); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Product title:</label>
        <input type="text" name="product_title" value="<?php echo esc_attr($prod->product_title); ?>" required>

        <label>Author:</label>
        <input type="text" name="author" value="<?php echo esc_attr($prod->author); ?>" required>

        <label>Comment (public):</label>
        <textarea name="comment_public"><?php echo esc_textarea($prod->comment_public); ?></textarea>

        <label>Comment for organizers (private):</label>
        <textarea name="comment_private"><?php echo esc_textarea($prod->comment_private); ?></textarea>

        <label>Upload ZIP file (leave empty to keep current):</label>
        <input type="file" name="uploaded_file" accept=".zip">

        <label>Screenshot (JPG, PNG, GIF) (leave empty to keep current):</label>
        <input type="file" name="screenshot" accept=".jpg,.jpeg,.png,.gif">

        <input type="submit" name="feryx_edit_submit" value="Save Changes">
    </form>
    <?php
    return ob_get_clean();
}

// Handle saving the edit
add_action('init', function() {
    if (!isset($_POST['feryx_edit_submit'])) return;
    global $wpdb;
    $prod_table  = $wpdb->prefix . '_Feryx_prods';
    $compo_table = $wpdb->prefix . '_Feryx_compos';
$compo_check = $wpdb->get_var(
    $wpdb->prepare("SELECT editing FROM {$compo_table} WHERE id = %d", intval($_POST['compo_id']))
);
if (intval($compo_check) === 0) {
    echo '<div class="feryx-error-msg">Editing is closed for this competition! <a href="' . site_url('/product-edit') . '" class="feryx-btn-back">← Back</a></div>';
    return;
}

    global $wpdb;
    $prod_table = $wpdb->prefix . '_Feryx_prods';
    $user_id = get_current_user_id();
    $edit_id = intval($_POST['edit_id']);

    $prod = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $prod_table WHERE id=%d AND user_id_uploader=%d",
        $edit_id, $user_id
    ));
    if (!$prod) wp_die('You do not have permission to edit this..');

    // Filename generator
    function feryx_random_name($ext) {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 20) . '.' . $ext;
    }

    // ZIP update
    $zip_name = $prod->uploaded_file;
    if ($_FILES['uploaded_file']['error'] === 0) {
        $zip_ext = pathinfo($_FILES['uploaded_file']['name'], PATHINFO_EXTENSION);
        if (strtolower($zip_ext) !== 'zip') wp_die('Only ZIP files allowed!');
        @unlink(WP_CONTENT_DIR . '/uploads/prods/' . $prod->uploaded_file);
        $zip_name = feryx_random_name('zip');
        $zip_path = WP_CONTENT_DIR . '/uploads/prods/' . $zip_name;
        wp_mkdir_p(dirname($zip_path));
        move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $zip_path);
    }

    // Screenshot update
    $img_name = $prod->screenshot;
    if ($_FILES['screenshot']['error'] === 0) {
        $img_ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
        if (!in_array($img_ext, ['jpg','jpeg','png','gif'])) wp_die('Only images allowed!');
        @unlink(WP_CONTENT_DIR . '/uploads/prods_screens/' . $prod->screenshot);
        $img_name = feryx_random_name($img_ext);
        $img_path = WP_CONTENT_DIR . '/uploads/prods_screens/' . $img_name;
        wp_mkdir_p(dirname($img_path));
        move_uploaded_file($_FILES['screenshot']['tmp_name'], $img_path);
    }

// Save
$wpdb->update($prod_table, [
    'compo_id'          => intval($_POST['compo_id']),
    'product_title'     => sanitize_text_field($_POST['product_title']),
    'author'            => sanitize_text_field($_POST['author']),
    'comment_public'    => sanitize_textarea_field($_POST['comment_public']),
    'comment_private'   => sanitize_textarea_field($_POST['comment_private']),
    'uploaded_file'     => $zip_name,
    'screenshot'        => $img_name,
    'uploadTime'        => current_time('mysql'),
    'version'           => intval($prod->version) + 1
], [
    'id' => $edit_id,
    'user_id_uploader' => $user_id
]);


    wp_redirect(add_query_arg('edited', '1', get_permalink(get_page_by_path('product-edit'))));
    exit;
});