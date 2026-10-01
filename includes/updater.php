<?php
/**
 * Updates straight from GitHub. WordPress shows "Update available" under Plugins as soon
 * as the version number in the repo's kaiserfinance.php is higher than the installed one.
 * The update downloads the repo's main branch as a zip, so no releases need to be made.
 * You can also turn on "Enable auto-updates" for the plugin to install them by itself.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('pre_set_site_transient_update_plugins', 'kf_updater_check');
add_filter('plugins_api', 'kf_updater_info', 10, 3);
add_filter('upgrader_source_selection', 'kf_updater_fix_folder', 10, 4);
add_action('upgrader_process_complete', 'kf_updater_clear', 10, 2);

function kf_updater_repo() {
    $repo = (defined('KF_GITHUB_REPO') && KF_GITHUB_REPO !== '') ? KF_GITHUB_REPO : kf_settings()['github_repo'];
    if ($repo === '' || $repo === null) {
        $repo = kf_default_settings()['github_repo']; // an empty saved field means "use the default"
    }
    return preg_match('#^[\w.-]+/[\w.-]+$#', (string) $repo) ? $repo : '';
}

/** Version number in the repo, cached for 6 hours (Dashboard → Updates → "Check again" refreshes it). */
function kf_updater_remote_version($force = false) {
    $repo = kf_updater_repo();
    if ($repo === '') {
        kf_updater_status('No GitHub repo set.');
        return null;
    }
    $cached = get_transient('kf_remote_version');
    if (!$force && $cached !== false) {
        return $cached ?: null;
    }

    $ver    = '';
    $errors = array();
    $args   = array('timeout' => 15, 'headers' => array('User-Agent' => 'KaiserFinance-Updater', 'Cache-Control' => 'no-cache'));

    // 1) Raw file (fast, cached by GitHub for up to 5 minutes).
    $res = wp_remote_get('https://raw.githubusercontent.com/' . $repo . '/main/kaiserfinance.php?t=' . time(), $args);
    if (is_wp_error($res)) {
        $errors[] = 'raw: ' . $res->get_error_message();
    } elseif (wp_remote_retrieve_response_code($res) !== 200) {
        $errors[] = 'raw: HTTP ' . wp_remote_retrieve_response_code($res);
    } elseif (preg_match('/^\s*\*\s*Version:\s*([0-9][0-9.]*)/mi', wp_remote_retrieve_body($res), $m)) {
        $ver = $m[1];
    } else {
        $errors[] = 'raw: no version line found';
    }

    // 2) GitHub API (always current), also used when the raw file can't be reached.
    $api = wp_remote_get('https://api.github.com/repos/' . $repo . '/contents/kaiserfinance.php?ref=main', array_merge($args, array(
        'headers' => array('User-Agent' => 'KaiserFinance-Updater', 'Accept' => 'application/vnd.github.raw'),
    )));
    if (is_wp_error($api)) {
        $errors[] = 'api: ' . $api->get_error_message();
    } elseif (wp_remote_retrieve_response_code($api) !== 200) {
        $errors[] = 'api: HTTP ' . wp_remote_retrieve_response_code($api);
    } elseif (preg_match('/^\s*\*\s*Version:\s*([0-9][0-9.]*)/mi', wp_remote_retrieve_body($api), $m)) {
        if ($ver === '' || version_compare($m[1], $ver, '>')) {
            $ver = $m[1];
        }
    }

    if ($ver !== '') {
        kf_updater_status(sprintf('GitHub has version %s (installed: %s).', $ver, KF_VERSION) . ($errors ? ' Notes: ' . implode('; ', $errors) : ''));
    } else {
        kf_updater_status('Could not reach GitHub: ' . implode('; ', $errors));
    }
    set_transient('kf_remote_version', $ver, $ver ? 6 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS);
    return $ver ?: null;
}

/** Remember the outcome of the last check, shown under Settings. */
function kf_updater_status($message = null) {
    if ($message === null) {
        return get_option('kf_update_status', array());
    }
    update_option('kf_update_status', array('time' => time(), 'message' => $message), false);
}

/** "Check GitHub now" in Settings: look up the version and refresh WordPress's update list. */
function kf_updater_check_now() {
    delete_transient('kf_remote_version');
    kf_updater_remote_version(true);
    delete_site_transient('update_plugins');
    if (function_exists('wp_update_plugins')) {
        wp_update_plugins();
    }
}

