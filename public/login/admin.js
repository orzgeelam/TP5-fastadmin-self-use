/**
 * 后台JS主入口
 */
var layer = layui.layer,
    element = layui.element(),
    laydate = layui.laydate,
    form = layui.form();

form.verify({
    pass: [
        /^[\S]{6,12}$/,
        '密码必须6到30位'
    ]
});

/**
 * AJAX全局设置
 */
$.ajaxSetup({
    type: "post",
    dataType: "json"
});

var isShowLock = false;

function lock($, layer) {
    if (isShowLock)
        return;
    //自定页
    layer.open({
        title: false,
        type: 1,
        closeBtn: 0,
        anim: 6,
        content: $('#lock-temp').html(),
        shade: [0.9, '#393D49'],
        success: function (layero, lockIndex) {
            isShowLock = true;
            //给显示用户名赋值
            //layero.find('div#lockUserName').text('admin');
            layero.find('input[name=lockPwd]').on('focus', function () {
                var $this = $(this);
                if ($this.val() === '输入密码解锁..') {
                    $this.val('').attr('type', 'password');
                }
            }).on('blur', function () {
                var $this = $(this);
                if ($this.val() === '' || $this.length === 0) {
                    $this.attr('type', 'text').val('输入密码解锁..');
                }
            });
            //在此处可以写一个请求到服务端删除相关身份认证，因为考虑到如果浏览器被强制刷新的时候，身份验证还存在的情况
            var url = $('div#lock-box').find('#lock-url').val();
            $.post(url, {}, function () {
            }, 'json');
            //绑定解锁按钮的点击事件
            layero.find('button#unlock').on('click', function () {
                var $lockBox = $('div#lock-box');
                var userName = $lockBox.find('div#lockUserName').text();
                var pwd = $lockBox.find('input[name=lockPwd]').val();
                var url = $lockBox.find('#unlock-url').val();
                if (pwd === '输入密码解锁..' || pwd.length === 0) {
                    layer.msg('请输入密码..', {
                        icon: 2,
                        time: 1000
                    });
                    return;
                }
                unlock(userName, pwd, url);
            });
            /**
             * 解锁操作方法
             * @param {String} 用户名
             * @param {String} 密码
             */
            var unlock = function (un, pwd, url) {
                $.post(url, {username: un, password: pwd}, function (data) {
                    if (data.status == 1) {
                        isShowLock = false;
                        layer.close(lockIndex);
                    } else {
                        layer.msg(data.info, {icon: 2, time: 1000});
                    }
                }, 'json');
            };
        }
    });
}

/**
 * 关闭弹出窗口
 * @returns {undefined}
 */
function closeLayerParentWindow() {
    //获取窗口索引
    var index = parent.layer.getFrameIndex(window.name);
    parent.layer.close(index);
}

/**
 * 通用获取选中ids
 * @returns {undefined}
 */
function getCheckedIds(){
    var ids = [];
    $("input[name='ids[]']").each(function(){
        var $this = $(this),
            id = $this.val();
        if($this.is(':checked')){
            ids.push(id);
        }
    });
    return ids;
}

