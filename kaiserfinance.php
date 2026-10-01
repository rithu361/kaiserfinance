<?php
/**
 * Plugin Name: Kai$erFinance
 * Description: Private trade ledger with a public performance page against the S&P 500 and other benchmarks, in USD or CHF. Use the shortcode [kaiserfinance] on any page.
 * Version:     2.3.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      shokulab
 * Update URI:  https://github.com/kaiserfinance
 * License:     GPL-2.0-or-later
 * Text Domain: kaiserfinance
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KF_VERSION', '2.3.2');
define('KF_DIR', plugin_dir_path(__FILE__));
define('KF_URL', plugin_dir_url(__FILE__));
// GitHub repo the plugin updates itself from (owner/name).
define('KF_GITHUB_REPO', '');

require_once KF_DIR . 'includes/db.php';
require_once KF_DIR . 'includes/prices.php';
require_once KF_DIR . 'includes/performance.php';
require_once KF_DIR . 'includes/public.php';
require_once KF_DIR . 'includes/og.php';
require_once KF_DIR . 'includes/updater.php';

if (is_admin()) {
    require_once KF_DIR . 'includes/admin.php';
}

register_activation_hook(__FILE__, 'kf_activate');
register_deactivation_hook(__FILE__, 'kf_deactivate');
