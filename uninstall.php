<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb, $wp_filesystem;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}optimum_gravatar_cache");

$options = get_option('OGC_options');

if ($options !== false) {
    delete_option('OGC_resolved');
    delete_option('OGC_options');
    delete_option('OGC_avatarUsedSizes');

    if ($options['cache_directory']) {
        if (is_dir(ABSPATH.$options['cache_directory']) && validateCacheDirectory($options['cache_directory'])) {
            $wp_filesystem->rmdir(ABSPATH.$options['cache_directory'], true);
        }
    }
}

function validateCacheDirectory($path)
{
    $systemDirectoriesConstants=array(
      ABSPATH,
      WP_CONTENT_DIR,
    );
    $systemDirectories=array(
      ABSPATH."wp-admin",
      ABSPATH."wp-includes",
      WPINC,
      WP_LANG_DIR,
      WP_TEMP_DIR,
      WPMU_PLUGIN_DIR,
      WP_ADMIN_DIR,
      WP_PLUGIN_DIR,
    );
    foreach ($systemDirectoriesConstants as $systemDirectory) {
        if (ABSPATH.$path == $systemDirectory) {
            return false;
        }
    }
    foreach ($systemDirectories as $systemDirectory) {
        if (ABSPATH.$path == $systemDirectory || strpos(ABSPATH.$path, $systemDirectory) === 0) {
            return false;
        }
    }
    return true;
}
