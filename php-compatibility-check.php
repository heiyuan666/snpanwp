<?php
/**
 * PHP兼容性检查工具
 * 
 * 检查插件在不同PHP版本下的兼容性
 * 访问方式: yoursite.com/wp-content/plugins/dmy-link1.3.6/php-compatibility-check.php?check=1
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP兼容性检查</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .check-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .version-badge { 
            display: inline-block; 
            padding: 4px 8px; 
            border-radius: 3px; 
            font-weight: bold; 
            color: white;
        }
        .php7 { background: #4CAF50; }
        .php8 { background: #2196F3; }
        .unsupported { background: #f44336; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .feature-card { background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 15px; }
    </style>
</head>
<body>
    <h1>🔧 PHP兼容性检查</h1>
    
    <div class="check-section">
        <h2>当前环境信息</h2>
        
        <?php
        $php_version = PHP_VERSION;
        $php_major = PHP_MAJOR_VERSION;
        $php_minor = PHP_MINOR_VERSION;
        $is_php7 = version_compare($php_version, '7.0', '>=') && version_compare($php_version, '8.0', '<');
        $is_php8 = version_compare($php_version, '8.0', '>=');
        $is_supported = version_compare($php_version, '7.0', '>=');
        ?>
        
        <table>
            <tr>
                <td><strong>PHP版本</strong></td>
                <td>
                    <span class="version-badge <?php echo $is_php8 ? 'php8' : ($is_php7 ? 'php7' : 'unsupported'); ?>">
                        PHP <?php echo $php_version; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>兼容性状态</strong></td>
                <td>
                    <?php if ($is_supported): ?>
                        <span class="success">✅ 支持</span>
                    <?php else: ?>
                        <span class="error">❌ 不支持 (需要PHP 7.0+)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>WordPress版本</strong></td>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <td><strong>服务器软件</strong></td>
                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
            </tr>
        </table>
    </div>
    
    <div class="check-section">
        <h2>PHP扩展检查</h2>
        
        <?php
        $required_extensions = array(
            'curl' => 'cURL扩展 (文件上传)',
            'json' => 'JSON扩展 (数据处理)',
            'mbstring' => 'Multibyte String扩展 (字符串处理)',
            'openssl' => 'OpenSSL扩展 (HTTPS连接)',
            'zip' => 'ZIP扩展 (文件解压)',
            'fileinfo' => 'Fileinfo扩展 (文件类型检测)'
        );
        
        $optional_extensions = array(
            'gd' => 'GD扩展 (图片处理)',
            'imagick' => 'ImageMagick扩展 (高级图片处理)',
            'exif' => 'EXIF扩展 (图片元数据)',
            'intl' => 'Intl扩展 (国际化支持)'
        );
        ?>
        
        <h3>必需扩展</h3>
        <table>
            <tr><th>扩展名</th><th>描述</th><th>状态</th></tr>
            <?php foreach ($required_extensions as $ext => $desc): ?>
            <tr>
                <td><?php echo $ext; ?></td>
                <td><?php echo $desc; ?></td>
                <td>
                    <?php if (extension_loaded($ext)): ?>
                        <span class="success">✅ 已安装</span>
                    <?php else: ?>
                        <span class="error">❌ 未安装</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h3>可选扩展</h3>
        <table>
            <tr><th>扩展名</th><th>描述</th><th>状态</th></tr>
            <?php foreach ($optional_extensions as $ext => $desc): ?>
            <tr>
                <td><?php echo $ext; ?></td>
                <td><?php echo $desc; ?></td>
                <td>
                    <?php if (extension_loaded($ext)): ?>
                        <span class="success">✅ 已安装</span>
                    <?php else: ?>
                        <span class="warning">⚠️ 未安装</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="check-section">
        <h2>PHP功能特性检查</h2>
        
        <div class="feature-grid">
            <div class="feature-card">
                <h4>JSON处理</h4>
                <?php
                $json_support = function_exists('json_decode') && function_exists('json_encode');
                $json_throw_support = defined('JSON_THROW_ON_ERROR');
                ?>
                <p><strong>基础JSON支持</strong>: <?php echo $json_support ? '<span class="success">✅</span>' : '<span class="error">❌</span>'; ?></p>
                <p><strong>JSON_THROW_ON_ERROR</strong>: <?php echo $json_throw_support ? '<span class="success">✅ PHP7.3+</span>' : '<span class="warning">⚠️ 不支持</span>'; ?></p>
            </div>
            
            <div class="feature-card">
                <h4>文件操作</h4>
                <?php
                $curl_file_support = class_exists('CURLFile');
                $recursive_iterator = class_exists('RecursiveIteratorIterator');
                ?>
                <p><strong>CURLFile类</strong>: <?php echo $curl_file_support ? '<span class="success">✅</span>' : '<span class="error">❌</span>'; ?></p>
                <p><strong>RecursiveIterator</strong>: <?php echo $recursive_iterator ? '<span class="success">✅</span>' : '<span class="error">❌</span>'; ?></p>
            </div>
            
            <div class="feature-card">
                <h4>异常处理</h4>
                <?php
                $json_exception = class_exists('JsonException');
                $throwable = interface_exists('Throwable');
                ?>
                <p><strong>JsonException</strong>: <?php echo $json_exception ? '<span class="success">✅ PHP7.3+</span>' : '<span class="warning">⚠️ 不支持</span>'; ?></p>
                <p><strong>Throwable接口</strong>: <?php echo $throwable ? '<span class="success">✅ PHP7+</span>' : '<span class="error">❌</span>'; ?></p>
            </div>
            
            <div class="feature-card">
                <h4>类型声明</h4>
                <?php
                $scalar_types = version_compare($php_version, '7.0', '>=');
                $return_types = version_compare($php_version, '7.0', '>=');
                $nullable_types = version_compare($php_version, '7.1', '>=');
                ?>
                <p><strong>标量类型</strong>: <?php echo $scalar_types ? '<span class="success">✅ PHP7+</span>' : '<span class="error">❌</span>'; ?></p>
                <p><strong>返回类型</strong>: <?php echo $return_types ? '<span class="success">✅ PHP7+</span>' : '<span class="error">❌</span>'; ?></p>
                <p><strong>可空类型</strong>: <?php echo $nullable_types ? '<span class="success">✅ PHP7.1+</span>' : '<span class="warning">⚠️ 不支持</span>'; ?></p>
            </div>
        </div>
    </div>
    
    <div class="check-section">
        <h2>插件兼容性测试</h2>
        
        <?php
        // 测试插件的关键功能
        $tests = array();
        
        // 测试JSON处理
        try {
            $test_data = array('test' => 'value', 'number' => 123);
            $json_string = json_encode($test_data);
            $decoded = json_decode($json_string, true);
            $tests['json'] = ($decoded && $decoded['test'] === 'value');
        } catch (Exception $e) {
            $tests['json'] = false;
        }
        
        // 测试cURL
        $tests['curl'] = function_exists('curl_init') && class_exists('CURLFile');
        
        // 测试文件操作
        try {
            $temp_dir = sys_get_temp_dir();
            $test_file = $temp_dir . '/dmy_test_' . time() . '.txt';
            $tests['file_ops'] = file_put_contents($test_file, 'test') !== false;
            if (file_exists($test_file)) {
                unlink($test_file);
            }
        } catch (Exception $e) {
            $tests['file_ops'] = false;
        }
        
        // 测试WordPress函数
        $tests['wp_functions'] = function_exists('wp_remote_get') && function_exists('wp_upload_dir');
        ?>
        
        <table>
            <tr><th>测试项目</th><th>结果</th><th>说明</th></tr>
            <tr>
                <td>JSON处理</td>
                <td><?php echo $tests['json'] ? '<span class="success">✅ 通过</span>' : '<span class="error">❌ 失败</span>'; ?></td>
                <td>JSON编码解码功能</td>
            </tr>
            <tr>
                <td>cURL支持</td>
                <td><?php echo $tests['curl'] ? '<span class="success">✅ 通过</span>' : '<span class="error">❌ 失败</span>'; ?></td>
                <td>HTTP请求和文件上传</td>
            </tr>
            <tr>
                <td>文件操作</td>
                <td><?php echo $tests['file_ops'] ? '<span class="success">✅ 通过</span>' : '<span class="error">❌ 失败</span>'; ?></td>
                <td>文件读写权限</td>
            </tr>
            <tr>
                <td>WordPress函数</td>
                <td><?php echo $tests['wp_functions'] ? '<span class="success">✅ 通过</span>' : '<span class="error">❌ 失败</span>'; ?></td>
                <td>WordPress核心函数</td>
            </tr>
        </table>
    </div>
    
    <div class="check-section">
        <h2>性能和限制检查</h2>
        
        <?php
        $memory_limit = ini_get('memory_limit');
        $max_execution_time = ini_get('max_execution_time');
        $upload_max_filesize = ini_get('upload_max_filesize');
        $post_max_size = ini_get('post_max_size');
        $max_input_vars = ini_get('max_input_vars');
        ?>
        
        <table>
            <tr><th>配置项</th><th>当前值</th><th>建议值</th><th>状态</th></tr>
            <tr>
                <td>内存限制</td>
                <td><?php echo $memory_limit; ?></td>
                <td>256M+</td>
                <td>
                    <?php
                    $memory_bytes = wp_convert_hr_to_bytes($memory_limit);
                    $recommended_bytes = 256 * 1024 * 1024;
                    echo $memory_bytes >= $recommended_bytes ? '<span class="success">✅</span>' : '<span class="warning">⚠️</span>';
                    ?>
                </td>
            </tr>
            <tr>
                <td>执行时间限制</td>
                <td><?php echo $max_execution_time; ?>秒</td>
                <td>300秒+</td>
                <td>
                    <?php echo $max_execution_time >= 300 || $max_execution_time == 0 ? '<span class="success">✅</span>' : '<span class="warning">⚠️</span>'; ?>
                </td>
            </tr>
            <tr>
                <td>上传文件大小限制</td>
                <td><?php echo $upload_max_filesize; ?></td>
                <td>64M+</td>
                <td>
                    <?php
                    $upload_bytes = wp_convert_hr_to_bytes($upload_max_filesize);
                    $recommended_upload = 64 * 1024 * 1024;
                    echo $upload_bytes >= $recommended_upload ? '<span class="success">✅</span>' : '<span class="warning">⚠️</span>';
                    ?>
                </td>
            </tr>
            <tr>
                <td>POST数据大小限制</td>
                <td><?php echo $post_max_size; ?></td>
                <td>64M+</td>
                <td>
                    <?php
                    $post_bytes = wp_convert_hr_to_bytes($post_max_size);
                    $recommended_post = 64 * 1024 * 1024;
                    echo $post_bytes >= $recommended_post ? '<span class="success">✅</span>' : '<span class="warning">⚠️</span>';
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="check-section">
        <h2>兼容性总结</h2>
        
        <?php
        $overall_compatible = $is_supported && $tests['json'] && $tests['curl'] && $tests['file_ops'] && $tests['wp_functions'];
        $missing_extensions = array();
        foreach ($required_extensions as $ext => $desc) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        ?>
        
        <div style="padding: 20px; border-radius: 5px; <?php echo $overall_compatible ? 'background: #d4edda; border: 1px solid #c3e6cb;' : 'background: #f8d7da; border: 1px solid #f5c6cb;'; ?>">
            <?php if ($overall_compatible): ?>
                <h3 style="color: #155724; margin: 0 0 10px 0;">✅ 兼容性检查通过</h3>
                <p style="margin: 0; color: #155724;">您的服务器环境完全支持速纳云盘上传插件的所有功能。</p>
            <?php else: ?>
                <h3 style="color: #721c24; margin: 0 0 10px 0;">❌ 兼容性检查失败</h3>
                <p style="margin: 0 0 10px 0; color: #721c24;">您的服务器环境存在以下问题：</p>
                <ul style="margin: 0; color: #721c24;">
                    <?php if (!$is_supported): ?>
                        <li>PHP版本过低，需要PHP 7.0或更高版本</li>
                    <?php endif; ?>
                    <?php if (!empty($missing_extensions)): ?>
                        <li>缺少必需的PHP扩展: <?php echo implode(', ', $missing_extensions); ?></li>
                    <?php endif; ?>
                    <?php if (!$tests['json']): ?>
                        <li>JSON处理功能异常</li>
                    <?php endif; ?>
                    <?php if (!$tests['curl']): ?>
                        <li>cURL功能不可用</li>
                    <?php endif; ?>
                    <?php if (!$tests['file_ops']): ?>
                        <li>文件操作权限不足</li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <?php if ($overall_compatible): ?>
        <div style="margin-top: 15px; padding: 15px; background: #cce7ff; border: 1px solid #99d6ff; border-radius: 5px;">
            <h4 style="margin: 0 0 10px 0; color: #004085;">🎉 推荐的PHP版本特性</h4>
            <ul style="margin: 0; color: #004085;">
                <?php if ($is_php8): ?>
                    <li>✅ PHP 8.x - 享受最新的性能优化和语言特性</li>
                    <li>✅ 支持联合类型、命名参数等新特性</li>
                    <li>✅ 更好的错误处理和类型安全</li>
                <?php elseif ($is_php7): ?>
                    <li>✅ PHP 7.x - 良好的性能和稳定性</li>
                    <li>💡 建议升级到PHP 8.x以获得更好的性能</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    
</body>
</html>
