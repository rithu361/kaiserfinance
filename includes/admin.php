<?php
/**
 * Admin: ledger, holdings with real amounts, trade form with instrument search, settings.
 * Only users who can manage_options (Administrators) get in.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'kf_admin_menu');
add_action('admin_init', 'kf_register_settings');
add_action('admin_enqueue_scripts', 'kf_admin_assets');
add_action('admin_post_kf_save_trade', 'kf_handle_save_trade');
add_action('admin_post_kf_delete_trade', 'kf_handle_delete_trade');
add_action('admin_post_kf_refresh', 'kf_handle_refresh');
add_action('admin_post_kf_set_price', 'kf_handle_set_price');
add_action('admin_post_kf_save_cash', 'kf_handle_save_cash');
add_action('admin_post_kf_delete_cash', 'kf_handle_delete_cash');
add_action('admin_post_kf_reset', 'kf_handle_reset');
add_action('admin_post_kf_check_updates', 'kf_handle_check_updates');

function kf_handle_check_updates() {
    if (!current_user_can('update_plugins')) {
        wp_die('Not allowed.');
    }
    check_admin_referer('kf_check_updates');
    kf_updater_check_now();
    wp_safe_redirect(admin_url('admin.php?page=kaiserfinance-settings#kf-updates'));
    exit;
}

/** Empty the ledger: trades, cash, assets and stored prices. Settings and API key stay. */
function kf_handle_reset() {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to change the ledger.');
    }
    check_admin_referer('kf_reset');
    $back = admin_url('admin.php?page=kaiserfinance-settings');
    if (trim(wp_unslash($_POST['confirm'] ?? '')) !== 'RESET') {
        wp_safe_redirect(add_query_arg(array('kf_msg' => rawurlencode('Nothing was deleted. Type RESET in capitals to confirm.'), 'kf_type' => 'error'), $back));
        exit;
    }
    global $wpdb;
    foreach (array('trades', 'cash', 'instruments', 'prices') as $t) {
        $wpdb->query('TRUNCATE TABLE ' . kf_table($t));
    }
    delete_option('kf_last_refresh');
    delete_option('kf_last_errors');
    kf_flush_cache();
    kf_redirect('Ledger reset. All trades, cash entries and prices are gone; settings and the API key are kept.');
}
add_action('wp_ajax_kf_symbol_search', 'kf_ajax_symbol_search');
add_action('wp_ajax_kf_last_price', 'kf_ajax_last_price');

/** Current cash per currency for the trade form, or null in "trades only" mode. */
function kf_cash_balances_for_js() {
    $perf = kf_performance();
    if ($perf['trades_only']) {
        return null;
    }
    $out = array();
    foreach ($perf['cash'] as $c) {
        $out[$c['currency']] = round($c['amount'], 2);
    }
    return $out;
}

/** Latest known price for an asset (stored, or one live quote), to hint the price field. */
function kf_ajax_last_price() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Not allowed', 403);
    }
    check_ajax_referer('kf_symbol_search', 'nonce');
    global $wpdb;
    $symbol = strtoupper(trim(sanitize_text_field(wp_unslash($_GET['symbol'] ?? ''))));
    $mic    = strtoupper(trim(sanitize_text_field(wp_unslash($_GET['mic_code'] ?? ''))));
    if ($symbol === '') {
        wp_send_json_success(null);
    }
    $ikey = kf_make_ikey($symbol, $mic);
    $row  = $wpdb->get_row($wpdb->prepare('SELECT day, close FROM ' . kf_table('prices') . ' WHERE symbol = %s ORDER BY day DESC LIMIT 1', $ikey), ARRAY_A);
    if ($row) {
        wp_send_json_success(array('price' => (float) $row['close'], 'day' => $row['day']));
    }
    $key = kf_settings()['api_key'];
    if ($key === '') {
        wp_send_json_success(null);
    }
    $args = array('symbol' => $symbol, 'apikey' => $key);
    if ($mic !== '') {
        $args['mic_code'] = $mic;
    }
    $res  = wp_remote_get('https://api.twelvedata.com/price?' . http_build_query($args), array('timeout' => 8));
    $body = is_wp_error($res) ? null : json_decode(wp_remote_retrieve_body($res), true);
    if (is_array($body) && isset($body['price']) && (float) $body['price'] > 0) {
        wp_send_json_success(array('price' => (float) $body['price'], 'day' => gmdate('Y-m-d')));
    }
    wp_send_json_success(null);
}

function kf_admin_menu() {
    add_menu_page('Kai$erFinance', 'Kai$erFinance', 'manage_options', 'kaiserfinance', 'kf_admin_page', 'dashicons-chart-line', 26);
    add_submenu_page('kaiserfinance', 'Ledger', 'Ledger', 'manage_options', 'kaiserfinance', 'kf_admin_page');
    add_submenu_page('kaiserfinance', 'Kai$erFinance settings', 'Settings', 'manage_options', 'kaiserfinance-settings', 'kf_settings_page');
}

function kf_admin_assets($hook) {
    if ($hook !== 'toplevel_page_kaiserfinance') {
        return;
    }
    wp_enqueue_script('kaiserfinance-admin', KF_URL . 'assets/admin.js', array(), KF_VERSION, true);
    wp_localize_script('kaiserfinance-admin', 'KF_ADMIN', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('kf_symbol_search'),
        'cash'    => kf_cash_balances_for_js(),
    ));
}

/* ---------- formatting ---------- */

function kf_money($v, $currency = 'USD') {
    if ($v === null) {
        return '—';
    }
    $decimals = abs($v) < 10 ? 4 : 2;
    $abs = number_format(abs($v), $decimals, '.', "'");
    if ($decimals === 4) {
        $abs = preg_replace('/(\.\d\d\d?)0+$/', '$1', $abs); // 4 decimals for small prices, trimmed to at least 2
        $abs = preg_replace('/(\.\d\d)0$/', '$1', $abs);
    }
    $sign = $v < 0 ? '−' : '';
    return $currency === 'USD' ? $sign . '$' . $abs : $sign . $abs . ' ' . $currency;
}

