<?php
/**
 * 版本检查和升级脚本
 * 
 * 用于检查插件版本和显示升级信息
 * 访问方式: yoursite.com/wp-content/plugins/dmy-link1.3.6/version-check.php?check=1
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

// 获取插件信息
$plugin_file = 'dmy-link1.3.6/dmy-link.php';
$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
$current_version = $plugin_data['Version'];
$plugin_name = $plugin_data['Name'];

// GitHub仓库信息
$github_repo = 'heiyuan666/snpanwp';
$github_api_url = 'https://api.github.com/repos/' . $github_repo;

// 获取GitHub最新版本信息
function get_github_latest_release($repo_url) {
    $response = wp_remote_get($repo_url . '/releases/latest', array(
        'timeout' => 15,
        'headers' => array(
            'User-Agent' => 'WordPress-Plugin-Updater'
        )
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['tag_name'])) {
        return $data;
    }

    return false;
}

// 获取最新版本信息
$github_release = get_github_latest_release($github_api_url);
$latest_version = $github_release ? ltrim($github_release['tag_name'], 'v') : '1.1.0';
$release_url = $github_release ? $github_release['html_url'] : '';
$download_url = $github_release ? $github_release['zipball_url'] : '';
$release_notes = $github_release ? $github_release['body'] : '';
$release_date = $github_release ? date('Y年m月d日', strtotime($github_release['published_at'])) : '2025年1月22日';

?>
<!DOCTYPE html>
<html>
<head>
    <title>插件版本检查</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .version-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .version-badge { 
            display: inline-block; 
            padding: 4px 8px; 
            background: #0073aa; 
            color: white; 
            border-radius: 3px; 
            font-weight: bold; 
        }
        .new-version { background: #00a32a; }
        .old-version { background: #d63638; }
        .feature-list { 
            background: #f0f6fc; 
            border: 1px solid #c3d9ff; 
            border-radius: 5px; 
            padding: 15px; 
            margin: 10px 0; 
        }
        .feature-item { 
            margin: 8px 0; 
            padding: 5px 0; 
            border-bottom: 1px solid #e1e8ed; 
        }
        .feature-item:last-child { border-bottom: none; }
        .upgrade-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .button {
            display: inline-block;
            padding: 8px 16px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .button:hover {
            background: #005a87;
        }
        .update-log {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>📋 插件版本检查</h1>
    
    <div class="version-section">
        <h2>当前版本信息</h2>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px; font-weight: bold;">插件名称</td>
                <td style="padding: 8px;"><?php echo esc_html($plugin_name); ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px; font-weight: bold;">当前版本</td>
                <td style="padding: 8px;">
                    <span class="version-badge <?php echo version_compare($current_version, '1.1.0', '>=') ? 'new-version' : 'old-version'; ?>">
                        v<?php echo esc_html($current_version); ?>
                    </span>
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px; font-weight: bold;">最新版本</td>
                <td style="padding: 8px;">
                    <span class="version-badge new-version">v<?php echo esc_html($latest_version); ?></span>
                    <?php if ($github_release): ?>
                        <a href="<?php echo esc_url($release_url); ?>" target="_blank" style="margin-left: 10px; font-size: 12px;">📋 查看发布说明</a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px; font-weight: bold;">发布日期</td>
                <td style="padding: 8px;"><?php echo esc_html($release_date); ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px; font-weight: bold;">GitHub仓库</td>
                <td style="padding: 8px;">
                    <a href="https://github.com/<?php echo esc_html($github_repo); ?>" target="_blank">
                        <?php echo esc_html($github_repo); ?>
                    </a>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">版本状态</td>
                <td style="padding: 8px;">
                    <?php
                    $is_latest = version_compare($current_version, $latest_version, '>=');
                    if ($is_latest): ?>
                        <span class="success">✅ 已是最新版本</span>
                    <?php else: ?>
                        <span class="warning">⚠️ 有新版本可用 (v<?php echo esc_html($latest_version); ?>)</span>
                        <?php if ($download_url): ?>
                            <br><a href="#update-section" style="font-size: 12px;">🔄 点击查看更新选项</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <?php if (!$is_latest): ?>
    <div class="upgrade-box" id="update-section">
        <h3>🎉 v<?php echo esc_html($latest_version); ?> 更新可用！</h3>
        <p><strong>建议立即升级以获得最新功能和修复：</strong></p>

        <?php if ($github_release && $release_notes): ?>
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; margin: 10px 0;">
            <h4>📋 GitHub发布说明</h4>
            <div style="max-height: 200px; overflow-y: auto; font-size: 13px;">
                <?php echo wp_kses_post(wpautop($release_notes)); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="feature-list">
            <div class="feature-item">
                <strong>📱 APK文件支持</strong> - 完整支持Android应用包上传和分发
            </div>
            <div class="feature-item">
                <strong>🗂️ 90+文件格式</strong> - 支持文档、压缩包、音视频等几乎所有文件类型
            </div>
            <div class="feature-item">
                <strong>🔧 增强检测</strong> - 智能文件类型识别和MIME类型支持
            </div>
            <div class="feature-item">
                <strong>🔗 修复URL问题</strong> - 解决媒体库链接不一致问题
            </div>
            <div class="feature-item">
                <strong>🛠️ 测试工具</strong> - 完整的诊断和测试功能
            </div>
            <div class="feature-item">
                <strong>🛡️ 安全增强</strong> - 提升文件上传安全性和稳定性
            </div>
        </div>
        
        <div style="margin: 15px 0; padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px;">
            <h4>🔄 更新选项</h4>

            <div style="margin: 10px 0;">
                <h5>方法1: GitHub直接下载</h5>
                <?php if ($download_url): ?>
                    <a href="<?php echo esc_url($download_url); ?>" class="button" style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px 0;">
                        📥 下载最新版本 v<?php echo esc_html($latest_version); ?>
                    </a>
                    <p style="font-size: 12px; color: #666; margin: 5px 0;">
                        直接从GitHub下载最新版本的ZIP文件
                    </p>
                <?php endif; ?>
            </div>

            <div style="margin: 10px 0;">
                <h5>方法2: 自动更新 (推荐)</h5>
                <button onclick="startAutoUpdate()" class="button" style="background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                    🚀 一键自动更新
                </button>
                <p style="font-size: 12px; color: #666; margin: 5px 0;">
                    自动下载并安装最新版本 (需要文件写入权限)
                </p>
                <div id="update-progress" style="display: none; margin: 10px 0; padding: 10px; background: #fff3cd; border-radius: 4px;">
                    <div id="update-status">准备更新...</div>
                    <div style="width: 100%; background: #e9ecef; border-radius: 4px; margin: 5px 0;">
                        <div id="update-bar" style="width: 0%; height: 20px; background: #28a745; border-radius: 4px; transition: width 0.3s;"></div>
                    </div>
                </div>
            </div>
        </div>

        <p><strong>手动升级步骤：</strong></p>
        <ol>
            <li><strong>备份</strong>：备份当前插件设置和文件</li>
            <li><strong>下载</strong>：从GitHub下载最新版本</li>
            <li><strong>解压</strong>：解压ZIP文件</li>
            <li><strong>替换</strong>：替换插件文件夹内容</li>
            <li><strong>检查</strong>：验证功能是否正常</li>
        </ol>
    </div>
    <?php endif; ?>
    
    <div class="version-section">
        <h2>版本历史</h2>
        
        <h3>v1.1.0 (2025-01-22) - 重大功能更新</h3>
        <div class="feature-list">
            <h4>🎯 新增功能</h4>
            <ul>
                <li><strong>全文件类型支持</strong>：支持90+种文件格式</li>
                <li><strong>APK文件特殊支持</strong>：Android应用包上传分发</li>
                <li><strong>增强文件检测</strong>：智能MIME类型识别</li>
                <li><strong>测试诊断工具</strong>：完整的测试和诊断功能</li>
            </ul>
            
            <h4>🔧 功能改进</h4>
            <ul>
                <li><strong>URL替换优化</strong>：修复媒体库链接不一致问题</li>
                <li><strong>批量处理增强</strong>：提升处理速度和准确性</li>
                <li><strong>管理界面改进</strong>：响应式设计和更好的用户体验</li>
                <li><strong>安全性提升</strong>：增强文件验证和权限检查</li>
            </ul>
            
            <h4>🐛 问题修复</h4>
            <ul>
                <li>修复媒体库转圈问题</li>
                <li>修复文件类型限制问题</li>
                <li>修复批量处理错误</li>
                <li>优化内存使用和性能</li>
            </ul>
        </div>
        
        <h3>v1.0.0 (2024-12-01) - 初始版本</h3>
        <div class="feature-list">
            <ul>
                <li>基础的图片文件上传功能</li>
                <li>自动链接替换</li>
                <li>简单的批量处理</li>
                <li>基础的管理界面</li>
            </ul>
        </div>
    </div>
    
    <div class="version-section">
        <h2>兼容性检查</h2>
        
        <?php
        // 检查WordPress版本
        $wp_version = get_bloginfo('version');
        $min_wp_version = '5.0';
        $wp_compatible = version_compare($wp_version, $min_wp_version, '>=');
        
        // 检查PHP版本
        $php_version = PHP_VERSION;
        $min_php_version = '7.4';
        $php_compatible = version_compare($php_version, $min_php_version, '>=');
        
        // 检查必要的PHP扩展
        $required_extensions = array('curl', 'json', 'mbstring');
        $missing_extensions = array();
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        ?>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px; font-weight: bold;">WordPress版本</td>
                <td style="padding: 8px;">
                    <?php echo esc_html($wp_version); ?>
                    <?php if ($wp_compatible): ?>
                        <span class="success">✅ 兼容</span>
                    <?php else: ?>
                        <span class="error">❌ 需要 <?php echo $min_wp_version; ?>+</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 8px; font-weight: bold;">PHP版本</td>
                <td style="padding: 8px;">
                    <?php echo esc_html($php_version); ?>
                    <?php if ($php_compatible): ?>
                        <span class="success">✅ 兼容</span>
                    <?php else: ?>
                        <span class="error">❌ 需要 <?php echo $min_php_version; ?>+</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">PHP扩展</td>
                <td style="padding: 8px;">
                    <?php if (empty($missing_extensions)): ?>
                        <span class="success">✅ 所有必需扩展已安装</span>
                    <?php else: ?>
                        <span class="error">❌ 缺少扩展: <?php echo implode(', ', $missing_extensions); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="version-section">
        <h2>快速测试</h2>
        
        <p>使用以下测试工具验证插件功能：</p>
        
        <ul>
            <li><a href="test-all-file-types.php?test=1" target="_blank">🗂️ 全文件类型支持测试</a></li>
            <li><a href="test-apk-upload.php?test=1" target="_blank">📱 APK文件上传测试</a></li>
            <li><a href="test-media-url-fix.php?test=1" target="_blank">🔗 URL替换测试</a></li>
            <li><a href="fix-media-library.php?action=fix" target="_blank">🛠️ 媒体库修复工具</a></li>
        </ul>
    </div>
    
    <div class="version-section">
        <h2>技术支持</h2>
        
        <p><strong>获取帮助：</strong></p>
        <ul>
            <li>🌐 <strong>官方网站</strong>: <a href="https://www.09cdn.com" target="_blank">www.09cdn.com</a></li>
            <li>📖 <strong>使用文档</strong>: 查看插件目录中的README.md和CHANGELOG.md</li>
            <li>🛠️ <strong>诊断工具</strong>: 使用内置的测试和诊断功能</li>
            <li>📧 <strong>技术支持</strong>: 通过官网联系技术支持团队</li>
        </ul>
        
        <p><strong>反馈渠道：</strong></p>
        <ul>
            <li>🐛 问题报告和错误反馈</li>
            <li>💡 功能建议和改进意见</li>
            <li>⭐ 使用体验和评价分享</li>
        </ul>
    </div>

    <script>
    function startAutoUpdate() {
        const progressDiv = document.getElementById('update-progress');
        const statusDiv = document.getElementById('update-status');
        const progressBar = document.getElementById('update-bar');

        progressDiv.style.display = 'block';

        // 模拟更新过程
        const steps = [
            { text: '正在连接GitHub...', progress: 10 },
            { text: '正在下载最新版本...', progress: 30 },
            { text: '正在验证文件...', progress: 50 },
            { text: '正在备份当前版本...', progress: 70 },
            { text: '正在安装更新...', progress: 90 },
            { text: '更新完成！', progress: 100 }
        ];

        let currentStep = 0;

        function updateProgress() {
            if (currentStep < steps.length) {
                const step = steps[currentStep];
                statusDiv.textContent = step.text;
                progressBar.style.width = step.progress + '%';

                if (currentStep === steps.length - 1) {
                    // 最后一步，显示成功信息
                    setTimeout(() => {
                        statusDiv.innerHTML = '✅ 更新成功！请刷新页面查看新版本。<br><button onclick="location.reload()" style="margin-top: 10px; padding: 5px 10px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">刷新页面</button>';
                    }, 1000);
                } else {
                    currentStep++;
                    setTimeout(updateProgress, 1500);
                }
            }
        }

        // 实际的更新请求
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=dmy_auto_update&nonce=<?php echo wp_create_nonce('dmy_auto_update'); ?>&download_url=<?php echo urlencode($download_url); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProgress();
            } else {
                statusDiv.innerHTML = '❌ 更新失败: ' + (data.data || '未知错误');
                progressBar.style.background = '#dc3545';
            }
        })
        .catch(error => {
            statusDiv.innerHTML = '❌ 更新失败: 网络错误';
            progressBar.style.background = '#dc3545';
        });
    }

    // 检查更新状态
    function checkUpdateStatus() {
        const currentVersion = '<?php echo esc_js($current_version); ?>';
        const latestVersion = '<?php echo esc_js($latest_version); ?>';

        if (currentVersion !== latestVersion) {
            // 显示更新提示
            const notification = document.createElement('div');
            notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 15px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 9999; max-width: 300px;';
            notification.innerHTML = `
                <strong>🎉 新版本可用!</strong><br>
                v${latestVersion} 已发布<br>
                <button onclick="document.getElementById('update-section').scrollIntoView(); this.parentElement.remove();" style="margin-top: 10px; padding: 5px 10px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 3px; cursor: pointer;">查看更新</button>
                <button onclick="this.parentElement.remove();" style="margin-top: 10px; margin-left: 5px; padding: 5px 10px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 3px; cursor: pointer;">关闭</button>
            `;
            document.body.appendChild(notification);

            // 5秒后自动关闭
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
    }

    // 页面加载完成后检查更新
    document.addEventListener('DOMContentLoaded', checkUpdateStatus);
    </script>

</body>
</html>
