<?php
/**
 * Performance maths, reported in USD and CHF.
 *
 * Account value = holdings at market + cash in every currency, converted at the day's rate.
 *
 * Return is time-weighted (the way brokers report it): each day's change in account
 * value, minus that day's deposits/withdrawals, is chained together. Deposits and
 * withdrawals therefore don't count as gains or losses, but idle cash does count.
 * The CHF view runs the same calculation on values converted to CHF, so it includes
 * the effect of the dollar moving against the franc.
 *
 * Benchmarks (S&P 500, Nasdaq 100, MSCI World, Switzerland, gold, Bitcoin) are shown
 * over exactly the same days, in USD or converted to CHF.
 *
 * Until the first cash entry is logged, every buy counts as money coming in and every
 * sell as money going out ("trades only" mode), so a ledger without cash still works.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Benchmarks the public page can compare against: symbol => [English name, German name]. */
function kf_benchmark_catalog() {
    return array(
        'SPY'     => array('S&P 500', 'S&P 500'),
        'QQQ'     => array('Nasdaq 100', 'Nasdaq 100'),
        'URTH'    => array('MSCI World', 'MSCI World'),
        'EWL'     => array('Switzerland (MSCI)', 'Schweiz (MSCI)'),
        'XAU/USD' => array('Gold', 'Gold'),
        'BTC/USD' => array('Bitcoin', 'Bitcoin'),
    );
}

/** All benchmarks are tracked; visitors choose which ones they see. S&P 500 comes first (the default). */
function kf_active_benchmarks() {
    return array_keys(kf_benchmark_catalog());
}

