<?php
                </tbody>
            </table>

            <h2 style="margin-top:24px;">Lukkedatoer</h2>
            <div id="wcr-closed-dates-list">
                <?php if (empty($closed_dates)) $closed_dates = ['']; ?>
                <?php foreach (array_values($closed_dates) as $date) : ?>
                    <div class="wcr-date-row">
                        <input type="text" class="wcr-datepicker wcr-date-input" name="<?php echo esc_attr(WCR_Utils::OPTION_CLOSED_DATES); ?>[]" value="<?php echo esc_attr($date); ?>" placeholder="dd/mm/yyyy" autocomplete="off">
                        <button type="button" class="button wcr-remove-date">Fjern</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <p><button type="button" class="button button-secondary" id="wcr-add-date-row">Tilføj lukkedato</button></p>

            <?php submit_button('Gem indstillinger'); ?>
        </form>

        <h2>Shortcodes</h2>
        <code>[cor_delivery_summary]</code><br>
        <code>[cor_delivery_selector]</code>

        <template id="wcr-date-row-template">
            <div class="wcr-date-row">
                <input type="text" class="wcr-datepicker wcr-date-input" name="<?php echo esc_attr(WCR_Utils::OPTION_CLOSED_DATES); ?>[]" value="" placeholder="dd/mm/yyyy" autocomplete="off">
                <button type="button" class="button wcr-remove-date">Fjern</button>
            </div>
        </template>
        <?php
    }

    private function overview_tab(): void {
        $products = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        echo '<p>Oversigt over produkter med særlige dato-/tidsregler.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Produkt</th><th>Status</th><th>Fra</th><th>Til</th><th>Tidsrum</th><th>Blokerede datoer</th><th>Redigér</th></tr></thead><tbody>';

        $found = false;
        foreach ($products as $product_post) {
            $product_id = $product_post->ID;
            $enabled = get_post_meta($product_id, '_wcr_enable_product_rules', true);
            $available_from = (string) get_post_meta($product_id, '_wcr_available_from', true);
            $available_to = (string) get_post_meta($product_id, '_wcr_available_to', true);
            $time_from = (string) get_post_meta($product_id, '_wcr_time_from', true);
            $time_to = (string) get_post_meta($product_id, '_wcr_time_to', true);
            $blocked_dates = (string) get_post_meta($product_id, '_wcr_blocked_dates', true);

            $has_rules = $enabled === 'yes' || $available_from || $available_to || $time_from || $time_to || $blocked_dates;
            if (!$has_rules) {
                continue;
            }

            $found = true;
            $blocked_count = $blocked_dates ? count(array_filter(preg_split('/\r\n|\r|\n/', $blocked_dates))) : 0;

            echo '<tr>';
            echo '<td><strong>' . esc_html(get_the_title($product_id)) . '</strong></td>';
            echo '<td>' . ($enabled === 'yes' ? '<span class="wcr-badge wcr-badge--active">Aktiv</span>' : '<span class="wcr-badge">Felter udfyldt</span>') . '</td>';
            echo '<td>' . esc_html($available_from ?: '—') . '</td>';
            echo '<td>' . esc_html($available_to ?: '—') . '</td>';
            echo '<td>' . esc_html(trim(($time_from ?: '—') . ' → ' . ($time_to ?: '—'))) . '</td>';
            echo '<td>' . esc_html($blocked_count ? $blocked_count . ' dato(er)' : '—') . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url(get_edit_post_link($product_id)) . '">Åbn produkt</a></td>';
            echo '</tr>';
        }

        if (!$found) {
            echo '<tr><td colspan="7">Ingen produkter med aktive regler endnu.</td></tr>';
        }

        echo '</tbody></table>';
    }
}
