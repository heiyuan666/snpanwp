<?php
/**
 * Plugin Name: 速纳云盘上传
 * Plugin URI: https://www.09cdn.com
 * Description: WordPress速纳云盘上传插件 - 自动上传文件到云盘存储并替换链接
 * Version: 1.0.0
 * Author: 零九CDN
 * Author URI: https://www.09cdn.com
 * Text Domain: 09cdn
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件URL常量
if (!defined('DMY_CLOUD_URL')) {
    define('DMY_CLOUD_URL', plugin_dir_url(__FILE__));
}

// 引入云盘上传功能设置页面
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

// 引入云盘上传功能
require_once plugin_dir_path(__FILE__) . 'includes/cloud-upload.php';

// 插件激活钩子
register_activation_hook(__FILE__, 'dmy_cloud_activation');
function dmy_cloud_activation() {
    // 设置默认选项
    $default_settings = array(
        'dmy_cloud_enable' => true,
        'dmy_cloud_auto_replace' => true,
        'dmy_cloud_keep_local' => true,
    );

    // 只在首次激活时设置默认值
    if (!get_option('dmy_cloud_settings')) {
        add_option('dmy_cloud_settings', $default_settings);
    }
}

// 插件停用钩子
register_deactivation_hook(__FILE__, 'dmy_cloud_deactivation');
function dmy_cloud_deactivation() {
    // 清理计划任务
    wp_clear_scheduled_hook('dmy_cloud_upload_single');
}

// 插件卸载时清理数据
function dmy_cloud_uninstall() {
    // 删除插件设置选项
    delete_option('dmy_cloud_settings');

    // 清理所有插件相关的transient数据
    global $wpdb;
    $transients = $wpdb->get_col(
        "SELECT option_name FROM $wpdb->options
        WHERE option_name LIKE '_transient_dmy_cloud_%'
        OR option_name LIKE '_transient_timeout_dmy_cloud_%'"
    );

    foreach ($transients as $transient) {
        $name = str_replace('_transient_', '', $transient);
        delete_transient($name);
    }
}
register_uninstall_hook(__FILE__, 'dmy_cloud_uninstall');






