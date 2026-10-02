<?php
/**
 * Link previews (WhatsApp, iMessage, LinkedIn, X, Slack…): Open Graph tags plus a
 * 1200×630 share image showing the current return against the main benchmark.
 * The image is drawn with PHP's GD library and cached for an hour.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'kf_maybe_serve_og_image');

/** What the preview says: percentages only, never amounts. */
function kf_og_summary() {
    $perf    = kf_performance();
    $s       = $perf['stats'];
    $catalog = kf_benchmark_catalog();
    $main    = kf_settings()['benchmark'];
    $bname   = isset($catalog[$main]) ? $catalog[$main][0] : $main;
    return array(
        'has'   => $s && $s['portfolio'] !== null,
        'p'     => $s ? $s['portfolio'] : null,
        'b'     => $s ? $s['benchmark'] : null,
        'since' => $s ? $s['since'] : null,
        'bname' => $bname,
        'dates' => $perf['dates'],
        'port'  => $perf['port']['USD'],
        'bench' => isset($perf['bench'][$main]) ? $perf['bench'][$main]['USD'] : array(),
    );
}

function kf_og_pct($v) {
    return $v === null ? '—' : (($v >= 0 ? '+' : '−') . number_format(abs($v), 2) . '%');
}

/** One-line description for the preview text. */
function kf_og_description() {
    $o = kf_og_summary();
    if (!$o['has']) {
        return 'Spot portfolio performance compared with the S&P 500.';
    }
    $since = date_i18n('j M Y', strtotime($o['since'] . ' 12:00:00'));
    return sprintf('Portfolio %s vs %s %s since %s. Time-weighted, cash included.', kf_og_pct($o['p']), $o['bname'], kf_og_pct($o['b']), $since);
}

/** URL of the share image; changes whenever the numbers change, so apps refetch it. */
function kf_og_image_url() {
    if (!function_exists('imagettftext')) {
        return KF_URL . 'assets/icon-512.png';
    }
    $o = kf_og_summary();
    $v = substr(md5(wp_json_encode(array($o['p'], $o['b'], $o['since'], KF_VERSION))), 0, 10);
    return add_query_arg(array('kf_og' => $v), home_url('/'));
}

/** Tags for the standalone page's <head>. */
function kf_og_tags() {
    $title = 'Kai$erFinance';
    $desc  = kf_og_description();
    $img   = kf_og_image_url();
    $big   = function_exists('imagettftext');
    $tags  = array(
        array('property', 'og:type', 'website'),
        array('property', 'og:site_name', $title),
        array('property', 'og:title', $title),
        array('property', 'og:description', $desc),
        array('property', 'og:url', home_url('/')),
        array('property', 'og:image', $img),
        array('name', 'twitter:card', $big ? 'summary_large_image' : 'summary'),
        array('name', 'twitter:title', $title),
        array('name', 'twitter:description', $desc),
        array('name', 'twitter:image', $img),
    );
    if ($big) {
        $tags[] = array('property', 'og:image:width', '1200');
        $tags[] = array('property', 'og:image:height', '630');
        $tags[] = array('property', 'og:image:alt', $desc);
    }
    $html = '';
    foreach ($tags as $t) {
        $html .= '<meta ' . $t[0] . '="' . esc_attr($t[1]) . '" content="' . esc_attr($t[2]) . '">' . "\n";
    }
    return $html;
}

function kf_maybe_serve_og_image() {
    if (!isset($_GET['kf_og']) || !function_exists('imagettftext')) {
        return;
    }
    $png = get_transient('kf_og_png');
    if ($png === false) {
        $png = kf_og_render();
        set_transient('kf_og_png', $png, HOUR_IN_SECONDS);
    }
    nocache_headers();
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . strlen($png));
    echo $png; // phpcs:ignore WordPress.Security.EscapeOutput -- binary image
    exit;
}

