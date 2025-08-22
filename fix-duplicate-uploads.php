<?php
/**
 * 修复重复上传工具
 * 
 * 用于检查和修复图片重复上传到云盘的问题
 * 访问方式: yoursite.com/wp-content/plugins/dmy-link1.3.6/fix-duplicate-uploads.php?fix=1
 */

// 防止直接访问
if (!isset($_GET['fix']) || $_GET['fix'] !== '1') {
    die('Access denied');
}

// 加载WordPress
require_once('../../../wp-load.php');

// 检查权限
if (!current_user_can('manage_options')) {
    die('Permission denied');
}

// 加载插件类
require_once('includes/cloud-upload.php');
$cloud_upload = new ZeroNine_CDN_Cloud_Upload();

// 处理清理请求
$action = isset($_GET['action']) ? $_GET['action'] : '';
$cleaned_duplicates = 0;
$cleaned_locks = 0;
$message = '';

if ($action === 'cleanup_duplicates') {
    $cleaned_duplicates = $cloud_upload->cleanup_duplicate_uploads();
    $message = "已清理 {$cleaned_duplicates} 个重复上传记录";
} elseif ($action === 'cleanup_locks') {
    $cleaned_locks = $cloud_upload->cleanup_upload_locks();
    $message = "已清理 {$cleaned_locks} 个过期上传锁定";
} elseif ($action === 'cleanup_all') {
    $cleaned_duplicates = $cloud_upload->cleanup_duplicate_uploads();
    $cleaned_locks = $cloud_upload->cleanup_upload_locks();
    $message = "已清理 {$cleaned_duplicates} 个重复上传记录和 {$cleaned_locks} 个过期锁定";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>修复重复上传问题</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .fix-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .button { 
            display: inline-block; 
            padding: 10px 20px; 
            background: #0073aa; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
            margin: 5px;
        }
        .button:hover { background: #005a87; }
        .button.danger { background: #d63638; }
        .button.danger:hover { background: #b32d2e; }
        .button.success { background: #00a32a; }
        .button.success:hover { background: #007a1f; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .message { 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 4px; 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            color: #155724; 
        }
    </style>
</head>
<body>
    <h1>🔧 修复重复上传问题</h1>
    
    <?php if ($message): ?>
    <div class="message">
        ✅ <?php echo esc_html($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="fix-section">
        <h2>问题说明</h2>
        
        <p><strong>重复上传问题</strong>：由于插件同时使用了多个WordPress钩子来处理文件上传，可能导致同一个图片被上传两次到云盘。</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 10px 0;">
            <h4>已修复的问题</h4>
            <ul>
                <li>✅ 禁用了 <code>wp_handle_upload</code> 钩子，避免重复处理</li>
                <li>✅ 添加了附件处理标记，防止重复上传</li>
                <li>✅ 增加了云盘URL检查，跳过已上传的文件</li>
                <li>✅ 添加了上传锁定机制，防止并发上传</li>
            </ul>
        </div>
    </div>
    
    <div class="fix-section">
        <h2>检查重复上传</h2>
        
        <?php
        // 检查重复的云盘URL
        global $wpdb;
        $duplicates = $wpdb->get_results("
            SELECT meta_value, COUNT(*) as count 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_dmy_cloud_url' 
            GROUP BY meta_value 
            HAVING count > 1
        ");
        
        $upload_locks = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_dmy_cloud_uploading' 
            AND meta_value < %d
        ", time() - 3600));
        ?>
        
        <table>
            <tr><th>检查项目</th><th>发现问题</th><th>状态</th></tr>
            <tr>
                <td>重复的云盘URL</td>
                <td><?php echo count($duplicates); ?> 个</td>
                <td>
                    <?php if (count($duplicates) > 0): ?>
                        <span class="warning">⚠️ 需要清理</span>
                    <?php else: ?>
                        <span class="success">✅ 正常</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>过期的上传锁定</td>
                <td><?php echo count($upload_locks); ?> 个</td>
                <td>
                    <?php if (count($upload_locks) > 0): ?>
                        <span class="warning">⚠️ 需要清理</span>
                    <?php else: ?>
                        <span class="success">✅ 正常</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <?php if (count($duplicates) > 0): ?>
    <div class="fix-section">
        <h2>重复的云盘URL详情</h2>
        
        <table>
            <tr><th>云盘URL</th><th>重复次数</th><th>附件ID</th></tr>
            <?php foreach ($duplicates as $duplicate): ?>
            <?php
            $attachments = $wpdb->get_results($wpdb->prepare("
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_dmy_cloud_url' 
                AND meta_value = %s
            ", $duplicate->meta_value));
            
            $attachment_ids = array();
            foreach ($attachments as $att) {
                $attachment_ids[] = $att->post_id;
            }
            ?>
            <tr>
                <td style="word-break: break-all;"><?php echo esc_html($duplicate->meta_value); ?></td>
                <td><?php echo $duplicate->count; ?></td>
                <td><?php echo implode(', ', $attachment_ids); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="fix-section">
        <h2>修复操作</h2>
        
        <div style="margin: 15px 0;">
            <h3>清理重复上传记录</h3>
            <p>删除重复的云盘URL记录，保留每个URL的第一个记录。</p>
            <a href="?fix=1&action=cleanup_duplicates" class="button success" 
               onclick="return confirm('确定要清理重复上传记录吗？此操作不可撤销。')">
                🧹 清理重复记录
            </a>
        </div>
        
        <div style="margin: 15px 0;">
            <h3>清理过期上传锁定</h3>
            <p>清理超过1小时的上传锁定，释放被锁定的附件。</p>
            <a href="?fix=1&action=cleanup_locks" class="button" 
               onclick="return confirm('确定要清理过期锁定吗？')">
                🔓 清理过期锁定
            </a>
        </div>
        
        <div style="margin: 15px 0;">
            <h3>一键修复所有问题</h3>
            <p>同时执行上述所有清理操作。</p>
            <a href="?fix=1&action=cleanup_all" class="button danger" 
               onclick="return confirm('确定要执行所有清理操作吗？此操作不可撤销。')">
                🚀 一键修复
            </a>
        </div>
    </div>
    
    <div class="fix-section">
        <h2>预防措施</h2>
        
        <div style="background: #e8f4fd; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px;">
            <h4>已实施的预防措施</h4>
            <ul>
                <li><strong>单一处理钩子</strong>: 只使用 <code>add_attachment</code> 钩子处理上传</li>
                <li><strong>重复检查</strong>: 上传前检查是否已有云盘URL</li>
                <li><strong>处理标记</strong>: 标记已处理的附件，避免重复处理</li>
                <li><strong>上传锁定</strong>: 防止同一文件的并发上传</li>
                <li><strong>详细日志</strong>: 记录所有上传过程，便于调试</li>
            </ul>
        </div>
        
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin-top: 10px;">
            <h4>注意事项</h4>
            <ul>
                <li>清理操作会删除重复的云盘URL记录，但不会删除云盘上的文件</li>
                <li>建议在低峰期执行清理操作</li>
                <li>清理后建议测试文件上传功能</li>
                <li>如有问题，可以查看WordPress错误日志</li>
            </ul>
        </div>
    </div>
    
    <div class="fix-section">
        <h2>测试上传</h2>
        
        <p>修复后，建议测试文件上传功能：</p>
        <ol>
            <li>上传一个测试图片到媒体库</li>
            <li>检查是否只生成一个云盘URL</li>
            <li>查看WordPress错误日志确认无重复上传</li>
            <li>验证图片在网站中正常显示</li>
        </ol>
        
        <a href="<?php echo admin_url('upload.php'); ?>" class="button">📁 访问媒体库</a>
        <a href="<?php echo admin_url('admin.php?page=dmy-cloud-settings'); ?>" class="button">⚙️ 插件设置</a>
    </div>
    
    <div class="fix-section">
        <h2>技术细节</h2>
        
        <h3>修复的代码变更</h3>
        <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">
// 1. 禁用了 wp_handle_upload 钩子
// add_filter('wp_handle_upload', array($this, 'handle_upload_filter'), 999, 2);

// 2. 添加了处理标记
$this->processed_attachments = array();

// 3. 增加了重复检查
if (isset($this->processed_attachments[$attachment_id])) {
    return; // 跳过已处理的附件
}

// 4. 添加了云盘URL检查
$existing_cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
if (!empty($existing_cloud_url)) {
    return $existing_cloud_url; // 跳过已上传的文件
}

// 5. 实施了上传锁定
update_post_meta($attachment_id, '_dmy_cloud_uploading', time());
        </pre>
        
        <h3>相关日志</h3>
        <p>可以在WordPress错误日志中查看以下信息：</p>
        <ul>
            <li><code>DMY Cloud: Attachment X already processed, skipping</code> - 跳过重复处理</li>
            <li><code>DMY Cloud: Attachment X already has cloud URL</code> - 跳过已上传文件</li>
            <li><code>DMY Cloud: Starting upload for attachment X</code> - 开始上传</li>
            <li><code>DMY Cloud: Successfully uploaded attachment X</code> - 上传成功</li>
        </ul>
    </div>
    
</body>
</html>
