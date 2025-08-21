<?php
/**
 * 设置调试页面
 * 
 * 用于调试云盘设置保存和读取问题
 * 访问方式: yoursite.com/wp-content/plugins/dmy-link1.3.6/debug-settings.php?debug=1
 */

// 防止直接访问
if (!isset($_GET['debug']) || $_GET['debug'] !== '1') {
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
    <title>云盘设置调试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>云盘设置调试页面</h1>
    
    <?php
    // 获取当前设置
    $settings = get_option('dmy_cloud_settings');
    
    echo '<div class="debug-section">';
    echo '<h2>1. 当前设置状态</h2>';
    
    if ($settings === false) {
        echo '<p class="error">❌ 设置选项不存在</p>';
    } elseif (empty($settings)) {
        echo '<p class="error">❌ 设置选项为空</p>';
    } else {
        echo '<p class="success">✅ 设置选项存在</p>';
        echo '<table>';
        echo '<tr><th>设置项</th><th>值</th><th>状态</th></tr>';
        
        $fields = array(
            'dmy_cloud_enable' => '启用云盘上传',
            'dmy_cloud_auto_replace' => '自动替换上传文件',
            'dmy_cloud_keep_local' => '保留本地文件',
            'dmy_cloud_aid' => '云盘 AID',
            'dmy_cloud_key' => '云盘 KEY',
            'dmy_cloud_folder_id' => '上传文件夹ID'
        );
        
        foreach ($fields as $key => $label) {
            $value = isset($settings[$key]) ? $settings[$key] : '';
            $status = '';
            
            if ($key === 'dmy_cloud_aid' || $key === 'dmy_cloud_key') {
                if (empty($value)) {
                    $status = '<span class="error">未配置</span>';
                    $display_value = '(空)';
                } else {
                    $status = '<span class="success">已配置</span>';
                    $display_value = substr($value, 0, 8) . '...';
                }
            } else {
                $display_value = $value ? ($value === true || $value === '1' ? '是' : $value) : '否';
                $status = $value ? '<span class="success">已启用</span>' : '<span class="info">已禁用</span>';
            }
            
            echo '<tr>';
            echo '<td>' . esc_html($label) . '</td>';
            echo '<td>' . esc_html($display_value) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 测试设置保存
    echo '<div class="debug-section">';
    echo '<h2>2. 设置保存测试</h2>';
    
    if (isset($_POST['test_save'])) {
        $test_settings = array(
            'dmy_cloud_enable' => true,
            'dmy_cloud_auto_replace' => true,
            'dmy_cloud_keep_local' => true,
            'dmy_cloud_aid' => 'test_aid_' . time(),
            'dmy_cloud_key' => 'test_key_' . time(),
            'dmy_cloud_folder_id' => 'test_folder'
        );
        
        $result = update_option('dmy_cloud_settings', $test_settings);
        
        if ($result) {
            echo '<p class="success">✅ 测试设置保存成功</p>';
            echo '<p><a href="?debug=1">刷新页面查看结果</a></p>';
        } else {
            echo '<p class="error">❌ 测试设置保存失败</p>';
        }
    } else {
        echo '<form method="post">';
        echo '<button type="submit" name="test_save" value="1">测试保存设置</button>';
        echo '</form>';
        echo '<p class="info">点击按钮将保存测试设置到数据库</p>';
    }
    echo '</div>';
    
    // 数据库检查
    echo '<div class="debug-section">';
    echo '<h2>3. 数据库检查</h2>';
    
    global $wpdb;
    $option_name = 'dmy_cloud_settings';
    $query = $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name);
    $result = $wpdb->get_var($query);
    
    if ($result === null) {
        echo '<p class="error">❌ 数据库中没有找到设置记录</p>';
    } else {
        echo '<p class="success">✅ 数据库中找到设置记录</p>';
        echo '<p><strong>原始数据:</strong></p>';
        echo '<pre>' . esc_html($result) . '</pre>';
        
        $unserialized = maybe_unserialize($result);
        echo '<p><strong>反序列化后:</strong></p>';
        echo '<pre>' . esc_html(print_r($unserialized, true)) . '</pre>';
    }
    echo '</div>';
    
    // WordPress选项API测试
    echo '<div class="debug-section">';
    echo '<h2>4. WordPress选项API测试</h2>';
    
    echo '<p><strong>get_option() 结果:</strong></p>';
    echo '<pre>' . esc_html(print_r($settings, true)) . '</pre>';
    
    echo '<p><strong>选项是否存在:</strong> ' . (get_option('dmy_cloud_settings', 'NOT_FOUND') !== 'NOT_FOUND' ? '是' : '否') . '</p>';
    
    // 测试选项自动加载
    $autoload = $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'dmy_cloud_settings'));
    echo '<p><strong>自动加载:</strong> ' . ($autoload ? $autoload : '未设置') . '</p>';
    echo '</div>';
    
    // 插件状态检查
    echo '<div class="debug-section">';
    echo '<h2>5. 插件状态检查</h2>';
    
    $plugin_file = 'dmy-link1.3.6/dmy-link.php';
    $is_active = is_plugin_active($plugin_file);
    
    echo '<p><strong>插件状态:</strong> ' . ($is_active ? '<span class="success">已激活</span>' : '<span class="error">未激活</span>') . '</p>';
    echo '<p><strong>插件文件:</strong> ' . $plugin_file . '</p>';
    
    // 检查钩子是否注册
    global $wp_filter;
    $hooks_to_check = array(
        'admin_menu',
        'admin_init',
        'admin_enqueue_scripts'
    );
    
    echo '<p><strong>注册的钩子:</strong></p>';
    echo '<ul>';
    foreach ($hooks_to_check as $hook) {
        $registered = isset($wp_filter[$hook]) && !empty($wp_filter[$hook]);
        echo '<li>' . $hook . ': ' . ($registered ? '<span class="success">已注册</span>' : '<span class="error">未注册</span>') . '</li>';
    }
    echo '</ul>';
    echo '</div>';
    
    // 清理测试数据
    if (isset($_POST['cleanup'])) {
        delete_option('dmy_cloud_settings');
        echo '<div class="debug-section">';
        echo '<p class="info">测试数据已清理，<a href="?debug=1">刷新页面</a></p>';
        echo '</div>';
    } else {
        echo '<div class="debug-section">';
        echo '<h2>6. 清理测试数据</h2>';
        echo '<form method="post">';
        echo '<button type="submit" name="cleanup" value="1" onclick="return confirm(\'确定要清理所有设置数据吗？\')">清理测试数据</button>';
        echo '</form>';
        echo '</div>';
    }
    ?>
    
    <div class="debug-section">
        <h2>7. 使用说明</h2>
        <ol>
            <li>检查当前设置状态，确认数据是否正确保存</li>
            <li>如果设置为空，使用"测试保存设置"功能</li>
            <li>检查数据库中的原始数据</li>
            <li>确认插件状态和钩子注册情况</li>
            <li>完成调试后清理测试数据</li>
        </ol>
        
        <p><strong>常见问题:</strong></p>
        <ul>
            <li>如果设置不存在，可能是插件未正确激活</li>
            <li>如果设置为空，可能是表单字段名称不匹配</li>
            <li>如果保存失败，可能是权限问题</li>
        </ul>
    </div>
    
</body>
</html>