/** <option>s for the cash currency dropdowns: common currencies plus any already in use. */
function kf_currency_options($selected) {
    $common = array('CHF', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'HKD', 'SGD', 'SEK', 'NOK', 'DKK', 'NZD');
    $used = array();
    foreach (kf_get_cash() as $c) {
        $used[] = $c['currency'];
        if ($c['currency2'] !== '') {
            $used[] = $c['currency2'];
        }
    }
    foreach (kf_get_instruments() as $i) {
        $used[] = $i['currency'];
    }
    $all = array_values(array_unique(array_merge($common, array_filter($used, function ($c) { return preg_match('/^[A-Z]{3}$/', $c); }))));
    $html = '';
    foreach ($all as $c) {
        $html .= '<option value="' . esc_attr($c) . '"' . selected($c, $selected, false) . '>' . esc_html($c) . '</option>';
    }
    return $html;
}

function kf_qty($v) {
    return rtrim(rtrim(number_format((float) $v, 8, '.', "'"), '0'), '.');
}

function kf_pct($v) {
    if ($v === null) {
        return '—';
    }
    return ($v >= 0 ? '+' : '−') . number_format(abs($v), 2) . '%';
}

function kf_num_input($key) {
    return (float) str_replace(array("'", ' ', ','), array('', '', '.'), wp_unslash($_POST[$key] ?? '0'));
}

function kf_redirect($msg, $type = 'success', $extra = array()) {
    $anchor = (isset($extra['kf_tab']) && $extra['kf_tab'] === 'cash') ? '#kf-cash' : '';
    wp_safe_redirect(add_query_arg(array_merge(array('page' => 'kaiserfinance', 'kf_msg' => rawurlencode($msg), 'kf_type' => $type), $extra), admin_url('admin.php')) . $anchor);
    exit;
}

/* ---------- handlers ---------- */

function kf_ajax_symbol_search() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Not allowed', 403);
    }
    check_ajax_referer('kf_symbol_search', 'nonce');
    $q = trim(sanitize_text_field(wp_unslash($_GET['q'] ?? '')));
    if (mb_strlen($q) < 2) {
        wp_send_json_success(array());
    }
    $results = kf_symbol_search($q);
    if (is_wp_error($results)) {
        wp_send_json_error($results->get_error_message());
    }
    wp_send_json_success($results);
}

function kf_handle_save_trade() {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to change the ledger.');
    }
    check_admin_referer('kf_save_trade');

    global $wpdb;
    $id       = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $side     = (isset($_POST['side']) && $_POST['side'] === 'sell') ? 'sell' : 'buy';
    $symbol   = strtoupper(trim(sanitize_text_field(wp_unslash($_POST['symbol'] ?? ''))));
    $mic      = strtoupper(trim(sanitize_text_field(wp_unslash($_POST['mic_code'] ?? ''))));
    $exchange = trim(sanitize_text_field(wp_unslash($_POST['exchange'] ?? '')));
    $country  = trim(sanitize_text_field(wp_unslash($_POST['country'] ?? '')));
    $type     = trim(sanitize_text_field(wp_unslash($_POST['type'] ?? '')));
    $currency = trim(sanitize_text_field(wp_unslash($_POST['currency'] ?? 'USD')));
    $mode     = (isset($_POST['mode']) && $_POST['mode'] === 'manual') ? 'manual' : 'auto';
    $name     = trim(sanitize_text_field(wp_unslash($_POST['name'] ?? '')));
    $qty      = kf_num_input('qty');
    $price    = kf_num_input('price');
    $date     = sanitize_text_field(wp_unslash($_POST['trade_date'] ?? ''));
    $note     = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));

    if ($symbol === '' || !preg_match('#^[A-Z0-9./:\-]{1,32}$#', $symbol)) {
        kf_redirect('Search for the asset and pick it from the list, or enter a valid symbol.', 'error');
    }
    if ($mic !== '' && !preg_match('#^[A-Z0-9]{2,16}$#', $mic)) {
        $mic = '';
    }
    if (!preg_match('/^[A-Za-z]{3}$/', $currency)) {
        kf_redirect('Currency must be a 3-letter code like USD, AUD or CHF.', 'error');
    }
    if (!in_array($currency, array('GBp', 'ZAc', 'ILA'), true)) {
        $currency = strtoupper($currency);
    }
    if ($name === '') {
        $name = $symbol;
    }
    if ($qty <= 0 || $price <= 0) {
        kf_redirect('Quantity and price must both be above zero.', 'error');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > gmdate('Y-m-d', strtotime('+1 day'))) {
        kf_redirect('Pick a valid trade date that is not in the future.', 'error');
    }

    $ikey = kf_make_ikey($symbol, $mic);

    if ($side === 'sell') {
        $held = kf_held_qty($ikey, $id);
        if ($qty > $held + 1e-9) {
            kf_redirect(sprintf('You only hold %s of %s. Lower the quantity to sell.', kf_qty($held), $name), 'error');
        }
    }

    // Cash check: a buy must be covered by cash in the asset's currency.
    $trades_after = array();
    foreach (kf_get_trades() as $t) {
        if ((int) $t['id'] !== $id) {
            $trades_after[] = $t;
        }
    }
    $trades_after[] = array('id' => $id ? $id : PHP_INT_MAX, 'trade_date' => $date, 'side' => $side, 'symbol' => $ikey, 'qty' => $qty, 'price' => $price);
    kf_sort_rows($trades_after, 'trade_date');
    $problem = kf_check_cash_change($trades_after, kf_get_cash(), array($ikey => $currency));
    if ($problem) {
        kf_redirect($problem, 'error');
    }

    // Keep instrument details from earlier trades when this form didn't come from a search pick.
    $existing = kf_get_instrument($ikey);
    kf_save_instrument(array(
        'ikey'     => $ikey,
        'symbol'   => $symbol,
        'mic_code' => $mic,
        'exchange' => $exchange !== '' ? $exchange : ($existing ? $existing['exchange'] : ''),
        'country'  => $country !== '' ? $country : ($existing ? $existing['country'] : ''),
        'name'     => mb_substr($name, 0, 100),
        'type'     => $type !== '' ? $type : ($existing ? $existing['type'] : ''),
        'currency' => $currency,
        'mode'     => $mode,
    ));

    $data = array(
        'trade_date' => $date,
        'side'       => $side,
        'symbol'     => $ikey,
        'name'       => mb_substr($name, 0, 100),
        'qty'        => $qty,
        'price'      => $price,
        'note'       => mb_substr($note, 0, 255),
    );
    $formats = array('%s', '%s', '%s', '%s', '%f', '%f', '%s');

    if ($id) {
        $wpdb->update(kf_table('trades'), $data, array('id' => $id), $formats, array('%d'));
        $msg = 'Trade updated.';
    } else {
        $data['created_at'] = current_time('mysql', true);
        $formats[] = '%s';
        $wpdb->insert(kf_table('trades'), $data, $formats);
        $msg = sprintf('Added: %s %s %s at %s.', $side, kf_qty($qty), $name, kf_money($price, $currency));
    }

    // A manual instrument with no price yet starts at its trade price, so it isn't valued at zero.
    if ($mode === 'manual') {
        $has_price = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . kf_table('prices') . ' WHERE symbol = %s', $ikey));
        if (!$has_price) {
            kf_set_manual_price($ikey, $price, $date);
        }
        $msg .= ' Its price is entered by hand in the Holdings table.';
    }

    kf_flush_cache();
    $result = kf_refresh_prices(); // fetch prices for a new asset, currency or older date right away
    if (is_wp_error($result) && $result->get_error_code() === 'kf_partial') {
        $msg .= ' Prices could not all be updated yet: ' . $result->get_error_message();
    }
    kf_redirect($msg);
}

