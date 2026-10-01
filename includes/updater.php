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
        return null;
    }
    $cached = get_transient('kf_remote_version');
    if (!$force && $cached !== false) {
        return $cached ?: null;
    }
    $res = wp_remote_get('https://raw.githubusercontent.com/' . $repo . '/main/kaiserfinance.php', array('timeout' => 10));
    $ver = '';
    if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200
        && preg_match('/^\s*\*\s*Version:\s*([0-9][0-9.]*)/mi', wp_remote_retrieve_body($res), $m)) {
        $ver = $m[1];
    }
    set_transient('kf_remote_version', $ver, $ver ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
    return $ver ?: null;
}

function kf_updater_package() {
    return 'https://github.com/' . kf_updater_repo() . '/archive/refs/heads/main.zip';
}

function kf_updater_check($transient) {
    if (!is_object($transient) || kf_updater_repo() === '') {
        return $transient;
    }
    $force  = isset($_GET['force-check']); // "Check again" on Dashboard → Updates
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
