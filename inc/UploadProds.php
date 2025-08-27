<?php
function feryx_create_pages() {
    // Create new page if it doesn't exist
    if (!get_page_by_path('product-upload')) {
        wp_insert_post([
            'post_title'   => 'UPLOAD',
            'post_name'    => 'product-upload',
            'post_content' => '[feryx_upload_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'menu_order'   => 2
        ]);
    }
}

// Shortcode for upload form
add_shortcode('feryx_upload_form', 'feryx_upload_form_html');
function feryx_upload_form_html() {
$access = feryx_check_access('Upload');
if ($access !== true) {
    echo $access;
    return;
}
    global $wpdb;

    // Load compo list
    $compo_table = $wpdb->prefix . '_Feryx_compos';
    $compos = $wpdb->get_results("SELECT id, name FROM $compo_table");

    ob_start();
    if (isset($_GET['uploaded']) && $_GET['uploaded'] == '1') {
    echo '<div class="feryx-success-msg">
                <span class="feryx-checkmark">&#10004;</span> Success! <a href="' . site_url('/product-upload') . '" class="feryx-btn-back">← Back</a>
            </div>';}
    if (isset($_GET['upload_closed']) && $_GET['upload_closed'] == '1') {
    echo '<div class="feryx-error-msg">
                <span class="feryx-crossmark">&#10006;</span> Upload closed <a href="' . site_url('/product-edit') . '" class="feryx-btn-back">Edit</a>
            </div>';}
    ?>
   <form class="feryx-upload-form" method="post" enctype="multipart/form-data">
    <label>Compo:</label>
    <select name="compo_id" required>
        <option value="">-- Choose --</option>
        <?php foreach ($compos as $c): ?>
            <option value="<?php echo esc_attr($c->id); ?>">
                <?php echo esc_html($c->name); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Product title:</label>
    <input type="text" name="product_title" required>

    <label>Author:</label>
    <input type="text" name="author" required>

    <label>Comment (public):</label>
    <textarea name="comment_public"></textarea>

    <label>Comment for organizers (private):</label>
    <textarea name="comment_private"></textarea>

    <label>Upload ZIP file:</label>
    <input type="file" name="uploaded_file" accept=".zip" required>

    <label>Screenshot (JPG, PNG, GIF):</label>
    <input type="file" name="screenshot" accept=".jpg,.jpeg,.png,.gif" required>

    <input type="submit" name="feryx_submit" value="Upload">
</form>

    <?php   if (isset($_GET['uploaded']) && $_GET['uploaded'] == '1') {
    echo '<div class="feryx-success-msg">
                <span class="feryx-checkmark">&#10004;</span> Success!
            </div>';}
         if (isset($_GET['upload_closed']) && $_GET['upload_closed'] == '1') {
    echo '<div class="feryx-error-msg">
                <span class="feryx-crossmark">&#10006;</span> Upload closed <a href="' . site_url('/product-edit') . '" class="feryx-btn-back">Edit</a>
            </div>';}
    return ob_get_clean();
}

// Processing
add_action('init', 'feryx_handle_upload');
function feryx_handle_upload() {

    if (!isset($_POST['feryx_submit'])) return;
global $wpdb;
$compo_table = $wpdb->prefix . '_Feryx_compos';

// Check if upload is enabled
$compo_id = intval($_POST['compo_id']);
$upload_status = $wpdb->get_var($wpdb->prepare(
    "SELECT upload FROM $compo_table WHERE id = %d",
    $compo_id
));

if ($upload_status != 1) {
    wp_redirect(add_query_arg('upload_closed', '1', get_permalink(get_page_by_path('product-upload'))));
    exit;
}


    global $wpdb;
    $prod_table = $wpdb->prefix . '_Feryx_prods';
    $user_id = get_current_user_id();

    // Generate 20-character filename
    function feryx_random_name($ext) {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 20) . '.' . $ext;
    }

    // Upload ZIP file
    if ($_FILES['uploaded_file']['error'] === 0) {
        $zip_ext = pathinfo($_FILES['uploaded_file']['name'], PATHINFO_EXTENSION);
        if (strtolower($zip_ext) !== 'zip') wp_die('Only ZIP files allowed. <a href="' . site_url('/product-upload') . '" class="feryx-btn-back">← Back</a>');
        $zip_name = feryx_random_name('zip');
        $zip_path = WP_CONTENT_DIR . '/uploads/prods/' . $zip_name;
        wp_mkdir_p(dirname($zip_path));
        move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $zip_path);
    }
// Upload screenshot
if ($_FILES['screenshot']['error'] === 0) {
    $img_ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
    if (!in_array($img_ext, ['jpg','jpeg','png','gif'])) {
        wp_die('Only images can upload! <a href="' . site_url('/product-upload') . '" class="feryx-btn-back">← Back</a>');
    }

    $img_name = feryx_random_name($img_ext);
    $img_path = WP_CONTENT_DIR . '/uploads/prods_screens/' . $img_name;
    wp_mkdir_p(dirname($img_path));
    move_uploaded_file($_FILES['screenshot']['tmp_name'], $img_path);

    // ---- IMAGE RESIZING ----
    $editor = wp_get_image_editor($img_path);
    if (!is_wp_error($editor)) {
        // Get dimensions
        $size = $editor->get_size();
        $width  = $size['width'];
        $height = $size['height'];
        $preview_Size  = get_option('po_previewSize', '');
        // Proportionally resize the longer side to 300px
        if ($width > $height) {
            $editor->resize($preview_Size, null); // fixed width, proportional height
        } else {
            $editor->resize(null, $preview_Size); // fixed height, proportional width
        }

        $editor->save($img_path); // overwrite the file
    }
}


    // Save to database
    $wpdb->insert($prod_table, [
        'user_id_uploader' => $user_id,
        'compo_id'         => intval($_POST['compo_id']),
        'product_title'    => sanitize_text_field($_POST['product_title']),
        'author'           => sanitize_text_field($_POST['author']),
        'comment_public'   => sanitize_textarea_field($_POST['comment_public']),
        'comment_private'  => sanitize_textarea_field($_POST['comment_private']),
        'uploaded_file'    => $zip_name,
        'screenshot'       => $img_name,
        'status'           => 0,
        'uploadTime'       => current_time('mysql'),
        'version'          => 1
    ]);

    wp_redirect(add_query_arg('uploaded', '1', get_permalink(get_page_by_path('product-upload'))));
exit;

}
// Create menu item only for logged-in users with 'visitor' role
add_action('wp', function() {
    if (is_user_logged_in() && current_user_can('read')) {
        $user = wp_get_current_user();
        if (in_array('visitor', (array) $user->roles)) {
            add_filter('wp_nav_menu_items', function($items, $args) {
                // Only add to the primary menu
                if ($args->theme_location === 'primary') {
                    $upload_page = get_page_by_path('product-upload');
                    if ($upload_page) {
                        $url = get_permalink($upload_page->ID);
                        $items .= '<li class="menu-item"><a href="' . esc_url($url) . '">UPLOAD</a></li>';
                    }
                }
                return $items;
            }, 10, 2);
        }
    }
});

// Responsive CSS for the form
add_action('wp_enqueue_scripts', function() {
    wp_register_style('feryx-upload-css', false);
    wp_enqueue_style('feryx-upload-css');
    $custom_css = "
    .feryx-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 15px;
}
.feryx_status_label{
    padding: 2px 6px;
}
.feryx-btn-back {
    display: inline-block;
    margin-left: 10px;
    padding: 8px 14px;
    background: #2271b1;
    border-color: #2271b1;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s ease-in-out;
}
.feryx-btn-back:hover {
    background: #444;
}
.feryx-entry-status {
    margin-top: 10px;
    font-size: 14px;
}
.feryx-entry-status strong {
    margin-right: 5px;
}

.feryx-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    text-align: center;
    padding: 10px;
}
.feryx-card-imgdiv {
    width: 100%;
    height: 228px;
    overflow: hidden;
    border-bottom: 1px solid #ddd;
}

.feryx-card img {
    width: 100%;
    height: 100%;
    object-fit: cover; /* proportional cropping + center */
    display: block;
}
.feryx-card h3 {
    margin: 10px 0 5px;
    size:12px;
}
.feryx-edit-btn {
    display: inline-block;
    padding: 8px 12px;
    margin-top: 8px;
    background: #0073aa;
    color: #fff;
    border-radius: 4px;
    text-decoration: none;
}
.feryx-edit-btn:hover {
    background: #005f8d;
}

    .feryx-success-msg {
    background-color: #d4edda;
    color: #155724;
    padding: 10px 15px;
    margin-bottom: 15px;
    border: 1px solid #c3e6cb;
    border-radius: 5px;
    display: flex;
    align-items: center;
    font-weight: bold;
}
.feryx-success-msg .feryx-checkmark {
    font-size: 20px;
    margin-right: 8px;
    color: #28a745;
}
.feryx-error-msg {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px 15px;
    margin-bottom: 15px;
    border: 1px solid #f5c6cb;
    border-radius: 5px;
    display: flex;
    align-items: center;
    font-weight: bold;
}
.feryx-error-msg .feryx-crossmark {
    font-size: 20px;
    margin-right: 8px;
    color: #dc3545;
}

    form.feryx-upload-form {
        max-width: 600px;
        margin: 20px auto;
        padding: 15px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    form.feryx-upload-form label {
        display: block;
        margin-top: 10px;
        font-weight: bold;
    }
    form.feryx-upload-form input[type='text'],
    form.feryx-upload-form textarea,
    form.feryx-upload-form select {
        width: 100%;
        padding: 8px;
        margin-top: 4px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    form.feryx-upload-form input[type='file'] {
        margin-top: 4px;
    }
    form.feryx-upload-form input[type='submit'] {
        margin-top: 15px;
        background: #0073aa;
        color: #fff;
        padding: 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
    }
    form.feryx-upload-form input[type='submit']:hover {
        background: #005f8d;
    }
    @media (max-width: 600px) {
        form.feryx-upload-form {
            padding: 10px;
        }
    }
    ";
    wp_add_inline_style('feryx-upload-css', $custom_css);
});