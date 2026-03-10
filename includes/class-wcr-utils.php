<?php
        }

        return $out;
    }

    public static function sanitize_closed_dates_array($value): array {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $row) {
            $row = sanitize_text_field((string) $row);
            if ($row === '') {
                continue;
            }
            if (self::dmy_to_ymd($row)) {
                $clean[] = $row;
            }
        }

        return array_values(array_unique($clean));
    }

    public static function sanitize_store_hours($value): array {
        $defaults = self::default_hours();
        $clean = [];

        foreach ($defaults as $weekday => $default_row) {
            $row = isset($value[$weekday]) && is_array($value[$weekday]) ? $value[$weekday] : [];
            $open = isset($row['open']) ? sanitize_text_field((string) $row['open']) : $default_row['open'];
            $close = isset($row['close']) ? sanitize_text_field((string) $row['close']) : $default_row['close'];
            $closed = isset($row['closed']) && $row['closed'] === 'yes' ? 'yes' : 'no';

            $clean[$weekday] = [
                'closed' => $closed,
                'open' => self::valid_time($open) ? $open : $default_row['open'],
                'close' => self::valid_time($close) ? $close : $default_row['close'],
            ];
        }

        return $clean;
    }

    public static function sanitize_yes_no($value): string {
        return $value === 'yes' ? 'yes' : 'no';
    }

    public static function validate_store_hours(string $date_ddmmyyyy, string $time_hhmm) {
        $ymd = self::dmy_to_ymd($date_ddmmyyyy);
        if (!$ymd) {
            return 'Datoformat er ugyldigt. Brug dd/mm/yyyy.';
        }
        if (!self::valid_time($time_hhmm)) {
            return 'Tidspunkt er ugyldigt.';
        }
        if (!self::quarter_time($time_hhmm)) {
            return 'Tidspunkt skal være i kvarter: 00, 15, 30 eller 45.';
        }

        $today_ymd = current_time('Y-m-d');
        if (get_option(self::OPTION_CLOSED_TODAY, 'no') === 'yes' && $ymd === $today_ymd) {
            return 'Butikken er midlertidigt lukket i dag.';
        }
        if (in_array($ymd, self::closed_dates_ymd(), true)) {
            return 'Den valgte dato er lukket for bestilling.';
        }

        $weekday = (int) date('w', strtotime($ymd));
        $hours = get_option(self::OPTION_HOURS, self::default_hours());
        $row = isset($hours[$weekday]) ? $hours[$weekday] : ['closed' => 'no', 'open' => '08:00', 'close' => '16:00'];

        if (($row['closed'] ?? 'no') === 'yes') {
            return 'Butikken er lukket på den valgte ugedag.';
        }

        $open = isset($row['open']) ? (string) $row['open'] : '';
        $close = isset($row['close']) ? (string) $row['close'] : '';

        if ($open && self::valid_time($open) && strcmp($time_hhmm, self::round_up_quarter($open)) < 0) {
            return 'Valgt leveringstid er før butikkens åbningstid (' . $open . ').';
        }
        if ($close && self::valid_time($close) && strcmp($time_hhmm, self::round_down_quarter($close)) > 0) {
            return 'Valgt leveringstid er efter butikkens lukketid (' . $close . ').';
        }

        return true;
    }
}
