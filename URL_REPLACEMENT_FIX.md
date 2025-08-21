# 媒体库插入文章URL不一致问题修复

## 🔍 **问题描述**

当从媒体库插入图片到文章页面时，显示的链接与实际的云盘链接不一样：
- **期望**: 显示云盘链接 `https://zz.snpan.cn/file/xxx.jpg`
- **实际**: 显示本地链接或其他格式

## 🎯 **问题原因**

WordPress的媒体库插入机制涉及多个环节：
1. **媒体库弹窗**: JavaScript获取附件数据
2. **编辑器插入**: 将HTML代码插入到编辑器
3. **内容显示**: 前端显示时的URL处理
4. **钩子时机**: 不同钩子的执行顺序和优先级

## ✅ **解决方案**

### **1. 多层URL替换机制**

#### **A. WordPress核心钩子**
```php
// 基础URL替换
add_filter('wp_get_attachment_url', array($this, 'replace_attachment_url'), 10, 2);
add_filter('wp_get_attachment_image_src', array($this, 'replace_attachment_image_src'), 10, 4);
add_filter('wp_calculate_image_srcset', array($this, 'replace_image_srcset'), 10, 5);
```

#### **B. 内容过滤器**
```php
// 文章内容URL替换
add_filter('the_content', array($this, 'replace_content_urls'), 999);
add_filter('widget_text', array($this, 'replace_content_urls'), 999);
```

#### **C. 编辑器插入钩子**
```php
// 媒体库插入到编辑器时的URL替换
add_filter('image_send_to_editor', array($this, 'replace_editor_image_url'), 10, 8);
add_filter('media_send_to_editor', array($this, 'replace_media_send_to_editor'), 10, 3);
add_filter('wp_get_attachment_image', array($this, 'replace_attachment_image_html'), 10, 5);
```

#### **D. JavaScript数据处理**
```php
// 为媒体库JavaScript准备正确的URL数据
add_filter('wp_prepare_attachment_for_js', array($this, 'prepare_attachment_for_js'), 10, 3);
```

### **2. 关键函数实现**

#### **A. 内容URL替换**
```php
public function replace_content_urls($content) {
    if (empty($this->settings['dmy_cloud_enable'])) {
        return $content;
    }
    
    $upload_dir = wp_upload_dir();
    $upload_url = $upload_dir['baseurl'];
    
    // 查找所有图片标签并替换URL
    $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
    
    $content = preg_replace_callback($pattern, function($matches) use ($upload_url) {
        $img_tag = $matches[0];
        $img_url = $matches[1];
        
        if (strpos($img_url, $upload_url) !== false) {
            $attachment_id = $this->get_attachment_id_by_url($img_url);
            
            if ($attachment_id) {
                $cloud_url = get_post_meta($attachment_id, '_dmy_cloud_url', true);
                if ($cloud_url) {
                    return str_replace($img_url, $cloud_url, $img_tag);
                }
            }
        }
        
        return $img_tag;
    }, $content);
    
    return $content;
}
```

#### **B. JavaScript数据准备**
```php
public function prepare_attachment_for_js($response, $attachment, $meta) {
    if (empty($this->settings['dmy_cloud_enable'])) {
        return $response;
    }
    
    $cloud_url = get_post_meta($attachment->ID, '_dmy_cloud_url', true);
    if ($cloud_url) {
        // 保存原始URL
        $response['original_url'] = $response['url'];
        
        // 替换为云盘URL
        $response['url'] = $cloud_url;
        $response['cloud_url'] = $cloud_url;
        
        // 替换所有尺寸的URL
        if (isset($response['sizes']) && is_array($response['sizes'])) {
            foreach ($response['sizes'] as $size => $size_data) {
                $response['sizes'][$size]['url'] = $cloud_url;
            }
        }
        
        $response['cloud_uploaded'] = true;
    }
    
    return $response;
}
```

