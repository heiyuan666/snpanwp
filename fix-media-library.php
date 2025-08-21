<?php
/**
 * 媒体库修复脚本
 * 
 * 用于修复媒体库加载问题
 * 访问方式: yoursite.com/wp-content/plugins/dmy-link1.3.6/fix-media-library.php?action=fix
 */

// 防止直接访问
if (!isset($_GET['action']) || $_GET['action'] !== 'fix') {
    die('Access denied');
}

// 加载WordPress
require_once('../../../wp-load.php');

// 检查权限
if (!current_user_can('manage_options')) {
    die('Permission denied');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>媒体库修复工具</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .fix-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        .button:hover { background: #005a87; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>媒体库修复工具</h1>
    
    <?php
    $action = isset($_POST['fix_action']) ? $_POST['fix_action'] : '';
    
    if ($action === 'clear_cache') {
        // 清除缓存
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // 清除对象缓存
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group('posts');
            wp_cache_delete_group('post_meta');
        }
        
        echo '<div class="fix-section">';
        echo '<p class="success">✅ 缓存已清除</p>';
        echo '</div>';
    }
    
    if ($action === 'check_conflicts') {
        echo '<div class="fix-section">';
        echo '<h2>检查插件冲突</h2>';
        
        // 检查活跃插件
        $active_plugins = get_option('active_plugins');
        $media_related_plugins = array();
        
        foreach ($active_plugins as $plugin) {
            if (strpos(strtolower($plugin), 'media') !== false || 
                strpos(strtolower($plugin), 'image') !== false ||
                strpos(strtolower($plugin), 'gallery') !== false) {
                $media_related_plugins[] = $plugin;
            }
        }
        
        if (!empty($media_related_plugins)) {
            echo '<p class="warning">⚠️ 发现可能冲突的媒体相关插件:</p>';
            echo '<ul>';
            foreach ($media_related_plugins as $plugin) {
                echo '<li>' . esc_html($plugin) . '</li>';
            }
            echo '</ul>';
            echo '<p>建议暂时禁用这些插件进行测试。</p>';
        } else {
            echo '<p class="success">✅ 未发现明显的媒体相关插件冲突</p>';
        }
        echo '</div>';
    }
    
    if ($action === 'reset_settings') {
        // 重置云盘设置中可能导致问题的选项
        $settings = get_option('dmy_cloud_settings', array());
        
        // 保留基本设置，移除可能有问题的设置
        $safe_settings = array(
            'dmy_cloud_enable' => isset($settings['dmy_cloud_enable']) ? $settings['dmy_cloud_enable'] : false,
            'dmy_cloud_auto_replace' => isset($settings['dmy_cloud_auto_replace']) ? $settings['dmy_cloud_auto_replace'] : false,
            'dmy_cloud_keep_local' => isset($settings['dmy_cloud_keep_local']) ? $settings['dmy_cloud_keep_local'] : true,
            'dmy_cloud_aid' => isset($settings['dmy_cloud_aid']) ? $settings['dmy_cloud_aid'] : '',
            'dmy_cloud_key' => isset($settings['dmy_cloud_key']) ? $settings['dmy_cloud_key'] : '',
            'dmy_cloud_folder_id' => isset($settings['dmy_cloud_folder_id']) ? $settings['dmy_cloud_folder_id'] : ''
        );
        
        update_option('dmy_cloud_settings', $safe_settings);
        
        echo '<div class="fix-section">';
        echo '<p class="success">✅ 插件设置已重置为安全模式</p>';
        echo '</div>';
    }
    ?>
    
    <div class="fix-section">
        <h2>问题诊断</h2>
        
        <?php
        // 检查JavaScript错误
        echo '<h3>1. 检查插件状态</h3>';
        
        $plugin_file = 'dmy-link1.3.6/dmy-link.php';
        $is_active = is_plugin_active($plugin_file);
        echo '<p>插件状态: ' . ($is_active ? '<span class="success">已激活</span>' : '<span class="error">未激活</span>') . '</p>';
        
        // 检查设置
        $settings = get_option('dmy_cloud_settings', array());
        echo '<h3>2. 检查插件设置</h3>';
        echo '<p>云盘功能: ' . (isset($settings['dmy_cloud_enable']) && $settings['dmy_cloud_enable'] ? '<span class="success">已启用</span>' : '<span class="warning">已禁用</span>') . '</p>';
        echo '<p>AID配置: ' . (!empty($settings['dmy_cloud_aid']) ? '<span class="success">已配置</span>' : '<span class="error">未配置</span>') . '</p>';
        echo '<p>KEY配置: ' . (!empty($settings['dmy_cloud_key']) ? '<span class="success">已配置</span>' : '<span class="error">未配置</span>') . '</p>';
        
        // 检查钩子
        echo '<h3>3. 检查关键钩子</h3>';
        global $wp_filter;
        
        $critical_hooks = array(
            'wp_get_attachment_url',
            'wp_prepare_attachment_for_js'
        );
        
        foreach ($critical_hooks as $hook) {
            $registered = isset($wp_filter[$hook]) && !empty($wp_filter[$hook]);
            echo '<p>' . $hook . ': ' . ($registered ? '<span class="success">已注册</span>' : '<span class="error">未注册</span>') . '</p>';
        }
        
        // 检查云盘附件
        echo '<h3>4. 检查云盘附件</h3>';
        global $wpdb;
        $cloud_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_dmy_cloud_url' AND meta_value != ''"
        );
        echo '<p>云盘附件数量: ' . $cloud_count . '</p>';
        ?>
    </div>
    
    <div class="fix-section">
        <h2>修复操作</h2>
        
        <form method="post" style="margin: 10px 0;">
            <input type="hidden" name="fix_action" value="clear_cache">
            <button type="submit" class="button">清除缓存</button>
            <p>清除WordPress缓存，解决可能的缓存问题。</p>
        </form>
        
        <form method="post" style="margin: 10px 0;">
            <input type="hidden" name="fix_action" value="check_conflicts">
            <button type="submit" class="button">检查插件冲突</button>
            <p>检查是否有其他插件可能导致媒体库冲突。</p>
        </form>
        
        <form method="post" style="margin: 10px 0;">
            <input type="hidden" name="fix_action" value="reset_settings">
            <button type="submit" class="button">重置插件设置</button>
            <p>将插件设置重置为安全模式，保留基本配置。</p>
        </form>
    </div>
    
    <div class="fix-section">
        <h2>手动修复步骤</h2>
        
        <h3>步骤1: 禁用云盘功能</h3>
        <ol>
            <li>进入 WordPress后台 → 设置 → 零九CDN云盘上传</li>
            <li>取消勾选"启用云盘上传"</li>
            <li>点击"保存更改"</li>
            <li>测试媒体库是否恢复正常</li>
        </ol>
        
        <h3>步骤2: 检查浏览器控制台</h3>
        <ol>
            <li>打开浏览器开发者工具 (F12)</li>
            <li>切换到"控制台"标签</li>
            <li>刷新媒体库页面</li>
            <li>查看是否有JavaScript错误</li>
        </ol>
        
        <h3>步骤3: 排除插件冲突</h3>
        <ol>
            <li>暂时禁用所有其他插件</li>
            <li>测试媒体库是否正常</li>
            <li>逐个启用插件，找出冲突的插件</li>
        </ol>
        
        <h3>步骤4: 检查主题兼容性</h3>
        <ol>
            <li>切换到WordPress默认主题</li>
            <li>测试媒体库是否正常</li>
            <li>如果正常，说明是主题兼容性问题</li>
        </ol>
    </div>
    
    <div class="fix-section">
        <h2>紧急恢复</h2>
        
        <p class="warning">⚠️ 如果媒体库仍然无法使用，可以执行以下紧急恢复操作：</p>
        
        <h3>方法1: 临时禁用插件</h3>
        <p>重命名插件文件夹：<code>dmy-link1.3.6</code> → <code>dmy-link1.3.6-disabled</code></p>
        
        <h3>方法2: 通过数据库禁用</h3>
        <p>在数据库中执行：</p>
        <pre>UPDATE wp_options SET option_value = '' WHERE option_name = 'active_plugins';</pre>
        <p class="error">⚠️ 这会禁用所有插件，请谨慎使用！</p>
        
        <h3>方法3: 联系技术支持</h3>
        <p>访问 <a href="https://www.09cdn.com" target="_blank">www.09cdn.com</a> 获取技术支持。</p>
    </div>
    
    <div class="fix-section">
        <h2>预防措施</h2>
        
        <ul>
            <li>✅ 定期备份网站数据</li>
            <li>✅ 在测试环境中测试插件更新</li>
            <li>✅ 保持WordPress和插件版本最新</li>
            <li>✅ 监控网站错误日志</li>
            <li>✅ 使用兼容的主题和插件</li>
        </ul>
    </div>
    
</body>
</html>