$(function () {
    
    if (typeof uploadIds !== 'undefined') {
        pluploadInit(uploadIds);
    }
    
    /**
     * 后台侧边菜单选中状态
     */
    $('.layui-nav-item').find('a').removeClass('layui-this');
    $('.layui-nav-tree').find('a[href*="' + myConfig.cur_controller + '/"]').parent().addClass('layui-this').parents('.layui-nav-item').addClass('layui-nav-itemed');

    /**
     * 通用日期时间选择
     */
    $('.datetime').on('click', function () {
        laydate({
            elem: this,
            istime: true,
            format: 'YYYY-MM-DD hh:mm:ss'
        });
    });

    /**
     * 通用表单提交(AJAX方式)
     */
    form.on('submit(*)', function (data) {
        $.ajax({
            url: data.form.action,
            type: data.form.method,
            data: $(data.form).serialize(),
            success: function (data) {
                if (data.status === 1) {
                    setTimeout(function () {
                        location.href = data.url;
                    }, 1500);
                }
                layer.msg(data.info);
            }
        });
        return false;
    });

    /**
     * 通用单个处理
     */
    $('.ajax-get').on('click', function () {
        var that = this;
        var _href = $(this).attr('href');
        layer.open({
            shade: false,
            content: '确定执行该操作吗？',
            btn: ['确定', '取消'],
            yes: function (index) {
                $.ajax({
                    url: _href,
                    type: "get",
                    success: function (data) {
                        if (data.status === 1) {
                            layer.msg(data.info);
                            setTimeout(function () {
                                if (data.url) {
                                    location.href = data.url;
                                } else if ($(that).hasClass('no-refresh')) {

                                } else {
                                    location.reload();
                                }
                            }, 1500);
                        } else {
                            layer.msg(data.info);
                            setTimeout(function () {
                                if (data.url) {
                                    location.href = data.url;
                                }
                            }, 1500);
                        }
                    }
                });
                layer.close(index);
            }
        });
        return false;
    });

    /**
     * 通用批量处理
     */
    $('.ajax-action').on('click', function () {
        var that = this;
        var _action = $(this).data('action');
        layer.open({
            shade: false,
            content: '确定执行此操作？',
            btn: ['确定', '取消'],
            yes: function (index) {
                $.ajax({
                    url: _action,
                    data: $('.ajax-form').serialize(),
                    success: function (data) {
                        if (data.status === 1) {
                            layer.msg(data.info);
                            setTimeout(function () {
                                if (data.url) {
                                    location.href = data.url;
                                } else if ($(that).hasClass('no-refresh')) {

                                } else {
                                    location.reload();
                                }
                            }, 1500);
                        } else {
                            layer.msg(data.info);
                            setTimeout(function () {
                                if (data.url) {
                                    location.href = data.url;
                                }
                            }, 1500);
                        }
                    }
                });
                layer.close(index);
            }
        });
        return false;
    });

    /**
     * 通用全选
     */
    $('.check-all').on('click', function () {
        $(this).parents('table').find('input[type="checkbox"]').prop('checked', $(this).prop('checked'));
    });

    /**
     * 清除缓存
     */
    $('#clear-cache').on('click', function () {
        var _url = $(this).data('url');
        if (_url !== 'undefined') {
            $.ajax({
                url: _url,
                success: function (data) {
                    if (data.status === 1) {
                        setTimeout(function () {
                            location.href = location.pathname;
                        }, 1000);
                    }
                    layer.msg(data.info);
                }
            });
        }
        return false;
    });

    $('.admin-side-toggle').on('click', function () {
        var sideWidth = $('#admin-side').width();
        if (sideWidth === 200) {
            $('#admin-body').animate({
                left: '0'
            });
//            $('#admin-footer').animate({
//                left: '0'
//            });
            $('#admin-side').animate({
                width: '0'
            });
        } else {
            $('#admin-body').animate({
                left: '200px'
            });
//            $('#admin-footer').animate({
//                left: '200px'
//            });
            $('#admin-side').animate({
                width: '200px'
            });
        }
    });

    $('.admin-side-full').on('click', function () {
        var docElm = document.documentElement;
        //W3C  
        if (docElm.requestFullscreen) {
            docElm.requestFullscreen();
        }
        //FireFox  
        else if (docElm.mozRequestFullScreen) {
            docElm.mozRequestFullScreen();
        }
        //Chrome等  
        else if (docElm.webkitRequestFullScreen) {
            docElm.webkitRequestFullScreen();
        }
        //IE11
        else if (docElm.msRequestFullscreen) {
            docElm.msRequestFullscreen();
        }
        layer.msg('按Esc即可退出全屏');
    });

    //锁屏
    $(document).on('keydown', function (e) {
        if (e.keyCode === 76 && e.altKey) {
            lock($, layer);
        }
    });

    $('#lock').on('click', function () {
        lock($, layer);
    });

    //手机设备的简单适配
    $('.site-tree-mobile').on('click', function () {
        $('body').addClass('site-mobile');
    });

    $('.site-mobile-shade').on('click', function () {
        $('body').removeClass('site-mobile');
    });

});
