/**
 * 媒体库云盘URL处理脚本
 * 
 * @author 零九CDN
 * @website https://www.09cdn.com
 */

jQuery(document).ready(function($) {
    'use strict';

    // 检查是否有必要的数据
    if (typeof dmyCloudMedia === 'undefined') {
        console.log('[DMY Cloud Media] 数据未加载，跳过初始化');
        return;
    }

    console.log('[DMY Cloud Media] 媒体库处理脚本已加载');
    console.log('[DMY Cloud Media] 云盘附件数据:', dmyCloudMedia.cloudAttachments);

    // 添加错误处理
    try {
        // 等待WordPress媒体库加载
        if (typeof wp !== 'undefined' && wp.media) {
            initializeMediaHandler();
        } else {
            // 如果wp.media还没加载，等待一下
            setTimeout(function() {
                try {
                    if (typeof wp !== 'undefined' && wp.media) {
                        initializeMediaHandler();
                    } else {
                        console.log('[DMY Cloud Media] WordPress媒体库未加载，跳过初始化');
                    }
                } catch (error) {
                    console.error('[DMY Cloud Media] 延迟初始化错误:', error);
                }
            }, 1000);
        }
    } catch (error) {
        console.error('[DMY Cloud Media] 初始化错误:', error);
    }
    
    function initializeMediaHandler() {
        console.log('[DMY Cloud Media] 初始化媒体库处理器');

        try {
            // 简化的URL替换逻辑，只在必要时进行替换
            if (wp.media.model && wp.media.model.Attachment) {
                console.log('[DMY Cloud Media] 媒体库模型可用，设置URL替换');

                // 保存原始方法
                var originalGet = wp.media.model.Attachment.prototype.get;

                wp.media.model.Attachment.prototype.get = function(key) {
                    try {
                        var value = originalGet.call(this, key);
                        var attachmentId = this.get('id');

                        // 只处理有云盘URL的附件
                        if (attachmentId && dmyCloudMedia.cloudAttachments[attachmentId]) {
                            var cloudUrl = dmyCloudMedia.cloudAttachments[attachmentId];

                            // 只替换URL相关的属性
                            if (key === 'url') {
                                console.log('[DMY Cloud Media] 替换附件 ' + attachmentId + ' 的URL');
                                return cloudUrl;
                            }
                        }

                        return value;
                    } catch (error) {
                        console.error('[DMY Cloud Media] URL获取错误:', error);
                        return originalGet.call(this, key);
                    }
                };
            } else {
                console.log('[DMY Cloud Media] 媒体库模型不可用');
            }
        } catch (error) {
            console.error('[DMY Cloud Media] 媒体库处理器初始化错误:', error);
        }
        
        // 简化的选择事件监听
        try {
            if (wp.media.view && wp.media.view.MediaFrame && wp.media.view.MediaFrame.Select) {
                console.log('[DMY Cloud Media] 设置选择事件监听');
                // 暂时移除复杂的事件监听，避免冲突
            }
        } catch (error) {
            console.error('[DMY Cloud Media] 选择事件设置错误:', error);
        }
        
        console.log('[DMY Cloud Media] 媒体库处理器设置完成');
    }

    console.log('[DMY Cloud Media] 媒体库处理器初始化完成');
});
