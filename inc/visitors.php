<?php
// ====== REGISTRATION ENHANCEMENTS ======

// Extra fields for the registration form
function po_register_form_extra_fields() {
    ?>
    <p>
        <label for="group_name"><?php _e('Group name', 'party-organizer'); ?><br />
            <input type="text" name="group_name" id="group_name" class="input" value="<?php echo esc_attr(wp_unslash($_POST['group_name'] ?? '')); ?>" size="25" />
        </label>
    </p>
    <p>
        <label for="nickname"><?php _e('Nick Name', 'party-organizer'); ?><br />
            <input type="text" name="nickname" id="nickname" class="input" value="<?php echo esc_attr(wp_unslash($_POST['nickname'] ?? '')); ?>" size="25" />
        </label>
    </p>
    <p>
        <label for="votekey"><?php _e('Vote Key (optional)', 'party-organizer'); ?><br />
            <input type="text" name="votekey" id="votekey" class="input" value="<?php echo esc_attr(wp_unslash($_POST['votekey'] ?? '')); ?>" size="25" />
        </label>
    </p>
    <?php
}
add_action('register_form', 'po_register_form_extra_fields');

// Validation
function po_validate_extra_fields($errors, $sanitized_user_login, $user_email) {
    global $wpdb;
    $votekey = sanitize_text_field($_POST['votekey'] ?? '');

    if (empty($_POST['group_name'])) {
        $errors->add('group_name_error', __('<strong>ERROR</strong>: Please enter your Group name.', 'party-organizer'));
    }
    if (empty($_POST['nickname'])) {
        $errors->add('nickname_error', __('<strong>ERROR</strong>: Please enter your Nick Name.', 'party-organizer'));
    }

    if (!empty($votekey)) {
        $votekey_table = $wpdb->prefix . '_Feryx_votekeys';
        $row = $wpdb->get_row($wpdb->prepare("SELECT used FROM $votekey_table WHERE votekey = %s", $votekey));

        if (!$row) {
            $errors->add('votekey_invalid', __('<strong>ERROR</strong>: Invalid Vote Key.', 'party-organizer'));
        } elseif ((int)$row->used === 1) {
            $errors->add('votekey_used', __('<strong>ERROR</strong>: This Vote Key has already been used.', 'party-organizer'));
        }
    }

    return $errors;
}
add_filter('registration_errors', 'po_validate_extra_fields', 10, 3);

// Save after registration
function po_save_extra_user_meta($user_id) {
    global $wpdb;

    if (!empty($_POST['group_name'])) {
        update_user_meta($user_id, 'group_name', sanitize_text_field($_POST['group_name']));
    }
    if (!empty($_POST['nickname'])) {
        update_user_meta($user_id, 'nickname', sanitize_text_field($_POST['nickname']));
        wp_update_user([
            'ID' => $user_id,
            'user_nicename' => sanitize_text_field($_POST['nickname'])
        ]);
    }

    if (!empty($_POST['votekey'])) {
        $votekey = sanitize_text_field($_POST['votekey']);
        $votekey_table = $wpdb->prefix . '_Feryx_votekeys';
        $row = $wpdb->get_row($wpdb->prepare("SELECT used FROM $votekey_table WHERE votekey = %s", $votekey));

        if ($row && (int)$row->used === 0) {
            // Mark key as used
            $wpdb->update(
                $votekey_table,
                ['user_id' => $user_id, 'used' => 1],
                ['votekey' => $votekey]
            );

            update_user_meta($user_id, 'votekey', $votekey);

            // Set visitor role
            $user = new WP_User($user_id);
            $user->set_role('visitor');
        }
    }
}
add_action('user_register', 'po_save_extra_user_meta');


// ====== MY ACCOUNT PAGE ENHANCEMENTS ======

