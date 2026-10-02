<?php
/**
 * Backup: download the whole ledger as one file and restore it later.
 *  - Full backup (.json): trades, cash, assets, stored prices and settings (never the API key).
 *  - CSV of trades or cash, for spreadsheets.
 *  - Restore: replaces the ledger with a backup file. The current ledger is kept as a safety copy
 *    first, so a wrong file can be undone with one click.
 * Admin only; every action checks the user's rights and a nonce.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_kf_export', 'kf_handle_export');
add_action('admin_post_kf_export_csv', 'kf_handle_export_csv');
add_action('admin_post_kf_import', 'kf_handle_import');
add_action('admin_post_kf_undo_import', 'kf_handle_undo_import');

define('KF_BACKUP_FORMAT', 'kaiserfinance-backup');
define('KF_BACKUP_TABLES', 'trades,cash,instruments,prices');

function kf_backup_tables() {
    return explode(',', KF_BACKUP_TABLES);
}

/** Everything in the ledger as an array (also used for the safety copy). */
function kf_backup_data() {
    global $wpdb;
    $settings = kf_settings();
    unset($settings['api_key']); // never leaves the server
    $out = array(
        'format'   => KF_BACKUP_FORMAT,
        'version'  => KF_VERSION,
        'exported' => gmdate('c'),
        'site'     => home_url('/'),
        'settings' => $settings,
        'tables'   => array(),
    );
    foreach (kf_backup_tables() as $t) {
        $out['tables'][$t] = $wpdb->get_results('SELECT * FROM ' . kf_table($t), ARRAY_A) ?: array();
    }
    return $out;
}

function kf_backup_counts($data) {
    $c = array();
    foreach (kf_backup_tables() as $t) {
        $c[$t] = isset($data['tables'][$t]) && is_array($data['tables'][$t]) ? count($data['tables'][$t]) : 0;
    }
    return $c;
}

function kf_backup_back($msg, $type = 'success') {
    wp_safe_redirect(add_query_arg(array('page' => 'kaiserfinance-settings', 'kf_msg' => rawurlencode($msg), 'kf_type' => $type), admin_url('admin.php')) . '#kf-backup');
    exit;
}

function kf_handle_export() {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }
    check_admin_referer('kf_export');
    update_option('kf_last_backup', time(), false);
    $json = wp_json_encode(kf_backup_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="kaiserfinance-backup-' . gmdate('Y-m-d') . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- file download
    exit;
}

function kf_handle_export_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }
    check_admin_referer('kf_export_csv');
    $which = ($_GET['what'] ?? '') === 'cash' ? 'cash' : 'trades';
    $rows  = $which === 'cash' ? kf_get_cash() : kf_get_trades();
    $cols  = $which === 'cash'
        ? array('flow_date', 'type', 'currency', 'amount', 'currency2', 'amount2', 'note')
        : array('trade_date', 'side', 'symbol', 'name', 'qty', 'price', 'note');
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kaiserfinance-' . $which . '-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens umlauts correctly
    fputcsv($out, $cols);
    foreach ($rows as $r) {
        $line = array();
        foreach ($cols as $c) {
            $v = isset($r[$c]) ? (string) $r[$c] : '';
            // Spreadsheets run cells that start with = + - @ as formulas; keep notes as plain text.
            if ($c === 'note' || $c === 'name') {
                $v = preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
            }
            $line[] = $v;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

/** Check a backup file and return its data, or a WP_Error explaining what's wrong. */
function kf_backup_validate($raw) {
    $data = json_decode((string) $raw, true);
    if (!is_array($data) || ($data['format'] ?? '') !== KF_BACKUP_FORMAT || !isset($data['tables']) || !is_array($data['tables'])) {
        return new WP_Error('kf_format', 'This is not a Kai$erFinance backup file.');
    }
    foreach (kf_backup_tables() as $t) {
        if (isset($data['tables'][$t]) && !is_array($data['tables'][$t])) {
            return new WP_Error('kf_format', 'The backup file is damaged (' . $t . ').');
        }
        foreach ((array) ($data['tables'][$t] ?? array()) as $row) {
            if (!is_array($row)) {
                return new WP_Error('kf_format', 'The backup file is damaged (' . $t . ').');
            }
        }
    }
    return $data;
}

/** Replace the ledger with $data. Only columns that exist in this version's tables are written. */
function kf_backup_restore($data) {
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    foreach (kf_backup_tables() as $t) {
        $table = kf_table($t);
        $cols  = $wpdb->get_col('SHOW COLUMNS FROM ' . $table);
        $wpdb->query('DELETE FROM ' . $table);
        foreach ((array) ($data['tables'][$t] ?? array()) as $row) {
            $clean = array();
            foreach ($cols as $c) {
                if (array_key_exists($c, $row) && (is_scalar($row[$c]) || $row[$c] === null)) {
                    $clean[$c] = $row[$c] === null ? null : (string) $row[$c];
                }
            }
            if ($clean && $wpdb->insert($table, $clean) === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('kf_db', 'Could not restore ' . $t . ': ' . $wpdb->last_error);
            }
        }
    }
    $wpdb->query('COMMIT');

    // Settings from the backup (the API key on this server is kept).
    if (!empty($data['settings']) && is_array($data['settings'])) {
        $keep  = kf_settings();
        $allow = array_keys(kf_default_settings());
        $new   = $keep;
        foreach ($data['settings'] as $k => $v) {
            if ($k !== 'api_key' && in_array($k, $allow, true) && is_scalar($v)) {
                $new[$k] = $v;
            }
        }
        update_option('kf_settings', $new);
    }
    kf_flush_cache();
    delete_transient('kf_og_png');
    update_option('kf_refresh_soon', 1, false);
    return true;
}

function kf_handle_import() {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }
    check_admin_referer('kf_import');
    if (empty($_POST['confirm'])) {
        kf_backup_back('Nothing was changed. Tick the box to confirm that the ledger will be replaced.', 'error');
    }
    $f = $_FILES['backup'] ?? null;
    if (!$f || !empty($f['error']) || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) {
        kf_backup_back('No file arrived. Choose the backup file (.json) and try again.', 'error');
    }
    if ($f['size'] > 50 * MB_IN_BYTES) {
        kf_backup_back('That file is too big to be a Kai$erFinance backup.', 'error');
    }
    $data = kf_backup_validate(file_get_contents($f['tmp_name']));
    if (is_wp_error($data)) {
        kf_backup_back($data->get_error_message() . ' Nothing was changed.', 'error');
    }

    // Safety copy of the current ledger, so this import can be undone.
    update_option('kf_backup_undo', wp_json_encode(kf_backup_data()), false);
    update_option('kf_backup_undo_time', time(), false);

    $res = kf_backup_restore($data);
    if (is_wp_error($res)) {
        $undo = kf_backup_validate(get_option('kf_backup_undo'));
        if (!is_wp_error($undo)) {
            kf_backup_restore($undo);
        }
        kf_backup_back($res->get_error_message() . ' The previous ledger was put back.', 'error');
    }
    $c = kf_backup_counts($data);
    kf_backup_back(sprintf(
        'Backup restored: %d trades, %d cash entries, %d assets, %d prices (from %s). Prices refresh on the next visit.',
        $c['trades'], $c['cash'], $c['instruments'], $c['prices'],
        isset($data['exported']) ? substr((string) $data['exported'], 0, 10) : 'unknown date'
    ));
}

function kf_handle_undo_import() {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }
    check_admin_referer('kf_undo_import');
    $data = kf_backup_validate(get_option('kf_backup_undo'));
    if (is_wp_error($data)) {
        kf_backup_back('There is no earlier ledger to go back to.', 'error');
    }
    $res = kf_backup_restore($data);
    if (is_wp_error($res)) {
        kf_backup_back($res->get_error_message(), 'error');
    }
    delete_option('kf_backup_undo');
    delete_option('kf_backup_undo_time');
    kf_backup_back('Import undone: the ledger is back to how it was before.');
}

