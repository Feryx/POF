<?php
/*
Plugin Name: POF+ Online Ticket System
Description: POF+ extra Ticket management system for WooCommerce with votekey generation, PDF tickets, and QR verification. Instant ticket delivery after payment.
Version: 1.4
Author: Feryx
RequiresPlugins: woocommerce/woocommerce.php
*/

if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . "_Feryx_votekeys";

// ===== Create table on activation =====
register_activation_hook(__FILE__, function() use ($wpdb, $table_name) {
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        votekey VARCHAR(50) NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        online TINYINT(1) NOT NULL DEFAULT 0,
        token VARCHAR(50) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY(id),
        UNIQUE KEY votekey (votekey)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

// ===== Create WooCommerce Ticket products on activation =====
register_activation_hook(__FILE__, function() {
    require_once( ABSPATH . 'wp-admin/includes/post.php' ); // <- FONTOS!

    $products = [
        'Regular Ticket'   => ['sku'=>1000, 'type'=>'normal'],
        'Supporter Ticket' => ['sku'=>3000, 'type'=>'normal'],
        'Online Ticket'    => ['sku'=>2000, 'type'=>'online_vote'],
    ];

    foreach ($products as $title => $data) {
        if (!post_exists($title)) {
            $post_id = wp_insert_post([
                'post_title'  => $title,
                'post_type'   => 'product',
                'post_status' => 'publish'
            ]);
            update_post_meta($post_id, '_price', 0);
            update_post_meta($post_id, '_sku', $data['sku']);
            wp_set_object_terms($post_id, 'simple', 'product_type');

            // Ticket meta
            update_post_meta($post_id, '_is_ticket', 'yes');
            update_post_meta($post_id, '_ticket_type', $data['type']);
        }
    }
});





// Add checkbox + dropdown to product edit page
add_action('woocommerce_product_options_general_product_data', function() {
    // Checkbox: jegy-e
    woocommerce_wp_checkbox([
        'id' => '_is_ticket',
        'label' => __('This is a ticket product', 'pof-ticket'),
        'description' => __('Enable if this product should generate a ticket on purchase.', 'pof-ticket')
    ]);

    // Dropdown: jegytípus
    woocommerce_wp_select([
        'id' => '_ticket_type',
        'label' => __('Ticket type', 'pof-ticket'),
        'options' => [
            '' => __('Select type', 'pof-ticket'),
            'normal' => __('Normal Ticket', 'pof-ticket'),
            'online_vote' => __('Online Vote Only', 'pof-ticket'),
        ],
        'description' => __('Select the type of this ticket.', 'pof-ticket')
    ]);
});

// Save values
add_action('woocommerce_admin_process_product_object', function($product) {
    $is_ticket = isset($_POST['_is_ticket']) ? 'yes' : 'no';
    $ticket_type = isset($_POST['_ticket_type']) ? sanitize_text_field($_POST['_ticket_type']) : '';

    $product->update_meta_data('_is_ticket', $is_ticket);
    $product->update_meta_data('_ticket_type', $ticket_type);
});


// ===== Create Admin role =====
register_activation_hook(__FILE__, function(){
    add_role('ticket_checker', 'Ticket Checker', ['read'=>true]);
});

// ===== Generate tickets after payment =====
add_action('woocommerce_payment_complete', 'feryx_send_tickets_on_payment');
function feryx_send_tickets_on_payment($order_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . "_Feryx_votekeys";
    $order = wc_get_order($order_id);
    $user_email = $order->get_billing_email();

    foreach ($order->get_items() as $item) {
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();

       $product = wc_get_product($item->get_product_id());
	if ($product->get_meta('_is_ticket') === 'yes') {
    $ticket_type = $product->get_meta('_ticket_type');

    for ($i=0; $i < $quantity; $i++) {
        $votekey = wp_generate_password(10,false,false);
        $salt = wp_generate_password(8,false,false);

        // Ha online vote only → hibás salt mentve az adatbázisba
        $db_salt = ($ticket_type === 'online_vote') ? wp_generate_password(8,false,false) : $salt;

        $wpdb->insert($table_name, [
            'votekey'=>$votekey,
            'used'=>0,
            'online'=>1,
            'token'=>$db_salt,   // ez kerül az adatbázisba
            'user_id'=>$order->get_user_id()
        ]);

        // PDF generálás – a "helyes" salt-ot kapja a user
        $ticket_pdf = generate_ticket_pdf($votekey, $salt, $product->get_name());

        if (!file_exists($ticket_pdf)) continue;

        // Email küldés
        $subject = "Your " . $product->get_name() . " Ticket";
        $body = "Attached is your ticket PDF for " . $product->get_name() . ".";
        pof_ticket_send_mail($user_email, $subject, $body, [$ticket_pdf]);
    }
}

    }
}

// ===== PDF generation with FPDF + QR =====
function generate_ticket_pdf($votekey, $salt, $title) {
    require_once(plugin_dir_path(__FILE__) . 'assets/fpdf/fpdf.php');
    require_once(plugin_dir_path(__FILE__) . 'assets/phpqrcode/qrlib.php');

    // Plugin settings
    $site_url     = get_site_url();
    $party_start  = get_option('po_party_start', '');
    $slider_logo  = get_option('po_slider_logo', '');

    // Create PDF file
    $file = tempnam(sys_get_temp_dir(), 'ticket_') . '.pdf';
    $pdf = new FPDF('P','mm','A4');
    $pdf->AddPage();

    // Logo
    if (!empty($slider_logo)) {
        $pdf->Image($slider_logo, 10, 10, 40);
    }

    // Event title
    $pdf->SetFont('Arial','B',20);
    $pdf->Cell(0,20, $title, 0, 1, 'C');

    // Event start
    if (!empty($party_start)) {
        $date_str = date("l, j F Y - H:i", strtotime($party_start));
        $pdf->SetFont('Arial','',12);
        $pdf->Cell(0,10,"Event starts: " . $date_str, 0, 1, 'C');
    }

    // --- Ticket Key Box ---
    $pdf->Ln(15);
    $boxWidth  = 180; // mm
    $boxHeight = 100; // mm
    $boxX = ($pdf->GetPageWidth() - $boxWidth) / 2;
    $boxY = $pdf->GetY();

    // Draw frame (light grey background)
    $pdf->SetFillColor(245,245,245);
    $pdf->Rect($boxX, $boxY, $boxWidth, $boxHeight, 'F');

    // Title
    $pdf->SetFont('Arial','B',16);
    $pdf->SetXY($boxX, $boxY + 10);
    $pdf->Cell($boxWidth, 10, "Your Vote Key", 0, 1, 'C');

    // Vote Key
    $pdf->SetFont('Arial','B',14);
    $pdf->SetX($boxX);
    $pdf->Cell($boxWidth, 10, "Vote Key: $votekey", 0, 1, 'C');

    // Salt
    $pdf->SetFont('Arial','',12);
    $pdf->SetX($boxX);
    $pdf->Cell($boxWidth, 10, "Salt: $salt", 0, 1, 'C');

    // --- QR generation ---
    $qrfile = tempnam(sys_get_temp_dir(), 'qrcode_') . '.png';
    QRcode::png($votekey . '|' . $salt, $qrfile, QR_ECLEVEL_L, 6);
    $qrSize = 50; // mm

    // Center QR code within the box
    $qrX = ($pdf->GetPageWidth() - $qrSize) / 2;
    $qrY = $pdf->GetY() + 5;
    $pdf->Image($qrfile, $qrX, $qrY, $qrSize, $qrSize);
    unlink($qrfile);

    // Jump after the box
    $pdf->SetY($boxY + $boxHeight + 15);

    // Footer usage notes
    $pdf->SetFont('Arial','',11);
    $pdf->MultiCell(0, 7,
        "Your Vote Key can also be used in the PartySystem.\nFor upload prods and V0te\nVisit: $site_url\n\n".
        "IMPORTANT:\n".
        "- This ticket can be validated only once.\n".
        "- Please present it at the entrance.\n".
        "- After validation you will receive a wristband for entry.",
        0, 'C'
    );

    // Save
    $pdf->Output('F', $file);
    //error_log("DEBUG: PDF generated: $file");

    return $file;
}


// ===== Custom 'From' for plugin emails =====
function pof_ticket_send_mail($to, $subject, $message, $attachments = array()) {
    $from_email = get_option('pof_ticket_email_from', get_bloginfo('admin_email'));
    $from_name  = get_option('pof_ticket_email_name', get_bloginfo('name'));
    $headers = array('From: ' . $from_name . ' <' . $from_email . '>');
    return wp_mail($to, $subject, $message, $headers, $attachments);
}

// ===== Add Admin menu =====
add_action('admin_menu', function() {
    add_menu_page(
        'POF+ TicketSystem',
        'POF+ TicketSystem',
        'manage_options',
        'pof_ticket_system',
        'pof_ticket_system_admin_page',
        'dashicons-tickets-alt',
        56
    );
});

// ===== Admin page content =====
function pof_ticket_system_admin_page() {
    if (isset($_POST['pof_ticket_email_from']) && check_admin_referer('pof_ticket_settings_save')) {
        update_option('pof_ticket_email_from', sanitize_email($_POST['pof_ticket_email_from']));
        update_option('pof_ticket_email_name', sanitize_text_field($_POST['pof_ticket_email_name']));
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $email_from = get_option('pof_ticket_email_from', get_bloginfo('admin_email'));
    $email_name = get_option('pof_ticket_email_name', get_bloginfo('name'));

    ?>
    <div class="wrap">
        <h2>Ticket System</h2>
        <p>Here's a step-by-step guide on how to use the <strong>Ticket+ POF</strong> plugin:</p>
        <ol>
            <li><strong>Activate the POF+ Ticket plugin</strong>. It will automatically add the ticket products to WooCommerce.</li>
            <li>The plugin also <strong>adds a new user role</strong>. Assign <code>Ticket Checker</code> to inspectors.</li>
            <li>Inspectors can check tickets at <strong>My Account → Tickets</strong>, by QR code or votekey.</li>
            <li><strong>Each ticket can only be validated once!</strong></li>
        </ol>

        <h3>Email settings</h3>
        <form method="post">
            <?php wp_nonce_field('pof_ticket_settings_save'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="pof_ticket_email_name">Sender name</label></th>
                    <td><input type="text" name="pof_ticket_email_name" id="pof_ticket_email_name"
                        value="<?php echo esc_attr($email_name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="pof_ticket_email_from">Sender email</label></th>
                    <td><input type="email" name="pof_ticket_email_from" id="pof_ticket_email_from"
                        value="<?php echo esc_attr($email_from); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button('Save settings'); ?>
        </form>

        <h3>Test PDF preview</h3>
<form method="get" action="<?php echo admin_url('admin-post.php'); ?>" target="_blank">
    <input type="hidden" name="action" value="pof_ticket_test_pdf">
    <button type="submit" class="button button-secondary">Generate test ticket PDF</button>
</form>

        <h3>Customizing the Ticket PDF</h3>
        <p>
            To change the layout of the ticket PDF, edit
            <code>generate_ticket_pdf($votekey, $salt, $title)</code> in the plugin.
        </p>

		 <h4>Example customization:</h4>
        <pre><code>
// Example inside generate_ticket_pdf($votekey, $salt, $title)

// Add your event logo at the top
$pdf->Image(__DIR__ . '/assets/logo.png', 15, 10, 50);

// Add a title
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 20, 'QB Party 2025 - Official Ticket', 0, 1, 'C');

// Add ticket details
$pdf->SetFont('helvetica', '', 12);
$pdf->Ln(10);
$pdf->Cell(0, 10, 'Ticket Type: ' . $title, 0, 1);
$pdf->Cell(0, 10, 'Ticket Key: ' . $votekey, 0, 1);
$pdf->Cell(0, 10, 'Security Salt: ' . $salt, 0, 1);

// Add QR Code (auto-generated)
$style = ['border' => 0, 'padding' => 4, 'fgcolor' => [0,0,0]];
$pdf->write2DBarcode($votekey . '|' . $salt, 'QRCODE,H', 150, 60, 40, 40, $style);
        </code></pre>

        <p>
            In this example, the PDF will include a logo, a bold title, ticket details,
            and a QR code containing the votekey + salt for validation.
        </p>
    <h2>About the Ticket System</h2>
    <p>
        This system uses <strong>FPDF</strong> and <strong>PHP QR Code</strong> and <strong>Html5-QRCode</strong> to generate PDF tickets and handle validations.
    </p>
    <p>
        Libraries used:
        <a href="https://www.fpdf.org/" target="_blank">FPDF</a> |
        <a href="https://sourceforge.net/projects/phpqrcode/" target="_blank">PHP QR Code</a> |
        <a href="https://github.com/mebjas/html5-qrcode" target="_blank">Html5-QRCode</a>
    </p>
    </div>
    <?php

    // Test PDF preview generation
    if (isset($_POST['pof_ticket_test_preview']) && $_POST['pof_ticket_test_preview']==1) {
        $votekey = wp_generate_password(10,false,false);
        $salt = wp_generate_password(8,false,false);
        $pdf_file = generate_ticket_pdf($votekey, $salt, 'Test Ticket');
        header('Content-Type: application/pdf');
        readfile($pdf_file);
        unlink($pdf_file);
        exit;
    }
}
// Admin test PDF generation
add_action('admin_post_pof_ticket_test_pdf', function() {
    if (!current_user_can('manage_options')) wp_die('No permission');

    $votekey = wp_generate_password(10,false,false);
    $salt = wp_generate_password(8,false,false);
    $pdf_file = generate_ticket_pdf($votekey, $salt, 'Test Ticket');

    if(file_exists($pdf_file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="test_ticket.pdf"');
        readfile($pdf_file);
        unlink($pdf_file);
        exit;
    } else {
        wp_die('PDF generation failed');
    }
});

add_action('admin_init', function () {
    // If WooCommerce is not active, display a warning
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning is-dismissible">
                <p><strong>WooCommerce inactive!</strong> The ticket sales feature will not be available.</p>
            </div>';
        });
    }
});

/*QR validation*/

// ===== Create Admin role =====
register_activation_hook(__FILE__, function(){
    add_role('ticket_checker', 'Ticket Checker', ['read'=>true]);
});

// ===== AJAX callback for ticket verification =====
add_action('wp_ajax_feryx_verify_ticket', 'feryx_ajax_verify_ticket');
function feryx_ajax_verify_ticket() {
    if (!current_user_can('ticket_checker') && !current_user_can('manage_options')) wp_die('No permission');
    if (empty($_POST['ticket_code'])) wp_die('No ticket code');

    global $wpdb;
    $table = $wpdb->prefix . "_Feryx_votekeys";

    $code = sanitize_text_field($_POST['ticket_code']);
    if(strpos($code,'|')!==false){
        list($votekey,$salt) = explode('|',$code);
    } else {
        $votekey = $code;
        $salt = '';
    }

    // verification votekey + salt
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE votekey=%s AND (%s='' OR token=%s)",
        $votekey, $salt, $salt
    ));

    if($row){
        // generate a new token so the ticket cannot be validated multiple times
        $new_salt = wp_generate_password(8,false,false);
        $wpdb->update($table,['token'=>$new_salt],['id'=>$row->id]);

        //echo '<span style="color:green;font-weight:bold;font-size:32px;">Valid Ticket</span>';
		echo 'Valid Ticket';
        //error_log("DEBUG: Ticket verified: $votekey, token updated");
    } else {
		echo 'Invalid/Used Ticket';

        //echo '<span style="color:red;font-weight:bold;font-size:32px;">Invalid Ticket</span>';
        //error_log("DEBUG: Ticket verification failed: $votekey");
    }

    wp_die();
}

