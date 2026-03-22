<?php
if (!defined('ABSPATH')) exit;

class WCR_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu() {
        add_submenu_page(
            'woocommerce',
            'Catering Rules',
            'Catering Rules',
            'manage_woocommerce',
            'wcr',
            [$this, 'page']
        );
    }

    public function register_settings() {
        register_setting('wcr_settings_group', 'wcr_closed_dates', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_closed_dates'],
            'default' => [],
        ]);

        register_setting('wcr_settings_group', 'wcr_store_hours', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_store_hours'],
            'default' => WCR_Session::get_default_hours(),
        ]);

        register_setting('wcr_settings_group', 'wcr_closed_today', [
            'type' => 'string',
            'sanitize_callback' => function($value) {
                return $value === 'yes' ? 'yes' : 'no';
            },
            'default' => 'no',
        ]);
    }

    public function sanitize_closed_dates($value) {
        if (!is_array($value)) return [];

        $clean = [];

        foreach ($value as $row) {
            if (is_string($row)) {
                $ymd = WCR_Session::date_to_ymd($row);
                if ($ymd) {
                    $clean[] = [
                        'date'  => $ymd,
                        'title' => '',
                        'show'  => 'yes',
                    ];
                }
                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $raw_date = sanitize_text_field((string) ($row['date'] ?? ''));
            $ymd      = WCR_Session::date_to_ymd($raw_date);

            if (!$ymd) {
                continue;
            }

            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            $show  = (($row['show'] ?? 'no') === 'yes') ? 'yes' : 'no';

            $clean[] = [
                'date'  => $ymd,
                'title' => $title,
                'show'  => $show,
            ];
        }

        usort($clean, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return array_values($clean);
    }

    public function sanitize_store_hours($value) {
        $defaults = WCR_Session::get_default_hours();
        $clean = [];

        foreach ($defaults as $weekday => $default_row) {
            $row = isset($value[$weekday]) && is_array($value[$weekday]) ? $value[$weekday] : [];
            $open = isset($row['open']) ? sanitize_text_field((string) $row['open']) : $default_row['open'];
            $close = isset($row['close']) ? sanitize_text_field((string) $row['close']) : $default_row['close'];
            $closed = isset($row['closed']) && $row['closed'] === 'yes' ? 'yes' : 'no';

            $clean[$weekday] = [
                'closed' => $closed,
                'open'   => WCR_Session::valid_time($open) ? $open : $default_row['open'],
                'close'  => WCR_Session::valid_time($close) ? $close : $default_row['close'],
            ];
        }

        return $clean;
    }

    public function assets($hook) {
        if (strpos((string) $hook, 'wcr') === false) return;

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style(
            'jquery-ui-css',
            'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
            [],
            '1.13.2'
        );

        wp_enqueue_style('wcr-style', WCR_URL . 'assets/style.css', [], WCR_VERSION);
        wp_enqueue_script('wcr-js', WCR_URL . 'assets/script.js', ['jquery', 'jquery-ui-datepicker'], WCR_VERSION, true);

        wp_localize_script('wcr-js', 'wcrAdmin', [
            'isAdmin' => true,
        ]);
    }

    public function page() {
        $hours = get_option('wcr_store_hours', WCR_Session::get_default_hours());
        $closed_days = WCR_Session::get_closed_days();
        $closed_today = get_option('wcr_closed_today', 'no');

        if (empty($closed_days)) {
            $closed_days = [
                [
                    'date'  => '',
                    'title' => '',
                    'show'  => 'yes',
                ]
            ];
        }
        ?>
        <div class="wrap wcr-admin-wrap">
            <h1>Catering Rules</h1>

            <form method="post" action="options.php">
                <?php settings_fields('wcr_settings_group'); ?>

                <h2>Hurtig status</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Lukket i dag</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wcr_closed_today" value="yes" <?php checked($closed_today, 'yes'); ?>>
                                Brug denne hvis butikken akut skal lukkes i dag
                            </label>
                            <p class="description">Denne funktion blokerer kun bestillinger til i dag. Den ændrer ikke butikkens normale åbningstider eller frontend-visningen af åbningstider.</p>
                        </td>
                    </tr>
                </table>

                <h2>Åbningstider</h2>
                <table class="widefat striped wcr-hours-table">
                    <thead>
                        <tr>
                            <th>Ugedag</th>
                            <th>Lukket</th>
                            <th>Åbner</th>
                            <th>Lukker</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $labels = [
                        1 => 'Mandag',
                        2 => 'Tirsdag',
                        3 => 'Onsdag',
                        4 => 'Torsdag',
                        5 => 'Fredag',
                        6 => 'Lørdag',
                        0 => 'Søndag',
                    ];

                    foreach ($labels as $weekday => $label) :
                        $row = isset($hours[$weekday]) ? $hours[$weekday] : ['closed' => 'no', 'open' => '08:00', 'close' => '16:00'];
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="wcr_store_hours[<?php echo esc_attr($weekday); ?>][closed]" value="yes" <?php checked($row['closed'], 'yes'); ?>>
                                    Lukket
                                </label>
                            </td>
                            <td><input type="time" step="900" name="wcr_store_hours[<?php echo esc_attr($weekday); ?>][open]" value="<?php echo esc_attr($row['open']); ?>"></td>
                            <td><input type="time" step="900" name="wcr_store_hours[<?php echo esc_attr($weekday); ?>][close]" value="<?php echo esc_attr($row['close']); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:24px;">Lukkedatoer</h2>
                <p class="description">Her kan du oprette lukkedage med titel og vælge, om de skal vises i frontend-listen.</p>

                <div id="wcr-closed-dates-list">
                    <?php foreach ($closed_days as $index => $row) : ?>
                        <div class="wcr-date-row wcr-date-row--closed-day">
                            <input
                                type="text"
                                class="wcr-datepicker wcr-date-input"
                                name="wcr_closed_dates[<?php echo esc_attr($index); ?>][date]"
                                value="<?php echo esc_attr(!empty($row['date']) ? WCR_Session::native_to_display_date($row['date']) : ''); ?>"
                                placeholder="dd/mm/yyyy"
                                autocomplete="off"
                            >

                            <input
                                type="text"
                                class="regular-text"
                                name="wcr_closed_dates[<?php echo esc_attr($index); ?>][title]"
                                value="<?php echo esc_attr($row['title'] ?? ''); ?>"
                                placeholder="Titel, fx Juleaften"
                            >

                            <label class="wcr-checkbox-inline">
                                <input
                                    type="checkbox"
                                    name="wcr_closed_dates[<?php echo esc_attr($index); ?>][show]"
                                    value="yes"
                                    <?php checked(($row['show'] ?? 'no'), 'yes'); ?>
                                >
                                Vis i frontend
                            </label>

                            <button type="button" class="button wcr-remove-date">Fjern</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" class="button button-secondary" id="wcr-add-date-row">Tilføj lukkedato</button>
                </p>

                <?php submit_button('Gem indstillinger'); ?>
            </form>

            <template id="wcr-date-row-template">
                <div class="wcr-date-row wcr-date-row--closed-day">
                    <input
                        type="text"
                        class="wcr-datepicker wcr-date-input"
                        name="wcr_closed_dates[__INDEX__][date]"
                        value=""
                        placeholder="dd/mm/yyyy"
                        autocomplete="off"
                    >

                    <input
                        type="text"
                        class="regular-text"
                        name="wcr_closed_dates[__INDEX__][title]"
                        value=""
                        placeholder="Titel, fx Juleaften"
                    >

                    <label class="wcr-checkbox-inline">
                        <input
                            type="checkbox"
                            name="wcr_closed_dates[__INDEX__][show]"
                            value="yes"
                            checked
                        >
                        Vis i frontend
                    </label>

                    <button type="button" class="button wcr-remove-date">Fjern</button>
                </div>
            </template>
        </div>
        <?php
    }
}
