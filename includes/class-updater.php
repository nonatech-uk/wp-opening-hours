<?php
/**
 * GitHub auto-updater for the Opening Hours plugin.
 *
 * Checks the GitHub releases API for new versions and integrates
 * with the WordPress plugin update system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Opening_Hours_Updater {

    private $plugin_file;
    private $plugin_slug;
    private $github_repo;
    private $transient_key = 'opening_hours_github_release';
    private $cache_duration = 43200; // 12 hours in seconds

    public function __construct($plugin_file) {
        $this->plugin_file  = $plugin_file;
        $this->plugin_slug  = plugin_basename($plugin_file);
        $this->github_repo  = 'nonatech-uk/opening-hours';

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_action('upgrader_process_complete', array($this, 'clear_cache'), 10, 2);
    }

    /**
     * Fetch the latest release data from GitHub, with caching.
     */
    private function get_github_release() {
        $cached = get_transient($this->transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $url      = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $release = json_decode(wp_remote_retrieve_body($response));
        if (empty($release) || empty($release->tag_name)) {
            return false;
        }

        set_transient($this->transient_key, $release, $this->cache_duration);

        return $release;
    }

    /**
     * Parse a version string from a GitHub tag name (strip leading "v" if present).
     */
    private function parse_version($tag) {
        return ltrim($tag, 'v');
    }

    /**
     * Get the download URL for the release zip.
     */
    private function get_download_url($release) {
        return "https://github.com/{$this->github_repo}/archive/refs/tags/{$release->tag_name}.zip";
    }

    /**
     * Check GitHub for a newer release and inject it into the update transient.
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $transient;
        }

        $current_version = isset($transient->checked[$this->plugin_slug])
            ? $transient->checked[$this->plugin_slug]
            : OPENING_HOURS_VERSION;

        $latest_version = $this->parse_version($release->tag_name);

        if (version_compare($latest_version, $current_version, '>')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug'        => dirname($this->plugin_slug),
                'plugin'      => $this->plugin_slug,
                'new_version' => $latest_version,
                'url'         => "https://github.com/{$this->github_repo}",
                'package'     => $this->get_download_url($release),
            );
        }

        return $transient;
    }

    /**
     * Provide plugin information for the update details modal.
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $result;
        }

        $plugin_data = get_plugin_data($this->plugin_file);

        $info              = new stdClass();
        $info->name        = $plugin_data['Name'];
        $info->slug        = dirname($this->plugin_slug);
        $info->version     = $this->parse_version($release->tag_name);
        $info->author      = $plugin_data['Author'];
        $info->homepage    = $plugin_data['PluginURI'];
        $info->download_link = $this->get_download_url($release);
        $info->sections    = array(
            'description' => $plugin_data['Description'],
            'changelog'   => nl2br(esc_html($release->body)),
        );

        return $info;
    }

    /**
     * Clear the cached release data after an update completes.
     */
    public function clear_cache($upgrader, $options) {
        if (
            isset($options['action'], $options['type'], $options['plugins']) &&
            $options['action'] === 'update' &&
            $options['type'] === 'plugin' &&
            in_array($this->plugin_slug, $options['plugins'], true)
        ) {
            delete_transient($this->transient_key);
        }
    }
}
