<?php
/**
 * 自动更新处理器
 * 
 * 处理从GitHub自动更新插件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class DMY_Auto_Updater {
    
    private $github_repo = 'heiyuan666/snpanwp';
    private $plugin_slug = 'dmy-link1.3.6';
    private $plugin_file = 'dmy-link.php';
    
    public function __construct() {
        add_action('wp_ajax_dmy_auto_update', array($this, 'handle_auto_update'));
        add_action('init', array($this, 'check_for_updates'));
        
        // 添加WordPress插件更新检查
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_plugin_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
    }
    
    /**
     * 处理AJAX自动更新请求
     */
    public function handle_auto_update() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dmy_auto_update')) {
            wp_die('Security check failed');
        }
        
        // 检查权限
        if (!current_user_can('update_plugins')) {
            wp_send_json_error('权限不足');
        }
        
        $download_url = sanitize_url($_POST['download_url']);
        if (empty($download_url)) {
            wp_send_json_error('下载链接无效');
        }
        
        // 执行更新
        $result = $this->perform_update($download_url);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * 执行插件更新
     */
    private function perform_update($download_url) {
        try {
            // 创建临时目录
            $temp_dir = wp_upload_dir()['basedir'] . '/dmy-temp-update/';
            if (!wp_mkdir_p($temp_dir)) {
                return array('success' => false, 'message' => '无法创建临时目录');
            }
            
            // 下载文件
            $zip_file = $temp_dir . 'update.zip';
            $response = wp_remote_get($download_url, array(
                'timeout' => 300,
                'stream' => true,
                'filename' => $zip_file
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => '下载失败: ' . $response->get_error_message());
            }
            
            // 检查文件是否下载成功
            if (!file_exists($zip_file)) {
                return array('success' => false, 'message' => '下载的文件不存在');
            }
            
            // 解压文件
            $extract_dir = $temp_dir . 'extracted/';
            $unzip_result = unzip_file($zip_file, $extract_dir);
            
            if (is_wp_error($unzip_result)) {
                return array('success' => false, 'message' => '解压失败: ' . $unzip_result->get_error_message());
            }
            
            // 查找解压后的插件目录
            $extracted_dirs = glob($extract_dir . '*', GLOB_ONLYDIR);
            if (empty($extracted_dirs)) {
                return array('success' => false, 'message' => '未找到解压的插件目录');
            }
            
            $source_dir = $extracted_dirs[0] . '/';
            $plugin_dir = WP_PLUGIN_DIR . '/' . $this->plugin_slug . '/';
            
            // 备份当前插件
            $backup_dir = $temp_dir . 'backup/';
            if (!wp_mkdir_p($backup_dir)) {
                return array('success' => false, 'message' => '无法创建备份目录');
            }
            
            $this->copy_directory($plugin_dir, $backup_dir);
            
            // 复制新文件
            $copy_result = $this->copy_directory($source_dir, $plugin_dir);
            if (!$copy_result) {
                // 恢复备份
                $this->copy_directory($backup_dir, $plugin_dir);
                return array('success' => false, 'message' => '文件复制失败，已恢复备份');
            }
            
            // 清理临时文件
            $this->delete_directory($temp_dir);
            
            return array('success' => true, 'message' => '更新成功');
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => '更新过程中发生错误: ' . $e->getMessage());
        }
    }
    
    /**
     * 复制目录 (PHP7-PHP8兼容)
     */
    private function copy_directory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }

        if (!wp_mkdir_p($destination)) {
            return false;
        }

        try {
            // PHP8兼容性：使用try-catch包装迭代器
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

                if ($item->isDir()) {
                    if (!wp_mkdir_p($target)) {
                        error_log('DMY Auto Updater: Failed to create directory: ' . $target);
                        continue;
                    }
                } else {
                    // 确保目标目录存在
                    $target_dir = dirname($target);
                    if (!is_dir($target_dir)) {
                        wp_mkdir_p($target_dir);
                    }

                    if (!copy($item->getPathname(), $target)) {
                        error_log('DMY Auto Updater: Failed to copy file: ' . $item->getPathname() . ' to ' . $target);
                        continue;
                    }
                }
            }

            return true;

        } catch (Exception $e) {
            error_log('DMY Auto Updater: Directory copy error: ' . $e->getMessage());
            return $this->copy_directory_fallback($source, $destination);
        }
    }

    /**
     * 目录复制的备用方法 (兼容性更好)
     */
    private function copy_directory_fallback($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }

        if (!wp_mkdir_p($destination)) {
            return false;
        }

        $files = scandir($source);
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $source_path = $source . DIRECTORY_SEPARATOR . $file;
            $dest_path = $destination . DIRECTORY_SEPARATOR . $file;

            if (is_dir($source_path)) {
                $this->copy_directory_fallback($source_path, $dest_path);
            } else {
                copy($source_path, $dest_path);
            }
        }

        return true;
    }
    
    /**
     * 删除目录 (PHP7-PHP8兼容)
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        try {
            $files = scandir($dir);
            if ($files === false) {
                return false;
            }

            $files = array_diff($files, array('.', '..'));

            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    $this->delete_directory($path);
                } else {
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }

            return rmdir($dir);

        } catch (Exception $e) {
            error_log('DMY Auto Updater: Directory deletion error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查插件更新
     */
    public function check_for_updates() {
        // 每天检查一次更新
        $last_check = get_option('dmy_last_update_check', 0);
        if (time() - $last_check < DAY_IN_SECONDS) {
            return;
        }
        
        $github_release = $this->get_github_latest_release();
        if ($github_release) {
            update_option('dmy_latest_version', $github_release);
            update_option('dmy_last_update_check', time());
        }
    }
    
    /**
     * 获取GitHub最新版本
     */
    private function get_github_latest_release() {
        $api_url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress-Plugin-Updater'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = $this->safe_json_decode($body);

        if ($data && isset($data['tag_name'])) {
            return $data;
        }
        
        return false;
    }
    
    /**
     * WordPress插件更新检查
     */
    public function check_plugin_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_path = $this->plugin_slug . '/' . $this->plugin_file;
        
        // 获取当前版本
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path);
        $current_version = $plugin_data['Version'];
        
        // 获取最新版本
        $latest_release = get_option('dmy_latest_version');
        if (!$latest_release) {
            return $transient;
        }
        
        $latest_version = ltrim($latest_release['tag_name'], 'v');
        
        // 比较版本
        if (version_compare($current_version, $latest_version, '<')) {
            $transient->response[$plugin_path] = (object) array(
                'slug' => $this->plugin_slug,
                'new_version' => $latest_version,
                'url' => 'https://github.com/' . $this->github_repo,
                'package' => $latest_release['zipball_url']
            );
        }
        
        return $transient;
    }
    
    /**
     * 插件信息
     */
    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return false;
        }
        
        if ($response->slug !== $this->plugin_slug) {
            return false;
        }
        
        $latest_release = get_option('dmy_latest_version');
        if (!$latest_release) {
            return false;
        }
        
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_slug . '/' . $this->plugin_file);
        
        return (object) array(
            'name' => $plugin_data['Name'],
            'slug' => $this->plugin_slug,
            'version' => ltrim($latest_release['tag_name'], 'v'),
            'author' => $plugin_data['Author'],
            'homepage' => 'https://github.com/' . $this->github_repo,
            'short_description' => $plugin_data['Description'],
            'sections' => array(
                'description' => $plugin_data['Description'],
                'changelog' => $latest_release['body']
            ),
            'download_link' => $latest_release['zipball_url']
        );
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
                    error_log('DMY Auto Updater: JSON decode error: ' . json_last_error_msg());
                    return null;
                }
                return $result;
            }
        } catch (JsonException $e) {
            error_log('DMY Auto Updater: JSON decode exception: ' . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log('DMY Auto Updater: JSON decode error: ' . $e->getMessage());
            return null;
        }
    }
}

// 初始化自动更新器
new DMY_Auto_Updater();
