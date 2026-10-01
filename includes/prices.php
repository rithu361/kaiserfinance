<?php
/**
 * Price updates from Twelve Data (free plan: 800 requests/day, 8/minute).
 *
 * One request per series per refresh: the daily time series from the last stored
 * day up to today. That returns today's latest price AND fills any days missed
 * while nobody visited the site. Refreshes are triggered by WP-Cron, which runs
 * on page visits, so a quiet site makes almost no API calls.
 *
 * Series fetched: every instrument in "auto" mode, the benchmark (SPY), and the
 * forex pair for every non-USD currency in the ledger (e.g. AUD/USD), so trades
 * in other currencies convert to USD. Instruments in "manual" mode are skipped:
 * their prices come from the ledger page.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('kf_refresh_event', 'kf_refresh_prices');

/**
 * Series to fetch: key => ['from' => earliest date needed, 'params' => request params].
 */
function kf_series_needed() {
    $trades = kf_get_trades();
    $cash   = kf_get_cash();
    if (!$trades && !$cash) {
        return array();
    }
    $instruments = kf_get_instruments();
    $need = array();

    $add = function ($key, $from, $params) use (&$need) {
        if (!isset($need[$key]) || $from < $need[$key]['from']) {
            $need[$key] = array('from' => $from, 'params' => $params);
        }
    };

    foreach ($trades as $t) {
        $ikey = $t['symbol'];
        $inst = isset($instruments[$ikey]) ? $instruments[$ikey] : null;

        if (!$inst || $inst['mode'] !== 'manual') {
            $params = array('symbol' => $inst ? $inst['symbol'] : $ikey);
            if ($inst && $inst['mic_code'] !== '') {
                $params['mic_code'] = $inst['mic_code'];
            }
            $add($ikey, $t['trade_date'], $params);
        }

        $fx = kf_fx_for($inst ? $inst['currency'] : 'USD');
        if ($fx) {
            $add($fx[0], $t['trade_date'], array('symbol' => $fx[0]));
        }
    }

    foreach ($cash as $c) {
        foreach (array($c['currency'], $c['currency2']) as $cur) {
            $fx = $cur === '' ? null : kf_fx_for($cur);
            if ($fx) {
                $add($fx[0], $c['flow_date'], array('symbol' => $fx[0]));
            }
        }
    }

    $first = '9999-12-31';
    if ($trades) {
        $first = $trades[0]['trade_date'];
    }
    if ($cash && $cash[0]['flow_date'] < $first) {
        $first = $cash[0]['flow_date'];
    }
    foreach (kf_active_benchmarks() as $bench) {
        $add($bench, $first, array('symbol' => $bench));
    }
    // Always fetch the franc, for the CHF view.
    $add('CHF/USD', $first, array('symbol' => 'CHF/USD'));

    return $need;
}

/**
 * Fetch prices. Returns true, false (nothing to do / already running) or WP_Error.
 */
function kf_refresh_prices() {
    global $wpdb;
    $settings = kf_settings();
    if ($settings['api_key'] === '') {
        update_option('kf_last_errors', array('No Twelve Data API key yet. Add it under Kai$erFinance → Settings.'), false);
        return new WP_Error('kf_no_key', 'No API key');
    }
    if (get_transient('kf_refresh_lock')) {
        return false;
    }
    set_transient('kf_refresh_lock', 1, 120);

    $table  = kf_table('prices');
    $needed = kf_series_needed();
    if (!$needed) {
        delete_transient('kf_refresh_lock');
        return false;
    }

    // Work out where each series must start; stalest go first.
    $plan = array();
    foreach ($needed as $key => $n) {
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT MIN(day) AS mn, MAX(day) AS mx FROM $table WHERE symbol = %s", $key),
            ARRAY_A
        );
        $want  = gmdate('Y-m-d', strtotime($n['from'] . ' -7 days')); // slack for weekends/holidays
        $start = (empty($row['mn']) || $row['mn'] > $want) ? $want : $row['mx'];
        $plan[$key] = array(
            'start'  => $start,
            'latest' => empty($row['mx']) ? '0000-00-00' : $row['mx'],
            'params' => $n['params'],
        );
    }
    uasort($plan, function ($a, $b) {
        return strcmp($a['latest'], $b['latest']);
    });
    // Stay under the free plan's 8 requests per minute. Remaining series go next run,
    // which comes sooner while some series have no prices at all yet (see below).
    $plan = array_slice($plan, 0, 7, true);

    $errors = array();
    foreach ($plan as $key => $p) {
        $url = 'https://api.twelvedata.com/time_series?' . http_build_query(array_merge($p['params'], array(
            'interval'   => '1day',
            'start_date' => $p['start'],
            'outputsize' => 5000,
            'order'      => 'ASC',
            'apikey'     => $settings['api_key'],
        )));
        $res = wp_remote_get($url, array('timeout' => 15));
        if (is_wp_error($res)) {
            $errors[] = $key . ': ' . $res->get_error_message();
            continue;
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($body) || (isset($body['status']) && $body['status'] === 'error') || empty($body['values'])) {
            $msg = is_array($body) && isset($body['message']) ? $body['message'] : 'no data returned';
            // "No data is available on the specified dates" just means nothing new (e.g. a holiday).
            if (stripos($msg, 'no data is available') === false) {
                $errors[] = $key . ': ' . $msg;
            }
            if (is_array($body) && isset($body['code']) && (int) $body['code'] === 429) {
                break; // rate limited: stop, try again next run
            }
            continue;
        }
        foreach ($body['values'] as $v) {
            $close = isset($v['close']) ? (float) $v['close'] : 0;
            if ($close <= 0 || empty($v['datetime'])) {
                continue;
            }
            $wpdb->replace(
                $table,
                array('symbol' => $key, 'day' => substr($v['datetime'], 0, 10), 'close' => $close),
                array('%s', '%s', '%f')
            );
        }
    }

    // If some series still have no prices (e.g. right after new benchmarks were added),
    // let the next visit a minute or two from now fetch the rest.
    $missing = 0;
    foreach (array_keys($needed) as $key) {
        if (!$wpdb->get_var($wpdb->prepare("SELECT 1 FROM $table WHERE symbol = %s LIMIT 1", $key))) {
            $missing++;
        }
    }
    update_option('kf_last_refresh', time(), false);
    update_option('kf_refresh_soon', $missing ? 1 : 0, false);
    update_option('kf_last_errors', $errors, false);
    kf_flush_cache();
    delete_transient('kf_refresh_lock');

    return $errors ? new WP_Error('kf_partial', implode('; ', $errors)) : true;
}