#### **C. 编辑器插入处理**
```php
public function replace_editor_image_url($html, $id, $caption, $title, $align, $url, $size, $alt) {
    if (empty($this->settings['dmy_cloud_enable'])) {
        return $html;
    }
    
    $cloud_url = get_post_meta($id, '_dmy_cloud_url', true);
    if ($cloud_url) {
        $local_url = wp_get_attachment_url($id);
        if ($local_url) {
            $html = str_replace($local_url, $cloud_url, $html);
        }
    }
    
    return $html;
}
```

### **3. 前端JavaScript增强**

```javascript
// 处理媒体库弹窗中的URL替换
if (typeof wp !== 'undefined' && wp.media) {
    // 重写媒体库的URL获取方法
    var originalGetUrl = wp.media.model.Attachment.prototype.get;
    wp.media.model.Attachment.prototype.get = function(key) {
        var value = originalGetUrl.call(this, key);
        
        // 如果请求URL且有云盘URL，返回云盘URL
        if (key === 'url') {
            var cloudUrl = originalGetUrl.call(this, 'cloud_url');
            if (cloudUrl) {
                return cloudUrl;
            }
        }
        
        return value;
    };
}
```

## 🔧 **测试方法**

### **1. 使用测试页面**
访问: `yoursite.com/wp-content/plugins/dmy-link1.3.6/test-url-replacement.php?test=1`

### **2. 手动测试步骤**
1. **上传图片到媒体库**
2. **确认图片已上传到云盘** (在媒体库中查看状态)
3. **在文章编辑器中插入图片**
4. **检查编辑器中显示的URL**
5. **保存文章并查看前端显示**

### **3. 调试方法**
```php
// 在functions.php中添加调试代码
add_action('wp_footer', function() {
    if (current_user_can('manage_options')) {
        echo '<script>console.log("Debug: 检查图片URL");</script>';
    }
});
```

## 📋 **检查清单**

### **插件设置**
- [ ] 云盘功能已启用
- [ ] AID和KEY已正确配置
- [ ] 自动替换功能已启用
- [ ] 测试连接成功

### **附件状态**
- [ ] 图片已成功上传到云盘
- [ ] 附件元数据中有 `_dmy_cloud_url`
- [ ] 附件元数据中有 `_dmy_cloud_uploaded`

### **钩子注册**
- [ ] `wp_get_attachment_url` 钩子已注册
- [ ] `wp_prepare_attachment_for_js` 钩子已注册
- [ ] `image_send_to_editor` 钩子已注册
- [ ] `the_content` 钩子已注册

### **JavaScript功能**
- [ ] 媒体库弹窗显示正确URL
- [ ] 插入编辑器时使用云盘URL
- [ ] 前端显示使用云盘URL

## 🚨 **常见问题**

### **1. URL仍然显示本地链接**
**原因**: 钩子优先级或设置问题
**解决**: 
- 检查插件设置是否启用
- 确认图片已上传到云盘
- 清除缓存重新测试

### **2. 编辑器中URL正确，前端显示错误**
**原因**: 主题或其他插件干扰
**解决**:
- 检查主题的图片处理代码
- 暂时禁用其他插件测试
- 提高钩子优先级

### **3. 媒体库弹窗显示错误URL**
**原因**: JavaScript数据未正确处理
**解决**:
- 检查 `wp_prepare_attachment_for_js` 钩子
- 确认附件数据包含 `cloud_url` 字段
- 检查浏览器控制台错误

### **4. 批量替换后URL不一致**
**原因**: 内容中的URL未更新
**解决**:
- 使用内容过滤器处理现有内容
- 考虑数据库批量更新
- 重新保存文章触发内容过滤

## 🎯 **最佳实践**

1. **测试环境**: 先在测试环境验证功能
2. **备份数据**: 修改前备份数据库
3. **分步测试**: 逐个功能点测试
4. **日志记录**: 启用详细日志记录
5. **缓存清理**: 测试时清除所有缓存

---

**修复完成后，媒体库插入的图片应该正确显示云盘链接！** 🚀
