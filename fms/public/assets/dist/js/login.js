$(function () {
    var inits = {
        countdown: 60,
        sendsms: ".sendsms",
        submit: "#submit",
        loginForm: "#loginForm",
        locksform: "#locks-form",
        usernamelogin: ".usernamelogin",
        smslogin: ".smslogin",
        loginboxmsg: ".login-box-msg",
        formcaptcha: "#pd-form-captcha",
        imgcaptcha: "#img-captcha",
        alert: ".alert",
        admin: false
    };
    // 开始登录
    var startLogin = function () {
        $(inits.submit).attr('disable', true).html('Loding...');
        $(inits.submit).removeClass('bg-purple').addClass('bg-default');
    }
    //停止登录
    var endLogin = function () {
        $(inits.submit).attr('disable', false).html('Sign In');
        $(inits.submit).removeClass('bg-default').addClass('bg-purple');
    }
    // 倒计时
    var countdown = inits.countdown;
    var _generate_code = $(inits.sendsms);
    var settime = function () {
        if (countdown == 0) {
            _generate_code.attr("disabled", false);
            _generate_code.val("获取验证码");
            countdown = inits.countdown;
            return false;
        } else {
            _generate_code.attr("disabled", true);
            _generate_code.val("重新发送(" + countdown + ")");
            countdown--;
        }
        setTimeout(function () {
            settime();
        }, 1000);
    }
    // 短信验证码登录
    var smsLogin = function (user, _this) {
        var url = _this.attr('login-action');

        $(inits.usernamelogin).hide();
        $(inits.smslogin).show();
        $(inits.loginboxmsg).html('Please enter Sms Captcha');
        $(inits.alert).hide();
        settime();

        _this.attr('action', url);
        inits.admin = user.data;
        // 发送短信
        sendsms($(inits.sendsms).attr('sendurl'));
        $(inits.formcaptcha).val('');
        $(inits.imgcaptcha).click();
    }
    // 发送短信
    var sendsms = function (url) {
        $.ajax({
            url: url,
            type: "post",
            dataType: "json",
            data: inits.admin,
            success: function (data) {
                if (data.code != 1) {
                    return layer.alert(data.msg);
                }
                layer.msg(data.msg);
                settime();
            },
            error: function (error) {
                return layer.alert("请求或返回数据异常");
            }
        });
    }
    // 发送短信验证码
    $(inits.sendsms).on('click', function () {
        var url = $(this).attr('sendurl');
        return sendsms(url);
    });
    // 登录
    $(inits.loginForm).on('valid.form', function () {
        var url = $(this).attr('action');
        var index = layer.load();
        var _this = $(this);
        startLogin();
        $.ajax({
            url: url,
            type: "post",
            dataType: "json",
            data: $(this).serialize(),
            success: function (data) {
                layer.close(index);
                endLogin();
                if (data.code != 1) {
                    return layer.alert(data.msg);
                }
                if (data.data.type == 'check') {
                    return smsLogin(data, _this);
                }
                if (data.url) {
                    location.href = data.url;
                    return;
                }
                layer.alert(data.msg, {closeBtn: 0}, function (index) {
                    parent.layer.close(parent.layer.getFrameIndex(window.name));
                    parent.layer.close(index);
                    parent.window.location.reload();
                    window.location.reload();
                });
            },
            error: function (error) {
                layer.close(index);
                endLogin();
                return layer.alert("请求或返回数据异常");
            }
        });
    });
    // 锁屏登录
    $(inits.locksform).on('valid.form', function () {
        var url = $(this).attr('action');
        var index = layer.load();
        $.ajax({
            url: url,
            type: "post",
            dataType: "json",
            data: $(this).serialize(),
            success: function (data) {
                layer.close(index);
                if (data.code != 1) {
                    layer.alert(data.msg, {closeBtn: 0}, function (index) {
                        if (data.url) {
                            location.href = data.url;
                        } else {
                            parent.layer.close(index);
                        }
                    });
                }
                if (data.url) {
                    location.href = data.url;
                    return;
                }
                layer.alert(data.msg, {closeBtn: 0}, function (index) {
                    parent.layer.close(index);
                    parent.window.location.reload();
                    window.location.reload();
                });
            },
            error: function (error) {
                layer.close(index);
                return layer.alert("请求或返回数据异常");
            }
        });
    });
})

