/**
 * CosmautDL 用户体验增强脚本（注释微调）
 * 提供交互式用户引导、工具提示、加载动画等功能
 */

(function($) {
    'use strict';

    // 等待DOM加载完成
    $(document).ready(function() {
        CosmautDL.init();
    });

    // 主要命名空间
    window.CosmautDL = {
        
        // 初始化所有功能
        init: function() {
            this.initTooltips();
            this.initLoading();
            this.initMessages();
            this.initAccordion();
            this.initTabs();
            this.initModal();
            this.initProgress();
            this.initKeyboardShortcuts();
            this.initUserGuide();
            this.initErrorReporting();
        },

        // 工具提示初始化
        initTooltips: function() {
            // 为带有cosmdl-tooltip类的元素创建动态工具提示
            $('.cosmdl-tooltip').each(function() {
                var $this = $(this);
                var tooltipText = $this.data('tooltip') || $this.attr('title');
                
                if (tooltipText) {
                    $this.on('mouseenter', function() {
                        CosmautDL.showTooltip($this, tooltipText);
                    }).on('mouseleave', function() {
                        CosmautDL.hideTooltip();
                    });
                }
            });
        },

        // 显示工具提示
        showTooltip: function($element, text) {
            CosmautDL.hideTooltip(); // 隐藏现有提示
            
            var $tooltip = $('<div class="cosmdl-tooltip-text"></div>');
            $tooltip.text(text);
            
            $('body').append($tooltip);
            
            var elementRect = $element[0].getBoundingClientRect();
            var tooltipRect = $tooltip[0].getBoundingClientRect();
            
            $tooltip.css({
                position: 'absolute',
                left: elementRect.left + (elementRect.width - tooltipRect.width) / 2,
                top: elementRect.top - tooltipRect.height - 10,
                zIndex: 9999,
                opacity: 0
            }).animate({
                opacity: 1
            }, 200);
        },

        // 隐藏工具提示
        hideTooltip: function() {
            $('.cosmdl-tooltip-text').remove();
        },

        // 加载动画初始化
        initLoading: function() {
            // 全局加载覆盖层
            var $loadingOverlay = $('<div class="cosmdl-loading-overlay" style="display: none;">');
            var $loadingContent = $('<div class="cosmdl-loading-content">');
            var $loadingIcon = $('<div class="cosmdl-loading"></div>');
            var $loadingText = $('<div class="cosmdl-loading-text"></div>');
            
            $loadingContent.append($loadingIcon, $loadingText);
            $loadingOverlay.append($loadingContent);
            $('body').append($loadingOverlay);
        },

        // 显示加载动画
        showLoading: function(message) {
            message = message || '<?php _e("加载中...", "cosmautdl"); ?>';
            $('.cosmdl-loading-text').text(message);
            $('.cosmdl-loading-overlay').fadeIn(200);
        },

        // 隐藏加载动画
        hideLoading: function() {
            $('.cosmdl-loading-overlay').fadeOut(200);
        },

        // 消息显示初始化
        initMessages: function() {
            // 自动隐藏成功消息
            setTimeout(function() {
                $('.cosmdl-message-success').fadeOut(300);
            }, 5000);
        },

        // 显示消息
        showMessage: function(message, type, autoHide) {
            type = type || 'info';
            autoHide = autoHide !== false;
            
            var $message = $('<div class="cosmdl-message cosmdl-message-' + type + '"></div>');
            $message.html(message).hide();
            
            // 添加关闭按钮
            var $closeBtn = $('<button class="cosmdl-message-close" style="float: right; background: none; border: none; cursor: pointer; font-size: 16px; color: inherit; opacity: 0.7;">&times;</button>');
            $message.prepend($closeBtn);
            
            $closeBtn.on('click', function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // 插入到页面顶部
            $('body').prepend($message);
            $message.fadeIn(300);
            
            // 自动隐藏
            if (autoHide && type !== 'error') {
                setTimeout(function() {
                    $message.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
            
            return $message;
        },

        // 折叠面板初始化
        initAccordion: function() {
            $('.cosmdl-accordion-header').on('click', function() {
                var $header = $(this);
                var $content = $header.next('.cosmdl-accordion-content');
                var isOpen = !$header.hasClass('collapsed');
                
                // 切换当前面板
                if (isOpen) {
                    $header.addClass('collapsed');
                    $content.slideUp(300);
                } else {
                    $header.removeClass('collapsed');
                    $content.slideDown(300);
                }
            });
        },

        // 标签页初始化
        initTabs: function() {
            $('.cosmdl-tab-button').on('click', function() {
                var $button = $(this);
                var target = $button.data('tab');
                
                // 移除所有活动状态
                $button.siblings().removeClass('active');
                $button.addClass('active');
                
                // 显示对应内容
                $('.cosmdl-tab-content').removeClass('active');
                $('#' + target).addClass('active');
            });
        },

        // 模态框初始化
        initModal: function() {
            // 模态框关闭事件
            $(document).on('click', '.cosmdl-modal-close, .cosmdl-modal', function(e) {
                if (e.target === this) {
                    CosmautDL.hideModal();
                }
            });
            
            // ESC键关闭
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    CosmautDL.hideModal();
                }
            });
        },

        // 显示模态框
        showModal: function(title, content, options) {
            options = options || {};
            
            var $modal = $('<div class="cosmdl-modal">');
            var $content = $('<div class="cosmdl-modal-content">');
            var $header = $('<div class="cosmdl-modal-header"></div>');
            var $body = $('<div class="cosmdl-modal-body"></div>');
            var $footer = $('<div class="cosmdl-modal-footer"></div>');
            var $close = $('<button class="cosmdl-modal-close">&times;</button>');
            
            $header.html(title).append($close);
            $body.html(content);
            
            // 添加按钮
            if (options.buttons) {
                options.buttons.forEach(function(button) {
                    var $btn = $('<button class="btn"></button>');
                    $btn.addClass(button.class || 'btn-secondary');
                    $btn.text(button.text);
                    $btn.on('click', button.callback || function() {});
                    $footer.append($btn);
                });
            }
            
            $content.append($header, $body, $footer);
            $modal.append($content);
            $('body').append($modal);
            
            // 显示动画
            setTimeout(function() {
                $modal.fadeIn(300);
            }, 10);
            
            // 保存引用以便后续操作
            $modal.data('modal-instance', $content);
            
            return $modal;
        },

        // 隐藏模态框
        hideModal: function() {
            $('.cosmdl-modal').fadeOut(300, function() {
                $(this).remove();
            });
        },

        // 进度条初始化
        initProgress: function() {
            // 为所有进度条添加动画
            $('.cosmdl-progress-bar').each(function() {
                var $bar = $(this);
                var targetWidth = $bar.data('target-width') || $bar.css('width');
                
                // 重置宽度并动画到目标值
                $bar.css('width', '0').animate({
                    width: targetWidth
                }, 1000);
            });
        },

        // 键盘快捷键初始化
        initKeyboardShortcuts: function() {
            $(document).on('keydown', function(e) {
                // Ctrl/Cmd + ? 显示帮助
                if ((e.ctrlKey || e.metaKey) && e.key === '?') {
                    e.preventDefault();
                    CosmautDL.showHelpModal();
                }
                
                // Ctrl/Cmd + R 在下载页面刷新
                if ((e.ctrlKey || e.metaKey) && e.key === 'r' && $('body').hasClass('cosmdl-download-page')) {
                    e.preventDefault();
                    location.reload();
                }
            });
        },

        // 用户引导初始化
        initUserGuide: function() {
            // 检查是否需要显示新用户引导
            if (!localStorage.getItem('cosmdl_user_guide_shown')) {
                this.showWelcomeGuide();
            }
        },

        // 显示欢迎引导
        showWelcomeGuide: function() {
            var steps = [
                {
                    element: '.cosmdl-card',
                    title: '<?php _e("下载卡片", "cosmautdl"); ?>',
                    content: '<?php _e("这是您的下载卡片，点击后可以访问下载页面。", "cosmautdl"); ?>'
                },
                {
                    element: '.cosmdl-download-btn',
                    title: '<?php _e("下载按钮", "cosmautdl"); ?>',
                    content: '<?php _e("点击这里开始下载文件。", "cosmautdl"); ?>'
                }
            ];
            
            // 只有在页面存在这些元素时才显示引导
            var validSteps = steps.filter(function(step) {
                return $(step.element).length > 0;
            });
            
            if (validSteps.length > 0) {
                this.startUserGuide(validSteps);
            }
        },

        // 开始用户引导
        startUserGuide: function(steps) {
            var currentStep = 0;
            var $overlay = $('<div class="cosmdl-guide-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998; display: none;"></div>');
            var $tooltip = $('<div class="cosmdl-guide-tooltip" style="position: absolute; background: white; border-radius: 8px; padding: 20px; max-width: 300px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); display: none; z-index: 9999;"></div>');
            
            $('body').append($overlay, $tooltip);
            
            var showStep = function() {
                if (currentStep >= steps.length) {
                    CosmautDL.endUserGuide();
                    return;
                }
                
                var step = steps[currentStep];
                var $element = $(step.element);
                
                if ($element.length === 0) {
                    currentStep++;
                    showStep();
                    return;
                }
                
                // 高亮元素
                var elementRect = $element[0].getBoundingClientRect();
                $overlay.fadeIn(300);
                $element.addClass('cosmdl-highlight');
                
                // 显示提示
                $tooltip.html('<h4>' + step.title + '</h4><p>' + step.content + '</p><div style="margin-top: 15px; text-align: right;"><button class="btn btn-primary cosmdl-guide-next">下一步</button></div>');
                $tooltip.css({
                    left: elementRect.left + elementRect.width + 20,
                    top: elementRect.top
                }).fadeIn(300);
                
                // 下一步按钮
                $('.cosmdl-guide-next').on('click', function() {
                    $tooltip.fadeOut(300);
                    $element.removeClass('cosmdl-highlight');
                    currentStep++;
                    setTimeout(showStep, 300);
                });
            };
            
            showStep();
            
            // 保存结束函数
            this.endUserGuide = function() {
                $overlay.fadeOut(300, function() {
                    $(this).remove();
                });
                $tooltip.fadeOut(300, function() {
                    $(this).remove();
                });
                $('.cosmdl-highlight').removeClass('cosmdl-highlight');
                localStorage.setItem('cosmdl_user_guide_shown', '1');
            };
        },

        // 错误上报初始化
        initErrorReporting: function() {
            if (typeof CosmautDLErrorReporting !== 'undefined') {
                // 监听AJAX错误
                $(document).ajaxError(function(event, xhr, settings, thrownError) {
                    CosmautDLErrorReporting.reportError({
                        type: 'ajax',
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        url: settings.url,
                        method: settings.type,
                        timestamp: new Date().toISOString()
                    });
                });
            }
        },

        // 显示帮助模态框
        showHelpModal: function() {
            var helpContent = `
                <h4><?php _e("快捷键帮助", "cosmautdl"); ?></h4>
                <ul style="list-style: none; padding: 0;">
                    <li><strong>Ctrl/Cmd + ?</strong> - <?php _e("显示此帮助", "cosmautdl"); ?></li>
                    <li><strong>Ctrl/Cmd + R</strong> - <?php _e("刷新页面", "cosmautdl"); ?></li>
                    <li><strong>ESC</strong> - <?php _e("关闭模态框/返回上一页", "cosmautdl"); ?></li>
                    <li><strong>H</strong> - <?php _e("在错误页面返回首页", "cosmautdl"); ?></li>
                </ul>
                <div style="margin-top: 20px; font-size: 0.9rem; color: #666;">
                    <p><?php _e("更多功能请查看插件文档或联系技术支持。", "cosmautdl"); ?></p>
                </div>
            `;
            
            this.showModal('<?php _e("帮助中心", "cosmautdl"); ?>', helpContent, {
                buttons: [
                    {
                        text: '<?php _e("关闭", "cosmautdl"); ?>',
                        class: 'btn-secondary',
                        callback: function() {
                            CosmautDL.hideModal();
                        }
                    }
                ]
            });
        },

        // 错误上报对象（如果需要的话）
        ErrorReporting: {
            reportError: function(errorData) {
                // 发送错误报告到服务器
                $.ajax({
                    url: '/wp-admin/admin-ajax.php',
                    method: 'POST',
                    data: {
                        action: 'cosmdl_error_report',
                        error_data: errorData,
                        nonce: '<?php echo wp_create_nonce("cosmdl_error_report"); ?>'
                    },
                    success: function(response) {
                        console.log('<?php _e("错误报告已发送", "cosmautdl"); ?>');
                    },
                    error: function() {
                        console.log('<?php _e("错误报告发送失败", "cosmautdl"); ?>');
                    }
                });
            }
        }
    };

    // 将错误上报对象暴露到全局
    window.CosmautDLErrorReporting = CosmautDL.ErrorReporting;

})(jQuery);
