<?php

// exit if uninstall constant is not defined

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove the option fields used by the maintenance mode plugin

delete_option('u3a_maintenance_active');
delete_option('u3a_maintenance_reminder');
delete_option('u3a_maintenance_msg');
delete_option('u3a_maintenance_image');
