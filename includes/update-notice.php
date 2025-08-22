<?php
/**
 * 更新通知组件
 * 
 * 在WordPress管理后台显示更新通知
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class DMY_Update_Notice {
    
    private $github_repo = 'heiyuan666/snpanwp';
    private $plugin_slug = 'dmy-link1.3.6';
    
    public function __construct() {
        add_action('admin_notices', array($this, 'show_update_notice'));
        add_action('wp_ajax_dmy_dismiss_notice', array($this, 'dismiss_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * 显示更新通知
     */
    public function show_update_notice() {
        // 只在插件相关页面显示
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('plugins', 'settings_page_dmy-cloud-settings'))) {
            return;
        }
        
        // 检查是否已经关闭通知
        $dismissed = get_option('dmy_update_notice_dismissed', array());
        $latest_release = get_option('dmy_latest_version');
        
        if (!$latest_release) {
            return;
        }
        
        $latest_version = ltrim($latest_release['tag_name'], 'v');
        
        // 如果已经关闭了这个版本的通知，不再显示
        if (in_array($latest_version, $dismissed)) {
            return;
        }
        
        // 获取当前版本
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_slug . '/dmy-link.php');
        $current_version = $plugin_data['Version'];
        
        // 如果已经是最新版本，不显示通知
        if (version_compare($current_version, $latest_version, '>=')) {
            return;
        }
        
        $release_url = $latest_release['html_url'];
        $download_url = $latest_release['zipball_url'];
        $release_date = date('Y年m月d日', strtotime($latest_release['published_at']));
        
        ?>
        <div class="notice notice-info is-dismissible dmy-update-notice" data-version="<?php echo esc_attr($latest_version); ?>">
            <div style="display: flex; align-items: center; padding: 10px 0;">
                <div style="margin-right: 15px; font-size: 24px;">🎉</div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 5px 0;">速纳云盘上传插件有新版本可用！</h3>
                    <p style="margin: 0;">
                        <strong>最新版本</strong>: v<?php echo esc_html($latest_version); ?> 
                        (当前: v<?php echo esc_html($current_version); ?>) 
                        - 发布于 <?php echo esc_html($release_date); ?>
                    </p>
                    <?php if (!empty($latest_release['body'])): ?>
                    <details style="margin: 10px 0;">
                        <summary style="cursor: pointer; color: #0073aa;">📋 查看更新内容</summary>
                        <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                            <?php echo wp_kses_post(wpautop(substr($latest_release['body'], 0, 500))); ?>
                            <?php if (strlen($latest_release['body']) > 500): ?>
                                <p><a href="<?php echo esc_url($release_url); ?>" target="_blank">查看完整更新说明</a></p>
                            <?php endif; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
                <div style="margin-left: 15px;">
                    <a href="<?php echo esc_url($download_url); ?>" class="button button-primary" style="margin-right: 10px;">
                        📥 下载更新
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=dmy-cloud-settings'); ?>" class="button">
                        ⚙️ 插件设置
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 关闭通知
     */
    public function dismiss_notice() {
        if (!wp_verify_nonce($_POST['nonce'], 'dmy_dismiss_notice')) {
            wp_die('Security check failed');
        }
        
        $version = sanitize_text_field($_POST['version']);
        $dismissed = get_option('dmy_update_notice_dismissed', array());
        
        if (!in_array($version, $dismissed)) {
            $dismissed[] = $version;
            update_option('dmy_update_notice_dismissed', $dismissed);
        }
        
        wp_send_json_success();
    }
    
    /**
     * 加载脚本
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, array('plugins.php', 'settings_page_dmy-cloud-settings'))) {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // 处理通知关闭
            $(document).on('click', '.dmy-update-notice .notice-dismiss', function() {
                const notice = $(this).closest('.dmy-update-notice');
                const version = notice.data('version');
                
                $.post(ajaxurl, {
                    action: 'dmy_dismiss_notice',
                    nonce: '<?php echo wp_create_nonce('dmy_dismiss_notice'); ?>',
                    version: version
                });
            });
            
            // 添加更新通知样式
            $('<style>')
                .prop('type', 'text/css')
                .html(`
                    .dmy-update-notice {
                        border-left-color: #00a32a !important;
                        background: linear-gradient(90deg, #e8f5e8 0%, #f0f8f0 100%);
                    }
                    .dmy-update-notice h3 {
                        color: #00a32a;
                    }
                    .dmy-update-notice details summary {
                        outline: none;
                        user-select: none;
                    }
                    .dmy-update-notice details summary:hover {
                        color: #005a87;
                    }
                `)
                .appendTo('head');
        });
        </script>
        <?php
    }
}

// 初始化更新通知
new DMY_Update_Notice();
