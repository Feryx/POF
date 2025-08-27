<?php
function feryx_export_entries() {
    global $wpdb;

    // --- base path ---
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/exported';

    // --- delete if it already exists ---
    if (is_dir($export_dir)) {
        feryx_rrmdir($export_dir);
    }
    wp_mkdir_p($export_dir);

    // --- get all competitions by name ---
    $compos = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}_Feryx_compos ORDER BY name ASC");

    foreach ($compos as $compo) {
        $compo_dir = $export_dir . '/' . sanitize_file_name($compo->name);
        wp_mkdir_p($compo_dir);

        // counter for the current compo
        $counter = 1;

        // --- get productions for the given compo_id ---
        $prods = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}_Feryx_prods WHERE compo_id=%d ORDER BY orderid ASC",
            $compo->id
        ));

        foreach ($prods as $prod) {
            // source file
            $source_file = WP_CONTENT_DIR . '/uploads/prods/' . $prod->uploaded_file;
            if (!file_exists($source_file)) {
                continue; // if no file, skip
            }

            // safe filename: product_title_author.zip
            $raw_name = $prod->product_title . '_' . $prod->author . '.zip';
            $new_filename = sanitize_file_name($raw_name);

            if ($prod->status == 1) {
                // only increment the counter for status=1
                $order_str = str_pad($counter, 4, '0', STR_PAD_LEFT);
                $prod_dir = $compo_dir . '/' . $order_str;
                wp_mkdir_p($prod_dir);

                copy($source_file, $prod_dir . '/' . $new_filename);

                $counter++; // next one
            } elseif ($prod->status == 2 || $prod->status == 3) {
                // disqualified folder
                $dq_dir = $compo_dir . '/Disqualified';
                wp_mkdir_p($dq_dir);

                copy($source_file, $dq_dir . '/' . $new_filename);
            }
        }
    }

    return "Export finished: " . esc_html($export_dir);
}

// helper function for recursive deletion
function feryx_rrmdir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            feryx_rrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}
/*
// Admin menu item
add_action('admin_menu', function() {
    add_menu_page('Feryx Export', 'Feryx Export', 'manage_options', 'feryx-export', function() {
        if (isset($_POST['do_export'])) {
            echo '<div class="updated"><p>' . feryx_export_entries() . '</p></div>';
        }
        echo '<form method="post"><button type="submit" name="do_export" class="button button-primary">Export</button></form>';
    });
});
*/