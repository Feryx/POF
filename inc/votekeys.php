<?php
function po_votekey_page() {
    global $wpdb;
    $table = $wpdb->prefix . '_Feryx_votekeys';

    wp_enqueue_style('po-admin-style', plugin_dir_url(__FILE__) . '../po-admin.css');
    wp_enqueue_script('jquery'); // biztosítsuk, hogy jQuery betöltve legyen
    ?>
    <div class="wrap">
        <h1>VoteKeys</h1>

        <!-- Generate keys -->
        <form method="post">
            <label for="key_count">How many votekey you need?</label>
            <input type="number" name="key_count" id="key_count" value="10" min="1" style="width:60px" />
            <input type="submit" name="generate_keys" class="button button-primary" value="Generate votekeys" />
        </form>

        <hr>

        <!-- Add 200 more -->
        <form method="post" style="margin-top:20px;">
            <input type="submit" name="add_200_keys" class="button button-primary" value="Add more keys +200pcs" />
            <label>Simply add more keys if you have more visitors than you calculated...</label>
        </form>

        <hr>

        <!-- Clear all -->
        <form method="post" style="margin-top:20px;">
            <input type="submit" name="clear_keys" class="button button-danger" value="Clear all votekeys from db" onclick="return confirm('ARE YOU SUUUUUUUUUUUUURE?');" />
            <label>Click and die.. Cannot roll back this action! ;)</label>
        </form>

        <hr>

        <!-- Export all votekeys -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top:20px;">
            <input type="hidden" name="action" value="po_export_csv">
            <input type="submit" class="button button-secondary" value="Export ALL to CSV" />
            <label>Download all votekeys as CSV file.</label>
        </form>

        <hr>

        <!-- Export only unused & offline -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top:20px;">
            <input type="hidden" name="action" value="po_export_unused_offline_csv">
            <input type="submit" class="button button-secondary" value="Export Unused & Offline to CSV" />
            <label>Download only unused and offline votekeys as a CSV file, because you don’t want to sell pre-sold votekeys again ;)</label>
        </form>

        <hr>

        <!-- Toggleable Votekey list -->
        <div id="po_votekey_list" class="postbox closed">
            <button type="button" class="handlediv" aria-expanded="false">
                <span class="toggle-label">Toggle Votekey List</span>
            </button>
            
            <div class="inside" style="display:none;">
			<h2 class="hndle"><span>Votekey list</span></h2>
                <?php
                $votekeys = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
                if ($votekeys):
                ?>
                <ul style="list-style:none; padding-left:0;">
                    <?php foreach ($votekeys as $key): ?>
                        <li style="margin-bottom:8px;">
                            <strong><?php echo esc_html($key->id); ?>.</strong>
                            <?php echo $key->used
                                ? '<span style="color:green; font-weight:bold; margin: 0 10px;">&#10004;</span>'
                                : '<span style="color:blue; font-weight:bold; margin: 0 10px;">&#9679;</span>'; ?>
                            <code><?php echo esc_html($key->votekey); ?></code>
                            <?php
                            if ($key->user_id != 0) {
                                $user_info = get_userdata($key->user_id);
                                if ($user_info) echo ' — <em>' . esc_html($user_info->display_name) . '</em>';
                            }
                            if ($key->online != 0) echo ' — <em>Online Ticket</em>';
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p>No votekeys found.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <style>
        #po_votekey_list .handlediv {
            background: #f1f1f1;
            color: #000;
            border: 1px solid #ccc;
            padding: 5px 10px;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 5px;
			width:200px;
        }
        #po_votekey_list .toggle-label {
            color: #000;
			}
    </style>

    <script>
        jQuery(document).ready(function($){
            $('#po_votekey_list .handlediv').click(function(){
                var postbox = $(this).closest('.postbox');
                postbox.toggleClass('closed');
                postbox.find('.inside').slideToggle();
            });
        });
    </script>

    <?php
    // --- FORM PROCESSING ---
    if (isset($_POST['generate_keys'])) {
        $count = intval($_POST['key_count']);
        if ($count > 0) {
            for ($i = 0; $i < $count; $i++) {
                $key = wp_generate_password(13, false, false);
                $wpdb->insert($table, ['user_id'=>0,'votekey'=>$key,'used'=>0,'online'=>0]);
            }
            echo '<div class="updated notice"><p>'.$count.' votekey generated.</p></div>';
        }
    }
    if (isset($_POST['clear_keys'])) {
        $wpdb->query("TRUNCATE TABLE $table");
        echo '<div class="updated notice"><p>All votekeys deleted.</p></div>';
    }
    if (isset($_POST['add_200_keys'])) {
        for ($i = 0; $i < 200; $i++) {
            $key = wp_generate_password(13, false, false);
            $wpdb->insert($table, ['user_id'=>0,'votekey'=>$key,'used'=>0,'online'=>0]);
        }
        echo '<div class="updated notice"><p>200pcs extra votekey added.</p></div>';
    }
}

// --- EXPORT ALL VOTEKEYS ---
add_action('admin_post_po_export_csv','po_export_csv');
function po_export_csv(){
    global $wpdb;
    $table = $wpdb->prefix.'_Feryx_votekeys';
    $votekeys = $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC");

    if ($votekeys){
        while (ob_get_level()) ob_end_clean();
        header('Content-Type:text/csv; charset=utf-8');
        header('Content-Disposition:attachment; filename=votekeys_export.csv');
        $output = fopen('php://output','w');
        fputcsv($output,['ID','Votekey','Used','User ID','Online']);
        foreach($votekeys as $row){
            fputcsv($output,[$row->id,$row->votekey,$row->used,$row->user_id,$row->online]);
        }
        fclose($output);
        exit;
    } else wp_die('No votekeys found.');
}

// --- EXPORT UNUSED & OFFLINE VOTEKEYS ---
add_action('admin_post_po_export_unused_offline_csv','po_export_unused_offline_csv');
function po_export_unused_offline_csv(){
    global $wpdb;
    $table = $wpdb->prefix.'_Feryx_votekeys';
    $votekeys = $wpdb->get_results("SELECT * FROM $table WHERE used=0 AND online=0 ORDER BY id ASC");

    if (!$votekeys) wp_die('No unused & offline votekeys found.');
    while(ob_get_level()) ob_end_clean();
    header('Content-Type:text/csv; charset=utf-8');
    header('Content-Disposition:attachment; filename=unused_offline_votekeys.csv');
    $output = fopen('php://output','w');
    fputcsv($output,['ID','Votekey']);
    foreach($votekeys as $row){
        fputcsv($output,[$row->id,$row->votekey]);
    }
    fclose($output);
    exit;
}