function kf_handle_delete_trade() {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to change the ledger.');
    }
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    check_admin_referer('kf_delete_trade_' . $id);

    // Deleting a sell removes cash that later buys may depend on.
    $trades_after = array_values(array_filter(kf_get_trades(), function ($t) use ($id) { return (int) $t['id'] !== $id; }));
    $problem = kf_check_cash_change($trades_after, kf_get_cash());
    if ($problem) {
        kf_redirect('Can\'t delete this trade. ' . $problem, 'error');
    }

    global $wpdb;
    $wpdb->delete(kf_table('trades'), array('id' => $id), array('%d'));
    kf_flush_cache();
    kf_redirect('Trade deleted.');
}

function kf_handle_refresh() {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }
    check_admin_referer('kf_refresh');
    delete_transient('kf_refresh_lock');
    $result = kf_refresh_prices();
    if (is_wp_error($result)) {
        kf_redirect('Price update had problems: ' . $result->get_error_message(), 'error');
    }
    kf_redirect($result ? 'Prices updated.' : 'Nothing to update yet. Add a trade first.');
}

function kf_handle_save_cash() {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to change the ledger.');
    }
    check_admin_referer('kf_save_cash');

    global $wpdb;
    $types = kf_cash_types();
    $type  = sanitize_key(wp_unslash($_POST['type'] ?? ''));
    $date  = sanitize_text_field(wp_unslash($_POST['flow_date'] ?? ''));
    $cur   = kf_normalize_currency(sanitize_text_field(wp_unslash($_POST['currency'] ?? '')));
    $amt   = kf_num_input('amount');
    $cur2  = kf_normalize_currency(sanitize_text_field(wp_unslash($_POST['currency2'] ?? '')));
    $amt2  = kf_num_input('amount2');
    $note  = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));

    if (!isset($types[$type])) {
        kf_redirect('Pick what kind of cash movement this is.', 'error', array('kf_tab' => 'cash'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > gmdate('Y-m-d', strtotime('+1 day'))) {
        kf_redirect('Pick a valid date that is not in the future.', 'error', array('kf_tab' => 'cash'));
    }
    if (!preg_match('/^[A-Za-z]{3}$/', $cur) || $amt <= 0) {
        kf_redirect('Enter a 3-letter currency (e.g. CHF) and an amount above zero.', 'error', array('kf_tab' => 'cash'));
    }
    if ($type === 'exchange') {
        if (!preg_match('/^[A-Za-z]{3}$/', $cur2) || $amt2 <= 0 || $cur2 === $cur) {
            kf_redirect('For a currency exchange, enter the currency and amount you received, different from what you paid.', 'error', array('kf_tab' => 'cash'));
        }
    } else {
        $cur2 = '';
        $amt2 = 0;
    }

    $cash_after   = kf_get_cash();
    $cash_after[] = array('id' => PHP_INT_MAX, 'flow_date' => $date, 'type' => $type, 'currency' => $cur, 'amount' => $amt, 'currency2' => $cur2, 'amount2' => $amt2);
    kf_sort_rows($cash_after, 'flow_date');
    $problem = kf_check_cash_change(kf_get_trades(), $cash_after);
    if ($problem) {
        kf_redirect($problem, 'error', array('kf_tab' => 'cash'));
    }

    $wpdb->insert(kf_table('cash'), array(
        'flow_date'  => $date,
        'type'       => $type,
        'currency'   => $cur,
        'amount'     => $amt,
        'currency2'  => $cur2,
        'amount2'    => $amt2,
        'note'       => mb_substr($note, 0, 255),
        'created_at' => current_time('mysql', true),
    ), array('%s', '%s', '%s', '%f', '%s', '%f', '%s', '%s'));

    kf_flush_cache();
    kf_refresh_prices(); // fetch the exchange rate for a new currency right away
    $msg = $type === 'exchange'
        ? sprintf('Added: exchanged %s for %s.', kf_money($amt, $cur), kf_money($amt2, $cur2))
        : sprintf('Added: %s of %s.', strtolower($types[$type]), kf_money($amt, $cur));
    kf_redirect($msg, 'success', array('kf_tab' => 'cash'));
}

function kf_handle_delete_cash() {
    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to change the ledger.');
    }
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    check_admin_referer('kf_delete_cash_' . $id);
    $cash_after = array_values(array_filter(kf_get_cash(), function ($c) use ($id) { return (int) $c['id'] !== $id; }));
    if ($cash_after) {
        $problem = kf_check_cash_change(kf_get_trades(), $cash_after);
        if ($problem) {
            kf_redirect('Can\'t delete this entry: later trades are paid from it. ' . $problem, 'error', array('kf_tab' => 'cash'));
        }
    }
    global $wpdb;
    $wpdb->delete(kf_table('cash'), array('id' => $id), array('%d'));
    kf_flush_cache();
    kf_redirect('Cash entry deleted.', 'success', array('kf_tab' => 'cash'));
}

