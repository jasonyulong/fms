var fms = {
    config: {
        ajax: '.btn-ajax',
        dialog: '.btn-dialog',
        dialogForm: '.dialog-form',
        refresh: '.btn-refresh',
        isexplode: ".is_explode",
        require: typeof require === 'object' ? require : JSON.parse(require),
        accessRequest: false
    },
    events: {
        // 成功提示
        success: function (e) {
            layer.alert(e);
        },
        // 错误提示
        error: function (e) {
            layer.alert(e);
        },
        //请求成功的回调
        onAjaxSuccess: function (ret, onAjaxSuccess) {
            var data = typeof ret.data !== 'undefined' ? ret.data : null;
            var msg = typeof ret.msg !== 'undefined' && ret.msg ? ret.msg : __('Operation completed');

            if (typeof onAjaxSuccess === 'function') {
                var result = onAjaxSuccess.call(this, data, ret);
                if (result === false)
                    return;
            }
            fms.events.success(msg);
        },
        //请求错误的回调
        onAjaxError: function (ret, onAjaxError) {
            var data = typeof ret.data !== 'undefined' ? ret.data : null;
            if (typeof onAjaxError === 'function') {
                var result = onAjaxError.call(this, data, ret);
                if (result === false) {
                    return;
                }
            }
            fms.events.error(ret.msg);
        },
        //服务器响应数据后
        onAjaxResponse: function (response) {
            try {
                var ret = typeof response === 'object' ? response : JSON.parse(response);
                if (!ret.hasOwnProperty('code')) {
                    $.extend(ret, {code: -2, msg: response, data: null});
                }
            } catch (e) {
                var ret = {code: -1, msg: e.message, data: null};
            }
            return ret;
        },
        access: function () {
            if (fms.config.accessRequest) return false;
            fms.config.accessRequest = true;
            var controllername = require.config.controllername;
            if (controllername == 'login') {
                fms.config.accessRequest = false;
                return true;
            }
            var url = require.config.modulename + '/' + require.config.controllername + '/' + require.config.actionname;
            var options = {url: require.config.urls.access_url, data: {url: url}, noload: true};
            var success = function (status, ret) {
                fms.config.accessRequest = false;
                return false;
            };
            var error = function (status, ret) {
                fms.config.accessRequest = false;
                // 开始锁屏
                if (ret && ret.url) {
                    location.href = ret.url;
                    return false;
                }
                return false;
            };
            fms.api.ajax(options, success, error);
            setTimeout(function () {
                fms.events.access();
            }, 10000);
            fms.config.accessRequest = false;
        },
        explode: function () {
            var current_url = window.location.href;
            var arg = 'is_export=1';
            current_url += (current_url.indexOf('?') == -1 ? '?' : '&') + arg;
            window.open(current_url, '_blank');
        }
    },
    api: {
        //发送Ajax请求
        ajax: function (options, success, error) {
            options = typeof options === 'string' ? {url: options} : options;
            var index = typeof options.noload !== 'undefined' ? false : layer.load();
            options = $.extend({
                type: "POST",
                dataType: "json",
                success: function (ret) {
                    layer.close(index);
                    ret = fms.events.onAjaxResponse(ret);
                    if (ret.code === 1) {
                        fms.events.onAjaxSuccess(ret, success);
                    } else {
                        fms.events.onAjaxError(ret, error);
                    }
                },
                error: function (xhr) {
                    layer.close(index);
                    var ret = {code: xhr.status, msg: xhr.statusText, data: null};
                    fms.events.onAjaxError(ret, error);
                }
            }, options);
            $.ajax(options);
        },
        //打开一个弹出窗口
        open: function (url, title, options) {
            title = title ? title : "弹窗窗口";
            url = url + (url.indexOf("?") > -1 ? "&" : "?") + "dialog=1";
            var area = [$(window).width() > 800 ? '800px' : '95%', $(window).height() > 600 ? '600px' : '95%'];
            options = $.extend({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                area: area,
                content: url,
                zIndex: layer.zIndex,
                success: function (layero, index) {
                    var that = this;
                    //存储callback事件
                    $(layero).data("callback", that.callback);
                    //$(layero).removeClass("layui-layer-border");
                    layer.setTop(layero);
                    try {
                        var frame = layer.getChildFrame('html', index);
                        var layerfooter = frame.find(".layer-footer");
                        fms.api.layerfooter(layero, index, that);

                        //绑定事件
                        if (layerfooter.size() > 0) {
                            // 监听窗口内的元素及属性变化
                            // Firefox和Chrome早期版本中带有前缀
                            var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;
                            if (MutationObserver) {
                                // 选择目标节点
                                var target = layerfooter[0];
                                // 创建观察者对象
                                var observer = new MutationObserver(function (mutations) {
                                    fms.api.layerfooter(layero, index, that);
                                    mutations.forEach(function (mutation) {
                                    });
                                });
                                // 配置观察选项:
                                var config = {attributes: true, childList: true, characterData: true, subtree: true}
                                // 传入目标节点和观察选项
                                observer.observe(target, config);
                                // 随后,你还可以停止观察
                                // observer.disconnect();
                            }
                        }
                    } catch (e) {

                    }
                    if ($(layero).height() > $(window).height()) {
                        //当弹出窗口大于浏览器可视高度时,重定位
                        layer.style(index, {
                            top: 0,
                            height: $(window).height()
                        });
                    }
                }
            }, options ? options : {});
            if ($(window).width() < 480 || (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream && top.$(".tab-pane.active").size() > 0)) {
                options.area = [top.$(".tab-pane.active").width() + "px", top.$(".tab-pane.active").height() + "px"];
                options.offset = [top.$(".tab-pane.active").scrollTop() + "px", "0px"];
            }
            return layer.open(options);
        },
        // 底部控件
        layerfooter: function (layero, index, that) {
            var frame = layer.getChildFrame('html', index);
            var layerfooter = frame.find(".layer-footer");
            if (layerfooter.size() > 0) {
                $(".layui-layer-footer", layero).remove();
                var footer = $("<div />").addClass('layui-layer-btn layui-layer-footer');
                footer.html(layerfooter.html());
                if ($(".row", footer).size() === 0) {
                    $(">", footer).wrapAll("<div class='row'></div>");
                }
                footer.insertAfter(layero.find('.layui-layer-content'));
                //绑定事件
                footer.on("click", ".btn", function () {
                    if ($(this).hasClass("disabled") || $(this).parent().hasClass("disabled")) {
                        return;
                    }
                    $(".btn:eq(" + $(this).index() + ")", layerfooter).trigger("click");
                });

                var titHeight = layero.find('.layui-layer-title').outerHeight() || 0;
                var btnHeight = layero.find('.layui-layer-btn').outerHeight() || 0;
                //重设iframe高度
                $("iframe", layero).height(layero.height() - titHeight - btnHeight);
            }
            //修复iOS下弹出窗口的高度和iOS下iframe无法滚动的BUG
            if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
                var titHeight = layero.find('.layui-layer-title').outerHeight() || 0;
                var btnHeight = layero.find('.layui-layer-btn').outerHeight() || 0;
                $("iframe", layero).parent().css("height", layero.height() - titHeight - btnHeight);
                $("iframe", layero).css("height", "100%");
            }
        },
        rendertree: function (content) {
            $("#treeview")
                .on('redraw.jstree', function (e) {
                    $(".layer-footer").attr("domrefresh", Math.random());
                })
                .jstree({
                    "themes": {"stripes": true},
                    "checkbox": {
                        "keep_selected_style": false,
                    },
                    "types": {
                        "root": {
                            "icon": "fa fa-folder-open",
                        },
                        "menu": {
                            "icon": "fa fa-folder-open",
                        },
                        "file": {
                            "icon": "fa fa-file-o",
                        }
                    },
                    "plugins": ["checkbox", "types"],
                    "core": {
                        'check_callback': true,
                        "data": content
                    }
                });
        }
    },
    layer: {},
    init: function () {
        // 绑定ESC关闭窗口事件
        $(window).keyup(function (e) {
            if (e.keyCode == 27) {
                if ($(".layui-layer").size() > 0) {
                    var index = 0;
                    $(".layui-layer").each(function () {
                        index = Math.max(index, parseInt($(this).attr("times")));
                    });
                    if (index) {
                        layer.close(index);
                    }
                }
            }
        });
        // 直接请求
        $(document).on('click', fms.config.ajax, function (e) {
            var that = this;
            var options = eval('(' + $(that).attr("options") + ')') || {};

            var options = $.extend(options, $(that).data() || {});
            if (typeof options.url === 'undefined' && $(that).attr("href")) {
                options.url = $(that).attr("href");
            }
            var success = typeof options.success === 'function' ? options.success : null;
            var error = typeof options.error === 'function' ? options.error : null;
            options.success = function (ret, data) {
                var data = ret.data || {};
                if (data && data.url) {
                    location.href = data.url;
                    return false;
                }
                layer.alert(ret.msg, {closeBtn: 0}, function (index) {
                    if (window.name) {
                        //parent.layer.close(parent.layer.getFrameIndex(window.name));
                    }
                    parent.layer.close(index);
                    parent.window.location.reload();
                    window.location.reload();
                });
                return false;
            };
            options.error = function (ret) {
                fms.events.error(ret.msg);
                return false;
            };
            if (typeof options.confirm !== 'undefined') {
                layer.confirm(options.confirm, function (index) {
                    fms.api.ajax(options, success, error);
                    layer.close(index);
                });
            } else {
                fms.api.ajax(options, success, error);
            }
            return false;
        });
        // 统一弹出窗口
        $(document).on('click', fms.config.dialog, function (e) {
            var that = this;
            var title = $(that).attr("title") || "弹窗窗口";
            var url = $(that).attr("href") || "";
            if (url == '#') {
                url = $(that).attr("data-url") || url;
            }
            var options = eval('(' + $(that).attr("options") + ')') || {};
            return fms.api.open(url, title, options);
        });
        // 刷新
        $(document).on('click', fms.config.refresh, function (e) {
            window.location.reload();
        });
        // 弹窗提交表单
        $(fms.config.dialogForm).on('valid.form', function () {
            var that = this;
            var options = $.extend({}, $(that).data() || {});
            if (typeof options.url === 'undefined' && $(that).attr("action")) {
                options.url = $(that).attr("action");
            }
            options.data = $(that).serialize();
            options.success = function (data, ret) {
                if (data && data.url) {
                    location.href = data.url;
                    return false;
                }
                if (data.code < 1) {
                    fms.events.error(ret.msg);
                    return false;
                }
                layer.alert(ret.msg, {closeBtn: 0}, function (index) {
                    if (window.name) {
                        //parent.layer.close(parent.layer.getFrameIndex(window.name));
                    }
                    parent.layer.close(index);
                    parent.window.location.reload();
                    window.location.reload();
                });
                return false;
            };
            options.error = function (data, ret) {
                fms.events.error(ret.msg);
                return false;
            };
            var success = typeof options.success === 'function' ? options.success : null;
            var error = typeof options.error === 'function' ? options.error : null;
            delete options.success;
            delete options.error;
            if (typeof options.confirm !== 'undefined') {
                layer.confirm(options.confirm, function (index) {
                    fms.api.ajax(options, success, error);
                    layer.close(index);
                });
            } else {
                fms.api.ajax(options, success, error);
            }
        });
        $(fms.config.isexplode).click(function () {
            return fms.events.explode();
        });
    },
    init_check_all: function () {
        $('.data-check_box_total').click(function () {
            var thisobj = $(this);
            var is_check = thisobj.is(':checked');

            var sub_check_box_list = $('.data-check_box');
            for (var i = 0; i < sub_check_box_list.length; i++) {
                $(sub_check_box_list[i]).prop('checked', is_check);
            }
        });
    },
    get_checked_data: function (attr_name) {
        var data_arr = [];
        var tmp_arr = $('.data-check_box');
        for (var i = 0; i < tmp_arr.length; i++) {
            if ($(tmp_arr[i]).is(':checked')) {
                var data_item = $(tmp_arr[i]).attr(attr_name);
                data_item = $.trim(data_item);
                if (data_item) data_arr.push(data_item);
            }
        }
        return data_arr;
    },
    // 获取选中的 id
    get_checked_id: function () {
        return fms.get_checked_data('data-id');
    }
};
//将fms渲染至全局
window.fms = fms;
// 全局配置
window.Config = fms.config.require.config;
// 初始化
fms.init();