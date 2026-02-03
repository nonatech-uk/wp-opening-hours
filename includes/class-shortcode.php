<?php
if (!defined('ABSPATH')) {
    exit;
}

class Opening_Hours_Shortcode {

    public function __construct() {
        add_shortcode('opening_hours', array($this, 'render'));
    }

    public function render($atts) {
        $atts = shortcode_atts(array(
            'class' => ''
        ), $atts, 'opening_hours');

        $google_places = new Opening_Hours_Google_Places();
        $hours_data = $google_places->get_opening_hours();
        $status = $google_places->get_current_status($hours_data);

        $class = 'opening-hours';
        if (!empty($atts['class'])) {
            $class .= ' ' . esc_attr($atts['class']);
        }

        $status_class = $status['is_open'] ? 'opening-hours--open' : 'opening-hours--closed';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class . ' ' . $status_class); ?>">
            <div class="opening-hours__status">
                <span class="opening-hours__indicator"></span>
                <span class="opening-hours__message"><?php echo esc_html($status['message']); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