// ===== Create WooCommerce My Account endpoint =====
add_action('init', function() {
    if (!function_exists('is_woocommerce')) return;
    add_rewrite_endpoint('tickets', EP_ROOT | EP_PAGES);
});

// ===== Endpoint content with QR scanner =====
add_action('woocommerce_account_tickets_endpoint', function() {
    if (!current_user_can('ticket_checker') && !current_user_can('manage_options')) {
        echo '<p>You do not have permission to check tickets.</p>';
        return;
    }
    ?>
    <h2>Tickets</h2>
    <form method="post" id="ticket-checker-form">
        <input type="text" name="ticket_code" placeholder="Scan or enter votekey" style="width:300px;">
        <button type="submit" class="button button-primary">Verify</button>
    </form>

    <h3>QR Scanner</h3>
    <div id="qr-reader" style="width:300px;"></div>
    <div id="ticket-check-result" style="margin-top:10px;"></div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    jQuery(document).ready(function($){
        let scanning = true;

        function verifyTicket(code){
    if(!scanning) return;
    scanning = false;

    $.post('<?php echo admin_url('admin-ajax.php'); ?>',
        {action:'feryx_verify_ticket', ticket_code:code},
        function(response){
            // create a full-screen overlay
            let overlay = $('<div></div>');
            overlay.css({
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                display: 'flex',
                'justify-content': 'center',
                'align-items': 'center',
                'font-size': '64px',
                'font-weight': 'bold',
                color: '#fff',
                'z-index': 9999,
                'text-align': 'center',
                'padding': '20px',
                'box-sizing': 'border-box',
                'background-color': response.includes('Valid') ? 'rgba(0,128,0,0.9)' : 'rgba(255,0,0,0.9)'
            });
            overlay.text(response);

            $('body').append(overlay);

            setTimeout(() => {
                overlay.remove();
                scanning = true;
            }, 5000);
        });
}


        $('#ticket-checker-form').on('submit', function(e){
            e.preventDefault();
            var code = $('input[name=ticket_code]').val();
            verifyTicket(code);
        });

        const html5QrCode = new Html5Qrcode("qr-reader");
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (decodedText, decodedResult) => { verifyTicket(decodedText); },
            (errorMessage) => {}
        ).catch(err => { console.error(err); });
    });
    </script>
    <?php
});

// ===== Add menu to the WooCommerce My Account menu =====
add_filter('woocommerce_account_menu_items', function($items) {
    if ( current_user_can('ticket_checker') || current_user_can('manage_options') ) {
        $items['tickets'] = 'Tickets';
    }
    return $items;
});