/** Full computation, including real amounts. */
function kf_performance() {
    $cached = get_transient('kf_performance');
    if ($cached !== false) {
        return $cached;
    }

    global $wpdb;
    $settings    = kf_settings();
    $bench       = $settings['benchmark'];
    $benchmarks  = kf_active_benchmarks();
    $trades      = kf_get_trades();
    $cash_rows   = kf_get_cash();
    $instruments = kf_get_instruments();
    $trades_only = !$cash_rows;

    $out = array(
        'dates'       => array(),
        'port'        => array('USD' => array(), 'CHF' => array()),   // cumulative return, %
        'bench'       => array(),                                     // symbol => ['USD' => [], 'CHF' => []]
        'has_chf'     => false,
        'stats'       => null,
        'holdings'    => array(),
        'cash'        => array(),   // balances per currency
        'totals'      => null,
        'pending'     => array(),
        'warnings'    => array(),
        'trades_only' => $trades_only,
        'updated'     => (int) get_option('kf_last_refresh', 0),
    );

    if (!$trades && !$cash_rows) {
        set_transient('kf_performance', $out, HOUR_IN_SECONDS);
        return $out;
    }

    $px = array();
    $rows = $wpdb->get_results('SELECT symbol, day, close FROM ' . kf_table('prices') . ' ORDER BY day ASC', ARRAY_A);
    foreach ($rows as $r) {
        $px[$r['symbol']][$r['day']] = (float) $r['close'];
    }

    $currency_of = function ($ikey) use ($instruments) {
        return isset($instruments[$ikey]) ? $instruments[$ikey]['currency'] : 'USD';
    };

    // Every currency needs a rate before anything can be valued in USD.
    $currencies = array();
    foreach ($trades as $t) {
        $currencies[$currency_of($t['symbol'])] = true;
    }
    foreach ($cash_rows as $c) {
        $currencies[$c['currency']] = true;
        if ($c['currency2'] !== '') {
            $currencies[$c['currency2']] = true;
        }
    }
    foreach (array_keys($currencies) as $cur) {
        $fx = kf_fx_for($cur);
        if ($fx && empty($px[$fx[0]])) {
            $out['pending'][] = 'Waiting for the ' . $fx[0] . ' exchange rate. Press "Update prices now".';
        }
    }
    if ($out['pending']) {
        set_transient('kf_performance', $out, 5 * MINUTE_IN_SECONDS);
        return $out;
    }

    $start = '9999-12-31';
    if ($trades) {
        $start = $trades[0]['trade_date'];
    }
    if ($cash_rows && $cash_rows[0]['flow_date'] < $start) {
        $start = $cash_rows[0]['flow_date'];
    }
    $end = gmdate('Y-m-d');
    if ($start > $end) {
        $end = $start;
    }

    // Seed each series with its last value on or before the start, or its first value
    // if it only begins later.
    $last = array();
    foreach ($px as $key => $series) {
        foreach ($series as $day => $close) {
            if ($day > $start && isset($last[$key])) {
                break;
            }
            $last[$key] = $close;
            if ($day >= $start) {
                break;
            }
        }
    }

    $rate = function ($currency) use (&$last) {
        $fx = kf_fx_for($currency);
        if (!$fx) {
            return 1.0;
        }
        return isset($last[$fx[0]]) ? $last[$fx[0]] * $fx[1] : 1.0;
    };
    // USD per CHF (CHF/USD quote); null until known.
    $usd_per_chf = function () use (&$last) {
        return isset($last['CHF/USD']) && $last['CHF/USD'] > 0 ? $last['CHF/USD'] : null;
    };

    $pos           = array();
    $last_trade_px = array();
    $cash          = array();
    $net_in        = 0.0;   // USD at each day's rate
    $net_in_chf    = 0.0;   // CHF at each day's rate
    $twr           = 1.0;
    $twr_chf       = 1.0;
    $prev_value    = null;
    $prev_chf      = null;
    $chf_ok        = true;
    $b_start       = array();   // symbol => [usd start price, chf start price]
    $value         = 0.0;
    $ti = 0; $nt = count($trades);
    $ci = 0; $nc = count($cash_rows);

    for ($d = $start; $d <= $end; $d = gmdate('Y-m-d', strtotime($d . ' +1 day'))) {
        foreach ($px as $key => $series) {
            if (isset($series[$d])) {
                $last[$key] = $series[$d];
            }
        }

        $flow = 0.0; // external money in (+) / out (−) today, USD

        while ($ci < $nc && $cash_rows[$ci]['flow_date'] <= $d) {
            $c   = $cash_rows[$ci++];
            $cur = $c['currency'];
            $amt = (float) $c['amount'];
            if (!isset($cash[$cur])) {
                $cash[$cur] = 0.0;
            }
            switch ($c['type']) {
                case 'deposit':
                    $cash[$cur] += $amt;
                    $flow += $amt * $rate($cur);
                    break;
                case 'withdrawal':
                    $cash[$cur] -= $amt;
                    $flow -= $amt * $rate($cur);
                    break;
                case 'income':
                    $cash[$cur] += $amt;
                    break;
                case 'fee':
                    $cash[$cur] -= $amt;
                    break;
                case 'exchange':
                    $cash[$cur] -= $amt;
                    $cur2 = $c['currency2'];
                    if (!isset($cash[$cur2])) {
                        $cash[$cur2] = 0.0;
                    }
                    $cash[$cur2] += (float) $c['amount2'];
                    break;
            }
        }

        while ($ti < $nt && $trades[$ti]['trade_date'] <= $d) {
            $t    = $trades[$ti++];
            $ikey = $t['symbol'];
            $cur  = $currency_of($ikey);
            $q    = (float) $t['qty'];
            $amt_native = $q * (float) $t['price'];
            $amt  = $amt_native * $rate($cur);
            if (!isset($pos[$ikey])) {
                $pos[$ikey] = array('name' => $t['name'], 'qty' => 0.0, 'cost' => 0.0, 'cost_native' => 0.0);
            }
            $pos[$ikey]['name'] = $t['name'];
            $last_trade_px[$ikey] = (float) $t['price'];

            if ($t['side'] === 'buy') {
                $pos[$ikey]['qty']         += $q;
                $pos[$ikey]['cost']        += $amt;
                $pos[$ikey]['cost_native'] += $amt_native;
                if ($trades_only) {
                    $flow += $amt;
                } else {
                    $cash[$cur] = (isset($cash[$cur]) ? $cash[$cur] : 0.0) - $amt_native;
                }
            } else {
                $share = $pos[$ikey]['qty'] > 0 ? $q / $pos[$ikey]['qty'] : 0;
                $pos[$ikey]['cost']        -= $pos[$ikey]['cost'] * $share;
                $pos[$ikey]['cost_native'] -= $pos[$ikey]['cost_native'] * $share;
                $pos[$ikey]['qty']         -= $q;
                if ($trades_only) {
                    $flow -= $amt;
                } else {
                    $cash[$cur] = (isset($cash[$cur]) ? $cash[$cur] : 0.0) + $amt_native;
                }
            }
        }

        $net_in += $flow;
        $chf = $usd_per_chf();
        if ($chf) {
            $net_in_chf += $flow / $chf;
        } else {
            $chf_ok = false;
        }

        $value = 0.0;
        foreach ($pos as $ikey => $p) {
            $price = isset($last[$ikey]) ? $last[$ikey] : (isset($last_trade_px[$ikey]) ? $last_trade_px[$ikey] : 0);
            $value += $p['qty'] * $price * $rate($currency_of($ikey));
        }
        foreach ($cash as $cur => $bal) {
            $value += $bal * $rate($cur);
        }
        $value_chf = $chf ? $value / $chf : null;

        if ($prev_value === null) {
            if ($value <= 0) {
                continue; // nothing invested yet
            }
            $prev_value = $value;
            $prev_chf   = $value_chf;
        } else {
            // Flows are treated as arriving at the end of the day.
            if ($prev_value > 0) {
                $twr *= ($value - $flow) / $prev_value;
            }
            if ($prev_chf > 0 && $value_chf !== null) {
                $twr_chf *= ($value_chf - $flow / $chf) / $prev_chf;
            }
            $prev_value = $value;
            $prev_chf   = $value_chf;
        }

        $out['dates'][]       = $d;
        $out['port']['USD'][] = round(($twr - 1) * 100, 3);
        $out['port']['CHF'][] = $value_chf === null ? null : round(($twr_chf - 1) * 100, 3);

        foreach ($benchmarks as $b) {
            $bp = isset($last[$b]) ? $last[$b] : null;
            if ($bp && !isset($b_start[$b])) {
                $b_start[$b] = array($bp, $chf ? $bp / $chf : null);
            }
            $usd = $bp && isset($b_start[$b]) ? round(($bp / $b_start[$b][0] - 1) * 100, 3) : null;
            $chfv = null;
            if ($bp && $chf && isset($b_start[$b]) && $b_start[$b][1]) {
                $chfv = round((($bp / $chf) / $b_start[$b][1] - 1) * 100, 3);
            }
            $out['bench'][$b]['USD'][] = $usd;
            $out['bench'][$b]['CHF'][] = $chfv;
        }
    }
    $out['has_chf'] = $chf_ok && $usd_per_chf() !== null && $out['dates'];

    $chf_now = $usd_per_chf();
    $total_cost = 0.0;
    foreach ($pos as $ikey => $p) {
        if (abs($p['qty']) < 1e-9) {
            continue;
        }
        $inst   = isset($instruments[$ikey]) ? $instruments[$ikey] : null;
        $cur    = $currency_of($ikey);
        $priced = isset($last[$ikey]);
        $price  = $priced ? $last[$ikey] : (isset($last_trade_px[$ikey]) ? $last_trade_px[$ikey] : null);
        $val    = $price === null ? 0 : $p['qty'] * $price * $rate($cur);

        $price_day = null;
        if (!empty($px[$ikey])) {
            $days = array_keys($px[$ikey]);
            $price_day = end($days);
        }

        $out['holdings'][] = array(
            'ikey'       => $ikey,
            'symbol'     => $inst ? $inst['symbol'] : $ikey,
            'exchange'   => $inst ? $inst['exchange'] : '',
            'name'       => $p['name'],
            'currency'   => $cur,
            'mode'       => $inst ? $inst['mode'] : 'auto',
            'qty'        => $p['qty'],
            'avg_cost'   => $p['qty'] ? $p['cost_native'] / $p['qty'] : 0, // instrument currency
            'price'      => $price,                                         // instrument currency
            'price_day'  => $price_day,
            'priced'     => $priced,
            'value'      => $val,                                           // USD
            'unrealized' => $val - $p['cost'],                              // USD
        );
    }

    $cash_usd = 0.0;
    ksort($cash);
    foreach ($cash as $cur => $bal) {
        if (abs($bal) < 0.005) {
            continue;
        }
        $usd = $bal * $rate($cur);
        $cash_usd += $usd;
        $out['cash'][] = array('currency' => $cur, 'amount' => $bal, 'usd' => $usd);
        if ($bal < 0) {
            $out['warnings'][] = sprintf(
                '%s cash is negative (%s). Log the deposit or currency exchange that paid for the trades in %s.',
                $cur, number_format($bal, 2, '.', "'"), $cur
            );
        }
    }

    $out['totals'] = array(
        'value'    => $value,
        'cash_usd' => $cash_usd,
        'net_in'   => $net_in,
        'pl'       => $value - $net_in,
        'chf'      => $out['has_chf'] ? array(
            'value'  => $value / $chf_now,
            'net_in' => $net_in_chf,
            'pl'     => $value / $chf_now - $net_in_chf,
            'rate'   => $chf_now,
        ) : null,
    );

    $n = count($out['dates']);
    $lastv = function ($arr) use ($n) { return $n ? $arr[$n - 1] : null; };
    $out['stats'] = array(
        'since'     => $n ? $out['dates'][0] : $start,
        'portfolio' => $n ? round($lastv($out['port']['USD']), 2) : null,
        'benchmark' => ($n && isset($out['bench'][$bench]) && $lastv($out['bench'][$bench]['USD']) !== null) ? round($lastv($out['bench'][$bench]['USD']), 2) : null,
        'chf'       => $out['has_chf'] ? array(
            'portfolio' => round($lastv($out['port']['CHF']), 2),
            'benchmark' => (isset($out['bench'][$bench]) && $lastv($out['bench'][$bench]['CHF']) !== null) ? round($lastv($out['bench'][$bench]['CHF']), 2) : null,
        ) : null,
    );

    set_transient('kf_performance', $out, HOUR_IN_SECONDS);
    return $out;
}