function kf_updater_package() {
    return 'https://github.com/' . kf_updater_repo() . '/archive/refs/heads/main.zip';
}

function kf_updater_check($transient) {
    if (!is_object($transient) || kf_updater_repo() === '') {
        return $transient;
    }
    $force  = isset($_GET['force-check']) || isset($_GET['kf-check']); // "Check again" on Dashboard → Updates
    $remote = kf_updater_remote_version($force);
    $file   = plugin_basename(KF_DIR . 'kaiserfinance.php');
    $item   = (object) array(
        'id'          => 'github.com/' . kf_updater_repo(),
        'slug'        => 'kaiserfinance',
        'plugin'      => $file,
        'new_version' => $remote ?: KF_VERSION,
        'url'         => 'https://github.com/' . kf_updater_repo(),
        'package'     => kf_updater_package(),
        'icons'       => array('1x' => KF_URL . 'assets/icon-180.png', '2x' => KF_URL . 'assets/icon-512.png'),
    );
    if ($remote && version_compare($remote, KF_VERSION, '>')) {
        $transient->response[$file] = $item;
    } else {
        unset($transient->response[$file]);
        $transient->no_update[$file] = $item; // lets "Enable auto-updates" show for this plugin
    }
    return $transient;
}

/** The "View details" window under Plugins. */
function kf_updater_info($result, $action, $args) {
    if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'kaiserfinance' || kf_updater_repo() === '') {
        return $result;
    }
    $remote = kf_updater_remote_version() ?: KF_VERSION;
    return (object) array(
        'name'          => 'Kai$erFinance',
        'slug'          => 'kaiserfinance',
        'version'       => $remote,
        'author'        => 'shokulab',
        'homepage'      => 'https://github.com/' . kf_updater_repo(),
        'download_link' => kf_updater_package(),
        'requires'      => '6.0',
        'requires_php'  => '7.4',
        'sections'      => array(
            'description' => 'Private trade ledger with a public performance page against the S&amp;P 500 and other benchmarks.',
            'changelog'   => kf_updater_changelog_html(),
        ),
    );
}

/** GitHub's zip unpacks to "repo-main/"; WordPress needs the folder to stay "kaiserfinance/". */
function kf_updater_fix_folder($source, $remote_source, $upgrader, $hook_extra = array()) {
    global $wp_filesystem;
    $ours = isset($hook_extra['plugin']) && $hook_extra['plugin'] === plugin_basename(KF_DIR . 'kaiserfinance.php');
    if (!$ours || !$wp_filesystem) {
        return $source;
    }
    $target = trailingslashit($remote_source) . 'kaiserfinance/';
    if (untrailingslashit($source) === untrailingslashit($target)) {
        return $source;
    }
    if ($wp_filesystem->move($source, $target, true)) {
        return $target;
    }
    return new WP_Error('kf_rename', 'Could not prepare the Kai$erFinance update folder.');
}

function kf_updater_clear($upgrader, $options) {
    if (isset($options['type']) && $options['type'] === 'plugin') {
        delete_transient('kf_remote_version');
    }
}

/** CHANGELOG.md from GitHub as simple HTML for the "View details" window. */
function kf_updater_changelog_html() {
    $fallback = '<p>See the commit history on <a href="' . esc_url('https://github.com/' . kf_updater_repo() . '/commits/main') . '" target="_blank" rel="noopener">GitHub</a>.</p>';
    $res = wp_remote_get('https://raw.githubusercontent.com/' . kf_updater_repo() . '/main/CHANGELOG.md', array('timeout' => 10));
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
        return $fallback;
    }
    $html = '';
    $open = false;
    foreach (preg_split('/\r?\n/', wp_remote_retrieve_body($res)) as $line) {
        if (preg_match('/^##\s+(.+)$/', $line, $m)) {
            if ($open) { $html .= '</ul>'; $open = false; }
            $html .= '<h4>' . esc_html($m[1]) . '</h4>';
        } elseif (preg_match('/^-\s+(.+)$/', $line, $m)) {
            if (!$open) { $html .= '<ul>'; $open = true; }
            $html .= '<li>' . esc_html($m[1]) . '</li>';
        }
    }
    if ($open) { $html .= '</ul>'; }
    return $html !== '' ? $html : $fallback;
}
