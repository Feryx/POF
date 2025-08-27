<?php
function po_organize_page() {
    global $wpdb;

    // Query for competitions
    $compos_table = $wpdb->prefix . '_Feryx_compos';
    $compos = $wpdb->get_results("SELECT id, name FROM $compos_table ORDER BY name ASC");

    // Current compo ID
    $selected_compo = isset($_GET['compo_id']) ? intval($_GET['compo_id']) : 0;

    echo '<div class="wrap"><h1>Organize Prods</h1>';
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="po_organize_page">';
    echo '<label for="compo_id">Select competition:</label> ';
    echo '<select name="compo_id" id="compo_id" onchange="this.form.submit()">';
    echo '<option value="">-- Select --</option>';
    foreach ($compos as $c) {
        $sel = ($selected_compo == $c->id) ? 'selected' : '';
        echo "<option value='{$c->id}' $sel>{$c->name}</option>";
    }
    echo '</select>';
    echo '</form>';

    if ($selected_compo) {
        $prods_table = $wpdb->prefix . '_Feryx_prods';
        $prods = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $prods_table WHERE compo_id = %d ORDER BY orderid ASC", $selected_compo)
        );

        ?>
        <style>
            .prod-list { list-style: none; padding: 0; margin: 0; }
            .prod-item { display: flex; align-items: center; padding: 5px; background: #fff; margin-bottom: 4px; border: 1px solid #ccc; cursor: grab; }
            .status-dot { width: 10px; height: 10px; border-radius: 50%; margin: 0 4px; opacity: 0.2; }
            .status-dot.active { opacity: 1; }
            .status-0 { background: blue; }
            .status-1 { background: green; }
            .status-2 { background: yellow; }
            .status-3 { background: red; }
            .edit-btn { margin-left: auto; }
            /* Popup */
            .popup-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 9999; }
            .popup-content { background:#fff; padding:20px; max-width:500px; margin:100px auto; border-radius:8px; position:relative; }
            .popup-content label { display:block; margin-top:10px; }
            .popup-buttons { margin-top:15px; }
        </style>

        <ul class="prod-list" id="prodList">
        <?php foreach ($prods as $prod): ?>
            <li class="prod-item" data-id="<?php echo $prod->id; ?>">
                <strong><?php echo esc_html($prod->product_title); ?></strong>
                (<?php echo esc_html($prod->author); ?>)

                <?php for ($i=0; $i<=3; $i++): ?>
                    <div class="status-dot status-<?php echo $i; ?> <?php echo ($prod->status == $i) ? 'active' : ''; ?>"
                         data-status="<?php echo $i; ?>"></div>
                <?php endfor; ?>

                <button class="button edit-btn">Edit</button>
            </li>
        <?php endforeach; ?>
        </ul>

        <div class="popup-overlay" id="editPopup">
            <div class="popup-content">
                <h2>Edit Prod</h2>
                <form id="editForm">
                    <input type="hidden" name="id" id="edit_id">
                    <label>Title: <input type="text" name="product_title" id="edit_title"></label>
                    <label>Author: <input type="text" name="author" id="edit_author"></label>
                    <label>Public Comment: <textarea name="comment_public" id="edit_comment_public"></textarea></label>
                    <label>Private Comment: <textarea name="comment_private" id="edit_comment_private"></textarea></label>
                    <label>Uploaded File: <input type="text" name="uploaded_file" id="edit_uploaded_file"></label>
                    <label>Screenshot: <input type="text" name="screenshot" id="edit_screenshot"></label>
                    <div class="popup-buttons">
                        <button type="submit" class="button button-primary">Save</button>
                        <button type="button" class="button" id="popupCancel">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>
        <script>
        jQuery(function($) {
            // Drag and drop
            $('#prodList').sortable({
                update: function(e, ui) {
                    var order = $(this).sortable('toArray', { attribute: 'data-id' });
                    $.post(ajaxurl, {
                        action: 'po_save_order',
                        order: order
                    });
                }
            });

            // Status change
            $('.status-dot').click(function() {
                var dot = $(this);
                var parent = dot.closest('.prod-item');
                var id = parent.data('id');
                var status = dot.data('status');

                parent.find('.status-dot').removeClass('active');
                dot.addClass('active');

                $.post(ajaxurl, {
                    action: 'po_update_status',
                    id: id,
                    status: status
                });
            });

            // Edit popup
            $('.edit-btn').click(function(e) {
                e.preventDefault();
                var id = $(this).closest('.prod-item').data('id');
                $.post(ajaxurl, { action: 'po_get_prod', id: id }, function(data) {
                    $('#edit_id').val(data.id);
                    $('#edit_title').val(data.product_title);
                    $('#edit_author').val(data.author);
                    $('#edit_comment_public').val(data.comment_public);
                    $('#edit_comment_private').val(data.comment_private);
                    $('#edit_uploaded_file').val(data.uploaded_file);
                    $('#edit_screenshot').val(data.screenshot);
                    $('#editPopup').fadeIn();
                }, 'json');
            });

            $('#popupCancel').click(function() {
                $('#editPopup').fadeOut();
            });

            // Save
            $('#editForm').submit(function(e) {
                e.preventDefault();
                $.post(ajaxurl, $(this).serialize() + '&action=po_save_prod', function() {
                    location.reload();
                });
            });
        });
        </script>
        <?php
    }

    echo '</div>';
}
// Get prod for editing
add_action('wp_ajax_po_get_prod', function() {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_prods';
    $id = intval($_POST['id']);
    $prod = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
    wp_send_json($prod);
});

// Save prod
add_action('wp_ajax_po_save_prod', function() {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_prods';
    $id = intval($_POST['id']);
    $wpdb->update($table, [
        'product_title' => sanitize_text_field($_POST['product_title']),
        'author' => sanitize_text_field($_POST['author']),
        'comment_public' => wp_kses_post($_POST['comment_public']),
        'comment_private' => wp_kses_post($_POST['comment_private']),
        'uploaded_file' => sanitize_text_field($_POST['uploaded_file']),
        'screenshot' => sanitize_text_field($_POST['screenshot']),
    ], ['id' => $id]);
    wp_die();
});