/**
 * What the public page receives. Percentages always; real amounts only as far as
 * Settings → "Public amounts" allows.
 */
function kf_public_payload() {
    $perf     = kf_performance();
    $settings = kf_settings();
    $catalog  = kf_benchmark_catalog();

    $bench = array();
    foreach ($perf['bench'] as $sym => $series) {
        // Only benchmarks that already have prices (new ones appear after their first fetch).
        if (!array_filter($series['USD'], function ($v) { return $v !== null; })) {
            continue;
        }
        $names = isset($catalog[$sym]) ? $catalog[$sym] : array($sym, $sym);
        $bench[] = array('id' => $sym, 'en' => $names[0], 'de' => $names[1], 'USD' => $series['USD'], 'CHF' => $perf['has_chf'] ? $series['CHF'] : null);
    }

    $payload = array(
        'dates'      => $perf['dates'],
        'port'       => array('USD' => $perf['port']['USD'], 'CHF' => $perf['has_chf'] ? $perf['port']['CHF'] : null),
        'bench'      => $bench,
        'since'      => $perf['stats'] ? $perf['stats']['since'] : null,
        'updated'    => $perf['updated'],
        'allocation' => array(),
        'amounts'    => null,
    );

    $chf_rate = ($perf['totals'] && $perf['totals']['chf']) ? $perf['totals']['chf']['rate'] : null;
    $both = function ($usd) use ($chf_rate) {
        return array('USD' => round($usd, 2), 'CHF' => $chf_rate ? round($usd / $chf_rate, 2) : null);
    };

    // Holdings incl. cash, valued in USD.
    $parts = array();
    foreach ($perf['holdings'] as $h) {
        if ($h['value'] > 0) {
            $parts[] = array('name' => $h['name'], 'value' => $h['value'], 'qty' => $h['qty'], 'symbol' => $h['symbol']);
        }
    }
    if ($perf['totals'] && $perf['totals']['cash_usd'] > 0) {
        $parts[] = array('name' => '__cash__', 'value' => $perf['totals']['cash_usd'], 'qty' => null, 'symbol' => '');
    }
    $sum = array_sum(array_column($parts, 'value'));
    usort($parts, function ($a, $b) { return $b['value'] <=> $a['value']; });

    $level = $settings['public_amounts'];
    if ((!empty($settings['show_allocation']) || $level === 'holdings') && $sum > 0) {
        foreach ($parts as $p) {
            $payload['allocation'][] = array('name' => $p['name'], 'pct' => round($p['value'] / $sum * 100, 1));
        }
    }

    if ($perf['totals'] && ($level === 'totals' || $level === 'holdings')) {
        $t = $perf['totals'];
        $payload['amounts'] = array(
            'value'  => $both($t['value']),
            'net_in' => $t['chf'] ? array('USD' => round($t['net_in'], 2), 'CHF' => round($t['chf']['net_in'], 2)) : array('USD' => round($t['net_in'], 2), 'CHF' => null),
            'gain'   => $t['chf'] ? array('USD' => round($t['pl'], 2), 'CHF' => round($t['chf']['pl'], 2)) : array('USD' => round($t['pl'], 2), 'CHF' => null),
            'trades_only' => $perf['trades_only'],
            'holdings' => null,
        );
        if ($level === 'holdings') {
            $payload['amounts']['holdings'] = array();
            foreach ($parts as $p) {
                $payload['amounts']['holdings'][] = array(
                    'name'   => $p['name'],
                    'symbol' => $p['symbol'],
                    'qty'    => $p['qty'] === null ? null : round($p['qty'], 6),
                    'value'  => $both($p['value']),
                    'pct'    => $sum > 0 ? round($p['value'] / $sum * 100, 1) : 0,
                );
            }
        }
    }

    return $payload;
}

