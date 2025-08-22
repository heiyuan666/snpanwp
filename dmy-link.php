<?php
/**
 * Plugin Name: 速纳云盘上传
 * Plugin URI: https://www.09cdn.com
 * Description: WordPress速纳云盘上传插件 - 支持图片、文档、压缩包、APK等90+种文件格式自动上传到云盘存储并替换链接 (兼容PHP 7.0-8.x)
 * Version: 1.2.0
 * Requires PHP: 7.0
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
 *
 * 更新日志:
 * v1.1.0 (2025-01-22)
 * - 🎉 新增支持90+种文件格式：APK、EXE、ZIP、RAR、PDF、DOC等
 * - 📱 特别支持Android APK文件上传和分发
 * - 🔧 增强文件类型检测和MIME类型支持
 * - ⚡ 优化批量上传处理逻辑和性能
 * - 🔗 修复媒体库URL替换问题，确保链接一致性
 * - 🛠️ 添加全面的文件类型测试和诊断工具
 * - 🛡️ 提升文件上传安全性和稳定性
 * - 📊 改进管理界面，显示支持的文件类型
 *
 * v1.0.0 (2024-12-01)
 * - 🚀 初始版本发布
 * - 📷 支持图片文件自动上传到云盘
 * - 🔄 自动替换图片链接为云盘链接
 * - ⚙️ 基础的批量处理功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// PHP版本兼容性检查
if (version_compare(PHP_VERSION, '7.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>速纳云盘上传插件</strong>: 需要PHP 7.0或更高版本。当前版本: ' . PHP_VERSION;
        echo '</p></div>';
    });
    return;
}

// 定义插件版本和常量
define('DMY_CLOUD_VERSION', '1.2.0');
define('DMY_CLOUD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DMY_CLOUD_PLUGIN_PATH', plugin_dir_path(__FILE__));

// PHP版本特性检查
define('DMY_CLOUD_PHP_VERSION', PHP_VERSION);
define('DMY_CLOUD_PHP8_COMPATIBLE', version_compare(PHP_VERSION, '8.0', '>='));

// 定义插件URL常量
if (!defined('DMY_CLOUD_URL')) {
    define('DMY_CLOUD_URL', plugin_dir_url(__FILE__));
}

// 引入云盘上传功能设置页面
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

// 引入云盘上传功能
require_once plugin_dir_path(__FILE__) . 'includes/cloud-upload.php';

// 只在管理后台加载自动更新器和更新通知
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/auto-updater.php';
    require_once plugin_dir_path(__FILE__) . 'includes/update-notice.php';
}

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






