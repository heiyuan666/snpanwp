<?php
/**
 * 零九CDN云盘上传 - 管理设置页面
 *
 * @package 零九CDN云盘上传
 * @version 1.2.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class ZeroNine_CDN_Cloud_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_options_page(
            '零九CDN云盘上传设置',
            '零九CDN云盘上传',
            'manage_options',
            'dmy-cloud-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('dmy_cloud_settings_group', 'dmy_cloud_settings', array($this, 'sanitize_settings'));
        
        // 基本设置
        add_settings_section(
            'dmy_cloud_basic_section',
            '基本设置',
            array($this, 'basic_section_callback'),
            'dmy-cloud-settings'
        );
        
        add_settings_field(
            'dmy_cloud_enable',
            '启用云盘上传',
            array($this, 'enable_field_callback'),
            'dmy-cloud-settings',
            'dmy_cloud_basic_section'
        );
        
        add_settings_field(
            'dmy_cloud_auto_replace',
            '自动替换上传文件',
            array($this, 'auto_replace_field_callback'),
            'dmy-cloud-settings',
            'dmy_cloud_basic_section'
        );
        
        add_settings_field(
            'dmy_cloud_keep_local',
            '保留本地文件',
            array($this, 'keep_local_field_callback'),
            'dmy-cloud-settings',
            'dmy_cloud_basic_section'
        );
        
        // 云盘配置
        add_settings_section(
            'dmy_cloud_config_section',
            '云盘配置',
            array($this, 'config_section_callback'),
            'dmy-cloud-settings'
        );
        
        add_settings_field(
            'dmy_cloud_aid',
            '云盘 AID',
            array($this, 'aid_field_callback'),
            'dmy-cloud-settings',
            'dmy_cloud_config_section'
        );
        
        add_settings_field(
            'dmy_cloud_key',
            '云盘 KEY',
            array($this, 'key_field_callback'),
            'dmy-cloud-settings',
            'dmy_cloud_config_section'
        );
        
        add_settings_field(
            'dmy_cloud_folder_id',
            '上传文件夹ID',
            array($this, 'folder_id_field_callback'),
            'dmy-cloud-settings',
            'dmy_cloud_config_section'
        );
    }
    
    /**
     * 设置页面
     */
    public function settings_page() {
        $settings = get_option('dmy_cloud_settings', array());
        ?>
        <div class="wrap">
            <h1>零九CDN云盘上传设置</h1>
            
            <div class="dmy-cloud-admin-container">
                <div class="dmy-cloud-main-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('dmy_cloud_settings_group');
                        do_settings_sections('dmy-cloud-settings');
                        ?>
                        
                        <!-- 功能状态显示 -->
                        <div class="dmy-cloud-status-section">
                            <h3>功能状态</h3>
                            <div class="dmy-cloud-status-grid">
                                <div class="status-item">
                                    <span class="status-label">🔄 自动上传:</span>
                                    <span id="auto-upload-status" class="status-value">
                                        <?php echo !empty($settings['dmy_cloud_enable']) && !empty($settings['dmy_cloud_auto_replace']) ? 
                                            '<span style="color: #46b450;">已启用</span>' : 
                                            '<span style="color: #dc3232;">已禁用</span>'; ?>
                                    </span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">🔗 URL替换:</span>
                                    <span id="url-replace-status" class="status-value">
                                        <?php echo !empty($settings['dmy_cloud_enable']) ? 
                                            '<span style="color: #46b450;">已启用</span>' : 
                                            '<span style="color: #dc3232;">已禁用</span>'; ?>
                                    </span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">📡 连接状态:</span>
                                    <span id="connection-status" class="status-value">
                                        <span style="color: #666;">点击测试连接</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 测试连接 -->
                        <div class="dmy-cloud-test-section">
                            <h3>连接测试</h3>
                            <p class="description" style="margin-bottom: 10px;">
                                <strong>注意：</strong>请先填写 AID 和 KEY，然后点击"保存更改"按钮保存设置，再进行连接测试。
                            </p>
                            <button type="button" id="dmy-test-cloud-connection" class="button button-secondary">测试云盘连接</button>
                            <span id="dmy-test-result" style="margin-left: 10px;"></span>
                        </div>
                        
                        <!-- 批量操作 -->
                        <div class="dmy-cloud-batch-section">
                            <h3>批量操作</h3>
                            <div class="batch-buttons" style="margin-bottom: 15px;">
                                <button type="button" id="dmy-batch-replace-media" class="button button-primary">一键替换媒体库图片到云盘</button>
                                <button type="button" id="dmy-clear-progress" class="button button-secondary" style="margin-left: 10px;">清除进度记录</button>
                            </div>

                            <div id="dmy-saved-progress" style="margin-bottom: 10px; display: none;">
                                <div class="notice notice-info inline">
                                    <p><strong>检测到未完成的任务：</strong><span id="saved-progress-text"></span></p>
                                </div>
                            </div>

                            <div id="dmy-replace-progress" style="margin-top: 10px; display: none;">
                                <div class="progress-bar" style="width: 100%; background: #f1f1f1; border-radius: 3px; position: relative;">
                                    <div class="progress-fill" style="width: 0%; background: #0073aa; height: 20px; border-radius: 3px; transition: width 0.3s;"></div>
                                    <div class="progress-percentage" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 12px; color: #fff; font-weight: bold;"></div>
                                </div>
                                <div class="progress-text" style="margin-top: 5px; font-size: 12px;"></div>
                                <div class="progress-actions" style="margin-top: 10px;">
                                    <button type="button" id="dmy-pause-batch" class="button button-secondary button-small">暂停处理</button>
                                    <button type="button" id="dmy-cancel-batch" class="button button-secondary button-small" style="margin-left: 5px;">取消处理</button>
                                </div>
                            </div>

                            <div class="batch-features" style="margin-top: 15px;">
                                <h4>断点续传功能</h4>
                                <ul style="margin-left: 20px; font-size: 13px;">
                                    <li>✅ 自动保存处理进度，页面刷新不丢失</li>
                                    <li>✅ 网络中断自动重试，最多重试3次</li>
                                    <li>✅ 支持暂停和继续处理</li>
                                    <li>✅ 详细的处理日志和错误报告</li>
                                </ul>
                            </div>

                            <p class="description">
                                <strong>注意：</strong>此功能将批量处理媒体库中的所有图片文件，上传到云盘并替换链接。<br/>
                                支持断点续传，即使页面关闭也可以继续未完成的任务。处理过程中建议不要进行其他文件操作。
                            </p>
                        </div>

                        <!-- 删除本地文件功能 -->
                        <div class="dmy-cloud-section" style="margin-top: 20px;">
                            <h3>🗑️ 删除本地文件</h3>
                            <p>对于已上传到云盘的文件，可以删除本地副本以节省服务器空间。</p>

                            <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 10px; margin: 10px 0;">
                                <strong>⚠️ 重要警告：</strong><br>
                                • 删除本地文件后无法恢复<br>
                                • 请确保云盘服务稳定可靠<br>
                                • 建议先备份重要文件<br>
                                • 删除操作不可撤销
                            </div>

                            <button type="button" id="dmy-delete-local-files" class="button button-secondary">
                                🗑️ 批量删除本地文件
                            </button>

                            <div id="dmy-delete-progress" style="display: none; margin-top: 15px;">
                                <div class="progress-bar-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill"></div>
                                    </div>
                                </div>
                                <div class="progress-text" style="margin-top: 5px; font-size: 12px;"></div>
                                <div class="progress-actions" style="margin-top: 10px;">
                                    <button type="button" id="dmy-pause-delete" class="button button-secondary button-small">暂停删除</button>
                                    <button type="button" id="dmy-cancel-delete" class="button button-secondary button-small" style="margin-left: 5px;">取消删除</button>
                                </div>
                            </div>

                            <p class="description" style="margin-top: 10px;">
                                <strong>说明：</strong>此功能只会删除已成功上传到云盘的文件的本地副本。<br/>
                                对于图片文件，会删除所有尺寸版本（缩略图、中等尺寸等）。
                            </p>
                        </div>

                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="dmy-cloud-sidebar">
                    <div class="dmy-cloud-info-box">
                        <h3>使用说明</h3>
                        <ol>
                            <li>填入您的云盘 AID 和 KEY</li>
                            <li>点击"测试云盘连接"验证配置</li>
                            <li>启用自动上传功能</li>
                            <li>上传图片将自动使用云盘链接</li>
                        </ol>
                    </div>
                    
                    <div class="dmy-cloud-info-box">
                        <h3>支持的文件类型</h3>
                        <div class="file-types-grid">
                            <div class="file-type-category">
                                <h4>📷 图片文件</h4>
                                <p>JPG, PNG, GIF, WebP, BMP, TIFF, SVG, ICO</p>
                            </div>
                            <div class="file-type-category">
                                <h4>📄 文档文件</h4>
                                <p>PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT</p>
                            </div>
                            <div class="file-type-category">
                                <h4>🗜️ 压缩文件</h4>
                                <p>ZIP, RAR, 7Z, TAR, GZ, BZ2</p>
                            </div>
                            <div class="file-type-category">
                                <h4>⚙️ 可执行文件</h4>
                                <p>EXE, MSI, DMG, <strong>APK</strong>, DEB, RPM</p>
                                <small style="color: #e74c3c;">⚠️ 请确保文件来源可信</small>
                            </div>
                            <div class="file-type-category">
                                <h4>🎵 音频文件</h4>
                                <p>MP3, WAV, FLAC, AAC, OGG, M4A</p>
                            </div>
                            <div class="file-type-category">
                                <h4>🎬 视频文件</h4>
                                <p>MP4, AVI, MKV, MOV, WMV, WebM</p>
                            </div>
                            <div class="file-type-category">
                                <h4>💻 代码文件</h4>
                                <p>HTML, CSS, JS, PHP, PY, JSON, XML</p>
                            </div>
                            <div class="file-type-category">
                                <h4>📚 其他文件</h4>
                                <p>ISO, TORRENT, EPUB, 字体文件等</p>
                            </div>
                        </div>
                        <p class="description" style="margin-top: 10px; font-size: 12px;">
                            <strong>全面支持：</strong>插件现已支持几乎所有常用文件类型的云盘上传和链接替换。
                        </p>
                    </div>
                    
                    <div class="dmy-cloud-info-box">
                        <h3>技术支持</h3>
                        <p>
                            <strong>作者:</strong> 零九CDN<br/>
                            <strong>网站:</strong> <a href="https://www.09cdn.com" target="_blank">www.09cdn.com</a><br/>
                            <strong>版本:</strong> <?php echo defined('DMY_CLOUD_VERSION') ? DMY_CLOUD_VERSION : '1.2.0'; ?><br/>
                            <strong>PHP版本:</strong>
                            <span style="color: <?php echo version_compare(PHP_VERSION, '8.0', '>=') ? '#00a32a' : (version_compare(PHP_VERSION, '7.0', '>=') ? '#dba617' : '#d63638'); ?>">
                                PHP <?php echo PHP_VERSION; ?>
                                <?php if (version_compare(PHP_VERSION, '8.0', '>=')): ?>
                                    ✅ 推荐
                                <?php elseif (version_compare(PHP_VERSION, '7.0', '>=')): ?>
                                    ⚠️ 兼容
                                <?php else: ?>
                                    ❌ 不支持
                                <?php endif; ?>
                            </span>
                        </p>

                        <div style="margin-top: 15px; padding: 10px; background: #e8f4fd; border-radius: 4px;">
                            <h4 style="margin: 0 0 8px 0; color: #0073aa;">🔧 v1.2.0 重要修复</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 12px;">
                                <li><strong>✅ 修复重复上传</strong>：解决图片生成两张的问题</li>
                                <li><strong>🗑️ 本地文件管理</strong>：修复"不保存副本"功能</li>
                                <li><strong>🔧 修复工具</strong>：专用问题检查和修复工具</li>
                                <li><strong>📊 状态管理</strong>：完整的文件状态检查统计</li>
                                <li><strong>🎨 用户体验</strong>：改进设置说明和操作反馈</li>
                            </ul>
                            <p style="margin: 8px 0 0 0; font-size: 11px; color: #666;">
                                <a href="<?php echo plugin_dir_url(__FILE__) . '../CHANGELOG.md'; ?>" target="_blank">查看完整更新日志</a> |
                                <a href="<?php echo plugin_dir_url(__FILE__) . '../test-all-file-types.php?test=1'; ?>" target="_blank">测试文件类型支持</a> |
                                <a href="<?php echo plugin_dir_url(__FILE__) . '../php-compatibility-check.php?check=1'; ?>" target="_blank">PHP兼容性检查</a> |
                                <a href="<?php echo plugin_dir_url(__FILE__) . '../fix-duplicate-uploads.php?fix=1'; ?>" target="_blank" style="color: #d63638;">修复重复上传</a> |
                                <a href="<?php echo plugin_dir_url(__FILE__) . '../local-files-status.php?check=1'; ?>" target="_blank" style="color: #0073aa;">本地文件状态</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 加载管理页面脚本和样式
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_dmy-cloud-settings') {
            return;
        }
        
        wp_enqueue_script('dmy-cloud-admin', plugin_dir_url(dirname(__FILE__)) . 'js/cloud-upload.js', array('jquery'), '1.2.0', true);
        wp_enqueue_style('dmy-cloud-admin', plugin_dir_url(dirname(__FILE__)) . 'css/cloud-upload.css', array(), '1.2.0');
        
        wp_localize_script('dmy-cloud-admin', 'dmy_cloud_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dmy_cloud_nonce')
        ));
    }
    
    /**
     * 设置验证
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['dmy_cloud_enable'] = !empty($input['dmy_cloud_enable']);
        $sanitized['dmy_cloud_auto_replace'] = !empty($input['dmy_cloud_auto_replace']);
        $sanitized['dmy_cloud_keep_local'] = !empty($input['dmy_cloud_keep_local']);
        $sanitized['dmy_cloud_aid'] = sanitize_text_field($input['dmy_cloud_aid'] ?? '');
        $sanitized['dmy_cloud_key'] = sanitize_text_field($input['dmy_cloud_key'] ?? '');
        $sanitized['dmy_cloud_folder_id'] = sanitize_text_field($input['dmy_cloud_folder_id'] ?? '');
        
        return $sanitized;
    }
    
    // 回调函数
    public function basic_section_callback() {
        echo '<p>配置云盘上传的基本功能设置。</p>';
    }
    
    public function config_section_callback() {
        echo '<p>配置您的云盘连接信息。</p>';
    }
    
    public function enable_field_callback() {
        $settings = get_option('dmy_cloud_settings', array());
        $checked = !empty($settings['dmy_cloud_enable']) ? 'checked' : '';
        echo "<input type='checkbox' name='dmy_cloud_settings[dmy_cloud_enable]' value='1' $checked />";
        echo "<p class='description'>开启后，启用云盘上传功能。</p>";
    }
    
    public function auto_replace_field_callback() {
        $settings = get_option('dmy_cloud_settings', array());
        $checked = !empty($settings['dmy_cloud_auto_replace']) ? 'checked' : '';
        echo "<input type='checkbox' name='dmy_cloud_settings[dmy_cloud_auto_replace]' value='1' $checked />";
        echo "<p class='description'>开启后，WordPress上传图片时自动上传到云盘并替换为云盘链接。</p>";
    }
    
    public function keep_local_field_callback() {
        $settings = get_option('dmy_cloud_settings', array());
        $checked = !empty($settings['dmy_cloud_keep_local']) ? 'checked' : '';
        echo "<input type='checkbox' name='dmy_cloud_settings[dmy_cloud_keep_local]' value='1' $checked />";
        echo "<p class='description'>";
        echo "<strong>开启</strong>：上传到云盘后保留本地文件副本（推荐，确保数据安全）<br>";
        echo "<strong>关闭</strong>：上传到云盘后删除本地文件，节省服务器空间（需确保云盘稳定可靠）";
        echo "</p>";

        // 添加警告信息
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; margin-top: 8px;'>";
        echo "<strong>⚠️ 重要提醒：</strong><br>";
        echo "• 关闭此选项后，本地文件将被永久删除<br>";
        echo "• 请确保云盘服务稳定可靠<br>";
        echo "• 建议先在测试环境中验证<br>";
        echo "• 删除的文件包括图片的所有尺寸版本";
        echo "</div>";
    }
    
    public function aid_field_callback() {
        $settings = get_option('dmy_cloud_settings', array());
        $value = esc_attr($settings['dmy_cloud_aid'] ?? '');
        echo "<input type='text' name='dmy_cloud_settings[dmy_cloud_aid]' value='$value' class='regular-text' />";
        echo "<p class='description'>您的云盘用户秘钥 AID。</p>";
    }
    
    public function key_field_callback() {
        $settings = get_option('dmy_cloud_settings', array());
        $value = esc_attr($settings['dmy_cloud_key'] ?? '');
        echo "<input type='password' name='dmy_cloud_settings[dmy_cloud_key]' value='$value' class='regular-text' />";
        echo "<p class='description'>您的云盘用户秘钥 KEY。</p>";
    }
    
    public function folder_id_field_callback() {
        $settings = get_option('dmy_cloud_settings', array());
        $value = esc_attr($settings['dmy_cloud_folder_id'] ?? '');
        echo "<input type='text' name='dmy_cloud_settings[dmy_cloud_folder_id]' value='$value' class='regular-text' />";
        echo "<p class='description'>指定上传到云盘的文件夹ID，留空则上传到根目录。</p>";
    }
}

// 初始化管理页面
new ZeroNine_CDN_Cloud_Admin();
