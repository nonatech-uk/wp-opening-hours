<?php
if (!defined('ABSPATH')) {
    exit;
}

class Opening_Hours_Google_Places {

    private $api_key;
    private $place_id;
    private $cache_duration;

    public function __construct() {
        $this->api_key = get_option('opening_hours_api_key', '');
        $this->place_id = get_option('opening_hours_place_id', '');
        $this->cache_duration = (int) get_option('opening_hours_cache_duration', 24);
    }

    public function get_opening_hours() {
        if (empty($this->api_key) || empty($this->place_id)) {
            return new WP_Error('missing_config', 'API Key or Place ID not configured');
        }

        $cached = get_transient('opening_hours_data');
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->fetch_from_api();
        if (is_wp_error($data)) {
            return $data;
        }

        set_transient('opening_hours_data', $data, $this->cache_duration * HOUR_IN_SECONDS);
        return $data;
    }

    private function fetch_from_api() {
        $url = add_query_arg(array(
            'place_id' => $this->place_id,
            'fields' => 'opening_hours',
            'key' => $this->api_key
        ), 'https://maps.googleapis.com/maps/api/place/details/json');

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || $data['status'] !== 'OK') {
            $error_msg = isset($data['error_message']) ? $data['error_message'] : 'Unknown API error';
            return new WP_Error('api_error', $error_msg);
        }

        if (!isset($data['result']['opening_hours'])) {
            return new WP_Error('no_hours', 'No opening hours found for this place');
        }