/** Store a manually entered price (in the instrument's own currency) for a day. */
function kf_set_manual_price($ikey, $price, $day = null) {
    global $wpdb;
    $wpdb->replace(
        kf_table('prices'),
        array('symbol' => $ikey, 'day' => $day ? $day : gmdate('Y-m-d'), 'close' => (float) $price),
        array('%s', '%s', '%f')
    );
    kf_flush_cache();
}

/**
 * Search Twelve Data's instrument list. Returns a list of matches, each with
 * whether the free plan can price it automatically.
 */
function kf_symbol_search($query) {
    $settings = kf_settings();
    $args = array('symbol' => $query, 'outputsize' => 30, 'show_plan' => 'true');
    if ($settings['api_key'] !== '') {
        $args['apikey'] = $settings['api_key'];
    }
    $res = wp_remote_get('https://api.twelvedata.com/symbol_search?' . http_build_query($args), array('timeout' => 10));
    if (is_wp_error($res)) {
        return $res;
    }
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($body) || empty($body['data']) || !is_array($body['data'])) {
        return array();
    }

    $free_types = array('physical currency', 'digital currency', 'forex', 'crypto');
    $out = array();
    foreach ($body['data'] as $d) {
        $type    = isset($d['instrument_type']) ? (string) $d['instrument_type'] : '';
        $country = isset($d['country']) ? (string) $d['country'] : '';
        $plan    = isset($d['access']['plan']) ? (string) $d['access']['plan'] : '';
        if ($plan !== '') {
            $auto = strcasecmp($plan, 'Basic') === 0;
        } else {
            $auto = $country === 'United States' || in_array(strtolower($type), $free_types, true);
        }
        $is_pair = strpos((string) $d['symbol'], '/') !== false; // metals, forex, crypto: no exchange needed
        $currency = isset($d['currency']) && $d['currency'] !== '' ? (string) $d['currency'] : '';
        if ($currency === '' && $is_pair) {
            $parts = explode('/', (string) $d['symbol']);
            $currency = isset($parts[1]) ? $parts[1] : 'USD';
        }
        $out[] = array(
            'symbol'   => (string) $d['symbol'],
            'name'     => isset($d['instrument_name']) ? (string) $d['instrument_name'] : (string) $d['symbol'],
            'exchange' => isset($d['exchange']) ? (string) $d['exchange'] : '',
            'mic_code' => $is_pair ? '' : (isset($d['mic_code']) ? (string) $d['mic_code'] : ''),
            'country'  => $country,
            'type'     => $type,
            'currency' => $currency !== '' ? $currency : 'USD',
            'auto'     => $auto,
            'plan'     => $plan,
        );
    }
    // Free-to-price matches first, then the rest.
    usort($out, function ($a, $b) {
        return (int) $b['auto'] - (int) $a['auto'];
    });
    return $out;
}

/**
 * Safety net for setups where WP-Cron's background request can't reach the site
 * (some tunnels/containers block it). If prices are far overdue, refresh at the
 * end of the current page load instead.
 */
function kf_maybe_refresh_fallback() {
    $settings = kf_settings();
    $interval = max(15, (int) $settings['refresh_minutes']) * 60;
    $age      = time() - (int) get_option('kf_last_refresh', 0);
    $soon     = get_option('kf_refresh_soon') && $age > 90; // series still waiting for their first prices
    if ((!$soon && $age < 3 * $interval) || get_transient('kf_refresh_lock') || $settings['api_key'] === '') {
        return;
    }
    add_action('shutdown', function () {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // send the page to the visitor first, where supported
        }
        ignore_user_abort(true);
        kf_refresh_prices();
    }, 100);
}
