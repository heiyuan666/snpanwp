<?php
/**
 * 零九CDN云盘上传功能模块
 *
 * 实现WordPress文件自动上传到云盘并替换链接
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class ZeroNine_CDN_Cloud_Upload {
    
    private $settings;
    private $api_base_url = 'https://api.snpan.com';
    
    public function __construct() {
        $this->settings = get_option('dmy_cloud_settings');
        $this->init_hooks();
    }
    
    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 总是添加AJAX处理和管理页面功能
        add_action('wp_ajax_dmy_batch_replace_media', array($this, 'ajax_batch_replace_media'));
        add_action('wp_ajax_dmy_upload_to_cloud', array($this, 'ajax_upload_to_cloud'));
        add_action('wp_ajax_dmy_test_cloud_connection', array($this, 'ajax_test_cloud_connection'));
        add_action('wp_ajax_dmy_verify_attachment_url', array($this, 'ajax_verify_attachment_url'));
        add_action('wp_ajax_dmy_delete_local_files', array($this, 'ajax_delete_local_files'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('manage_media_columns', array($this, 'add_media_column'));
        add_action('manage_media_custom_column', array($this, 'display_media_column'), 10, 2);

        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return;
        }

        // 启用自动上传功能 - 避免重复上传
        // 只使用一个钩子来处理文件上传，防止重复
        add_action('add_attachment', array($this, 'handle_new_attachment'), 10, 1);

        // 添加标记来防止重复处理
        $this->processed_attachments = array();

        // 替换内容中的媒体URL为云盘URL
        add_filter('wp_get_attachment_url', array($this, 'replace_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'replace_attachment_image_src'), 10, 4);
        add_filter('wp_calculate_image_srcset', array($this, 'replace_image_srcset'), 10, 5);

        // 替换文章内容中的图片URL
        add_filter('the_content', array($this, 'replace_content_urls'), 999);
        add_filter('widget_text', array($this, 'replace_content_urls'), 999);

        // 替换编辑器中的URL（用于预览）
        add_filter('wp_get_attachment_image', array($this, 'replace_attachment_image_html'), 10, 5);

        // 处理媒体库插入到编辑器的URL
        add_filter('image_send_to_editor', array($this, 'replace_editor_image_url'), 10, 8);
        add_filter('media_send_to_editor', array($this, 'replace_media_send_to_editor'), 10, 3);

        // 处理AJAX请求中的附件数据
        add_filter('wp_prepare_attachment_for_js', array($this, 'prepare_attachment_for_js'), 10, 3);

        // 拦截WordPress生成的图片尺寸URL
        add_filter('wp_get_attachment_image_url', array($this, 'replace_attachment_image_url'), 10, 4);

        // 添加管理员脚本来处理媒体库 (暂时禁用以修复媒体库加载问题)
        // add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));

        // 添加对更多文件类型的支持
        add_filter('upload_mimes', array($this, 'add_custom_upload_mimes'));
        add_filter('wp_check_filetype_and_ext', array($this, 'check_filetype_and_ext'), 10, 4);

        // 添加异步上传处理
        add_action('dmy_cloud_upload_single', array($this, 'async_upload_single'), 10, 1);

        // 在保存附件元数据时检查云盘URL
        add_filter('wp_update_attachment_metadata', array($this, 'update_attachment_metadata'), 10, 2);
    }
    
    /**
     * 加载管理页面脚本
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'dmy_link_settings') !== false || $hook === 'upload.php') {
            wp_enqueue_script('dmy-cloud-upload', plugin_dir_url(dirname(__FILE__)) . 'js/cloud-upload.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('dmy-cloud-upload', plugin_dir_url(dirname(__FILE__)) . 'css/cloud-upload.css', array(), '1.0.0');
            wp_localize_script('dmy-cloud-upload', 'dmy_cloud_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dmy_cloud_nonce')
            ));
        }
    }
    
    /**
     * 处理新上传的附件
     */
    public function handle_new_attachment($attachment_id) {
        try {
            // 检查是否已经处理过这个附件，防止重复上传
            if (isset($this->processed_attachments[$attachment_id])) {
                error_log('DMY Cloud: Attachment ' . $attachment_id . ' already processed, skipping');
                return;
            }

            // 标记为已处理
            $this->processed_attachments[$attachment_id] = true;

            // 获取附件文件路径
            $file_path = get_attached_file($attachment_id);
            if (!$file_path || !file_exists($file_path)) {
                error_log('DMY Cloud: File not found for attachment ' . $attachment_id);
                return;
            }

            // 检查是否已经上传过到云盘
            $existing_cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
            if (!empty($existing_cloud_url)) {
                error_log('DMY Cloud: Attachment ' . $attachment_id . ' already has cloud URL: ' . $existing_cloud_url);
                return;
            }

            // 检查文件类型，支持所有常用文件格式
            $file_type = wp_check_filetype($file_path);
            if (!$this->is_supported_file_type($file_type['type'], $file_path)) {
                error_log('DMY Cloud: Skipping unsupported file type: ' . $file_type['type']);
                return;
            }

            // 记录文件类型信息
            error_log('DMY Cloud: Processing file type: ' . $file_type['type'] . ' (' . $file_type['ext'] . ') for attachment ' . $attachment_id);

            // 立即上传到云盘（同步方式，但添加超时保护）
            $this->upload_attachment_to_cloud($attachment_id);

        } catch (Exception $e) {
            error_log('DMY Cloud Upload Error: ' . $e->getMessage());
        }
    }

    /**
     * 上传附件到云盘的核心方法
     */
    public function upload_attachment_to_cloud($attachment_id) {
        // 检查是否已经上传过
        $existing_cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
        if (!empty($existing_cloud_url)) {
            error_log('DMY Cloud: Attachment ' . $attachment_id . ' already uploaded to: ' . $existing_cloud_url);
            return $existing_cloud_url;
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            error_log('DMY Cloud: File not found for attachment ' . $attachment_id . ': ' . $file_path);
            return false;
        }

        // 检查文件是否正在上传中（防止并发上传）
        $upload_lock = get_post_meta($attachment_id, '_dmy_cloud_uploading', true);
        if ($upload_lock && (time() - $upload_lock) < 300) { // 5分钟锁定时间
            error_log('DMY Cloud: Attachment ' . $attachment_id . ' is currently being uploaded, skipping');
            return false;
        }

        // 设置上传锁定
        update_post_meta($attachment_id, '_dmy_cloud_uploading', time());

        // 设置最大执行时间
        $original_time_limit = ini_get('max_execution_time');
        set_time_limit(120); // 2分钟超时

        try {
            error_log('DMY Cloud: Starting upload for attachment ' . $attachment_id . ' (' . basename($file_path) . ')');

            // 上传到云盘
            $cloud_url = $this->upload_file_to_cloud($file_path);

            if ($cloud_url) {
                // 保存云盘URL到数据库
                update_post_meta($attachment_id, '_dmy_cloud_url', $cloud_url);
                update_post_meta($attachment_id, '_dmy_cloud_uploaded', true);
                update_post_meta($attachment_id, '_dmy_cloud_upload_time', current_time('mysql'));

                // 清除上传锁定
                delete_post_meta($attachment_id, '_dmy_cloud_uploading');

                error_log('DMY Cloud: Successfully uploaded attachment ' . $attachment_id . ' to ' . $cloud_url);

                // 如果设置不保留本地文件，删除本地文件
                if (empty($this->settings['dmy_cloud_keep_local'])) {
                    $this->delete_local_file_safely($attachment_id, $file_path);
                }

                return $cloud_url;
            } else {
                error_log('DMY Cloud: Failed to upload attachment ' . $attachment_id);
                // 清除上传锁定
                delete_post_meta($attachment_id, '_dmy_cloud_uploading');
                return false;
            }

        } catch (Exception $e) {
            error_log('DMY Cloud Upload Exception: ' . $e->getMessage());
            // 清除上传锁定
            delete_post_meta($attachment_id, '_dmy_cloud_uploading');
            return false;
        } finally {
            // 恢复原始执行时间限制
            set_time_limit($original_time_limit);
            // 确保清除上传锁定
            delete_post_meta($attachment_id, '_dmy_cloud_uploading');
        }
    }

    /**
     * 处理文件上传过滤器（已禁用，避免重复上传）
     *
     * 注意：此函数已被禁用，因为它会导致重复上传同一个文件到云盘。
     * 现在只使用 add_attachment 钩子来处理文件上传。
     */
    public function handle_upload_filter($upload, $context) {
        // 此函数已禁用，避免重复上传
        error_log('DMY Cloud: handle_upload_filter called but disabled to prevent duplicate uploads');
        return $upload;

        /*
        // 原始代码已注释，避免重复上传
        if (isset($upload['error'])) {
            return $upload;
        }

        $file_type = wp_check_filetype($upload['file']);
        if (!$this->is_supported_file_type($file_type['type'], $upload['file'])) {
            return $upload;
        }

        try {
            $cloud_url = $this->upload_file_to_cloud($upload['file']);
            if ($cloud_url) {
                $upload['url'] = $cloud_url;
                $upload['cloud_url'] = $cloud_url;
                error_log('DMY Cloud: Replaced upload URL with cloud URL: ' . $cloud_url);
            }
        } catch (Exception $e) {
            error_log('DMY Cloud Upload Filter Error: ' . $e->getMessage());
        }

        return $upload;
        */
    }

    /**
     * 处理文件上传（保留原方法作为备用）
     */
    public function handle_upload($upload, $context) {
        // 检查上传是否成功
        if (isset($upload['error'])) {
            return $upload;
        }

        // 不在上传过程中处理云盘上传，避免影响WordPress正常流程
        return $upload;
    }

    /**
     * 异步上传单个文件到云盘
     */
    public function async_upload_single($attachment_id) {
        try {
            $file_path = get_attached_file($attachment_id);
            if (!$file_path || !file_exists($file_path)) {
                error_log('DMY Cloud: File not found for async upload: ' . $attachment_id);
                return;
            }

            // 上传到云盘
            $cloud_url = $this->upload_file_to_cloud($file_path);

            if ($cloud_url) {
                // 保存云盘URL
                update_post_meta($attachment_id, '_dmy_cloud_url', $cloud_url);
                update_post_meta($attachment_id, '_dmy_cloud_uploaded', true);

                // 如果不保留本地文件，删除本地文件
                if (empty($this->settings['dmy_cloud_keep_local'])) {
                    $this->delete_local_file_safely($attachment_id, $file_path);
                }

                error_log('DMY Cloud: Successfully uploaded attachment ' . $attachment_id . ' to ' . $cloud_url);
            } else {
                error_log('DMY Cloud: Failed to upload attachment ' . $attachment_id);
            }

        } catch (Exception $e) {
            error_log('DMY Cloud Async Upload Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取AuthCode
     */
    private function get_auth_code() {
        $aid = isset($this->settings['dmy_cloud_aid']) ? $this->settings['dmy_cloud_aid'] : '';
        $key = isset($this->settings['dmy_cloud_key']) ? $this->settings['dmy_cloud_key'] : '';
        
        if (empty($aid) || empty($key)) {
            return false;
        }
        
        $url = $this->api_base_url . '/opapi/GetAuthCode';
        $params = array(
            'aid' => $aid,
            'key' => $key
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('DMY Cloud Upload: Failed to get auth code: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = $this->safe_json_decode($body);

        if ($data && isset($data['code']) && $data['code'] == 200) {
            return $data['data'];
        }
        
        return false;
    }
    
    /**
     * 获取上传地址
     */
    private function get_upload_url($auth_code, $folder_id = '') {
        $url = $this->api_base_url . '/opapi/Getuploads';
        $params = array(
            'authcode' => $auth_code
        );
        
        if (!empty($folder_id)) {
            $params['fid'] = $folder_id;
        }
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = $this->safe_json_decode($body);

        if ($data && isset($data['code']) && $data['code'] == 200) {
            return $data['data'];
        }
        
        return false;
    }
    
    /**
     * 上传文件到云盘
     */
    public function upload_file_to_cloud($file_path) {
        try {
            error_log('DMY Cloud: Starting upload for file: ' . $file_path);

            // 检查文件是否存在
            if (!file_exists($file_path)) {
                error_log('DMY Cloud: File does not exist: ' . $file_path);
                return false;
            }

            // 检查文件大小（限制为100MB）
            $file_size = filesize($file_path);
            if ($file_size > 100 * 1024 * 1024) {
                error_log('DMY Cloud: File too large: ' . $file_size . ' bytes');
                return false;
            }

            // 获取AuthCode
            $auth_code = $this->get_auth_code();
            if (!$auth_code) {
                error_log('DMY Cloud: Failed to get auth code');
                return false;
            }

            // 获取上传地址
            $folder_id = isset($this->settings['dmy_cloud_folder_id']) ? $this->settings['dmy_cloud_folder_id'] : '';
            $upload_data = $this->get_upload_url($auth_code, $folder_id);
            if (!$upload_data) {
                error_log('DMY Cloud: Failed to get upload URL');
                return false;
            }

            // 构建上传URL
            $upload_url = $upload_data['url'] . '/upload?' . $upload_data['query'];
            error_log('DMY Cloud: Upload URL: ' . $upload_url);

            // 使用cURL进行文件上传（更可靠）
            if (function_exists('curl_init')) {
                return $this->upload_with_curl($upload_url, $file_path);
            } else {
                return $this->upload_with_wp_http($upload_url, $file_path);
            }

        } catch (Exception $e) {
            error_log('DMY Cloud Upload Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 使用cURL上传文件
     */
    private function upload_with_curl($upload_url, $file_path) {
        // 检查cURL扩展和文件类 (PHP7-PHP8兼容)
        if (!function_exists('curl_init') || !class_exists('CURLFile')) {
            return $this->upload_with_wp_http($upload_url, $file_path);
        }

        $ch = curl_init();
        if ($ch === false) {
            error_log('DMY Cloud: Failed to initialize cURL');
            return $this->upload_with_wp_http($upload_url, $file_path);
        }

        try {
            // 获取文件MIME类型 (PHP8兼容)
            $mime_type = 'application/octet-stream';

            if (function_exists('mime_content_type') && is_readable($file_path)) {
                $detected_mime = mime_content_type($file_path);
                if ($detected_mime !== false) {
                    $mime_type = $detected_mime;
                }
            } elseif (function_exists('finfo_open') && function_exists('finfo_file')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $detected_mime = finfo_file($finfo, $file_path);
                    if ($detected_mime !== false) {
                        $mime_type = $detected_mime;
                    }
                    finfo_close($finfo);
                }
            }

            // 创建CURLFile对象 (PHP8兼容)
            $curl_file = new CURLFile($file_path, $mime_type, basename($file_path));

            $curl_options = array(
                CURLOPT_URL => $upload_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => array('file' => $curl_file),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'DMY-Cloud-Upload/1.1.0'
            );

            // PHP8: 使用更安全的curl_setopt_array
            if (!curl_setopt_array($ch, $curl_options)) {
                throw new Exception('Failed to set cURL options');
            }

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($response === false || !empty($error)) {
                throw new Exception('cURL execution failed: ' . $error);
            }

            error_log('DMY Cloud: HTTP Code: ' . $http_code);
            error_log('DMY Cloud: Response: ' . substr($response, 0, 500));

            if ($http_code == 200) {
                $data = $this->safe_json_decode($response);
                if ($data && isset($data['code']) && $data['code'] == 200) {
                    error_log('DMY Cloud: Upload successful: ' . $data['data']);
                    return $data['data'];
                } else {
                    $error_msg = isset($data['msg']) ? $data['msg'] : 'Unknown error';
                    throw new Exception('API Error: ' . $error_msg);
                }
            } else {
                throw new Exception('HTTP error code: ' . $http_code);
            }

        } catch (Exception $e) {
            error_log('DMY Cloud: cURL upload error: ' . $e->getMessage());
            return false;
        } finally {
            if (is_resource($ch)) {
                curl_close($ch);
            }
        }
    }

    /**
     * 使用WordPress HTTP API上传文件
     */
    private function upload_with_wp_http($upload_url, $file_path) {
        // 读取文件内容
        $file_content = file_get_contents($file_path);
        if ($file_content === false) {
            error_log('DMY Cloud: Failed to read file content');
            return false;
        }

        // 准备multipart/form-data
        $boundary = wp_generate_password(24, false);
        $body = '';

        // 添加文件字段
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . '"' . "\r\n";
        $body .= 'Content-Type: ' . wp_get_mime_type($file_path) . "\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= '--' . $boundary . '--' . "\r\n";

        // 使用WordPress HTTP API上传文件
        $response = wp_remote_post($upload_url, array(
            'timeout' => 300,
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'Content-Length' => strlen($body)
            ),
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            error_log('DMY Cloud Upload: Upload failed: ' . $response->get_error_message());
            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('DMY Cloud: HTTP Code: ' . $http_code);
        error_log('DMY Cloud: Response: ' . $response_body);

        if ($http_code == 200) {
            $data = $this->safe_json_decode($response_body);
            if ($data && isset($data['code']) && $data['code'] == 200) {
                return $data['data'];
            }
        }

        return false;
    }
    
    /**
     * AJAX批量替换媒体库文件
     */
    public function ajax_batch_replace_media() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dmy_cloud_nonce')) {
            wp_die('Security check failed');
        }

        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // 重新获取最新设置
        $this->settings = get_option('dmy_cloud_settings');
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10; // 每次处理10个文件
        
        // 获取媒体库文件
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => array(
                array(
                    'key' => '_dmy_cloud_uploaded',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        $results = array();
        $total_count = wp_count_posts('attachment')->inherit;
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            if ($file_path && file_exists($file_path)) {
                // 检查是否已经上传过（防止重复上传）
                $existing_cloud_url = get_post_meta($attachment->ID, '_dmy_cloud_url', true);
                if (!empty($existing_cloud_url)) {
                    $results[] = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'status' => 'skipped',
                        'message' => '已上传到云盘: ' . $existing_cloud_url,
                        'cloud_url' => $existing_cloud_url
                    );
                    continue;
                }

                // 检查文件类型是否支持
                $file_type = wp_check_filetype($file_path);
                if (!$this->is_supported_file_type($file_type['type'], $file_path)) {
                    $results[] = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'status' => 'skipped',
                        'message' => '不支持的文件类型: ' . $file_type['type']
                    );
                    continue;
                }

                // 使用统一的上传函数，包含重复检查和锁定机制
                $cloud_url = $this->upload_attachment_to_cloud($attachment->ID);
                if ($cloud_url) {
                    $result = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'cloud_url' => $cloud_url,
                        'status' => 'success',
                        'file_type' => $file_type['type']
                    );

                    // 检查是否删除了本地文件
                    $local_deleted = get_post_meta($attachment->ID, '_dmy_cloud_local_deleted', true);
                    if ($local_deleted) {
                        $result['local_deleted'] = true;
                        $result['message'] = '上传成功，本地文件已删除';
                    }

                    $results[] = $result;
                } else {
                    $results[] = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'status' => 'failed',
                        'file_type' => $file_type['type'],
                        'message' => '上传失败，请查看错误日志'
                    );
                }
            }
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'has_more' => count($attachments) == $per_page,
            'total' => $total_count,
            'processed' => ($page - 1) * $per_page + count($attachments)
        ));
    }

    /**
     * AJAX删除本地文件
     */
    public function ajax_delete_local_files() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dmy_cloud_nonce')) {
            wp_die('Security check failed');
        }

        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10; // 每次处理10个文件

        // 获取已上传到云盘但本地文件未删除的附件
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_dmy_cloud_uploaded',
                    'value' => true,
                    'compare' => '='
                ),
                array(
                    'key' => '_dmy_cloud_local_deleted',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        $results = array();

        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            $cloud_url = get_post_meta($attachment->ID, '_dmy_cloud_url', true);

            if ($file_path && file_exists($file_path) && !empty($cloud_url)) {
                // 删除本地文件
                if ($this->delete_local_file_safely($attachment->ID, $file_path)) {
                    $results[] = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'status' => 'success',
                        'message' => '本地文件已删除',
                        'cloud_url' => $cloud_url
                    );
                } else {
                    $results[] = array(
                        'id' => $attachment->ID,
                        'title' => $attachment->post_title,
                        'status' => 'failed',
                        'message' => '删除本地文件失败'
                    );
                }
            } else {
                $results[] = array(
                    'id' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'status' => 'skipped',
                    'message' => '文件不存在或未上传到云盘'
                );
            }
        }

        wp_send_json_success(array(
            'results' => $results,
            'has_more' => count($attachments) == $per_page,
            'processed' => ($page - 1) * $per_page + count($attachments)
        ));
    }

    /**
     * AJAX单个文件上传
     */
    public function ajax_upload_to_cloud() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dmy_cloud_nonce')) {
            wp_die('Security check failed');
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error('Invalid attachment ID');
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error('File not found');
        }

        $cloud_url = $this->upload_file_to_cloud($file_path);
        if ($cloud_url) {
            update_post_meta($attachment_id, '_dmy_cloud_url', $cloud_url);
            update_post_meta($attachment_id, '_dmy_cloud_uploaded', true);

            wp_send_json_success(array(
                'cloud_url' => $cloud_url
            ));
        } else {
            wp_send_json_error('Upload failed');
        }
    }

    /**
     * AJAX测试云盘连接
     */
    public function ajax_test_cloud_connection() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dmy_cloud_nonce')) {
            wp_die('Security check failed');
        }

        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // 重新获取最新设置
        $this->settings = get_option('dmy_cloud_settings');

        // 测试获取AuthCode
        $auth_code = $this->get_auth_code();
        if (!$auth_code) {
            wp_send_json_error('无法获取AuthCode，请检查AID和KEY是否正确');
        }

        // 测试获取上传地址
        $folder_id = isset($this->settings['dmy_cloud_folder_id']) ? $this->settings['dmy_cloud_folder_id'] : '';
        $upload_data = $this->get_upload_url($auth_code, $folder_id);
        if (!$upload_data) {
            wp_send_json_error('无法获取上传地址，请检查文件夹ID是否正确');
        }

        wp_send_json_success('云盘连接测试成功！可以正常使用上传功能。');
    }

    /**
     * 替换附件URL为云盘URL
     */
    public function replace_attachment_url($url, $attachment_id) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $url;
        }

        $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
        if ($cloud_url) {
            error_log('DMY Cloud: Replacing URL for attachment ' . $attachment_id . ': ' . $url . ' -> ' . $cloud_url);
            return $cloud_url;
        }
        return $url;
    }

    /**
     * 替换附件图片源为云盘URL
     */
    public function replace_attachment_image_src($image, $attachment_id, $size, $icon) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $image;
        }

        if ($image && is_array($image)) {
            $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
            if ($cloud_url) {
                error_log('DMY Cloud: Replacing image src for attachment ' . $attachment_id);
                $image[0] = $cloud_url;
            }
        }
        return $image;
    }

    /**
     * 替换WordPress生成的图片尺寸URL
     */
    public function replace_attachment_image_url($url, $attachment_id, $size, $icon) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $url;
        }

        $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
        if ($cloud_url) {
            error_log('DMY Cloud: Replacing image URL for attachment ' . $attachment_id . ' (size: ' . $size . '): ' . $url . ' -> ' . $cloud_url);
            return $cloud_url;
        }

        return $url;
    }

    /**
     * 替换图片srcset中的URL
     */
    public function replace_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $sources;
        }

        $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
        if ($cloud_url && is_array($sources)) {
            foreach ($sources as $width => $source) {
                $sources[$width]['url'] = $cloud_url;
            }
        }

        return $sources;
    }

    /**
     * 替换文章内容中的文件URL
     */
    public function replace_content_urls($content) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $content;
        }

        // 获取网站的上传目录URL
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];

        // 查找所有图片标签
        $img_pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
        $content = preg_replace_callback($img_pattern, function($matches) use ($upload_url) {
            return $this->replace_file_url_in_content($matches, $upload_url);
        }, $content);

        // 查找所有链接标签中的文件URL
        $link_pattern = '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i';
        $content = preg_replace_callback($link_pattern, function($matches) use ($upload_url) {
            $link_tag = $matches[0];
            $link_url = $matches[1];

            // 只处理本站上传的文件
            if (strpos($link_url, $upload_url) !== false) {
                $attachment_id = $this->get_attachment_id_by_url($link_url);

                if ($attachment_id) {
                    $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
                    if ($cloud_url) {
                        $new_link_tag = str_replace($link_url, $cloud_url, $link_tag);
                        error_log('DMY Cloud: Replacing content link URL: ' . $link_url . ' -> ' . $cloud_url);
                        return $new_link_tag;
                    }
                }
            }

            return $link_tag;
        }, $content);

        // 查找所有直接的文件URL（不在标签中的）
        $url_pattern = '/(' . preg_quote($upload_url, '/') . '[^\s<>"\']+)/i';
        $content = preg_replace_callback($url_pattern, function($matches) {
            $file_url = $matches[1];
            $attachment_id = $this->get_attachment_id_by_url($file_url);

            if ($attachment_id) {
                $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
                if ($cloud_url) {
                    error_log('DMY Cloud: Replacing direct URL: ' . $file_url . ' -> ' . $cloud_url);
                    return $cloud_url;
                }
            }

            return $file_url;
        }, $content);

        return $content;
    }

    /**
     * 替换内容中的文件URL（辅助函数）
     */
    private function replace_file_url_in_content($matches, $upload_url) {
        $tag = $matches[0];
        $file_url = $matches[1];

        // 只处理本站上传的文件
        if (strpos($file_url, $upload_url) !== false) {
            $attachment_id = $this->get_attachment_id_by_url($file_url);

            if ($attachment_id) {
                $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
                if ($cloud_url) {
                    $new_tag = str_replace($file_url, $cloud_url, $tag);
                    error_log('DMY Cloud: Replacing content file URL: ' . $file_url . ' -> ' . $cloud_url);
                    return $new_tag;
                }
            }
        }

        return $tag;
    }

    /**
     * 替换附件图片HTML中的URL
     */
    public function replace_attachment_image_html($html, $attachment_id, $size, $icon, $attr) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $html;
        }

        $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
        if ($cloud_url && $html) {
            // 替换HTML中的src属性
            $html = preg_replace('/src=["\']([^"\']+)["\']/', 'src="' . esc_url($cloud_url) . '"', $html);
            error_log('DMY Cloud: Replacing attachment image HTML for ID: ' . $attachment_id);
        }

        return $html;
    }

    /**
     * 根据URL获取附件ID（支持WordPress尺寸URL）
     */
    private function get_attachment_id_by_url($url) {
        global $wpdb;

        // 移除查询参数
        $url = strtok($url, '?');

        // 获取文件名
        $filename = basename($url);

        // 移除WordPress添加的尺寸信息（如 -150x150, -300x200 等）
        $original_filename = preg_replace('/-\d+x\d+(?=\.[^.]*$)/', '', $filename);

        // 首先尝试原始文件名
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta
             WHERE meta_key = '_wp_attached_file'
             AND meta_value LIKE %s",
            '%' . $original_filename
        ));

        // 如果没找到，尝试带尺寸的文件名
        if (!$attachment_id) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta
                 WHERE meta_key = '_wp_attached_file'
                 AND meta_value LIKE %s",
                '%' . $filename
            ));
        }

        // 如果还没找到，尝试通过guid查找（使用原始文件名）
        if (!$attachment_id) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM $wpdb->posts
                 WHERE post_type = 'attachment'
                 AND guid LIKE %s",
                '%' . $original_filename
            ));
        }

        // 最后尝试通过guid查找（使用带尺寸的文件名）
        if (!$attachment_id) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM $wpdb->posts
                 WHERE post_type = 'attachment'
                 AND guid LIKE %s",
                '%' . $filename
            ));
        }

        error_log('DMY Cloud: Looking for attachment by URL: ' . $url . ' (original: ' . $original_filename . ', with size: ' . $filename . ') -> ID: ' . $attachment_id);

        return $attachment_id;
    }

    /**
     * 加载媒体库处理脚本
     */
    public function enqueue_media_scripts($hook) {
        // 只在需要媒体库的页面加载
        if (in_array($hook, array('post.php', 'post-new.php', 'upload.php'))) {
            wp_enqueue_script('dmy-cloud-media', plugin_dir_url(__FILE__) . '../js/media-handler.js', array('jquery', 'media-editor'), '1.0.0', true);

            // 传递云盘附件数据给JavaScript
            $cloud_attachments = $this->get_cloud_attachments_data();
            wp_localize_script('dmy-cloud-media', 'dmyCloudMedia', array(
                'cloudAttachments' => $cloud_attachments,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dmy_cloud_media_nonce')
            ));
        }
    }

    /**
     * 获取云盘附件数据
     */
    private function get_cloud_attachments_data() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT post_id, meta_value as cloud_url
             FROM $wpdb->postmeta
             WHERE meta_key = '_dmy_cloud_url'
             AND meta_value != ''",
            ARRAY_A
        );

        $cloud_data = array();
        foreach ($results as $row) {
            $cloud_data[$row['post_id']] = $row['cloud_url'];
        }

        return $cloud_data;
    }

    /**
     * 替换发送到编辑器的图片URL
     */
    public function replace_editor_image_url($html, $id, $caption, $title, $align, $url, $size, $alt) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $html;
        }

        $cloud_url = get_post_meta($id, '_dmy_cloud_url', true);
        if ($cloud_url) {
            // 获取原始的本地URL
            $local_url = wp_get_attachment_url($id);

            // 替换HTML中的所有本地URL为云盘URL
            if ($local_url) {
                $html = str_replace($local_url, $cloud_url, $html);
                error_log('DMY Cloud: Replacing editor image URL for ID ' . $id . ': ' . $local_url . ' -> ' . $cloud_url);
            }
        }

        return $html;
    }

    /**
     * 替换发送到编辑器的媒体URL
     */
    public function replace_media_send_to_editor($html, $id, $attachment) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $html;
        }

        // 检查是否为支持的附件类型
        $attachment_path = get_attached_file($id);
        if (!$attachment_path || !$this->is_supported_file_extension($attachment_path)) {
            return $html;
        }

        $cloud_url = get_post_meta($id, '_dmy_cloud_url', true);
        if ($cloud_url) {
            // 获取所有可能的本地URL尺寸
            $metadata = wp_get_attachment_metadata($id);

            // 替换主图片URL
            $local_url = wp_get_attachment_url($id);
            if ($local_url) {
                $html = str_replace($local_url, $cloud_url, $html);
            }

            // 替换各种尺寸的URL
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $size_data) {
                    $size_url = wp_get_attachment_image_url($id, $size);
                    if ($size_url) {
                        $html = str_replace($size_url, $cloud_url, $html);
                    }
                }
            }

            error_log('DMY Cloud: Replacing media send to editor for ID: ' . $id);
        }

        return $html;
    }

    /**
     * 为JavaScript准备附件数据时替换URL
     */
    public function prepare_attachment_for_js($response, $attachment, $meta) {
        // 检查是否启用云盘功能
        if (empty($this->settings['dmy_cloud_enable'])) {
            return $response;
        }

        $cloud_url = get_post_meta($attachment->ID, '_dmy_cloud_url', true);
        if ($cloud_url) {
            // 保存原始URL作为备份
            $response['original_url'] = $response['url'];

            // 替换主URL
            $response['url'] = $cloud_url;
            $response['cloud_url'] = $cloud_url;

            // 对于云盘图片，所有尺寸都使用同一个云盘URL
            // 因为云盘存储的是原图，不需要WordPress的多尺寸版本
            if (isset($response['sizes']) && is_array($response['sizes'])) {
                foreach ($response['sizes'] as $size => $size_data) {
                    // 保存原始尺寸URL
                    $response['sizes'][$size]['original_url'] = $response['sizes'][$size]['url'];

                    // 所有尺寸都使用云盘原图URL
                    $response['sizes'][$size]['url'] = $cloud_url;

                    // 保持尺寸信息，但URL统一使用云盘链接
                    // 这样前端可以通过CSS控制显示尺寸
                }
            }

            // 添加云盘状态标识
            $response['cloud_uploaded'] = true;

            error_log('DMY Cloud: Preparing attachment for JS - ID: ' . $attachment->ID . ', Cloud URL: ' . $cloud_url);
            error_log('DMY Cloud: All sizes now use cloud URL: ' . $cloud_url);
        } else {
            $response['cloud_uploaded'] = false;
        }

        return $response;
    }

    /**
     * 更新附件元数据时处理云盘URL
     */
    public function update_attachment_metadata($data, $attachment_id) {
        // 检查是否有云盘URL需要处理
        if (isset($_POST['cloud_url']) && !empty($_POST['cloud_url'])) {
            update_post_meta($attachment_id, '_dmy_cloud_url', sanitize_url($_POST['cloud_url']));
            update_post_meta($attachment_id, '_dmy_cloud_uploaded', true);
        }

        return $data;
    }

    /**
     * 获取云盘文件的鉴权链接
     */
    public function get_cloud_file_auth_url($file_identifier) {
        $auth_code = $this->get_auth_code();
        if (!$auth_code) {
            return false;
        }

        $url = $this->api_base_url . '/opapi/GetSign';
        $params = array(
            'file' => $file_identifier,
            'authcode' => $auth_code
        );

        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = $this->safe_json_decode($body);

        if ($data && isset($data['code']) && $data['code'] == 200) {
            return $data['data'];
        }

        return false;
    }

    /**
     * 添加媒体库云盘状态列
     */
    public function add_media_column($columns) {
        $columns['cloud_status'] = '云盘状态';
        return $columns;
    }

    /**
     * 显示媒体库云盘状态列内容
     */
    public function display_media_column($column_name, $attachment_id) {
        if ($column_name === 'cloud_status') {
            $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
            $is_uploaded = get_post_meta($attachment_id, '_dmy_cloud_uploaded', true);

            if ($cloud_url && $is_uploaded) {
                echo '<span style="color: #46b450;">✓ 已上传</span><br>';
                echo '<a href="' . esc_url($cloud_url) . '" target="_blank" style="font-size: 11px;">查看云盘文件</a>';
            } else {
                echo '<span style="color: #666;">未上传</span><br>';
                echo '<button class="button button-small dmy-upload-single" data-attachment-id="' . $attachment_id . '">上传到云盘</button>';
            }
        }
    }

    /**
     * AJAX验证附件URL
     */
    public function ajax_verify_attachment_url() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dmy_cloud_media_nonce')) {
            wp_die('Security check failed');
        }

        // 检查权限
        if (!current_user_can('upload_files')) {
            wp_die('Insufficient permissions');
        }

        $url = sanitize_url($_POST['url']);
        $attachment_id = intval($_POST['attachment_id']);

        // 检查URL是否属于指定的附件
        $matches = $this->verify_url_belongs_to_attachment($url, $attachment_id);

        wp_send_json_success(array(
            'matches' => $matches,
            'url' => $url,
            'attachment_id' => $attachment_id
        ));
    }

    /**
     * 验证URL是否属于指定的附件
     */
    private function verify_url_belongs_to_attachment($url, $attachment_id) {
        // 获取附件的文件路径
        $attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);
        if (!$attached_file) {
            return false;
        }

        // 获取文件名（不含路径）
        $filename = basename($attached_file);
        $url_filename = basename($url);

        // 移除尺寸信息进行比较
        $clean_filename = preg_replace('/-\d+x\d+(?=\.[^.]*$)/', '', $filename);
        $clean_url_filename = preg_replace('/-\d+x\d+(?=\.[^.]*$)/', '', $url_filename);

        // 检查文件名是否匹配
        $matches = ($clean_filename === $clean_url_filename) || ($filename === $url_filename);

        error_log('DMY Cloud: Verifying URL ' . $url . ' for attachment ' . $attachment_id . ': ' . ($matches ? 'MATCH' : 'NO MATCH'));

        return $matches;
    }

    /**
     * 添加自定义文件类型到WordPress允许上传列表
     */
    public function add_custom_upload_mimes($mimes) {
        // 添加常用但WordPress默认不支持的文件类型
        $custom_mimes = array(
            // Android应用
            'apk' => 'application/vnd.android.package-archive',

            // iOS应用
            'ipa' => 'application/octet-stream',

            // 压缩文件
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'xz' => 'application/x-xz',

            // 可执行文件
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'dmg' => 'application/x-apple-diskimage',
            'deb' => 'application/x-debian-package',
            'rpm' => 'application/x-rpm',

            // 音频文件
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/opus',

            // 视频文件
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',

            // 字体文件
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'eot' => 'application/vnd.ms-fontobject',

            // 其他文件
            'iso' => 'application/x-iso9660-image',
            'torrent' => 'application/x-bittorrent',
            'epub' => 'application/epub+zip',
            'mobi' => 'application/x-mobipocket-ebook',
            'azw3' => 'application/x-mobipocket-ebook',

            // 配置文件
            'conf' => 'text/plain',
            'cfg' => 'text/plain',
            'ini' => 'text/plain',
            'yaml' => 'text/plain',
            'yml' => 'text/plain',
            'toml' => 'text/plain',
            'log' => 'text/plain'
        );

        // 合并到现有的MIME类型列表
        return array_merge($mimes, $custom_mimes);
    }

    /**
     * 增强文件类型检查
     */
    public function check_filetype_and_ext($data, $file, $filename, $mimes) {
        $wp_filetype = wp_check_filetype($filename, $mimes);
        $ext = $wp_filetype['ext'];
        $type = $wp_filetype['type'];

        // 如果WordPress无法识别文件类型，尝试我们的自定义检测
        if (!$type || !$ext) {
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // 特殊处理一些文件类型
            switch ($file_ext) {
                case 'apk':
                    $type = 'application/vnd.android.package-archive';
                    $ext = 'apk';
                    break;
                case 'ipa':
                    $type = 'application/octet-stream';
                    $ext = 'ipa';
                    break;
                case 'exe':
                case 'msi':
                    $type = 'application/x-msdownload';
                    $ext = $file_ext;
                    break;
                case 'dmg':
                    $type = 'application/x-apple-diskimage';
                    $ext = 'dmg';
                    break;
                case 'deb':
                    $type = 'application/x-debian-package';
                    $ext = 'deb';
                    break;
                case 'rpm':
                    $type = 'application/x-rpm';
                    $ext = 'rpm';
                    break;
                case 'rar':
                    $type = 'application/x-rar-compressed';
                    $ext = 'rar';
                    break;
                case '7z':
                    $type = 'application/x-7z-compressed';
                    $ext = '7z';
                    break;
                case 'iso':
                    $type = 'application/x-iso9660-image';
                    $ext = 'iso';
                    break;
                case 'torrent':
                    $type = 'application/x-bittorrent';
                    $ext = 'torrent';
                    break;
            }

            if ($type && $ext) {
                $data['ext'] = $ext;
                $data['type'] = $type;
                $data['proper_filename'] = $filename;
            }
        }

        return $data;
    }

    /**
     * 检查文件类型是否支持上传到云盘
     */
    private function is_supported_file_type($mime_type, $file_path) {
        // 支持的MIME类型
        $supported_mime_types = array(
            // 图片文件
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'image/bmp', 'image/tiff', 'image/svg+xml', 'image/x-icon',

            // 文档文件
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'text/rtf',
            'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',

            // 压缩文件
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
            'application/x-tar', 'application/gzip', 'application/x-bzip2', 'application/x-xz',

            // 可执行文件
            'application/x-msdownload', 'application/x-msdos-program', 'application/x-executable',
            'application/x-apple-diskimage', 'application/x-debian-package', 'application/x-rpm',
            'application/vnd.android.package-archive',

            // 音频文件
            'audio/mpeg', 'audio/wav', 'audio/flac', 'audio/aac', 'audio/ogg',
            'audio/mp4', 'audio/x-ms-wma',

            // 视频文件
            'video/mp4', 'video/x-msvideo', 'video/x-matroska', 'video/quicktime',
            'video/x-ms-wmv', 'video/x-flv', 'video/webm', 'video/3gpp',

            // 代码文件
            'text/html', 'text/css', 'application/javascript', 'application/x-httpd-php',
            'text/x-python', 'text/x-java-source', 'text/x-c', 'text/xml', 'application/json',
            'application/sql',

            // 其他文件
            'application/octet-stream', 'application/x-iso9660-image', 'application/x-bittorrent',
            'application/epub+zip', 'application/x-mobipocket-ebook'
        );

        // 如果MIME类型在支持列表中，直接返回true
        if (in_array($mime_type, $supported_mime_types)) {
            return true;
        }

        // 如果MIME类型检测失败或为通用类型，通过文件扩展名判断
        if ($mime_type === 'application/octet-stream' || empty($mime_type)) {
            return $this->is_supported_file_extension($file_path);
        }

        // 对于未明确支持的MIME类型，也通过扩展名检查
        return $this->is_supported_file_extension($file_path);
    }

    /**
     * 通过文件扩展名检查是否支持
     */
    private function is_supported_file_extension($file_path) {
        $supported_extensions = array(
            // 图片文件
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'svg', 'ico',

            // 文档文件
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf',
            'odt', 'ods', 'odp', 'csv',

            // 压缩文件
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'lzma',

            // 可执行文件
            'exe', 'msi', 'dmg', 'pkg', 'deb', 'rpm', 'apk', 'ipa',

            // 音频文件
            'mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a', 'wma', 'opus',

            // 视频文件
            'mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'm4v', '3gp', 'ogv',

            // 代码文件
            'html', 'htm', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'h',
            'xml', 'json', 'sql', 'sh', 'bat', 'ps1',

            // 字体文件
            'ttf', 'otf', 'woff', 'woff2', 'eot',

            // 其他常用文件
            'iso', 'bin', 'img', 'torrent', 'epub', 'mobi', 'azw3', 'fb2',
            'log', 'conf', 'cfg', 'ini', 'yaml', 'yml', 'toml'
        );

        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        return in_array($file_extension, $supported_extensions);
    }

    /**
     * 检查文件是否为图片（用于特殊处理）
     */
    private function is_image_file($file_path) {
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'svg', 'ico');
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        return in_array($file_extension, $image_extensions);
    }

    /**
     * 安全的JSON解码 (PHP7-PHP8兼容)
     */
    private function safe_json_decode($json, $assoc = true) {
        if (empty($json)) {
            return null;
        }

        try {
            // PHP8: 使用JSON_THROW_ON_ERROR标志
            if (defined('JSON_THROW_ON_ERROR')) {
                return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
            } else {
                // PHP7: 传统方式
                $result = json_decode($json, $assoc);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('DMY Cloud: JSON decode error: ' . json_last_error_msg());
                    return null;
                }
                return $result;
            }
        } catch (JsonException $e) {
            error_log('DMY Cloud: JSON decode exception: ' . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log('DMY Cloud: JSON decode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 清理重复上传的文件（管理工具）
     */
    public function cleanup_duplicate_uploads() {
        global $wpdb;

        // 查找可能重复的云盘URL
        $duplicates = $wpdb->get_results("
            SELECT meta_value, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_dmy_cloud_url'
            GROUP BY meta_value
            HAVING count > 1
        ");

        $cleaned = 0;
        foreach ($duplicates as $duplicate) {
            error_log('DMY Cloud: Found duplicate cloud URL: ' . $duplicate->meta_value . ' (used ' . $duplicate->count . ' times)');

            // 获取使用此URL的所有附件
            $attachments = $wpdb->get_results($wpdb->prepare("
                SELECT post_id
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_dmy_cloud_url'
                AND meta_value = %s
            ", $duplicate->meta_value));

            // 保留第一个，删除其他的云盘URL记录
            $first = true;
            foreach ($attachments as $attachment) {
                if ($first) {
                    $first = false;
                    continue;
                }

                // 删除重复的云盘URL记录
                delete_post_meta($attachment->post_id, '_dmy_cloud_url');
                delete_post_meta($attachment->post_id, '_dmy_cloud_uploaded');
                $cleaned++;

                error_log('DMY Cloud: Cleaned duplicate upload record for attachment ' . $attachment->post_id);
            }
        }

        return $cleaned;
    }

    /**
     * 检查并修复上传锁定（清理过期的锁定）
     */
    public function cleanup_upload_locks() {
        global $wpdb;

        // 清理超过1小时的上传锁定
        $expired_locks = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_dmy_cloud_uploading'
            AND meta_value < %d
        ", time() - 3600));

        $cleaned = 0;
        foreach ($expired_locks as $lock) {
            delete_post_meta($lock->post_id, '_dmy_cloud_uploading');
            $cleaned++;
            error_log('DMY Cloud: Cleaned expired upload lock for attachment ' . $lock->post_id);
        }

        return $cleaned;
    }

    /**
     * 安全删除本地文件
     */
    private function delete_local_file_safely($attachment_id, $file_path) {
        // 再次确认设置
        if (!empty($this->settings['dmy_cloud_keep_local'])) {
            error_log('DMY Cloud: Keep local file setting is enabled, not deleting file for attachment ' . $attachment_id);
            return false;
        }

        // 确认文件存在
        if (!file_exists($file_path)) {
            error_log('DMY Cloud: File does not exist, cannot delete: ' . $file_path);
            return false;
        }

        // 确认云盘URL存在
        $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
        if (empty($cloud_url)) {
            error_log('DMY Cloud: No cloud URL found, not deleting local file for attachment ' . $attachment_id);
            return false;
        }

        // 对于图片文件，需要删除所有尺寸的文件
        if ($this->is_image_file($file_path)) {
            $this->delete_image_files($attachment_id, $file_path);
        } else {
            // 删除单个文件
            if (@unlink($file_path)) {
                error_log('DMY Cloud: Successfully deleted local file: ' . $file_path);

                // 更新附件记录，标记本地文件已删除
                update_post_meta($attachment_id, '_dmy_cloud_local_deleted', true);
                update_post_meta($attachment_id, '_dmy_cloud_local_delete_time', current_time('mysql'));

                return true;
            } else {
                error_log('DMY Cloud: Failed to delete local file: ' . $file_path);
                return false;
            }
        }
    }

    /**
     * 删除图片的所有尺寸文件
     */
    private function delete_image_files($attachment_id, $main_file_path) {
        $deleted_files = array();
        $failed_files = array();

        // 获取图片的所有尺寸
        $metadata = wp_get_attachment_metadata($attachment_id);

        // 删除主文件
        if (@unlink($main_file_path)) {
            $deleted_files[] = basename($main_file_path);
            error_log('DMY Cloud: Deleted main image file: ' . $main_file_path);
        } else {
            $failed_files[] = basename($main_file_path);
            error_log('DMY Cloud: Failed to delete main image file: ' . $main_file_path);
        }

        // 删除各种尺寸的文件
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $base_dir = dirname($main_file_path);

            foreach ($metadata['sizes'] as $size => $size_data) {
                if (isset($size_data['file'])) {
                    $size_file_path = $base_dir . '/' . $size_data['file'];

                    if (file_exists($size_file_path)) {
                        if (@unlink($size_file_path)) {
                            $deleted_files[] = $size_data['file'] . ' (' . $size . ')';
                            error_log('DMY Cloud: Deleted image size file: ' . $size_file_path);
                        } else {
                            $failed_files[] = $size_data['file'] . ' (' . $size . ')';
                            error_log('DMY Cloud: Failed to delete image size file: ' . $size_file_path);
                        }
                    }
                }
            }
        }

        // 记录删除结果
        if (!empty($deleted_files)) {
            update_post_meta($attachment_id, '_dmy_cloud_local_deleted', true);
            update_post_meta($attachment_id, '_dmy_cloud_local_delete_time', current_time('mysql'));
            update_post_meta($attachment_id, '_dmy_cloud_deleted_files', $deleted_files);

            error_log('DMY Cloud: Successfully deleted ' . count($deleted_files) . ' files for attachment ' . $attachment_id);
        }

        if (!empty($failed_files)) {
            update_post_meta($attachment_id, '_dmy_cloud_delete_failed_files', $failed_files);
            error_log('DMY Cloud: Failed to delete ' . count($failed_files) . ' files for attachment ' . $attachment_id);
        }

        return count($failed_files) === 0;
    }
}

// 初始化云盘上传功能
new ZeroNine_CDN_Cloud_Upload();
