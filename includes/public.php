<?php
/**
 * Public side.
 *  - Shortcode [kaiserfinance]: the performance block, usable on any page.
 *  - Standalone homepage: when enabled in Settings, the front page is drawn
 *    entirely by the plugin (header, block, footer) and the theme is not used.
 * Only percentages are ever sent to the browser.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('kaiserfinance', 'kf_shortcode');
add_action('template_redirect', 'kf_maybe_standalone', 1);
add_filter('get_site_icon_url', 'kf_default_site_icon', 10, 2);

/**
 * Use the plugin's gold $ as the site icon (browser tabs, bookmarks, phone home screens,
 * admin and login pages) unless one was set under Settings → General → Site Icon.
 */
function kf_default_site_icon($url, $size) {
    // A real site icon chosen in WordPress wins. Otherwise replace WordPress's own
    // fallback too (the grey "W" it serves for /favicon.ico).
    if ((int) get_option('site_icon')) {
        return $url;
    }
    if ($size <= 64) {
        return KF_URL . 'assets/icon-32.png';
    }
    return KF_URL . ($size <= 180 ? 'assets/icon-180.png' : 'assets/icon-512.png');
}

/** The block's HTML. On the standalone homepage the language switch sits in the page header. */
function kf_block_html($standalone = false) {
    kf_maybe_refresh_fallback();

    $data = kf_public_payload();
    $id   = 'kf-' . wp_rand(1000, 999999);
    // The numbers ride along as a JSON script (not an HTML attribute): no &quot; escaping, so the page is much smaller.
    $json = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

    return '<div class="kf" id="' . esc_attr($id) . '" data-kf="">'
        . '<script type="application/json" class="kf-data">' . $json . '</script>'
        . '<noscript><p>Turn on JavaScript to see the performance chart.</p></noscript>'
        . '</div>';
}

function kf_shortcode() {
    wp_enqueue_style('kaiserfinance', KF_URL . 'assets/kaiserfinance.css', array(), KF_VERSION);
    wp_enqueue_script('kaiserfinance', KF_URL . 'assets/kaiserfinance.js', array(), KF_VERSION, true);
    return kf_block_html(false);
}

function kf_maybe_standalone() {
    if (!is_front_page() || is_admin() || is_feed() || is_embed()) {
        return;
    }
    if (empty(kf_settings()['standalone'])) {
        return;
    }
    kf_render_standalone();
    exit;
}

function kf_render_standalone() {
    $v        = rawurlencode(KF_VERSION);
    $css      = esc_url(KF_URL . 'assets/kaiserfinance.css?ver=' . $v);
    $site_css = esc_url(KF_URL . 'assets/site.css?ver=' . $v);
    $js       = esc_url(KF_URL . 'assets/kaiserfinance.js?ver=' . $v);
    $icon     = get_site_icon_url(64);
    $noindex  = get_option('blog_public') === '0';
    $year     = gmdate('Y');

    status_header(200);
    // Always ask the server for a fresh copy (prices change hourly), but without "no-store",
    // so the browser's back/forward cache can show the page instantly.
    header('Cache-Control: no-cache, max-age=0, private');
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>try{if(localStorage.getItem('kf-theme')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}</script>
<title>Kai$erFinance</title>
<meta name="description" content="<?php echo esc_attr(kf_og_description()); ?>">
<?php echo kf_og_tags(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
<?php if ($noindex) : ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if ($icon) : ?><link rel="icon" href="<?php echo esc_url($icon); ?>">
<?php endif; ?>
<?php if (!(int) get_option('site_icon')) : ?><link rel="icon" type="image/svg+xml" href="<?php echo esc_url(KF_URL . 'assets/icon.svg'); ?>">
<?php endif; ?>
<link rel="apple-touch-icon" href="<?php echo esc_url(get_site_icon_url(180)); ?>">
<?php echo kf_structured_data(); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded ?>
<link rel="stylesheet" href="<?php echo $css; ?>">
<link rel="stylesheet" href="<?php echo $site_css; ?>">
<script src="<?php echo $js; ?>" defer></script>
</head>
<body class="kf-site">
<header class="kf-site-head">
  <a class="kf-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Kai$erFinance">Kai<span class="kf-brand-mark">$</span>er<span class="kf-brand-sub">Finance</span></a>
  <div class="kf-head-right">
    <div id="kf-lang-slot"></div>
  </div>
</header>
<main class="kf-site-main">
  <div class="kf-site-intro">
    <p class="kf-eyebrow" data-kf-t="eyebrow">Spot portfolio · USD</p>
    <h1 data-kf-t="headline">How the portfolio stacks up against the S&amp;P 500</h1>
    <p class="kf-lede" data-kf-t="lede">The return of the whole account, cash included, against the S&amp;P 500 over exactly the same days. Money paid in or taken out doesn&#8217;t count as performance.</p>
  </div>
  <?php echo kf_block_html(true); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts ?>
</main>
<footer class="kf-site-foot">
  <span>© <?php echo esc_html($year); ?> Kai$erFinance</span>
  <?php echo kf_social_links_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
  <span class="kf-site-note" data-kf-t="disclaimer">Past performance is no guarantee of future results. Not investment advice.</span>
  <span class="kf-foot-end">
    <a class="kf-login" href="<?php echo esc_url(admin_url('admin.php?page=kaiserfinance')); ?>" data-kf-t="login">Log in</a>
    <a class="kf-powered" href="https://shokulab.ch" target="_blank" rel="noopener">Powered by shokulab</a>
  </span>
</footer>
</body>
</html>
<?php
}

/** Kai$erFinance's own channels (Settings → Social links). Plain links: no third-party scripts or trackers. */
function kf_social_links() {
    $s     = kf_settings();
    $links = array();
    if (!empty($s['youtube_url'])) {
        $links['youtube'] = array('url' => $s['youtube_url'], 'name' => 'YouTube',
            'svg' => '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8Z" fill="currentColor"/><path d="m10 15 5.2-3L10 9v6Z" fill="var(--site-bg, #fff)"/>');
    }
    if (!empty($s['instagram_url'])) {
        $links['instagram'] = array('url' => $s['instagram_url'], 'name' => 'Instagram',
            'svg' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/>');
    }
    return $links;
}

function kf_social_links_html() {
    $links = kf_social_links();
    if (!$links) {
        return '';
    }
    $html = '<nav class="kf-social" aria-label="Social">';
    foreach ($links as $key => $l) {
        $label = 'Kai$erFinance on ' . $l['name'];
        $html .= '<a class="kf-social-link" href="' . esc_url($l['url']) . '" target="_blank" rel="noopener me"'
            . ' data-kf-t-label="' . esc_attr($key) . '" aria-label="' . esc_attr($label) . '" title="' . esc_attr($label) . '">'
            . '<svg viewBox="0 0 24 24" aria-hidden="true">' . $l['svg'] . '</svg><span>' . esc_html($l['name']) . '</span></a>';
    }
    return $html . '</nav>';
}

/** schema.org data so search engines link the site with its YouTube and Instagram accounts. */
function kf_structured_data() {
    $data = array(
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => 'Kai$erFinance',
        'url'      => home_url('/'),
        'logo'     => KF_URL . 'assets/icon-512.png',
    );
    $same = array_values(array_map(function ($l) { return $l['url']; }, kf_social_links()));
    if ($same) {
        $data['sameAs'] = $same;
    }
    return '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "</script>\n";
}