/* ---------- cash checks ---------- */

/**
 * Lowest balance each currency reaches over time, for a given set of trades and cash
 * movements. Per day, cash movements count before trades, so a deposit and a buy on the
 * same day work.
 *
 * @return array currency => ['min' => float, 'date' => 'Y-m-d']
 */
function kf_cash_lowpoints($trades, $cash_rows, $currency_of) {
    $events = array();
    foreach ($cash_rows as $c) {
        $amt = (float) $c['amount'];
        switch ($c['type']) {
            case 'deposit':
            case 'income':
                $events[] = array($c['flow_date'], 0, $c['currency'], $amt);
                break;
            case 'withdrawal':
            case 'fee':
                $events[] = array($c['flow_date'], 0, $c['currency'], -$amt);
                break;
            case 'exchange':
                $events[] = array($c['flow_date'], 0, $c['currency'], -$amt);
                $events[] = array($c['flow_date'], 0, $c['currency2'], (float) $c['amount2']);
                break;
        }
    }
    foreach ($trades as $t) {
        $amt = (float) $t['qty'] * (float) $t['price'];
        $events[] = array($t['trade_date'], 1, $currency_of($t['symbol']), $t['side'] === 'buy' ? -$amt : $amt);
    }
    // Stable sort: date, then cash before trades, then original order.
    foreach ($events as $i => &$e) {
        $e[] = $i;
    }
    unset($e);
    usort($events, function ($a, $b) {
        return array($a[0], $a[1], $a[4]) <=> array($b[0], $b[1], $b[4]);
    });

    $bal = array();
    $low = array();
    $n   = count($events);
    for ($i = 0; $i < $n; $i++) {
        list($day, , $cur, $amt) = $events[$i];
        $bal[$cur] = (isset($bal[$cur]) ? $bal[$cur] : 0.0) + $amt;
        // Judge the balance at the end of each day, so order within a day doesn't matter.
        if ($i + 1 < $n && $events[$i + 1][0] === $day) {
            continue;
        }
        foreach ($bal as $c => $b) {
            if (!isset($low[$c]) || $b < $low[$c]['min']) {
                $low[$c] = array('min' => $b, 'date' => $day);
            }
        }
    }
    return $low;
}