        return $this->parse_opening_hours($data['result']['opening_hours']);
    }

    private function parse_opening_hours($hours_data) {
        $result = array(
            'open_now' => isset($hours_data['open_now']) ? $hours_data['open_now'] : null,
            'periods' => array(),
            'weekday_text' => isset($hours_data['weekday_text']) ? $hours_data['weekday_text'] : array()
        );

        if (isset($hours_data['periods'])) {
            foreach ($hours_data['periods'] as $period) {
                $result['periods'][] = array(
                    'open' => array(
                        'day' => $period['open']['day'],
                        'time' => $period['open']['time']
                    ),
                    'close' => isset($period['close']) ? array(
                        'day' => $period['close']['day'],
                        'time' => $period['close']['time']
                    ) : null
                );
            }
        }

        return $result;
    }

    public function get_current_status($hours_data) {
        if (is_wp_error($hours_data)) {
            return array(
                'is_open' => false,
                'message' => $hours_data->get_error_message()
            );
        }

        $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        $current_day = (int) current_time('w');
        $current_time = current_time('Hi');

        $today_hours = $this->get_today_hours($hours_data, $current_day);
        $is_open = $this->check_if_open($hours_data['periods'], $current_day, $current_time);

        if ($is_open) {
            $closing_time = $this->get_closing_time($hours_data['periods'], $current_day, $current_time);
            $next_open = $this->get_next_opening_after_close($hours_data['periods'], $current_day, $closing_time);
            $message = 'Open now - Closes at ' . $this->format_time($closing_time);
            if ($next_open) {
                $day_name = ($next_open['day'] === $current_day) ? 'today' : $days[$next_open['day']];
                $message .= ', open again ' . $day_name . ' at ' . $this->format_time($next_open['time']);
            }
            return array(
                'is_open' => true,
                'today_hours' => $today_hours,
                'message' => $message
            );
        } else {
            $next_open = $this->get_next_opening($hours_data['periods'], $current_day, $current_time);
            if ($next_open) {
                $day_name = ($next_open['day'] === $current_day) ? 'today' : $days[$next_open['day']];
                return array(
                    'is_open' => false,
                    'today_hours' => $today_hours,
                    'message' => 'Closed now - Open again ' . $day_name . ' at ' . $this->format_time($next_open['time'])
                );
            }
            return array(
                'is_open' => false,
                'today_hours' => $today_hours,
                'message' => 'Closed'
            );
        }
    }

    private function get_today_hours($hours_data, $current_day) {
        if (!empty($hours_data['weekday_text'])) {
            $index = ($current_day + 6) % 7;
            if (isset($hours_data['weekday_text'][$index])) {
                return $hours_data['weekday_text'][$index];
            }
        }
        return null;
    }

    private function check_if_open($periods, $current_day, $current_time) {
        foreach ($periods as $period) {
            if ($period['close'] === null) {
                return true;
            }

            $open_day = $period['open']['day'];
            $close_day = $period['close']['day'];
            $open_time = $period['open']['time'];
            $close_time = $period['close']['time'];

            if ($open_day === $close_day && $open_day === $current_day) {
                if ($current_time >= $open_time && $current_time < $close_time) {
                    return true;
                }
            } elseif ($open_day !== $close_day) {
                if ($current_day === $open_day && $current_time >= $open_time) {
                    return true;
                }
                if ($current_day === $close_day && $current_time < $close_time) {
                    return true;
                }
            }
        }
        return false;
    }

    private function get_closing_time($periods, $current_day, $current_time) {
        foreach ($periods as $period) {
            if ($period['close'] === null) {
                return null;
            }

            $open_day = $period['open']['day'];
            $close_day = $period['close']['day'];
            $open_time = $period['open']['time'];
            $close_time = $period['close']['time'];

            if ($open_day === $close_day && $open_day === $current_day) {
                if ($current_time >= $open_time && $current_time < $close_time) {
                    return $close_time;
                }
            } elseif ($open_day !== $close_day) {
                if ($current_day === $open_day && $current_time >= $open_time) {
                    return $close_time;
                }
                if ($current_day === $close_day && $current_time < $close_time) {
                    return $close_time;
                }
            }
        }
        return null;
    }

    private function get_next_opening($periods, $current_day, $current_time) {
        $candidates = array();

        foreach ($periods as $period) {
            $open_day = $period['open']['day'];
            $open_time = $period['open']['time'];

            if ($open_day === $current_day && $open_time > $current_time) {
                $candidates[] = array('day' => $open_day, 'time' => $open_time, 'days_ahead' => 0);
            } elseif ($open_day > $current_day) {
                $candidates[] = array('day' => $open_day, 'time' => $open_time, 'days_ahead' => $open_day - $current_day);
            } else {
                $candidates[] = array('day' => $open_day, 'time' => $open_time, 'days_ahead' => 7 - $current_day + $open_day);
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function($a, $b) {
            if ($a['days_ahead'] !== $b['days_ahead']) {
                return $a['days_ahead'] - $b['days_ahead'];
            }
            return strcmp($a['time'], $b['time']);
        });

        return $candidates[0];
    }

    private function get_next_opening_after_close($periods, $close_day, $close_time) {
        $candidates = array();

        foreach ($periods as $period) {
            $open_day = $period['open']['day'];
            $open_time = $period['open']['time'];

            if ($open_day === $close_day && $open_time >= $close_time) {
                $candidates[] = array('day' => $open_day, 'time' => $open_time, 'days_ahead' => 0);
            } elseif ($open_day > $close_day) {
                $candidates[] = array('day' => $open_day, 'time' => $open_time, 'days_ahead' => $open_day - $close_day);
            } else {
                $candidates[] = array('day' => $open_day, 'time' => $open_time, 'days_ahead' => 7 - $close_day + $open_day);
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function($a, $b) {
            if ($a['days_ahead'] !== $b['days_ahead']) {
                return $a['days_ahead'] - $b['days_ahead'];
            }
            return strcmp($a['time'], $b['time']);
        });

        return $candidates[0];
    }

    private function format_time($time) {
        if (empty($time)) {
            return '';
        }
        $hour = (int) substr($time, 0, 2);
        $minute = substr($time, 2, 2);
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour = $hour % 12;
        if ($hour === 0) $hour = 12;
        return $hour . ':' . $minute . ' ' . $ampm;
    }
}
