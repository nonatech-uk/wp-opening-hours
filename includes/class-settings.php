<?php
if (!defined('ABSPATH')) {
    exit;
}

class Opening_Hours_Settings {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_menu() {
        add_options_page(
            'Opening Hours',
            'Opening Hours',
            'manage_options',
            'opening-hours',
            array($this, 'render_page')
        );
    }

    public function register_settings() {
        register_setting('opening_hours_settings', 'opening_hours_api_key');
        register_setting('opening_hours_settings', 'opening_hours_place_id');
        register_setting('opening_hours_settings', 'opening_hours_cache_duration', array(
            'type' => 'integer',
            'default' => 24,
            'sanitize_callback' => 'absint'
        ));

        add_settings_section(
            'opening_hours_main',
            'Google Places API Settings',
            null,
            'opening-hours'
        );

        add_settings_field(
            'opening_hours_api_key',
            'Google API Key',
            array($this, 'render_api_key_field'),
            'opening-hours',
            'opening_hours_main'
        );

        add_settings_field(
            'opening_hours_place_id',
            'Google Place ID',
            array($this, 'render_place_id_field'),
            'opening-hours',
            'opening_hours_main'
        );

        add_settings_field(
            'opening_hours_cache_duration',
            'Cache Duration (hours)',
            array($this, 'render_cache_field'),
            'opening-hours',
            'opening_hours_main'
        );
    }

    public function render_api_key_field() {
        $value = get_option('opening_hours_api_key', '');
        echo '<input type="password" name="opening_hours_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Google Places API key</p>';
    }

    public function render_place_id_field() {
        $value = get_option('opening_hours_place_id', '');
        echo '<input type="text" name="opening_hours_place_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Find your Place ID at <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank">Google Place ID Finder</a></p>';
    }

    public function render_cache_field() {
        $value = get_option('opening_hours_cache_duration', 24);
        echo '<input type="number" name="opening_hours_cache_duration" value="' . esc_attr($value) . '" min="1" max="168" class="small-text" />';
        echo '<p class="description">How long to cache the opening hours (1-168 hours)</p>';
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            delete_transient('opening_hours_data');
            add_settings_error('opening_hours', 'settings_updated', 'Settings saved. Cache cleared.', 'success');
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('opening_hours'); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('opening_hours_settings');
                do_settings_sections('opening-hours');
                submit_button();
                ?>
            </form>
            <hr>
            <h2>Usage</h2>
            <p>Use the shortcode <code>[opening_hours]</code> to display your business hours.</p>
            <h3>Preview</h3>
            <?php echo do_shortcode('[opening_hours]'); ?>
        </div>
        <?php
    }
}