/** The "Backup" section on the Settings page. */
function kf_backup_section() {
    $last  = (int) get_option('kf_last_backup', 0);
    $undo  = (int) get_option('kf_backup_undo_time', 0);
    $post  = admin_url('admin-post.php');
    $json  = wp_nonce_url(add_query_arg('action', 'kf_export', $post), 'kf_export');
    $csv   = wp_nonce_url(add_query_arg('action', 'kf_export_csv', $post), 'kf_export_csv');
    ?>
    <hr style="margin:32px 0">
    <h2 id="kf-backup">Backup</h2>
    <p>The ledger lives only in this site's database. Download a backup now and then and keep it somewhere safe (it contains your trades and amounts, so treat it as private). The API key is never included.</p>
    <p>
        <a class="button button-primary" href="<?php echo esc_url($json); ?>">Download backup (.json)</a>
        <a class="button" href="<?php echo esc_url(add_query_arg('what', 'trades', $csv)); ?>">Trades as CSV</a>
        <a class="button" href="<?php echo esc_url(add_query_arg('what', 'cash', $csv)); ?>">Cash as CSV</a>
    </p>
    <p class="description">
        <?php echo $last ? 'Last backup downloaded: ' . esc_html(wp_date('j M Y, H:i', $last)) . '.' : '<strong>No backup downloaded yet.</strong>'; ?>
    </p>

    <h3 style="margin-top:24px">Restore from a backup</h3>
    <p>Replaces the whole ledger (trades, cash, assets, prices) and the settings with the file's contents. Your current ledger is kept as a safety copy, so you can undo this.</p>
    <form method="post" action="<?php echo esc_url($post); ?>" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="kf_import">
        <?php wp_nonce_field('kf_import'); ?>
        <input type="file" name="backup" accept=".json,application/json" required>
        <label><input type="checkbox" name="confirm" value="1" required> Replace the current ledger</label>
        <button class="button">Restore backup</button>
    </form>
    <?php if ($undo) : ?>
        <form method="post" action="<?php echo esc_url($post); ?>" style="margin-top:12px">
            <input type="hidden" name="action" value="kf_undo_import">
            <?php wp_nonce_field('kf_undo_import'); ?>
            <p class="description" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                Safety copy from before the last restore (<?php echo esc_html(wp_date('j M Y, H:i', $undo)); ?>).
                <button class="button button-small">Undo last restore</button>
            </p>
        </form>
    <?php endif; ?>
    <?php
}

/** A gentle reminder on the ledger page when there is data but no recent backup. */
function kf_backup_nudge() {
    global $wpdb;
    $last = (int) get_option('kf_last_backup', 0);
    if ($last > time() - 30 * DAY_IN_SECONDS) {
        return;
    }
    if (!(int) $wpdb->get_var('SELECT COUNT(*) FROM ' . kf_table('trades')) && !(int) $wpdb->get_var('SELECT COUNT(*) FROM ' . kf_table('cash'))) {
        return;
    }
    $url = admin_url('admin.php?page=kaiserfinance-settings#kf-backup');
    echo '<div class="notice notice-info"><p>' . ($last ? 'Your last backup is over a month old.' : 'You haven\'t downloaded a backup yet.')
        . ' <a href="' . esc_url($url) . '">Download one</a>: it takes a second and keeps the ledger safe if the server ever fails.</p></div>';
}
