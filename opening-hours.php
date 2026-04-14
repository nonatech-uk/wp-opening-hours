<?php
/**
 * Plugin Name: Opening Hours
 * Plugin URI: https://github.com/nonatech-uk/opening-hours
 * Description: Display business opening hours from Google Places API
 * Version: 1.1.1
 * Author: Stu
 * License: CC BY-NC 4.0
 * Update URI: https://github.com/nonatech-uk/opening-hours
 * Text Domain: opening-hours
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OPENING_HOURS_VERSION', '1.1.1');
define('OPENING_HOURS_PATH', plugin_dir_path(__FILE__));
define('OPENING_HOURS_URL', plugin_dir_url(__FILE__));

require_once OPENING_HOURS_PATH . 'includes/class-settings.php';
require_once OPENING_HOURS_PATH . 'includes/class-google-places.php';
require_once OPENING_HOURS_PATH . 'includes/class-shortcode.php';
require_once OPENING_HOURS_PATH . 'includes/class-updater.php';

function opening_hours_log( $level, $message, $context = [] ) {
    if ( function_exists( 'loki_send_log' ) ) {
        $context['plugin']  = 'opening-hours';
        $context['version'] = OPENING_HOURS_VERSION;
        loki_send_log( $level, $message, $context );
    }
}

class Opening_Hours {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        new Opening_Hours_Settings();
        new Opening_Hours_Shortcode();
        new Opening_Hours_Updater(__FILE__);

        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'opening-hours',
            OPENING_HOURS_URL . 'assets/css/opening-hours.css',
            array(),
            OPENING_HOURS_VERSION
        );
    }

    public static function activate() {
        add_option('opening_hours_api_key', '');
        add_option('opening_hours_place_id', '');
        add_option('opening_hours_cache_duration', 24);
    }

    public static function deactivate() {
        delete_transient('opening_hours_data');
    }

    public static function uninstall() {
        delete_option('opening_hours_api_key');
        delete_option('opening_hours_place_id');
        delete_option('opening_hours_cache_duration');
        delete_transient('opening_hours_data');
    }
}

register_activation_hook(__FILE__, array('Opening_Hours', 'activate'));
register_deactivation_hook(__FILE__, array('Opening_Hours', 'deactivate'));
register_uninstall_hook(__FILE__, array('Opening_Hours', 'uninstall'));

add_action('plugins_loaded', array('Opening_Hours', 'instance'));
