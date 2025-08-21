/**
 * 零九CDN云盘上传功能前端脚本
 *
 * @author 零九CDN
 * @website https://www.09cdn.com
 */

jQuery(document).ready(function($) {
    
    // 批量替换按钮点击事件
    $('#dmy-batch-replace-media').on('click', function() {
        var $button = $(this);
        var $progress = $('#dmy-replace-progress');
        var $progressBar = $progress.find('.progress-fill');
        var $progressText = $progress.find('.progress-text');

        // 检查是否有未完成的任务
        var savedProgress = localStorage.getItem('dmy_batch_progress');
        if (savedProgress) {
            var progressData = JSON.parse(savedProgress);
            if (confirm('检测到未完成的批量上传任务，是否继续上次的进度？\n\n已处理: ' + progressData.processed + ' / ' + progressData.total + ' 个文件')) {
                // 继续上次的进度
                $button.prop('disabled', true).text('正在处理...');
                $progress.show();
                $progressBar.css('width', Math.round((progressData.processed / progressData.total) * 100) + '%');
                $progressText.text('恢复进度中...');

                batchReplaceMedia(progressData.currentPage, progressData.processed, progressData.total);
                return;
            } else {
                // 清除保存的进度，重新开始
                localStorage.removeItem('dmy_batch_progress');
            }
        }

        // 确认对话框
        if (!confirm('确定要将媒体库中的所有图片上传到云盘吗？这个过程可能需要一些时间。')) {
            return;
        }

        // 禁用按钮并显示进度条
        $button.prop('disabled', true).text('正在处理...');
        $progress.show();
        $progressBar.css('width', '0%');
        $progressText.text('准备开始...');

        // 开始批量处理
        batchReplaceMedia(1, 0, 0);
    });
    
    /**
     * 批量替换媒体文件（支持断点续传）
     */
    function batchReplaceMedia(page, processed, total, retryCount) {
        retryCount = retryCount || 0;
        var $button = $('#dmy-batch-replace-media');
        var $progressBar = $('#dmy-replace-progress .progress-fill');
        var $progressText = $('#dmy-replace-progress .progress-text');

        // 保存当前进度到本地存储
        if (total > 0) {
            var progressData = {
                currentPage: page,
                processed: processed,
                total: total,
                timestamp: Date.now()
            };
            localStorage.setItem('dmy_batch_progress', JSON.stringify(progressData));
        }

        $.ajax({
            url: dmy_cloud_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dmy_batch_replace_media',
                nonce: dmy_cloud_ajax.nonce,
                page: page
            },
            timeout: 60000, // 60秒超时
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    processed = data.processed;
                    total = data.total;

                    // 更新进度条
                    var percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
                    $progressBar.css('width', percentage + '%');
                    $('.progress-percentage').text(percentage + '%');
                    $progressText.text('已处理 ' + processed + ' / ' + total + ' 个文件 (' + percentage + '%) - 页面 ' + page);

                    // 检查是否被暂停或取消
                    if (window.dmyBatchPaused) {
                        $progressText.append(' - 已暂停');
                        return; // 暂停处理
                    }

                    if (window.dmyBatchCancelled) {
                        window.dmyBatchCancelled = false; // 重置标志
                        return; // 取消处理
                    }

                    // 显示处理结果
                    if (data.results && data.results.length > 0) {
                        var successCount = 0;
                        var failCount = 0;
                        data.results.forEach(function(result) {
                            if (result.status === 'success') {
                                successCount++;
                                console.log('上传成功: ' + result.title + ' -> ' + result.cloud_url);
                            } else {
                                failCount++;
                                console.log('上传失败: ' + result.title);
                            }
                        });

                        if (failCount > 0) {
                            $progressText.append(' (本批次: 成功' + successCount + ', 失败' + failCount + ')');
                        }
                    }

                    // 如果还有更多文件需要处理
                    if (data.has_more) {
                        setTimeout(function() {
                            batchReplaceMedia(page + 1, processed, total, 0); // 重置重试计数
                        }, 2000); // 延迟2秒避免服务器压力过大
                    } else {
                        // 处理完成，清除保存的进度
                        localStorage.removeItem('dmy_batch_progress');

                        $button.prop('disabled', false).text('一键替换媒体库图片到云盘');
                        $progressText.text('处理完成！共处理 ' + processed + ' 个文件');

                        // 显示完成提示
                        alert('批量替换完成！共处理了 ' + processed + ' 个文件。');

                        // 5秒后隐藏进度条
                        setTimeout(function() {
                            $('#dmy-replace-progress').hide();
                        }, 5000);
                    }
                } else {
                    // 处理错误，尝试重试
                    handleBatchError(page, processed, total, retryCount, response.data || '未知错误');
                }
            },
            error: function(xhr, status, error) {
                // 网络错误，尝试重试
                handleBatchError(page, processed, total, retryCount, '网络错误: ' + error);
            }
        });
    }

    /**
     * 处理批量上传错误和重试
     */
    function handleBatchError(page, processed, total, retryCount, errorMsg) {
        var $button = $('#dmy-batch-replace-media');
        var $progressText = $('#dmy-replace-progress .progress-text');

        retryCount++;

        if (retryCount <= 3) {
            // 最多重试3次
            $progressText.text('处理出错，正在重试 (' + retryCount + '/3): ' + errorMsg);

            setTimeout(function() {
                batchReplaceMedia(page, processed, total, retryCount);
            }, 5000 * retryCount); // 递增延迟重试
        } else {
            // 重试次数用完，询问用户是否继续
            $button.prop('disabled', false).text('继续批量替换');
            $progressText.text('处理失败: ' + errorMsg + ' (已重试3次)');

            if (confirm('批量处理遇到错误：' + errorMsg + '\n\n已重试3次仍然失败。是否继续处理下一批文件？\n\n点击"确定"继续，点击"取消"停止处理。')) {
                $button.prop('disabled', true).text('正在处理...');
                setTimeout(function() {
                    batchReplaceMedia(page + 1, processed, total, 0); // 跳过当前页，继续下一页
                }, 1000);
            } else {
                // 用户选择停止，但保留进度
                alert('批量处理已停止。您可以稍后点击"一键替换"按钮继续未完成的任务。');
            }
        }
    }
    
    /**
     * 单个文件上传到云盘
     */
    function uploadSingleFile(attachmentId, callback) {
        $.ajax({
            url: dmy_cloud_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dmy_upload_to_cloud',
                nonce: dmy_cloud_ajax.nonce,
                attachment_id: attachmentId
            },
            success: function(response) {
                if (response.success) {
                    callback(null, response.data.cloud_url);
                } else {
                    callback(response.data || '上传失败');
                }
            },
            error: function(xhr, status, error) {
                callback('网络错误: ' + error);
            }
        });
    }
    
    // 在媒体库页面添加上传到云盘的按钮
    if (window.location.href.indexOf('upload.php') !== -1) {
        // 等待媒体库加载完成
        setTimeout(function() {
            addCloudUploadButtons();
        }, 2000);
    }
    
    /**
     * 在媒体库添加云盘上传按钮
     */
    function addCloudUploadButtons() {
        // 为每个媒体项添加上传按钮
        $('.attachment').each(function() {
            var $attachment = $(this);
            var attachmentId = $attachment.data('id');
            
            if (attachmentId && !$attachment.find('.dmy-cloud-upload-btn').length) {
                var $uploadBtn = $('<button class="button dmy-cloud-upload-btn" style="margin-top: 5px;">上传到云盘</button>');
                
                $uploadBtn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('上传中...');
                    
                    uploadSingleFile(attachmentId, function(error, cloudUrl) {
                        if (error) {
                            alert('上传失败: ' + error);
                            $btn.prop('disabled', false).text('上传到云盘');
                        } else {
                            alert('上传成功！云盘地址: ' + cloudUrl);
                            $btn.text('已上传').addClass('button-primary');
                        }
                    });
                });
                
                $attachment.find('.attachment-preview').append($uploadBtn);
            }
        });
    }
    
    // 监听媒体库的动态加载
    if (typeof wp !== 'undefined' && wp.media) {
        var originalRender = wp.media.view.Attachment.prototype.render;
        wp.media.view.Attachment.prototype.render = function() {
            var result = originalRender.apply(this, arguments);
            
            // 延迟添加按钮，确保DOM已渲染
            setTimeout(function() {
                addCloudUploadButtons();
            }, 100);
            
            return result;
        };
    }
    
    // 配置验证
    function validateCloudConfig() {
        // 等待表单完全加载
        var $aidField = $('input[name="dmy_cloud_settings[dmy_cloud_aid]"]');
        var $keyField = $('input[name="dmy_cloud_settings[dmy_cloud_key]"]');

        // 调试信息
        console.log('AID field found:', $aidField.length);
        console.log('KEY field found:', $keyField.length);

        if ($aidField.length === 0 || $keyField.length === 0) {
            alert('表单字段未找到，请刷新页面重试');
            return false;
        }

        var aid = $aidField.val();
        var key = $keyField.val();

        console.log('AID value:', aid);
        console.log('KEY value:', key ? '***已设置***' : '未设置');

        if (!aid || !key) {
            var missingFields = [];
            if (!aid) missingFields.push('AID');
            if (!key) missingFields.push('KEY');

            alert('请先配置云盘 ' + missingFields.join(' 和 ') + '，然后点击页面底部的"保存更改"按钮保存设置，再进行测试连接。');

            // 滚动到对应的输入框
            if (!aid) {
                $('input[name="dmy_cloud_settings[dmy_cloud_aid]"]').focus();
            } else if (!key) {
                $('input[name="dmy_cloud_settings[dmy_cloud_key]"]').focus();
            }

            return false;
        }

        return true;
    }

    /**
     * 检查是否有未保存的更改
     */
    function checkUnsavedChanges() {
        // 检查表单是否有修改但未保存的内容
        var $form = $('form');
        if ($form.length > 0) {
            // 简单检查：如果页面URL没有包含settings-updated参数，可能有未保存的更改
            return window.location.search.indexOf('settings-updated=true') === -1 &&
                   ($('input[name="dmy_cloud_settings[dmy_cloud_aid]"]').val() ||
                    $('input[name="dmy_cloud_settings[dmy_cloud_key]"]').val());
        }
        return false;
    }

    // 测试云盘连接
    $(document).on('click', '#dmy-test-cloud-connection', function() {
        var $button = $(this);
        var $result = $('#dmy-test-result');
        var $connectionStatus = $('#dmy-connection-status span');

        // 先检查页面是否有未保存的更改
        var hasUnsavedChanges = checkUnsavedChanges();
        if (hasUnsavedChanges) {
            if (!confirm('检测到有未保存的配置更改。建议先保存设置再测试连接。\n\n是否继续测试？')) {
                return;
            }
        }

        // 延迟一点时间确保DOM完全加载
        setTimeout(function() {
            if (!validateCloudConfig()) {
                return;
            }

            $button.prop('disabled', true).text('测试中...');
            $result.html('<span style="color: #666;">正在测试连接...</span>');
            $connectionStatus.html('<span style="color: #666;">测试中...</span>');

            $.ajax({
                url: dmy_cloud_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dmy_test_cloud_connection',
                    nonce: dmy_cloud_ajax.nonce
                },
                timeout: 30000, // 30秒超时
                success: function(response) {
                    $button.prop('disabled', false).text('测试云盘连接');

                    if (response.success) {
                        $result.html('<span style="color: #46b450;">✓ ' + response.data + '</span>');
                        $connectionStatus.html('<span style="color: #46b450;">连接正常</span>');
                    } else {
                        $result.html('<span style="color: #dc3232;">✗ 测试失败: ' + (response.data || '未知错误') + '</span>');
                        $connectionStatus.html('<span style="color: #dc3232;">连接失败</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).text('测试云盘连接');
                    var errorMsg = '网络错误';
                    if (status === 'timeout') {
                        errorMsg = '连接超时，请检查网络或稍后重试';
                    } else if (xhr.status) {
                        errorMsg = '服务器错误 (' + xhr.status + ')';
                    }
                    $result.html('<span style="color: #dc3232;">✗ ' + errorMsg + '</span>');
                    $connectionStatus.html('<span style="color: #dc3232;">连接失败</span>');
                }
            });
        }, 100);
    });

    // 更新功能状态显示
    function updateFunctionStatus() {
        var autoUploadEnabled = $('input[name="dmy_cloud_settings[dmy_cloud_auto_replace]"]').is(':checked');
        var cloudEnabled = $('input[name="dmy_cloud_settings[dmy_cloud_enable]"]').is(':checked');

        var $autoStatus = $('#auto-upload-status');
        var $urlStatus = $('#url-replace-status');

        if (cloudEnabled && autoUploadEnabled) {
            $autoStatus.html('<span style="color: #46b450;">已启用</span>');
        } else {
            $autoStatus.html('<span style="color: #dc3232;">已禁用</span>');
        }

        if (cloudEnabled) {
            $urlStatus.html('<span style="color: #46b450;">已启用</span>');
        } else {
            $urlStatus.html('<span style="color: #dc3232;">已禁用</span>');
        }
    }

    // 监听设置变化
    $(document).on('change', 'input[name="dmy_cloud_settings[dmy_cloud_enable]"], input[name="dmy_cloud_settings[dmy_cloud_auto_replace]"]', function() {
        setTimeout(updateFunctionStatus, 100);
    });

    // 监听 AID 和 KEY 输入框变化
    $(document).on('input', 'input[name="dmy_cloud_settings[dmy_cloud_aid]"], input[name="dmy_cloud_settings[dmy_cloud_key]"]', function() {
        setTimeout(function() {
            var aid = $('input[name="dmy_cloud_settings[dmy_cloud_aid]"]').val();
            var key = $('input[name="dmy_cloud_settings[dmy_cloud_key]"]').val();
            var $connectionStatus = $('#connection-status');

            if (!aid || !key) {
                $connectionStatus.html('<span style="color: #dc3232;">请先配置 AID 和 KEY</span>');
            } else {
                $connectionStatus.html('<span style="color: #f0ad4e;">配置已更改，请保存后测试</span>');
            }
        }, 100);
    });

    // 页面加载时更新状态
    setTimeout(updateFunctionStatus, 500);

    // 页面加载时检查保存的进度
    checkSavedProgress();

    // 页面加载时检查配置状态
    checkConfigStatus();

    // 清除进度记录按钮
    $('#dmy-clear-progress').on('click', function() {
        if (confirm('确定要清除保存的进度记录吗？这将删除所有未完成的批量上传任务记录。')) {
            localStorage.removeItem('dmy_batch_progress');
            $('#dmy-saved-progress').hide();
            alert('进度记录已清除。');
        }
    });

    // 暂停处理按钮
    $('#dmy-pause-batch').on('click', function() {
        window.dmyBatchPaused = true;
        $(this).prop('disabled', true).text('已暂停');
        $('#dmy-cancel-batch').text('继续处理');
        alert('批量处理已暂停。点击"继续处理"按钮可以恢复。');
    });

    // 取消/继续处理按钮
    $('#dmy-cancel-batch').on('click', function() {
        var $button = $(this);
        if (window.dmyBatchPaused) {
            // 继续处理
            window.dmyBatchPaused = false;
            $button.text('取消处理');
            $('#dmy-pause-batch').prop('disabled', false).text('暂停处理');

            // 恢复处理
            var savedProgress = localStorage.getItem('dmy_batch_progress');
            if (savedProgress) {
                var progressData = JSON.parse(savedProgress);
                batchReplaceMedia(progressData.currentPage, progressData.processed, progressData.total);
            }
        } else {
            // 取消处理
            if (confirm('确定要取消批量处理吗？当前进度将被保存，您可以稍后继续。')) {
                window.dmyBatchCancelled = true;
                $('#dmy-batch-replace-media').prop('disabled', false).text('一键替换媒体库图片到云盘');
                $('#dmy-replace-progress').hide();
                alert('批量处理已取消。进度已保存，您可以稍后继续。');
            }
        }
    });

    /**
     * 检查保存的进度
     */
    function checkSavedProgress() {
        var savedProgress = localStorage.getItem('dmy_batch_progress');
        if (savedProgress) {
            var progressData = JSON.parse(savedProgress);
            var timeAgo = Math.round((Date.now() - progressData.timestamp) / 1000 / 60); // 分钟

            $('#saved-progress-text').text('已处理 ' + progressData.processed + ' / ' + progressData.total + ' 个文件 (' + timeAgo + ' 分钟前)');
            $('#dmy-saved-progress').show();
        }
    }

    /**
     * 检查配置状态
     */
    function checkConfigStatus() {
        setTimeout(function() {
            var aid = $('input[name="dmy_cloud_settings[dmy_cloud_aid]"]').val();
            var key = $('input[name="dmy_cloud_settings[dmy_cloud_key]"]').val();
            var $connectionStatus = $('#connection-status');

            if (!aid || !key) {
                $connectionStatus.html('<span style="color: #dc3232;">请先配置 AID 和 KEY</span>');
            } else {
                $connectionStatus.html('<span style="color: #666;">点击测试连接</span>');
            }
        }, 500); // 延迟500ms确保页面完全加载
    }

    // 处理媒体库列表页面的单个上传按钮
    $(document).on('click', '.dmy-upload-single', function(e) {
        e.preventDefault();

        var $button = $(this);
        var attachmentId = $button.data('attachment-id');

        $button.prop('disabled', true).text('上传中...');

        uploadSingleFile(attachmentId, function(error, cloudUrl) {
            if (error) {
                alert('上传失败: ' + error);
                $button.prop('disabled', false).text('上传到云盘');
            } else {
                // 更新显示
                var $cell = $button.closest('td');
                $cell.html('<span style="color: #46b450;">✓ 已上传</span><br><a href="' + cloudUrl + '" target="_blank" style="font-size: 11px;">查看云盘文件</a>');
            }
        });
    });

    // 处理媒体库弹窗中的URL替换
    if (typeof wp !== 'undefined' && wp.media) {
        // 监听媒体库选择事件
        wp.media.view.MediaFrame.Select.prototype.on('select', function() {
            var selection = this.state().get('selection');
            selection.each(function(attachment) {
                // 检查是否有云盘URL
                var cloudUrl = attachment.get('cloud_url');
                if (cloudUrl) {
                    // 更新attachment的URL
                    attachment.set('url', cloudUrl);

                    // 更新所有尺寸的URL
                    var sizes = attachment.get('sizes');
                    if (sizes) {
                        Object.keys(sizes).forEach(function(size) {
                            sizes[size].url = cloudUrl;
                        });
                        attachment.set('sizes', sizes);
                    }
                }
            });
        });

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

});