/** Draw the share image. Light design matching the homepage. */
function kf_og_render() {
    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    imageantialias($im, true);
    $hex = function ($h) use ($im) {
        return imagecolorallocate($im, hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2)));
    };
    $bg = $hex('#e0ddcd'); $ink = $hex('#1d1d1b'); $muted = $hex('#5f5b4c'); $line = $hex('#c9c4ae');
    $gold = $hex('#1d1d1b'); $olive = $hex('#6e6849'); $index = $hex('#3f5878'); $up = $hex('#3b6b45'); $down = $hex('#9d3b2b'); $panel = $hex('#e8e5d8');
    imagefilledrectangle($im, 0, 0, $W, $H, $bg);

    $serif = KF_DIR . 'assets/fonts/kf-serif.ttf';
    $sans  = KF_DIR . 'assets/fonts/kf-sans.ttf';
    $bold  = KF_DIR . 'assets/fonts/kf-sans-bold.ttf';
    $text  = function ($size, $font, $color, $x, $y, $str) use ($im) {
        $box = imagettftext($im, $size, 0, (int) $x, (int) $y, $color, $font, $str);
        return $box[2]; // right edge
    };

    // Wordmark, as on the site: KAI$ER in ink, FINANCE in olive, serif capitals.
    $x = 72; $y = 104;
    $x = $text(34, $serif, $ink, $x, $y, 'KAI$ER');
    $text(34, $serif, $olive, $x + 14, $y, 'FINANCE');
    $text(17, $bold, $muted, 72, 158, 'SPOT PORTFOLIO VS ' . strtoupper(kf_og_summary()['bname']));

    $o = kf_og_summary();
    if ($o['has']) {
        $pc = $o['p'] >= 0 ? $up : $down;
        $text(96, $bold, $pc, 66, 300, kf_og_pct($o['p']));
        $text(26, $sans, $ink, 74, 360, 'vs ' . $o['bname'] . '  ' . kf_og_pct($o['b']));
        $since = 'since ' . date_i18n('j M Y', strtotime($o['since'] . ' 12:00:00'));
        $text(21, $sans, $muted, 74, 404, $since . ' · cash included');

        // Chart panel on the right.
        $px0 = 700; $py0 = 70; $pw = 430; $ph = 330;
        imagefilledrectangle($im, $px0, $py0, $px0 + $pw, $py0 + $ph, $panel);
        imagerectangle($im, $px0, $py0, $px0 + $pw, $py0 + $ph, $line);
        $vals = array();
        foreach (array($o['port'], $o['bench']) as $arr) {
            foreach ($arr as $v) {
                if ($v !== null) { $vals[] = $v; }
            }
        }
        $n = count($o['dates']);
        if ($n > 1 && $vals) {
            $lo = min(0, min($vals)); $hi = max(0, max($vals)); $pad = ($hi - $lo) * 0.12 ?: 1; $lo -= $pad; $hi += $pad;
            $cx = function ($i) use ($px0, $pw, $n) { return $px0 + 24 + $i / ($n - 1) * ($pw - 48); };
            $cy = function ($v) use ($py0, $ph, $lo, $hi) { return $py0 + 24 + ($hi - $v) / ($hi - $lo) * ($ph - 48); };
            imagesetthickness($im, 1);
            imageline($im, $px0 + 24, (int) $cy(0), $px0 + $pw - 24, (int) $cy(0), $line);
            foreach (array(array($o['bench'], $index, 3), array($o['port'], $gold, 4)) as $series) {
                list($arr, $col, $th) = $series;
                imagesetthickness($im, $th);
                $prev = null;
                foreach ($arr as $i => $v) {
                    if ($v === null) { $prev = null; continue; }
                    $pt = array($cx($i), $cy($v));
                    if ($prev) { imageline($im, (int) $prev[0], (int) $prev[1], (int) $pt[0], (int) $pt[1], $col); }
                    $prev = $pt;
                }
                if ($prev) { imagefilledellipse($im, (int) $prev[0], (int) $prev[1], 14, 14, $col); }
            }
            imagesetthickness($im, 1);
        }
        // Legend under the chart.
        imagefilledrectangle($im, $px0, $py0 + $ph + 28, $px0 + 22, $py0 + $ph + 32, $gold);
        $text(17, $sans, $muted, $px0 + 32, $py0 + $ph + 38, 'Kai$erFinance');
        $box = imagettfbbox(17, 0, $sans, 'Kai$erFinance');
        $lx = $px0 + 32 + ($box[2] - $box[0]) + 36;
        imagefilledrectangle($im, $lx, $py0 + $ph + 28, $lx + 22, $py0 + $ph + 32, $index);
        $text(17, $sans, $muted, $lx + 32, $py0 + $ph + 38, $o['bname']);
    } else {
        $text(44, $serif, $ink, 72, 320, 'Performance coming soon');
    }

    // Footer line.
    imagesetthickness($im, 1);
    imageline($im, 72, 520, $W - 72, 520, $line);
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $text(22, $bold, $ink, 72, 568, $host);
    $right = 'Powered by shokulab';
    $box = imagettfbbox(18, 0, $bold, $right);
    $text(18, $bold, $muted, $W - 72 - ($box[2] - $box[0]), 566, $right);

    ob_start();
    imagepng($im, null, 6);
    imagedestroy($im);
    return ob_get_clean();
}
