<?php

if( !defined( 'ABSPATH' ) && !defined('WP_UNINSTALL_PLUGIN') ) {
	exit();
}
delete_option('aveJana_settings');
global $wpdb;
$wpdb->query("ALTER TABLE " . $wpdb->prefix . "posts DROP `is_uploaded_to_aveJana`");