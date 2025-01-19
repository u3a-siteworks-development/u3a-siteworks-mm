<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbols

/*
Plugin Name: u3a SiteWorks Maintenance Mode
Description: Displays a maintenance page for site visitors when activated.
Version: 1.1.0
Author: u3a SiteWorks team
Author URI: https://siteworks.u3a.org.uk/
Plugin URI: https://siteworks.u3a.org.uk/
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

// Use the plugin update service on SiteWorks update server

require 'inc/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$u3aMmUpdateChecker = PucFactory::buildUpdateChecker(
    'https://siteworks.u3a.org.uk/wp-update-server/?action=get_metadata&slug=u3a-siteworks-mm', //Metadata URL
    __FILE__, //Full path to the main plugin file or functions.php.
    'u3a-siteworks-mm'
);
$default_u3a_mm_msg = 'This website is not currently open for public access';
// Show "Maintenance Mode" page unless user is logged in as Author, Editor or Administrator
// Reads options to retrieve maintenance message and custom image (if selected)
// Returns HTTP Status Code 503 - Service Unavailable

add_action('after_setup_theme', 'u3a_wp_maintenance_mode');
function u3a_wp_maintenance_mode()
{
    global $default_u3a_mm_msg;
// Skip if option not set
    if (get_option('u3a_maintenance_active', '9') == '9') {
        return;
    }

    // Skip if we're trying to log in
    $url = $_SERVER['REQUEST_URI'];
    foreach (['wp-admin', 'login'] as $slug) {
        if (strpos($url, $slug)) {
            return;
        }
    }

    // Show maintenance page if not logged in as Author, Editor or Administrator
    $user = wp_get_current_user();
    $allowed_roles = array('editor', 'administrator', 'author');
    if (!array_intersect($allowed_roles, $user->roles)) {
        $maint_msg = get_option('u3a_maintenance_msg', $default_u3a_mm_msg);
        $custom_image = get_option('u3a_maintenance_image', 'default');
        if (is_numeric($custom_image)) {
            $background = wp_get_attachment_image_url($custom_image, 'large');
            if ($background == false) {
                $background = plugin_dir_url(__FILE__) . 'images/background.jpg';
            }
        } else {
            $background = plugin_dir_url(__FILE__) . 'images/background.jpg';
        }
        $html = <<< END
        <div style="position:absolute; top:0px; right:0px; bottom:0px; left:0px;
        background-image: url('$background');   background-size:cover;
        text-align: center;">
        <p style="padding: 0 10px; color: white; font-size: 1.9rem; text-shadow: 5px 5px 5px #000">
        <strong>$maint_msg</strong></p>
        </div>
        END;
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- source trusted
        wp_die($html, 'Temporarily Unavailable', array('response' => '503'));
    }
}


// Add the Settings menu to the dashboard.  Currently added as sub-menu on the regular Tools menu.
// If menu location changes, alter u3a_mm_settings_link() and
// u3a_mm_admin_notice() and add_action('admin_enqueue_scripts' to match

add_action('admin_menu', 'u3a_mm_settings_menu');
function u3a_mm_settings_menu()
{

    add_submenu_page(
        'u3a-settings',
        'Maintenance Mode',
        'Maintenance Mode',
        'manage_options',
        'u3a-mm-settings',
        'u3a_mm_settings_cb'
    );
}

// Add a link to the settings page to the plugin entry on the plugins page

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'u3a_mm_settings_link');
function u3a_mm_settings_link($links)
{


    // Add the link to the end of the current array
    $page = admin_url('admin.php?page=u3a-mm-settings');
    $links[] = "<a href='$page'>Settings</a>";
    return $links;
}


// Generate the Maintenance Mode Settings page
// Note.  Perhaps the JavaScipt should be separate file and enqueued rather than inline.

function u3a_mm_settings_cb()
{

    global $default_u3a_mm_msg;
// Set/retrieve form settings

    $nonce_code =  wp_nonce_field('u3a_settings', 'u3a_nonce', true, false);
    $submit_button = get_submit_button('Save Settings');
    $u3aMQDetect = "<input type=\"hidden\" name=\"u3aMQDetect\" value=\"test'\">\n";
    $maint_msg = get_option('u3a_maintenance_msg', $default_u3a_mm_msg);
    $maint_active = get_option('u3a_maintenance_active', '9');
    $maint_active_chk = ($maint_active == '1') ? ' checked' : '';
    $maint_reminder = get_option('u3a_maintenance_reminder', '1');
    $maint_reminder_chk = ($maint_reminder == '1') ? ' checked' : '';
// Use the default image unless a custom image has been set and is available
    $custom_image = get_option('u3a_maintenance_image', 'default');
    $default_image = plugin_dir_url(__FILE__) . 'images/background.jpg';
    if (is_numeric($custom_image)) {
        $background = wp_get_attachment_image_url($custom_image, 'thumbnail');
        if ($background == false) {
            $background = $default_image;
        }
    } else {
        $background = $default_image;
    }

    // Check if there is a status returned from a save

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- any value other than 1 is ignored
    $status = isset($_GET['status']) ? $_GET['status'] : "";
    $status_text = '';
    if ($status == "1") {
        $status_text = '<div class="notice notice-error is-dismissible inline"><p>Changes Saved</p></div>';
    }

    // Generate the Maintenance Mode Settings page

// phpcs:disable WordPress.Security.EscapeOutput.HeredocOutputNotEscaped -- all variables from trusted sources
    print <<< END
    
<div class="wrap"><div id="icon-tools" class="icon32"></div>
$status_text
<h2>Website Maintenance Page Settings</h2>
<form method="POST" action="admin-post.php">
<input type="hidden" name="action" value="u3a_mm_settings">
$nonce_code
$u3aMQDetect

<h3>Enable Maintenance Mode</h3>
<p>When ticked, only Administrators, Editors and Authors who are logged in 
will be able to see the website.<br>Other visitors to the site will just see a 
maintenance page showing the message given below.</p>
<label for="maint_active"> Enable Maintenance Mode</label>
<input type="checkbox" id="maint_active" name="maint_active" value="1" $maint_active_chk>
<br>
<label for="maint_reminder"> Show a reminder on admin pages when active</label>
<input type="checkbox" id="maint_reminder" name="maint_reminder" value="1" $maint_reminder_chk>


<h3>Maintenance Mode Text</h3>
<p><label for="maint_msg">This is the message that will be displayed to visitors to the site that are not logged in as 
an Administrator, Editor or Author.</label></p>
<input style = "width: 100%;" type="text" id="maint_msg" name="maint_msg" value="$maint_msg">

<h3>Maintenance Mode Image</h3>

<p>
<img id="image_src" src="$background" style="vertical-align: middle; 
object-fit: cover; height: 150px; width: 150px; border: 2px solid gray; padding: 5px;">
<input type="text" value="$custom_image" id="custom_image" name="custom_image" readonly size="5" style="display:none;" >
<button class="set_custom_images button">Select Image</button>
<button class="button" onClick="document.getElementById('custom_image').value = 'default'; 
    document.getElementById('image_src').attr('src', '');
    return false;">Use Default Image</button>
</p>

$submit_button
</form>

</div>
<script>
jQuery(document).ready(function() {
    var $ = jQuery;
    if ($('.set_custom_images').length > 0) {
        if ( typeof wp !== 'undefined' && wp.media && wp.media.editor) {
            $('.set_custom_images').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var id = button.prev();
                wp.media.editor.send.attachment = function(props, attachment) {
                    id.val(attachment.id);
                    attachmentURL = wp.media.attachment(attachment.id).get("url");
                    $("#image_src").attr("src", attachmentURL);
                };
                wp.media.editor.open(button);
                return false;
            });
        }
    }
});
</script>

END;
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
}


// Enqueues all scripts, styles, settings, and templates necessary to use the WordPress JavaScript media APIs.

add_action('admin_enqueue_scripts', function () {

    // Skip if we're not on the plugin settings page
    $screen = get_current_screen();
    if ('u3a-settings_page_u3a-mm-settings' != $screen->id) {
        return;
    }
    wp_enqueue_media();
});
// Add function to process the Maintenance Page Settings form submission

add_action('admin_post_u3a_mm_settings', 'u3a_mm_save_settings');
function u3a_mm_save_settings()
{
    global $default_u3a_mm_msg;
// check nonce
    if (check_admin_referer('u3a_settings', 'u3a_nonce') == false) {
        wp_die('Invalid form submission');
    }
    // verify admin user
    if (!current_user_can('manage_options')) {
        wp_die('Invalid form submission');
    }

    // check for WP magic quotes
    $u3aMQDetect = $_POST['u3aMQDetect'];
    $needStripSlashes = (strlen($u3aMQDetect) > 5) ? true : false;
// backslash added to apostrophe in test string?

    $maint_msg = $needStripSlashes ? stripslashes($_POST['maint_msg']) : $_POST['maint_msg'];
    $maint_msg = sanitize_text_field($maint_msg);
    if (empty($maint_msg)) {
        update_option('u3a_maintenance_msg', $default_u3a_mm_msg);
    } else {
        update_option('u3a_maintenance_msg', $maint_msg);
    }

    $maint_active = isset($_POST['maint_active']) ? '1' : '9';
    update_option('u3a_maintenance_active', $maint_active);
    $maint_reminder = isset($_POST['maint_reminder']) ? '1' : '9';
    update_option('u3a_maintenance_reminder', $maint_reminder);
    $custom_image =  isset($_POST['custom_image']) ? trim($_POST['custom_image']) : 'default';
    if ($custom_image != 'default') {
        $custom_image = absint($custom_image);
    }
    update_option('u3a_maintenance_image', $custom_image);
// redirect back to u3a mm settings page with status set to success (1)
    wp_safe_redirect(admin_url('admin.php?page=u3a-mm-settings&status=1'));
    exit;
}


// Add a reminder message to all admin pages when Maintenance Mode is active
// unless the setting to suppress the reminder is selected

add_action('admin_notices', 'u3a_mm_admin_notice');
function u3a_mm_admin_notice()
{
    // Skip if option not set or we don't want reminders
    if (get_option('u3a_maintenance_active', '9') == '9') {
        return;
    }
    if (get_option('u3a_maintenance_reminder', '9') == '9') {
        return;
    }

    // SKip if we're already on the settings page
    $screen = get_current_screen();
    if ('u3a-settings_page_u3a-mm-settings' == $screen->id) {
        return;
    }

    $url = admin_url('admin.php?page=u3a-mm-settings');
    print <<< END
    <div class="notice notice-warning is-dismissible">
        <p>Maintenance Mode is active. <a href="$url">Settings</a></p>
    </div>
    END;
}