function kf_handle_set_price() {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }
    check_admin_referer('kf_set_price');
    $ikey  = sanitize_text_field(wp_unslash($_POST['ikey'] ?? ''));
    $price = kf_num_input('price');
    $inst  = kf_get_instrument($ikey);
    if (!$inst || $price <= 0) {
        kf_redirect('Enter a price above zero.', 'error');
    }
    kf_set_manual_price($ikey, $price);
    kf_redirect(sprintf('%s set to %s.', $inst['name'], kf_money($price, $inst['currency'])));
}

/* ---------- ledger page ---------- */

function kf_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $perf        = kf_performance();
    $trades      = array_reverse(kf_get_trades());
    $instruments = kf_get_instruments();
    $settings    = kf_settings();
    $edit        = isset($_GET['edit']) ? kf_get_trade(absint($_GET['edit'])) : null;
    $edit_inst   = $edit && isset($instruments[$edit['symbol']]) ? $instruments[$edit['symbol']] : null;
    $updated     = (int) get_option('kf_last_refresh', 0);
    $errors      = (array) get_option('kf_last_errors', array());
    $f = function ($key, $default = '') use ($edit_inst) {
        return $edit_inst && isset($edit_inst[$key]) ? $edit_inst[$key] : $default;
    };
    ?>
    <div class="wrap kf-admin">
        <style>
            .kf-admin .kf-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:16px 0}
            .kf-admin .kf-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 14px}
            .kf-admin .kf-card span{display:block;color:#646970;font-size:12px}
            .kf-admin .kf-card strong{font-size:20px;font-variant-numeric:tabular-nums}
            .kf-admin .kf-card small{display:block;color:#646970;font-variant-numeric:tabular-nums;margin-top:2px}
            .kf-admin .up{color:#1d7a4f}.kf-admin .down{color:#b3392f}
            .kf-admin td.num,.kf-admin th.num{text-align:right;font-variant-numeric:tabular-nums}
            .kf-admin .kf-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;align-items:end;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px}
            .kf-admin .kf-form label{display:grid;gap:4px;font-weight:600;font-size:12px}
            .kf-admin .kf-form input,.kf-admin .kf-form select{width:100%}
            .kf-admin .kf-search{grid-column:1/-1;position:relative}
            .kf-admin .kf-results{position:absolute;z-index:10;left:0;right:0;top:100%;margin:2px 0 0;padding:0;list-style:none;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-height:320px;overflow:auto;box-shadow:0 4px 12px rgba(0,0,0,.08)}
            .kf-admin .kf-results li{padding:8px 10px;border-bottom:1px solid #f0f0f1;cursor:pointer;display:flex;justify-content:space-between;gap:10px}
            .kf-admin .kf-results li:hover,.kf-admin .kf-results li[aria-selected="true"]{background:#f0f6fc}
            .kf-admin .kf-results small{color:#646970}
            .kf-admin .kf-chip{font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px;white-space:nowrap;align-self:center}
            .kf-admin .kf-chip.auto{background:#e7f5ee;color:#1d7a4f}.kf-admin .kf-chip.manual{background:#fcf0e3;color:#8a5a00}
            .kf-admin .kf-picked{grid-column:1/-1;padding:8px 10px;background:#f6f7f7;border-radius:4px}
            .kf-admin .kf-side{font-weight:600;text-transform:uppercase;font-size:11px}
            .kf-admin .kf-hint{font-weight:400;color:#646970;min-height:1.2em}
            .kf-admin .kf-hint.short{color:#b32d2e}
            .kf-admin .kf-inline{display:flex;gap:6px;justify-content:flex-end;align-items:center}
            .kf-admin .kf-inline input{width:110px;text-align:right}
        </style>

        <h1>Kai$erFinance ledger</h1>

        <?php if (!empty($_GET['kf_msg'])) : ?>
            <div class="notice notice-<?php echo ($_GET['kf_type'] ?? '') === 'error' ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html(rawurldecode(wp_unslash($_GET['kf_msg']))); ?></p></div>
        <?php endif; ?>

        <?php if ($settings['api_key'] === '') : ?>
            <div class="notice notice-warning"><p>Add your Twelve Data API key under <a href="<?php echo esc_url(admin_url('admin.php?page=kaiserfinance-settings')); ?>">Settings</a> so prices can update.</p></div>
        <?php elseif ($errors) : ?>
            <div class="notice notice-warning"><p>Last price update: <?php echo esc_html(implode(' · ', $errors)); ?></p></div>
        <?php endif; ?>
        <?php foreach ($perf['pending'] as $p) : ?>
            <div class="notice notice-info"><p><?php echo esc_html($p); ?></p></div>
        <?php endforeach; ?>
        <?php foreach ($perf['warnings'] as $w) : ?>
            <div class="notice notice-warning"><p><?php echo esc_html($w); ?></p></div>
        <?php endforeach; ?>
        <?php if ($perf['trades_only'] && $trades) : ?>
            <div class="notice notice-info"><p>No cash entries yet, so returns are measured only on the money that went into trades. Log his deposits under <a href="#kf-cash">Cash</a> to track the whole account, including idle cash.</p></div>
        <?php endif; ?>

        <p>
            Prices <?php echo $updated ? 'last updated ' . esc_html(human_time_diff($updated)) . ' ago' : 'not updated yet'; ?>.
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=kf_refresh'), 'kf_refresh')); ?>">Update prices now</a>
        </p>

        <?php $t = $perf['totals']; $s = $perf['stats']; ?>
        <div class="kf-cards">
            <?php $c = ($t && $t['chf']) ? $t['chf'] : null; $sc = ($s && $s['chf']) ? $s['chf'] : null; ?>
            <div class="kf-card"><span>Account value</span><strong><?php echo esc_html(kf_money($t ? $t['value'] : 0)); ?></strong><?php if ($c) : ?><small><?php echo esc_html(kf_money($c['value'], 'CHF')); ?></small><?php endif; ?></div>
            <div class="kf-card"><span><?php echo $perf['trades_only'] ? 'Money put into trades' : 'Net deposits'; ?></span><strong><?php echo esc_html(kf_money($t ? $t['net_in'] : 0)); ?></strong><?php if ($c) : ?><small><?php echo esc_html(kf_money($c['net_in'], 'CHF')); ?></small><?php endif; ?></div>
            <div class="kf-card"><span>Gain / loss</span><strong class="<?php echo $t && $t['pl'] < 0 ? 'down' : 'up'; ?>"><?php echo esc_html(kf_money($t ? $t['pl'] : 0)); ?></strong><?php if ($c) : ?><small><?php echo esc_html(kf_money($c['pl'], 'CHF')); ?></small><?php endif; ?></div>
            <div class="kf-card"><span>Return (time-weighted)</span><strong><?php echo esc_html(kf_pct($s ? $s['portfolio'] : null)); ?></strong><?php if ($sc) : ?><small><?php echo esc_html(kf_pct($sc['portfolio'])); ?> in CHF</small><?php endif; ?></div>
            <div class="kf-card"><?php $bn = kf_benchmark_catalog(); $bname = isset($bn[$settings['benchmark']]) ? $bn[$settings['benchmark']][0] : $settings['benchmark']; ?><span><?php echo esc_html($bname); ?>, same period</span><strong><?php echo esc_html(kf_pct($s ? $s['benchmark'] : null)); ?></strong><?php if ($sc) : ?><small><?php echo esc_html(kf_pct($sc['benchmark'])); ?> in CHF</small><?php endif; ?></div>
        </div>

        <h2>Holdings</h2>
        <table class="widefat striped">
            <thead><tr><th>Asset</th><th class="num">Qty</th><th class="num">Avg cost</th><th class="num">Last price</th><th class="num">Value (USD)</th><th class="num">Unrealized (USD)</th></tr></thead>
            <tbody>
            <?php if (!$perf['holdings']) : ?>
                <tr><td colspan="6">No open positions yet.</td></tr>
            <?php else : foreach ($perf['holdings'] as $h) : ?>
                <tr>
                    <td>
                        <?php echo esc_html($h['name']); ?><br>
                        <small><code><?php echo esc_html($h['symbol']); ?></code><?php echo $h['exchange'] ? ' · ' . esc_html($h['exchange']) : ''; ?> · <?php echo esc_html($h['currency']); ?>
                        <?php echo $h['mode'] === 'manual' ? ' · <span class="kf-chip manual">Manual price</span>' : ''; ?></small>
                    </td>
                    <td class="num"><?php echo esc_html(kf_qty($h['qty'])); ?></td>
                    <td class="num"><?php echo esc_html(kf_money($h['avg_cost'], $h['currency'])); ?></td>
                    <td class="num">
                        <?php if ($h['mode'] === 'manual') : ?>
                            <form class="kf-inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="kf_set_price">
                                <input type="hidden" name="ikey" value="<?php echo esc_attr($h['ikey']); ?>">
                                <?php wp_nonce_field('kf_set_price'); ?>
                                <input name="price" inputmode="decimal" aria-label="New price for <?php echo esc_attr($h['name']); ?> in <?php echo esc_attr($h['currency']); ?>" value="<?php echo esc_attr($h['price'] === null ? '' : rtrim(rtrim(number_format($h['price'], 6, '.', ''), '0'), '.')); ?>">
                                <button class="button button-small">Set</button>
                            </form>
                            <small><?php echo esc_html($h['currency']); ?><?php echo $h['price_day'] ? ', set ' . esc_html(human_time_diff(strtotime($h['price_day'] . ' 12:00:00 UTC'))) . ' ago' : ''; ?></small>
                        <?php else : ?>
                            <?php echo esc_html(kf_money($h['price'], $h['currency'])); ?><?php echo $h['priced'] ? '' : ' <em>(trade price)</em>'; ?>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?php echo esc_html(kf_money($h['value'])); ?></td>
                    <td class="num <?php echo $h['unrealized'] < 0 ? 'down' : 'up'; ?>"><?php echo esc_html(kf_money($h['unrealized'])); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <h2 id="kf-form"><?php echo $edit ? 'Edit trade' : 'Add a trade'; ?></h2>
        <form class="kf-form" id="kf-trade-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
            <input type="hidden" name="action" value="kf_save_trade">
            <?php if ($edit) : ?><input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>"><?php endif; ?>
            <?php wp_nonce_field('kf_save_trade'); ?>
            <input type="hidden" name="mic_code" id="kf-mic" value="<?php echo esc_attr($f('mic_code')); ?>">
            <input type="hidden" name="country" id="kf-country" value="<?php echo esc_attr($f('country')); ?>">
            <input type="hidden" name="type" id="kf-type" value="<?php echo esc_attr($f('type')); ?>">

            <label class="kf-search" for="kf-q">Find the asset (name or ticker)
                <input id="kf-q" type="search" placeholder="e.g. Northern Star, NST, gold, BTC" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="kf-results">
                <ul class="kf-results" id="kf-results" role="listbox" hidden></ul>
            </label>
            <div class="kf-picked" id="kf-picked" <?php echo $edit_inst ? '' : 'hidden'; ?>>
                <?php if ($edit_inst) : ?>
                    <?php echo esc_html($edit_inst['name']); ?> · <code><?php echo esc_html($edit_inst['symbol']); ?></code><?php echo $edit_inst['exchange'] ? ' · ' . esc_html($edit_inst['exchange']) : ''; ?> · <?php echo esc_html($edit_inst['currency']); ?>
                <?php endif; ?>
            </div>

            <label for="kf-side">Side
                <select id="kf-side" name="side">
                    <option value="buy" <?php selected($edit ? $edit['side'] : 'buy', 'buy'); ?>>Buy</option>
                    <option value="sell" <?php selected($edit ? $edit['side'] : '', 'sell'); ?>>Sell</option>
                </select>
            </label>
            <label for="kf-symbol">Symbol
                <input id="kf-symbol" name="symbol" required value="<?php echo esc_attr($f('symbol', $edit ? $edit['symbol'] : '')); ?>" placeholder="XAU/USD">
            </label>
            <label for="kf-exchange">Exchange
                <input id="kf-exchange" name="exchange" value="<?php echo esc_attr($f('exchange')); ?>" placeholder="Filled by search">
            </label>
            <label for="kf-name">Name
                <input id="kf-name" name="name" value="<?php echo esc_attr($edit ? $edit['name'] : ''); ?>" placeholder="Gold">
            </label>
            <label for="kf-currency">Currency
                <input id="kf-currency" name="currency" maxlength="3" required value="<?php echo esc_attr($f('currency', 'USD')); ?>">
            </label>
            <label for="kf-mode">Prices
                <select id="kf-mode" name="mode">
                    <option value="auto" <?php selected($f('mode', 'auto'), 'auto'); ?>>Automatic (hourly)</option>
                    <option value="manual" <?php selected($f('mode', 'auto'), 'manual'); ?>>Manual (I type them)</option>
                </select>
            </label>
            <label for="kf-qty">Quantity
                <input id="kf-qty" name="qty" inputmode="decimal" required value="<?php echo esc_attr($edit ? kf_qty($edit['qty']) : ''); ?>" placeholder="200">
            </label>
            <label for="kf-price"><span>Price per unit (<span id="kf-price-cur"><?php echo esc_html($f('currency', 'USD')); ?></span>)</span>
                <input id="kf-price" name="price" inputmode="decimal" required value="<?php echo esc_attr($edit ? rtrim(rtrim($edit['price'], '0'), '.') : ''); ?>" placeholder="">
                <small id="kf-price-hint" class="kf-hint"></small>
            </label>
            <label for="kf-date">Date
                <input id="kf-date" name="trade_date" type="date" required value="<?php echo esc_attr($edit ? $edit['trade_date'] : gmdate('Y-m-d')); ?>">
            </label>
            <label for="kf-note">Note (private)
                <input id="kf-note" name="note" value="<?php echo esc_attr($edit ? $edit['note'] : ''); ?>">
            </label>
            <div>
                <?php submit_button($edit ? 'Save changes' : 'Add to ledger', 'primary', 'submit', false); ?>
                <?php if ($edit) : ?> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=kaiserfinance')); ?>">Cancel</a><?php endif; ?>
            </div>
        </form>
        <p class="description">
            Search shows each listing with its exchange and currency, so the same ticker on two exchanges can't be mixed up.
            Listings marked <span class="kf-chip auto">Auto</span> update hourly on the free plan; <span class="kf-chip manual">Manual price</span> ones
            (e.g. ASX stocks) you price by hand in Holdings. Enter the price in the asset's own currency; everything is converted to USD automatically.
            Metals are per troy ounce (1 kg = 32.1507 oz).
        </p>

        <h2>Ledger</h2>
        <table class="widefat striped">
            <thead><tr><th>Date</th><th>Side</th><th>Asset</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Total</th><th>Note</th><th></th></tr></thead>
            <tbody>
            <?php if (!$trades) : ?>
                <tr><td colspan="8">No trades yet. Search for the first asset above, for example "gold".</td></tr>
            <?php else : foreach ($trades as $tr) :
                $inst = isset($instruments[$tr['symbol']]) ? $instruments[$tr['symbol']] : null;
                $cur  = $inst ? $inst['currency'] : 'USD';
                $del  = wp_nonce_url(admin_url('admin-post.php?action=kf_delete_trade&id=' . (int) $tr['id']), 'kf_delete_trade_' . (int) $tr['id']); ?>
                <tr>
                    <td><?php echo esc_html($tr['trade_date']); ?></td>
                    <td class="kf-side <?php echo $tr['side'] === 'buy' ? 'up' : 'down'; ?>"><?php echo esc_html($tr['side']); ?></td>
                    <td><?php echo esc_html($tr['name']); ?> <code><?php echo esc_html($inst ? $inst['symbol'] : $tr['symbol']); ?></code><?php echo $inst && $inst['exchange'] ? ' <small>' . esc_html($inst['exchange']) . '</small>' : ''; ?></td>
                    <td class="num"><?php echo esc_html(kf_qty($tr['qty'])); ?></td>
                    <td class="num"><?php echo esc_html(kf_money((float) $tr['price'], $cur)); ?></td>
                    <td class="num"><?php echo esc_html(kf_money((float) $tr['qty'] * (float) $tr['price'], $cur)); ?></td>
                    <td><?php echo esc_html($tr['note']); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=kaiserfinance&edit=' . (int) $tr['id'] . '#kf-form')); ?>">Edit</a> ·
                        <a href="<?php echo esc_url($del); ?>" onclick="return confirm('Delete this trade? This cannot be undone.');" style="color:#b32d2e">Delete</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php
        $cash_rows  = array_reverse(kf_get_cash());
        $cash_types = kf_cash_types();
        ?>
        <h2 id="kf-cash">Cash</h2>
        <p class="description">Buys are paid from, and sells paid into, cash in the asset's currency. Log deposits and withdrawals so the return covers the whole account; log a currency exchange when he converts, e.g. CHF to USD before buying gold.</p>

        <table class="widefat striped" style="max-width:640px;margin:12px 0 18px">
            <thead><tr><th>Currency</th><th class="num">Balance</th><th class="num">In USD</th></tr></thead>
            <tbody>
            <?php if (!$perf['cash']) : ?>
                <tr><td colspan="3">No cash balances yet.</td></tr>
            <?php else : foreach ($perf['cash'] as $c) : ?>
                <tr>
                    <td><?php echo esc_html($c['currency']); ?></td>
                    <td class="num <?php echo $c['amount'] < 0 ? 'down' : ''; ?>"><?php echo esc_html(kf_money($c['amount'], $c['currency'])); ?></td>
                    <td class="num"><?php echo esc_html(kf_money($c['usd'])); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <form class="kf-form" id="kf-cash-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
            <input type="hidden" name="action" value="kf_save_cash">
            <?php wp_nonce_field('kf_save_cash'); ?>
            <label for="kf-c-type">Type
                <select id="kf-c-type" name="type">
                    <?php foreach ($cash_types as $k => $label) : ?>
                        <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label for="kf-c-date">Date
                <input id="kf-c-date" name="flow_date" type="date" required value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
            </label>
            <label for="kf-c-cur"><span id="kf-c-cur-label">Currency</span>
                <select id="kf-c-cur" name="currency" required><?php echo kf_currency_options('CHF'); ?></select>
            </label>
            <label for="kf-c-amt"><span id="kf-c-amt-label">Amount</span>
                <input id="kf-c-amt" name="amount" inputmode="decimal" required placeholder="10000">
            </label>
            <label for="kf-c-cur2" class="kf-ex" hidden>Received currency
                <select id="kf-c-cur2" name="currency2"><?php echo kf_currency_options('USD'); ?></select>
            </label>
            <label for="kf-c-amt2" class="kf-ex" hidden>Received amount
                <input id="kf-c-amt2" name="amount2" inputmode="decimal" placeholder="12500">
            </label>
            <label for="kf-c-note">Note (private)
                <input id="kf-c-note" name="note">
            </label>
            <div><?php submit_button('Add cash entry', 'primary', 'submit', false); ?></div>
        </form>
        <script>
        (function () {
            var type = document.getElementById('kf-c-type');
            function sync() {
                var ex = type.value === 'exchange';
                document.querySelectorAll('#kf-cash-form .kf-ex').forEach(function (n) { n.hidden = !ex; });
                document.getElementById('kf-c-cur2').required = ex;
                document.getElementById('kf-c-amt2').required = ex;
                document.getElementById('kf-c-cur-label').textContent = ex ? 'Paid currency' : 'Currency';
                document.getElementById('kf-c-amt-label').textContent = ex ? 'Paid amount' : 'Amount';
            }
            type.addEventListener('change', sync);
            sync();
        })();
        </script>

        <table class="widefat striped" style="margin-top:16px">
            <thead><tr><th>Date</th><th>Type</th><th class="num">Amount</th><th>Note</th><th></th></tr></thead>
            <tbody>
            <?php if (!$cash_rows) : ?>
                <tr><td colspan="5">No cash entries yet. Start with his first deposit, e.g. Deposit · CHF · 10000.</td></tr>
            <?php else : foreach ($cash_rows as $c) :
                $del = wp_nonce_url(admin_url('admin-post.php?action=kf_delete_cash&id=' . (int) $c['id']), 'kf_delete_cash_' . (int) $c['id']);
                $sign = in_array($c['type'], array('withdrawal', 'fee'), true) ? -1 : 1; ?>
                <tr>
                    <td><?php echo esc_html($c['flow_date']); ?></td>
                    <td><?php echo esc_html(isset($cash_types[$c['type']]) ? $cash_types[$c['type']] : $c['type']); ?></td>
                    <td class="num">
                        <?php if ($c['type'] === 'exchange') : ?>
                            <?php echo esc_html(kf_money(-(float) $c['amount'], $c['currency'])); ?> → <?php echo esc_html(kf_money((float) $c['amount2'], $c['currency2'])); ?>
                        <?php else : ?>
                            <span class="<?php echo $sign < 0 ? 'down' : 'up'; ?>"><?php echo esc_html(kf_money($sign * (float) $c['amount'], $c['currency'])); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($c['note']); ?></td>
                    <td><a href="<?php echo esc_url($del); ?>" onclick="return confirm('Delete this cash entry? This cannot be undone.');" style="color:#b32d2e">Delete</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* ---------- settings ---------- */

function kf_register_settings() {
    register_setting('kf_settings_group', 'kf_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'kf_sanitize_settings',
        'default'           => kf_default_settings(),
    ));
}

function kf_sanitize_settings($in) {
    $out = kf_settings();
    $key = isset($in['api_key']) ? trim(sanitize_text_field($in['api_key'])) : '';
    if ($key !== '') {
        $out['api_key'] = $key; // empty field = keep the saved key
    }
    $catalog = kf_benchmark_catalog();
    $out['benchmark']       = 'SPY'; // S&P 500 is the default comparison; visitors add others themselves
    $level = $in['public_amounts'] ?? 'none';
    $out['public_amounts']  = in_array($level, array('none', 'totals', 'holdings'), true) ? $level : 'none';
    $repo = trim(sanitize_text_field($in['github_repo'] ?? ''));
    $repo = preg_replace('#^(https?://)?(www\.)?github\.com/#i', '', rtrim($repo, '/'));
    $repo = preg_replace('#\.git$#', '', $repo);
    $out['github_repo'] = preg_match('#^[\w.-]+/[\w.-]+$#', $repo) ? $repo : kf_default_settings()['github_repo'];
    if ($out['github_repo'] !== kf_settings()['github_repo']) {
        delete_transient('kf_remote_version');
        delete_site_transient('update_plugins');
    }
    foreach (array('youtube_url' => 'youtube.com', 'instagram_url' => 'instagram.com') as $key => $host) {
        $url = esc_url_raw(trim((string) ($in[$key] ?? '')), array('https'));
        $out[$key] = ($url !== '' && stripos((string) wp_parse_url($url, PHP_URL_HOST), $host) !== false) ? $url : '';
    }
    $out['show_allocation'] = empty($in['show_allocation']) ? 0 : 1;
    $out['standalone']      = empty($in['standalone']) ? 0 : 1;
    kf_flush_cache();
    return $out;
}

function kf_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $s = kf_settings();
    ?>
    <div class="wrap">
        <h1>Kai$erFinance settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('kf_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="kf-api">Twelve Data API key</label></th>
                    <td>
                        <input id="kf-api" type="password" class="regular-text" name="kf_settings[api_key]" value="" autocomplete="off" placeholder="<?php echo $s['api_key'] ? 'Saved. Leave empty to keep it.' : 'Paste your key'; ?>">
                        <p class="description">Free key from twelvedata.com. It stays on your server and is never sent to visitors.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Public amounts</th>
                    <td>
                        <fieldset>
                            <label style="display:block;margin-bottom:6px"><input type="radio" name="kf_settings[public_amounts]" value="none" <?php checked($s['public_amounts'], 'none'); ?>> Percentages only</label>
                            <label style="display:block;margin-bottom:6px"><input type="radio" name="kf_settings[public_amounts]" value="totals" <?php checked($s['public_amounts'], 'totals'); ?>> Also totals: account value, money paid in, gain or loss</label>
                            <label style="display:block"><input type="radio" name="kf_settings[public_amounts]" value="holdings" <?php checked($s['public_amounts'], 'holdings'); ?>> Also every holding: quantity and value</label>
                        </fieldset>
                        <p class="description">Shown in USD or CHF, following the visitor's currency switch. Trades, prices paid and notes always stay private.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Allocation</th>
                    <td>
                        <label><input type="checkbox" name="kf_settings[show_allocation]" value="1" <?php checked($s['show_allocation'], 1); ?>> Show allocation in % (e.g. Gold 70%, Silver 30%)</label>
                        <p class="description">Always shown when "every holding" is selected above.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Social links</th>
                    <td>
                        <p><label for="kf-yt" style="display:inline-block;min-width:80px">YouTube</label> <input id="kf-yt" type="url" class="regular-text" name="kf_settings[youtube_url]" value="<?php echo esc_attr($s['youtube_url']); ?>" placeholder="https://www.youtube.com/@yourchannel"></p>
                        <p><label for="kf-ig" style="display:inline-block;min-width:80px">Instagram</label> <input id="kf-ig" type="url" class="regular-text" name="kf_settings[instagram_url]" value="<?php echo esc_attr($s['instagram_url']); ?>" placeholder="https://www.instagram.com/yourname/"></p>
                        <p class="description">Shown as icons in the homepage header and footer. Leave a field empty to hide that icon. Plain links: nothing is loaded from YouTube or Instagram.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="kf-repo">Updates from GitHub</label></th>
                    <td>
                        <input id="kf-repo" class="regular-text" name="kf_settings[github_repo]" value="<?php echo esc_attr($s['github_repo']); ?>" placeholder="github.com/yourname/kaiserfinance">
                        <p class="description">Paste the public repo address. New versions then show up under Plugins as "Update available"; switch on "Enable auto-updates" there to install them automatically.</p>
                        <?php $st = kf_updater_status(); ?>
                        <p id="kf-updates" class="description" style="margin-top:8px">
                            <strong>Last check:</strong>
                            <?php echo $st ? esc_html(wp_date('d.m.Y H:i', $st['time']) . ' · ' . $st['message']) : 'not yet'; ?>
                            · <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=kf_check_updates'), 'kf_check_updates')); ?>">Check GitHub now</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Homepage design</th>
                    <td>
                        <label><input type="checkbox" name="kf_settings[standalone]" value="1" <?php checked($s['standalone'], 1); ?>> Draw the homepage with Kai$erFinance's own design (header, chart, "Powered by shokulab" footer), without the theme</label>
                        <p class="description">Untick to show the homepage through the WordPress theme instead.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save settings'); ?>
        </form>

        <hr style="margin:32px 0">
        <h2>Reset ledger</h2>
        <?php if (!empty($_GET['kf_msg'])) : ?>
            <div class="notice notice-<?php echo ($_GET['kf_type'] ?? '') === 'error' ? 'error' : 'success'; ?>"><p><?php echo esc_html(rawurldecode(wp_unslash($_GET['kf_msg']))); ?></p></div>
        <?php endif; ?>
        <p>Deletes every trade, cash entry, asset and stored price, so the public page starts from zero. Settings and the API key are kept. This can't be undone.</p>
<?php
        global $wpdb;
        $n_trades = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . kf_table('trades'));
        $n_cash   = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . kf_table('cash'));
        $n_assets = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . kf_table('instruments'));
        ?>
        <form id="kf-reset-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="action" value="kf_reset">
            <?php wp_nonce_field('kf_reset'); ?>
            <label for="kf-reset-confirm">Type <code>RESET</code> to confirm</label>
            <input id="kf-reset-confirm" name="confirm" autocomplete="off" style="width:120px">
            <button type="button" id="kf-reset-open" class="button" style="color:#b32d2e;border-color:#b32d2e" disabled>Reset ledger…</button>
        </form>
        <dialog id="kf-reset-dialog" style="border:0;border-radius:8px;padding:24px;max-width:440px;box-shadow:0 12px 40px rgba(0,0,0,.25)">
            <h2 style="margin-top:0">Delete everything?</h2>
            <p>This permanently deletes:</p>
            <ul style="list-style:disc;margin-left:20px">
                <li><?php echo (int) $n_trades; ?> trades</li>
                <li><?php echo (int) $n_cash; ?> cash entries</li>
                <li><?php echo (int) $n_assets; ?> assets and all their stored prices</li>
            </ul>
            <p>The public page goes back to zero. Settings and the API key are kept. This can't be undone.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button type="button" class="button" id="kf-reset-cancel">Cancel</button>
                <button type="button" class="button button-primary" id="kf-reset-yes" style="background:#b32d2e;border-color:#b32d2e">Yes, delete everything</button>
            </div>
        </dialog>
        <script>
        (function () {
            var input = document.getElementById('kf-reset-confirm'), open = document.getElementById('kf-reset-open');
            var dlg = document.getElementById('kf-reset-dialog'), form = document.getElementById('kf-reset-form');
            input.addEventListener('input', function () { open.disabled = input.value.trim() !== 'RESET'; });
            open.addEventListener('click', function () { if (dlg.showModal) dlg.showModal(); });
            document.getElementById('kf-reset-cancel').addEventListener('click', function () { dlg.close(); });
            document.getElementById('kf-reset-yes').addEventListener('click', function () { this.disabled = true; form.submit(); });
        })();
        </script>
    </div>
    <?php
}
