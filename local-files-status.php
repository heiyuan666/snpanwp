<?php
/**
 * 本地文件状态检查工具
 * 
 * 检查本地文件保存/删除状态
 * 访问方式: yoursite.com/wp-content/plugins/dmy-link1.3.6/local-files-status.php?check=1
 */

// 防止直接访问
if (!isset($_GET['check']) || $_GET['check'] !== '1') {
    die('Access denied');
}

// 加载WordPress
require_once('../../../wp-load.php');

// 检查权限
if (!current_user_can('manage_options')) {
    die('Permission denied');
}

// 获取设置
$settings = get_option('dmy_cloud_settings', array());
$keep_local = !empty($settings['dmy_cloud_keep_local']);

?>
<!DOCTYPE html>
<html>
<head>
    <title>本地文件状态检查</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .status-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .setting-badge { 
            display: inline-block; 
            padding: 4px 8px; 
            border-radius: 3px; 
            font-weight: bold; 
            color: white;
        }
        .keep-local { background: #4CAF50; }
        .delete-local { background: #f44336; }
    </style>
</head>
<body>
    <h1>📁 本地文件状态检查</h1>
    
    <div class="status-section">
        <h2>当前设置</h2>
        
        <table>
            <tr>
                <td><strong>保留本地文件设置</strong></td>
                <td>
                    <span class="setting-badge <?php echo $keep_local ? 'keep-local' : 'delete-local'; ?>">
                        <?php echo $keep_local ? '✅ 保留本地文件' : '🗑️ 删除本地文件'; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>设置说明</strong></td>
                <td>
                    <?php if ($keep_local): ?>
                        上传到云盘后保留本地文件副本，确保数据安全
                    <?php else: ?>
                        上传到云盘后删除本地文件，节省服务器空间
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="status-section">
        <h2>文件统计</h2>
        
        <?php
        global $wpdb;
        
        // 统计各种状态的文件
        $total_attachments = wp_count_posts('attachment')->inherit;
        
        $uploaded_to_cloud = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_dmy_cloud_uploaded' 
            AND meta_value = '1'
        ");
        
        $local_deleted = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_dmy_cloud_local_deleted' 
            AND meta_value = '1'
        ");
        
        $cloud_with_local = $uploaded_to_cloud - $local_deleted;
        $not_uploaded = $total_attachments - $uploaded_to_cloud;
        ?>
        
        <table>
            <tr><th>文件状态</th><th>数量</th><th>百分比</th><th>说明</th></tr>
            <tr>
                <td>总文件数</td>
                <td><?php echo $total_attachments; ?></td>
                <td>100%</td>
                <td>媒体库中的所有附件</td>
            </tr>
            <tr>
                <td>已上传到云盘</td>
                <td><?php echo $uploaded_to_cloud; ?></td>
                <td><?php echo $total_attachments > 0 ? round(($uploaded_to_cloud / $total_attachments) * 100, 1) : 0; ?>%</td>
                <td>已成功上传到云盘的文件</td>
            </tr>
            <tr>
                <td>云盘+本地都有</td>
                <td><?php echo $cloud_with_local; ?></td>
                <td><?php echo $total_attachments > 0 ? round(($cloud_with_local / $total_attachments) * 100, 1) : 0; ?>%</td>
                <td>云盘和本地都保存的文件</td>
            </tr>
            <tr>
                <td>仅云盘有（本地已删除）</td>
                <td><?php echo $local_deleted; ?></td>
                <td><?php echo $total_attachments > 0 ? round(($local_deleted / $total_attachments) * 100, 1) : 0; ?>%</td>
                <td>只在云盘保存，本地已删除</td>
            </tr>
            <tr>
                <td>仅本地有（未上传）</td>
                <td><?php echo $not_uploaded; ?></td>
                <td><?php echo $total_attachments > 0 ? round(($not_uploaded / $total_attachments) * 100, 1) : 0; ?>%</td>
                <td>只在本地，未上传到云盘</td>
            </tr>
        </table>
    </div>
    
    <div class="status-section">
        <h2>最近删除的本地文件</h2>
        
        <?php
        $recent_deleted = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm1.meta_value as delete_time, pm2.meta_value as cloud_url
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_dmy_cloud_local_delete_time'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_dmy_cloud_url'
            WHERE p.post_type = 'attachment'
            AND pm1.meta_value IS NOT NULL
            ORDER BY pm1.meta_value DESC
            LIMIT 10
        ");
        ?>
        
        <?php if (!empty($recent_deleted)): ?>
        <table>
            <tr><th>附件ID</th><th>文件名</th><th>删除时间</th><th>云盘URL</th></tr>
            <?php foreach ($recent_deleted as $file): ?>
            <tr>
                <td><?php echo $file->ID; ?></td>
                <td><?php echo esc_html($file->post_title); ?></td>
                <td><?php echo $file->delete_time; ?></td>
                <td style="word-break: break-all; max-width: 300px;">
                    <a href="<?php echo esc_url($file->cloud_url); ?>" target="_blank">
                        <?php echo esc_html(substr($file->cloud_url, 0, 50)) . '...'; ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p class="info">📝 暂无本地文件删除记录</p>
        <?php endif; ?>
    </div>
    
    <div class="status-section">
        <h2>存储空间分析</h2>
        
        <?php
        // 计算存储空间使用情况
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];
        
        function get_directory_size($directory) {
            $size = 0;
            if (is_dir($directory)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                    }
                }
            }
            return $size;
        }
        
        function format_bytes($size, $precision = 2) {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
                $size /= 1024;
            }
            return round($size, $precision) . ' ' . $units[$i];
        }
        
        $total_upload_size = get_directory_size($upload_path);
        
        // 估算可节省的空间（已上传但未删除的文件）
        $can_delete_files = $wpdb->get_results("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_dmy_cloud_uploaded' AND pm1.meta_value = '1'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_dmy_cloud_local_deleted'
            WHERE p.post_type = 'attachment'
            AND pm2.meta_value IS NULL
        ");
        
        $potential_savings = 0;
        foreach ($can_delete_files as $file) {
            $file_path = get_attached_file($file->ID);
            if ($file_path && file_exists($file_path)) {
                $potential_savings += filesize($file_path);
                
                // 对于图片，计算所有尺寸的大小
                $metadata = wp_get_attachment_metadata($file->ID);
                if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                    $base_dir = dirname($file_path);
                    foreach ($metadata['sizes'] as $size_data) {
                        if (isset($size_data['file'])) {
                            $size_file = $base_dir . '/' . $size_data['file'];
                            if (file_exists($size_file)) {
                                $potential_savings += filesize($size_file);
                            }
                        }
                    }
                }
            }
        }
        ?>
        
        <table>
            <tr><th>存储项目</th><th>大小</th><th>说明</th></tr>
            <tr>
                <td>上传目录总大小</td>
                <td><?php echo format_bytes($total_upload_size); ?></td>
                <td>wp-content/uploads 目录的总大小</td>
            </tr>
            <tr>
                <td>可节省空间</td>
                <td><?php echo format_bytes($potential_savings); ?></td>
                <td>删除已上传到云盘的本地文件可节省的空间</td>
            </tr>
            <tr>
                <td>节省比例</td>
                <td>
                    <?php 
                    $savings_percent = $total_upload_size > 0 ? round(($potential_savings / $total_upload_size) * 100, 1) : 0;
                    echo $savings_percent . '%';
                    ?>
                </td>
                <td>可节省空间占总空间的比例</td>
            </tr>
        </table>
        
        <?php if ($potential_savings > 0): ?>
        <div style="background: #e8f4fd; border: 1px solid #b3d9ff; border-radius: 4px; padding: 10px; margin-top: 10px;">
            <strong>💡 空间优化建议：</strong><br>
            您可以通过删除已上传到云盘的本地文件节省 <strong><?php echo format_bytes($potential_savings); ?></strong> 的存储空间。<br>
            <a href="<?php echo admin_url('admin.php?page=dmy-cloud-settings'); ?>" style="color: #0073aa;">前往设置页面进行批量删除</a>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="status-section">
        <h2>操作建议</h2>
        
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px;">
            <?php if ($keep_local): ?>
                <h4>🛡️ 当前策略：保留本地文件（安全模式）</h4>
                <p><strong>优点：</strong></p>
                <ul>
                    <li>数据安全性高，有本地备份</li>
                    <li>云盘服务中断时网站仍可正常访问</li>
                    <li>可以随时切换回本地文件</li>
                </ul>
                <p><strong>缺点：</strong></p>
                <ul>
                    <li>占用服务器存储空间</li>
                    <li>需要更多的备份空间</li>
                </ul>
                
                <?php if ($potential_savings > 0): ?>
                <p><strong>💡 优化建议：</strong></p>
                <ul>
                    <li>如果云盘服务稳定，可以考虑删除部分本地文件</li>
                    <li>可以先删除较大的文件或较旧的文件</li>
                    <li>建议保留重要文件的本地副本</li>
                </ul>
                <?php endif; ?>
            <?php else: ?>
                <h4>💾 当前策略：删除本地文件（节省空间模式）</h4>
                <p><strong>优点：</strong></p>
                <ul>
                    <li>节省服务器存储空间</li>
                    <li>减少备份时间和成本</li>
                    <li>自动清理，无需手动管理</li>
                </ul>
                <p><strong>缺点：</strong></p>
                <ul>
                    <li>依赖云盘服务稳定性</li>
                    <li>云盘故障时可能影响网站</li>
                    <li>删除后无法恢复</li>
                </ul>
                
                <p><strong>⚠️ 重要提醒：</strong></p>
                <ul>
                    <li>确保云盘服务稳定可靠</li>
                    <li>定期检查云盘文件完整性</li>
                    <li>重要文件建议额外备份</li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="status-section">
        <h2>快速操作</h2>
        
        <a href="<?php echo admin_url('admin.php?page=dmy-cloud-settings'); ?>" 
           style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 5px;">
            ⚙️ 插件设置
        </a>
        
        <a href="<?php echo admin_url('upload.php'); ?>" 
           style="display: inline-block; padding: 10px 20px; background: #00a32a; color: white; text-decoration: none; border-radius: 4px; margin: 5px;">
            📁 媒体库
        </a>
        
        <a href="<?php echo plugin_dir_url(__FILE__) . 'fix-duplicate-uploads.php?fix=1'; ?>" 
           style="display: inline-block; padding: 10px 20px; background: #d63638; color: white; text-decoration: none; border-radius: 4px; margin: 5px;">
            🔧 修复工具
        </a>
    </div>
    
</body>
</html>
