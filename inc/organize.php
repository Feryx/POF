<?php
function po_organize_page() {
    global $wpdb;

    // Query for competitions
    $compos_table = $wpdb->prefix . '_Feryx_compos';
    $compos = $wpdb->get_results("SELECT id, name FROM $compos_table ORDER BY name ASC");

    // Current compo ID
    $selected_compo = isset($_GET['compo_id']) ? intval($_GET['compo_id']) : 0;

    echo '<div class="wrap"><h1>Organize Prods</h1>';
    echo '<p><div class="status-dot status-0 active"></div>NEW!<div class="status-dot status-1 active"></div><b>Qualified<div class="status-dot status-2 active"></div><b>Not Qualified<div class="status-dot status-3 active"></div><b>Disqualified</p>';
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
            .prod-item { padding: 8px; background: #fff; margin-bottom: 4px; border: 1px solid #ccc; cursor: grab; }
            .prod-header { display: flex; align-items: center; justify-content: space-between; }
            .prod-info { font-size: 12px; color: #666; margin-top: 4px; }
            .status-dot { width: 15px; height: 15px; border-radius: 50%; margin: 0 2px; opacity: 0.2; display: inline-block; cursor: pointer; box-shadow: 3px 0px;}
            .status-dot.active { opacity: 1; }
            .status-0 { background: blue; }
            .status-1 { background: #2ad408; }
            .status-2 { background: #ffa200; }
            .status-3 { background: red; }
            .edit-btn { margin-left: 10px; }
            /* Popup */
            .popup-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 9999; }
            .popup-content { background:#fff; padding:20px; max-width:700px; margin:50px auto; border-radius:8px; position:relative; }
            .popup-content label { display:block; margin-top:10px; font-weight: bold; }
            .popup-buttons { margin-top:15px; }
        </style>

        <ul class="prod-list" id="prodList">
        <?php
        $num=0;
        foreach ($prods as $prod):
            $user_name = get_the_author_meta('display_name', $prod->user_id_uploader); // uploader's name
            $num++;
            ?>

            <li class="prod-item" data-id="<?php echo $prod->id; ?>">
                <div class="prod-header">
                    <strong><?php echo '#'.$num.' ';?><?php echo esc_html($prod->product_title); ?></strong>
                    <span>
                        <?php for ($i=0; $i<=3; $i++): ?>
                            <div class="status-dot status-<?php echo $i; ?> <?php echo ($prod->status == $i) ? 'active' : ''; ?>"
                                 data-status="<?php echo $i; ?>"></div>
                        <?php endfor; ?>
                        <button class="button edit-btn">Edit</button>
                    </span>
                </div>
                <div class="prod-info">
                    By: <?php echo esc_html($prod->author); ?>
                    | uploadBy: <?php echo esc_html($user_name ?: 'N/A'); ?>
                    | Uploaded: <?php echo esc_html($prod->uploadTime); ?>
                    | Version: <?php echo intval($prod->version); ?>
                    | Status: <?php echo intval($prod->status); ?>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
<?php
global $wpdb;

// If coming from a POST (e.g. after form submission)
$current_compo_id = isset($_POST['compo_id']) ? intval($_POST['compo_id']) : 0;

// If data already exists in edit mode (e.g. $data->compo_id)
if (!$current_compo_id && isset($adat->compo_id)) {
    $current_compo_id = intval($adat->compo_id);
}

// Load competitions by name
$compos = $wpdb->get_results("SELECT id, name FROM wp__Feryx_compos ORDER BY name ASC");
?>
        <div class="popup-overlay" id="editPopup">
            <div class="popup-content">
                <h2>Edit Prod</h2>
                <form id="editForm">
                    <input type="hidden" name="id" id="edit_id">
                    <label>User ID Uploader: <input type="number" name="user_id_uploader" id="edit_user_id_uploader"></label>
                    <label for="edit_compo_id">Competition:</label>
<select name="compo_id" id="edit_compo_id">
    <?php foreach ($compos as $compo): ?>
        <option value="<?= esc_attr($compo->id) ?>" <?= ($compo->id == $current_compo_id ? 'selected' : '') ?>>
            <?= esc_html($compo->name) ?>
        </option>
    <?php endforeach; ?>
</select>

                    <label>Product Title: <input type="text" name="product_title" id="edit_product_title"></label>
                    <label>Author: <input type="text" name="author" id="edit_author"></label>
                    <label>Comment Public: <textarea name="comment_public" id="edit_comment_public"></textarea></label>
                    <label>Comment Private: <textarea name="comment_private" id="edit_comment_private"></textarea></label>
                    <label>Uploaded File: <input type="text" name="uploaded_file" id="edit_uploaded_file"></label>
                    <label>Screenshot: <input type="text" name="screenshot" id="edit_screenshot"></label>
                    <label>Status:
                        <select name="status" id="edit_status">
                            <option value="0">NEW! (0)</option>
                            <option value="1">Qualified (1)</option>
                            <option value="2">Not Qualified (2)</option>
                            <option value="3">Disqualified (3)</option>
                        </select>
                    </label>
                    <label>Upload Time: <input type="number" name="uploadTime" id="edit_uploadTime"></label>
                    <label>Version: <input type="number" name="version" id="edit_version"></label>
                    <label>Live Vote Flag: <input type="number" name="livevoteflag" id="edit_livevoteflag"></label>
                    <label>Order ID: <input type="number" name="orderid" id="edit_orderid"></label>
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
        var order = $(this).children().map(function(){
            return $(this).data('id');
        }).get();

        $.post(ajaxurl, {
            action: 'po_save_order',
            order: order
        });
    }
});


$(document).on('click', '.status-dot', function(){
    var $dot = $(this);
    var prodId = $dot.closest('.prod-item').data('id');

    var newStatus = parseInt($dot.data('status'));

    $.post(ajaxurl, {
        action: 'po_update_status',
        id: prodId,
        status: newStatus
    }, function(resp){
        if(resp.success){
            // update appearance
            $dot.closest('td').find('.status-dot').css('opacity', '0.2');
            $dot.css('opacity', '1');
            $dot.closest('tr').addClass('row-saved');
            if(resp.success){
    // update appearance
    $dot.closest('.prod-item').find('.status-dot').css('opacity', '0.2');
    $dot.css('opacity', '1');
}
            setTimeout(function(){
                $dot.closest('tr').removeClass('row-saved');
            }, 500);
        } else {
            alert(resp.data.message);
        }
    });
});


            // Open edit popup
            $('.edit-btn').click(function(e) {
                e.preventDefault();
                var id = $(this).closest('.prod-item').data('id');
                $.post(ajaxurl, { action: 'po_get_prod', id: id }, function(data) {
                    for (var key in data) {
                        if ($('#edit_' + key).length) {
                            $('#edit_' + key).val(data[key]);
                        }
                    }
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

// Get prod
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

    $data = [];
    foreach ($_POST as $key => $value) {
        if ($key == 'id' || $key == 'action') continue;
        $data[$key] = sanitize_text_field($value);
    }

    $wpdb->update($table, $data, ['id' => $id]);
    wp_die();
});
add_action('wp_ajax_po_save_order', function() {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_prods';

    if (!empty($_POST['order']) && is_array($_POST['order'])) {
        $pos = 1;
        foreach ($_POST['order'] as $id) {
            $wpdb->update(
                $table,
                ['orderid' => $pos],
                ['id' => intval($id)],
                ['%d'],
                ['%d']
            );
            $pos++;
        }
    }
    wp_die();
});
add_action('wp_ajax_po_update_status', function() {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_prods';

    $id = intval($_POST['id']);
    $status = intval($_POST['status']);

    if ($id > 0 && $status >= 0 && $status <= 3) {
        $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        wp_send_json_success(['message' => 'Status saved']);
    }

    wp_send_json_error(['message' => 'Bad bad baddatas']);
});