// Add new field to My Account page
function po_myaccount_votekey_field() {
    $user_id = get_current_user_id();
    $votekey = get_user_meta($user_id, 'votekey', true);
    ?>
    <p class="form-row form-row-wide">
        <label for="votekey"><?php _e('Vote Key (if available)', 'party-organizer'); ?></label>
        <input type="text" name="votekey" id="votekey" value="<?php echo esc_attr($votekey); ?>" />
    </p>
    <?php
}
add_action('woocommerce_edit_account_form', 'po_myaccount_votekey_field');

// Save on My Account page
function po_save_myaccount_votekey($user_id) {
    if (isset($_POST['votekey'])) {
        global $wpdb;
        $votekey = sanitize_text_field($_POST['votekey']);
        if (!empty($votekey)) {
            $votekey_table = $wpdb->prefix . '_Feryx_votekeys';
            $row = $wpdb->get_row($wpdb->prepare("SELECT used FROM $votekey_table WHERE votekey = %s", $votekey));

            if (!$row) {
                wc_add_notice(__('Invalid Vote Key.', 'party-organizer'), 'error');
                return;
            }

            if ((int)$row->used === 1) {
                wc_add_notice(__('This Vote Key has already been used.', 'party-organizer'), 'error');
                return;
            }

            // Mark key as used
            $wpdb->update(
                $votekey_table,
                ['user_id' => $user_id, 'used' => 1],
                ['votekey' => $votekey]
            );

            update_user_meta($user_id, 'votekey', $votekey);

            // Set visitor role
            $user = new WP_User($user_id);
            $user->set_role('visitor');

            wc_add_notice(__('Successfully added the Vote Key! You now have the Visitor role.', 'party-organizer'), 'success');
        }
    }
}
add_action('woocommerce_save_account_details', 'po_save_myaccount_votekey', 10, 1);



// ====== VOTEKEY FIELD AFTER NICKNAME ======
function po_user_profile_votekey_field_after_nickname($user) {
    $votekey = get_user_meta($user->ID, 'votekey', true);
    ?>
    <h2><?php _e('Vote Key', 'party-organizer'); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="votekey"><?php _e('Vote Key', 'party-organizer'); ?></label></th>
            <td>
                <input type="text" name="votekey" id="votekey"
                       value="<?php echo esc_attr($votekey); ?>"
                       class="regular-text" /><br/>
                <span class="description"><?php _e('Enter a valid Vote Key if you have one.', 'party-organizer'); ?></span>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'po_user_profile_votekey_field_after_nickname');
add_action('edit_user_profile', 'po_user_profile_votekey_field_after_nickname');

// ====== SAVE PROFILE FIELD ======
function po_save_user_profile_votekey($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }

    if (isset($_POST['votekey'])) {
        global $wpdb;
        $votekey = sanitize_text_field($_POST['votekey']);

        if (!empty($votekey)) {
            $votekey_table = $wpdb->prefix . '_Feryx_votekeys';
            $row = $wpdb->get_row($wpdb->prepare("SELECT used FROM $votekey_table WHERE votekey = %s", $votekey));

            if (!$row) {
                add_action('user_profile_update_errors', function($errors) {
                    $errors->add('votekey_invalid', __('Invalid Vote Key.', 'party-organizer'));
                });
                return;
            }

            if ((int)$row->used === 1) {
                add_action('user_profile_update_errors', function($errors) {
                    $errors->add('votekey_used', __('This Vote Key has already been used.', 'party-organizer'));
                });
                return;
            }

            // Mark key as used
            $wpdb->update(
                $votekey_table,
                ['user_id' => $user_id, 'used' => 1],
                ['votekey' => $votekey]
            );

            update_user_meta($user_id, 'votekey', $votekey);

            // Switch role to visitor
            $user = new WP_User($user_id);
            $user->set_role('visitor');
        }
    }
}
add_action('personal_options_update', 'po_save_user_profile_votekey');
add_action('edit_user_profile_update', 'po_save_user_profile_votekey');