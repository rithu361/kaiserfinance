<?php
/**
 * Database tables, settings, instruments and trades.
 *
 * An "instrument" is one tradable thing on one exchange (e.g. NST on the ASX).
 * Its key (ikey) is what trades and prices refer to:
 *   - metals, forex, crypto and anything without an exchange: the symbol itself, e.g. "XAU/USD"
 *   - stocks/ETFs: symbol@MIC, e.g. "NST@XASX", so the same ticker on two exchanges never mixes up
 * Prices are stored in the instrument's own currency and converted to USD for all maths.
 */

if (!defined('ABSPATH')) {
    exit;
}

function kf_table($name) {
    global $wpdb;
    return $wpdb->prefix . 'kf_' . $name;
}

function kf_default_settings() {
    return array(
        'api_key'         => '',
        'benchmark'       => 'SPY',   // tracks the S&P 500; available on Twelve Data's free plan
        'refresh_minutes' => 60,
        'show_allocation' => 0,       // 1 = show allocation in % on the public page
        'standalone'      => 1,       // 1 = the plugin draws the whole homepage (no theme)
        'benchmarks'      => array(), // extra benchmarks visitors can switch to (see kf_benchmark_catalog)
        'public_amounts'  => 'none',
        'github_repo'     => '',      // owner/name the plugin updates itself from  // none | totals | holdings: what real amounts the public page shows
    );
}

function kf_settings() {
    return wp_parse_args(get_option('kf_settings', array()), kf_default_settings());
}

function kf_install_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE " . kf_table('trades') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  trade_date date NOT NULL,
  side varchar(4) NOT NULL,
  symbol varchar(64) NOT NULL,
  name varchar(100) NOT NULL,
  qty decimal(24,8) NOT NULL,
  price decimal(24,8) NOT NULL,
  note varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY trade_date (trade_date)
) $charset;");

    dbDelta("CREATE TABLE " . kf_table('prices') . " (
  symbol varchar(64) NOT NULL,
  day date NOT NULL,
  close decimal(24,8) NOT NULL,
  PRIMARY KEY  (symbol,day)
) $charset;");

    dbDelta("CREATE TABLE " . kf_table('instruments') . " (
  ikey varchar(64) NOT NULL,
  symbol varchar(32) NOT NULL,
  mic_code varchar(16) NOT NULL DEFAULT '',
  exchange varchar(64) NOT NULL DEFAULT '',
  country varchar(64) NOT NULL DEFAULT '',
  name varchar(100) NOT NULL,
  type varchar(64) NOT NULL DEFAULT '',
  currency varchar(8) NOT NULL DEFAULT 'USD',
  mode varchar(8) NOT NULL DEFAULT 'auto',
  PRIMARY KEY  (ikey)
) $charset;");

    // Cash movements. type: deposit | withdrawal | exchange | income | fee
    // An exchange moves `amount` of `currency` out and `amount2` of `currency2` in.
    dbDelta("CREATE TABLE " . kf_table('cash') . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  flow_date date NOT NULL,
  type varchar(12) NOT NULL,
  currency varchar(8) NOT NULL,
  amount decimal(24,8) NOT NULL,
  currency2 varchar(8) NOT NULL DEFAULT '',
  amount2 decimal(24,8) NOT NULL DEFAULT 0,
  note varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY flow_date (flow_date)
) $charset;");

    update_option('kf_db_version', KF_VERSION);
}

function kf_activate() {
    kf_install_tables();
    add_option('kf_settings', kf_default_settings());
    if (!wp_next_scheduled('kf_refresh_event')) {
        wp_schedule_event(time() + 60, 'hourly', 'kf_refresh_event');
    }
}

function kf_deactivate() {
    wp_clear_scheduled_hook('kf_refresh_event');
}

/* Upgrade tables automatically when the plugin is updated by uploading a new zip. */
add_action('plugins_loaded', function () {
    if (get_option('kf_db_version') !== KF_VERSION) {
        kf_install_tables();
    }
});

/* ---------- instruments ---------- */

function kf_make_ikey($symbol, $mic_code) {
    $symbol = strtoupper(trim($symbol));
    $mic    = strtoupper(trim($mic_code));
    return $mic === '' ? $symbol : $symbol . '@' . $mic;
}

/** All instruments keyed by ikey. */
function kf_get_instruments() {
    global $wpdb;
    $out = array();
    foreach ($wpdb->get_results('SELECT * FROM ' . kf_table('instruments'), ARRAY_A) as $row) {
        $out[$row['ikey']] = $row;
    }
    return $out;
}

function kf_get_instrument($ikey) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . kf_table('instruments') . ' WHERE ikey = %s', $ikey),
        ARRAY_A
    );
}

function kf_save_instrument($data) {
    global $wpdb;
    $wpdb->replace(kf_table('instruments'), $data, array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));
}

/**
 * How to convert a currency to USD: [forex pair to fetch, multiplier] or null for USD.
 * London prices in pence (GBX/GBp) use GBP/USD divided by 100.
 */
function kf_fx_for($currency) {
    $c = trim((string) $currency);
    if ($c === '' || strtoupper($c) === 'USD') {
        return null;
    }
    if ($c === 'GBp' || strtoupper($c) === 'GBX') {
        return array('GBP/USD', 0.01);
    }
    if ($c === 'ZAc' || strtoupper($c) === 'ZAC') {
        return array('ZAR/USD', 0.01);
    }
    if ($c === 'ILA') {
        return array('ILS/USD', 0.01);
    }
    return array(strtoupper($c) . '/USD', 1.0);
}

/* ---------- trades ---------- */

/** All trades, oldest first. `symbol` holds the instrument key. */
function kf_get_trades() {
    global $wpdb;
    return $wpdb->get_results(
        'SELECT * FROM ' . kf_table('trades') . ' ORDER BY trade_date ASC, id ASC',
        ARRAY_A
    );
}

function kf_get_trade($id) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . kf_table('trades') . ' WHERE id = %d', $id),
        ARRAY_A
    );
}

/** Quantity currently held of an instrument, optionally ignoring one trade (when editing it). */
function kf_held_qty($ikey, $exclude_id = 0) {
    $qty = 0.0;
    foreach (kf_get_trades() as $t) {
        if ($t['symbol'] !== $ikey || (int) $t['id'] === (int) $exclude_id) {
            continue;
        }
        $qty += ($t['side'] === 'buy' ? 1 : -1) * (float) $t['qty'];
    }
    return $qty;
}

/** Call after any change to trades or prices. */
function kf_flush_cache() {
    delete_transient('kf_performance');
}

/* ---------- cash ---------- */

function kf_cash_types() {
    return array(
        'deposit'    => 'Deposit',
        'withdrawal' => 'Withdrawal',
        'exchange'   => 'Currency exchange',
        'income'     => 'Interest / dividend',
        'fee'        => 'Fee',
    );
}

/** All cash movements, oldest first. */
function kf_get_cash() {
    global $wpdb;
    return $wpdb->get_results(
        'SELECT * FROM ' . kf_table('cash') . ' ORDER BY flow_date ASC, id ASC',
        ARRAY_A
    );
}

function kf_normalize_currency($c) {
    $c = trim((string) $c);
    if (in_array($c, array('GBp', 'ZAc', 'ILA'), true)) {
        return $c;
    }
    return strtoupper($c);
}

