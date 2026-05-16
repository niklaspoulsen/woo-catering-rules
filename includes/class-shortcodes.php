<?php
if (!defined('ABSPATH')) exit;

class WCR_Shortcodes {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_shortcode('wcr_opening_hours', [$this, 'render_opening_hours']);
        add_shortcode('wcr_opening_hours_today', [$this, 'render_opening_hours_today']);
        add_shortcode('wcr_closed_days', [$this, 'render_closed_days']);
    }

    public function assets() {
        wp_enqueue_style('wcr-style', WCR_URL . 'assets/style.css', [], WCR_VERSION);
    }

    private function get_day_labels() {
        return [
            1 => 'mandag',
            2 => 'tirsdag',
            3 => 'onsdag',
            4 => 'torsdag',
            5 => 'fredag',
            6 => 'lørdag',
            0 => 'søndag',
        ];
    }

    private function get_day_labels_display() {
        return [
            1 => 'Mandag',
            2 => 'Tirsdag',
            3 => 'Onsdag',
            4 => 'Torsdag',
            5 => 'Fredag',
            6 => 'Lørdag',
            0 => 'Søndag',
        ];
    }

    private function format_time($time) {
        $time = trim((string) $time);

        if (!$time) {
            return '';
        }

        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $m)) {
            return $time;
        }

        return sprintf('%02d.%02d', (int) $m[1], (int) $m[2]);
    }

    private function format_date_for_frontend($ymd, $show_year = true) {
        $timestamp = strtotime($ymd . ' 12:00:00');
        if (!$timestamp) {
            return $ymd;
        }

        return $show_year ? wp_date('j. F Y', $timestamp) : wp_date('j. F', $timestamp);
    }

    private function get_today_ymd() {
        return current_time('Y-m-d');
    }

    private function get_closed_day_by_date($ymd) {
        $ymd = WCR_Session::date_to_ymd($ymd);
        if (!$ymd) {
            return null;
        }

        $closed_days = WCR_Session::get_closed_days();

        foreach ($closed_days as $day) {
            if (($day['date'] ?? '') === $ymd) {
                return $day;
            }
        }

        return null;
    }

    private function get_closed_day_title($ymd) {
        $day = $this->get_closed_day_by_date($ymd);
        if (!$day) {
            return '';
        }

        return trim((string) ($day['title'] ?? ''));
    }

    /**
     * Used by shortcodes only.
     * IMPORTANT: Ignores "wcr_closed_today".
     */
    private function is_store_closed_for_display($weekday, $ymd = '') {
        if (!$ymd) {
            $ymd = $this->get_today_ymd();
        }

        if ($this->get_closed_day_by_date($ymd)) {
            return true;
        }

        $hours = WCR_Session::get_hours();
        $row   = $hours[$weekday] ?? ['closed' => 'yes', 'open' => '', 'close' => '', 'note' => '', 'pickup_only' => 'no', 'pickup_text' => WCR_Session::get_default_pickup_only_text()];

        return (($row['closed'] ?? 'yes') === 'yes');
    }

    private function get_row_for_day($weekday) {
        $hours = WCR_Session::get_hours();
        return $hours[$weekday] ?? ['closed' => 'yes', 'open' => '', 'close' => '', 'note' => '', 'pickup_only' => 'no', 'pickup_text' => WCR_Session::get_default_pickup_only_text()];
    }

    private function get_row_note($row) {
        $pickup_only = (($row['pickup_only'] ?? 'no') === 'yes');

        if ($pickup_only) {
            $pickup_text = trim((string) ($row['pickup_text'] ?? ''));
            return $pickup_text !== '' ? $pickup_text : WCR_Session::get_default_pickup_only_text();
        }

        return trim((string) ($row['note'] ?? ''));
    }

    private function is_pickup_only_row($row) {
        return (($row['pickup_only'] ?? 'no') === 'yes');
    }

    private function find_next_open_day($today_ymd) {
        $labels = $this->get_day_labels();

        for ($i = 1; $i <= 14; $i++) {
            $timestamp = strtotime($today_ymd . ' +' . $i . ' day');
            if (!$timestamp) {
                continue;
            }

            $weekday = (int) wp_date('w', $timestamp);
            $ymd     = wp_date('Y-m-d', $timestamp);

            if ($this->is_store_closed_for_display($weekday, $ymd)) {
                continue;
            }

            $row = $this->get_row_for_day($weekday);

            return [
                'weekday' => $weekday,
                'ymd'     => $ymd,
                'label'   => $labels[$weekday] ?? '',
                'open'    => $row['open'] ?? '',
                'close'   => $row['close'] ?? '',
            ];
        }

        return null;
    }

    private function get_current_status_data() {
        $today_ymd        = $this->get_today_ymd();
        $today            = (int) current_time('w');
        $row              = $this->get_row_for_day($today);
        $now              = current_time('H:i');
        $open             = $row['open'] ?? '';
        $close            = $row['close'] ?? '';
        $closed_day_title = $this->get_closed_day_title($today_ymd);
        $closed_day       = $this->is_store_closed_for_display($today, $today_ymd);

        if ($closed_day) {
            $next = $this->find_next_open_day($today_ymd);
            $headline = $closed_day_title !== '' ? $closed_day_title : 'Vi holder lukket nu';
            $message  = $headline;
            $detail   = $next
                ? 'Vi åbner igen ' . $next['label'] . ' kl. ' . $this->format_time($next['open'])
                : '';

            if ($closed_day_title === '' && $next) {
                $headline = 'Vi holder lukket nu, men åbner igen ' . $next['label'] . ' kl. ' . $this->format_time($next['open']);
                $message = $headline;
                $detail = '';
            }

            return [
                'state'    => 'closed',
                'badge'    => 'Lukket nu',
                'headline' => $headline,
                'message'  => $message,
                'detail'   => $detail,
            ];
        }

        if (!$open || !$close) {
            return [
                'state'    => 'closed',
                'badge'    => 'Lukket nu',
                'headline' => 'Vi holder lukket nu',
                'message'  => 'Vi holder lukket nu',
                'detail'   => '',
            ];
        }

        if (strcmp($now, $open) < 0) {
            if ($this->is_pickup_only_row($row)) {
                return [
                    'state'    => 'opening-later',
                    'badge'    => 'Afhentning',
                    'headline' => 'Afhentning i dag fra ' . $this->format_time($open),
                    'message'  => 'Afhentning i dag fra ' . $this->format_time($open),
                    'detail'   => $this->get_row_note($row),
                ];
            }

            return [
                'state'    => 'opening-later',
                'badge'    => 'Lukket nu',
                'headline' => 'Vi åbner i dag kl. ' . $this->format_time($open),
                'message'  => 'Vi åbner i dag kl. ' . $this->format_time($open),
                'detail'   => $this->get_row_note($row) ?: 'Dagens åbningstid er ' . $this->format_time($open) . ' - ' . $this->format_time($close),
            ];
        }

        if (strcmp($now, $open) >= 0 && strcmp($now, $close) < 0) {
            if ($this->is_pickup_only_row($row)) {
                return [
                    'state'    => 'open',
                    'badge'    => 'Afhentning',
                    'headline' => 'Afhentning i dag indtil ' . $this->format_time($close),
                    'message'  => 'Afhentning i dag indtil ' . $this->format_time($close),
                    'detail'   => $this->get_row_note($row),
                ];
            }

            return [
                'state'    => 'open',
                'badge'    => 'Åben nu',
                'headline' => 'Åbent i dag indtil ' . $this->format_time($close),
                'message'  => 'Åbent i dag indtil ' . $this->format_time($close),
                'detail'   => $this->get_row_note($row) ?: 'I dag: ' . $this->format_time($open) . ' - ' . $this->format_time($close),
            ];
        }

        $next = $this->find_next_open_day($today_ymd);

        return [
            'state'    => 'closed',
            'badge'    => 'Lukket nu',
            'headline' => $next
                ? 'Vi holder lukket nu, men åbner igen ' . $next['label'] . ' kl. ' . $this->format_time($next['open'])
                : 'Vi holder lukket nu',
            'message'  => $next
                ? 'Vi holder lukket nu, men åbner igen ' . $next['label'] . ' kl. ' . $this->format_time($next['open'])
                : 'Vi holder lukket nu',
            'detail'   => '',
        ];
    }

    private function render_hours_value($row, $is_closed, $closed_text = '') {
        if ($is_closed) {
            $html = '<span class="wcr-time-main">Lukket</span>';

            if ($closed_text !== '') {
                $html .= '<span class="wcr-time-note">' . esc_html($closed_text) . '</span>';
            }

            return $html;
        }

        $time = trim($this->format_time($row['open'] ?? '') . ' - ' . $this->format_time($row['close'] ?? ''));
        $note = $this->get_row_note($row);

        $html = '<span class="wcr-time-main">' . esc_html($time) . '</span>';

        if ($note !== '') {
            $html .= '<span class="wcr-time-note">' . esc_html($note) . '</span>';
        }

        return $html;
    }

    public function render_opening_hours($atts = []) {
        $atts = shortcode_atts([
            'highlight_today' => 'yes',
            'show_closed'     => 'yes',
            'show_status'     => 'yes',
        ], $atts, 'wcr_opening_hours');

        $hours       = WCR_Session::get_hours();
        $labels      = $this->get_day_labels_display();
        $today       = (int) current_time('w');
        $today_ymd   = $this->get_today_ymd();
        $highlight   = $atts['highlight_today'] === 'yes';
        $show_closed = $atts['show_closed'] === 'yes';
        $show_status = $atts['show_status'] === 'yes';
        $status      = $this->get_current_status_data();

        ob_start();
        ?>
        <div class="wcr-opening-hours-wrap">
            <?php if ($show_status) : ?>
                <div class="wcr-opening-status-card state-<?php echo esc_attr($status['state']); ?>">
                    <div class="wcr-opening-status-badge">
                        <?php echo esc_html($status['badge']); ?>
                    </div>
                    <div class="wcr-opening-status-text">
                        <div class="wcr-opening-status-headline">
                            <?php echo esc_html($status['headline']); ?>
                        </div>
                        <?php if (!empty($status['detail'])) : ?>
                            <div class="wcr-opening-status-detail">
                                <?php echo esc_html($status['detail']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="wcr-opening-hours">
                <table class="wcr-opening-hours-table">
                    <tbody>
                    <?php foreach ($labels as $weekday => $label) :
                        $row = $hours[$weekday] ?? ['closed' => 'yes', 'open' => '', 'close' => '', 'note' => '', 'pickup_only' => 'no', 'pickup_text' => WCR_Session::get_default_pickup_only_text()];
                        $is_closed = (($row['closed'] ?? 'yes') === 'yes');
                        $closed_text = '';

                        if ($weekday === $today && $this->is_store_closed_for_display($weekday, $today_ymd)) {
                            $is_closed = true;
                            $closed_text = $this->get_closed_day_title($today_ymd);
                        }

                        if ($is_closed && !$show_closed) {
                            continue;
                        }

                        $classes = ['wcr-opening-hours-row'];

                        if ($highlight && $weekday === $today) {
                            $classes[] = 'is-today';
                        }
                        ?>
                        <tr class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                            <td class="wcr-day"><?php echo esc_html($label); ?></td>
                            <td class="wcr-time">
                                <?php echo wp_kses_post($this->render_hours_value($row, $is_closed, $closed_text)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_opening_hours_today($atts = []) {
        $atts = shortcode_atts([
            'show_badge' => 'yes',
        ], $atts, 'wcr_opening_hours_today');

        $status     = $this->get_current_status_data();
        $show_badge = $atts['show_badge'] === 'yes';

        ob_start();
        ?>
        <div class="wcr-opening-status-card wcr-opening-status-card--single state-<?php echo esc_attr($status['state']); ?>">
            <?php if ($show_badge) : ?>
                <div class="wcr-opening-status-badge">
                    <?php echo esc_html($status['badge']); ?>
                </div>
            <?php endif; ?>

            <div class="wcr-opening-status-text">
                <div class="wcr-opening-status-headline">
                    <?php echo esc_html($status['message']); ?>
                </div>

                <?php if (!empty($status['detail'])) : ?>
                    <div class="wcr-opening-status-detail">
                        <?php echo esc_html($status['detail']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_closed_days($atts = []) {
        $atts = shortcode_atts([
            'upcoming_only' => 'yes',
            'show_year'     => 'yes',
            'show_title'    => 'yes',
            'empty_text'    => 'Ingen planlagte lukkedage.',
        ], $atts, 'wcr_closed_days');

        $upcoming_only = $atts['upcoming_only'] === 'yes';
        $show_year     = $atts['show_year'] === 'yes';
        $show_title    = $atts['show_title'] === 'yes';
        $today_ymd     = current_time('Y-m-d');

        $rows = WCR_Session::get_closed_days();
        $items = [];

        foreach ($rows as $row) {
            $date  = WCR_Session::date_to_ymd($row['date'] ?? '');
            $title = trim((string) ($row['title'] ?? ''));
            $show  = ($row['show'] ?? 'no') === 'yes';

            if (!$date || !$show) {
                continue;
            }

            if ($upcoming_only && strcmp($date, $today_ymd) < 0) {
                continue;
            }

            $items[] = [
                'date'  => $date,
                'title' => $title,
            ];
        }

        if (empty($items)) {
            return '<div class="wcr-closed-days-empty">' . esc_html($atts['empty_text']) . '</div>';
        }

        ob_start();
        ?>
        <div class="wcr-closed-days">
            <ul class="wcr-closed-days-list">
                <?php foreach ($items as $item) : ?>
                    <li class="wcr-closed-days-item">
                        <span class="wcr-closed-days-date">
                            <?php echo esc_html($this->format_date_for_frontend($item['date'], $show_year)); ?>
                        </span>

                        <?php if ($show_title && $item['title'] !== '') : ?>
                            <span class="wcr-closed-days-separator">—</span>
                            <span class="wcr-closed-days-title">
                                <?php echo esc_html($item['title']); ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