/**
 * Would this change overdraw any currency? Compares the cash timeline before and after.
 * Only blocks changes that make things worse, so an older ledger with gaps can still
 * be fixed step by step. Does nothing until the first cash entry exists.
 *
 * @param array $trades_after  full list of trades after the change
 * @param array $cash_after    full list of cash movements after the change
 * @param array $extra_currencies ikey => currency for instruments not saved yet
 * @return string|null error message, or null when fine
 */
function kf_check_cash_change($trades_after, $cash_after, $extra_currencies = array()) {
    if (!$cash_after) {
        return null;
    }
    $instruments = kf_get_instruments();
    $currency_of = function ($ikey) use ($instruments, $extra_currencies) {
        if (isset($extra_currencies[$ikey])) {
            return $extra_currencies[$ikey];
        }
        return isset($instruments[$ikey]) ? $instruments[$ikey]['currency'] : 'USD';
    };
    $before = kf_cash_lowpoints(kf_get_trades(), kf_get_cash(), $currency_of);
    $after  = kf_cash_lowpoints($trades_after, $cash_after, $currency_of);

    foreach ($after as $cur => $a) {
        $floor = isset($before[$cur]) ? min($before[$cur]['min'], 0.0) : 0.0;
        if ($a['min'] < -0.005 && $a['min'] < $floor - 0.005) {
            return sprintf(
                'Not enough %1$s cash: on %2$s the %1$s balance would drop to %3$s. Log a deposit or a currency exchange into %1$s first.',
                $cur,
                date_i18n(get_option('date_format'), strtotime($a['date'] . ' 12:00:00')),
                kf_money($a['min'], $cur)
            );
        }
    }
    return null;
}

/** Sort helper: trades/cash rows by date, then id (new rows last). */
function kf_sort_rows(&$rows, $date_key) {
    usort($rows, function ($a, $b) use ($date_key) {
        return array($a[$date_key], (int) $a['id']) <=> array($b[$date_key], (int) $b['id']);
    });
}
