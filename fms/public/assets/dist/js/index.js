var indexController = {
        // 初始化
        inits: function () {
            // 根据登录时间判断是否要锁屏
            fms.events.access();
        },
        // 首页
        index: function () {
            return true;
        },
        // 系统设置
        config: function () {
            var form = $(".dialog-form");
            //追加控制
            $(".fieldlist", form).on("click", ".btn-append,.append", function (e, row) {
                var container = $(this).closest("dl");
                var index = container.data("index");
                var name = container.data("name");
                var data = container.data();
                index = index ? parseInt(index) : 0;
                container.data("index", index + 1);
                var row = row ? row : {};
                var vars = {index: index, name: name, data: data, row: row};

                var html = '<dd class="form-inline"><input type="text" name="<%=name%>[field][]" class="form-control" value="" size="10" /> <input type="text" name="<%=name%>[value][]" class="form-control" value="" size="40" /> <span class="btn btn-sm btn-danger btn-remove"><i class="fa fa-times"></i></span></dd>';

                html = html.replace('<%=name%>', vars.name);
                html = html.replace('<%=name%>', vars.name);

                $(html).insertBefore($(this).closest("dd"));
                $(this).trigger("fa.event.appendfieldlist", $(this).closest("dd").prev());
            });
            //移除控制
            $(".fieldlist", form).on("click", "dd .btn-remove", function () {
                $(this).closest("dd").remove();
            });
        },
        // 菜单管理
        rule: function () {
            $(document).on('click', ".btn-search-icon", function () {
                window.open('http://adminlte.la998.com/pages/UI/icons.html');
            });
        },
        // 角色管理
        group: function () {
            var checkedAll = function () {
                var r = $("#treeview").jstree("get_all_checked");
                $("input[name='row[rules]']").val(r.join(','));
            };
            //读取选中的条目
            $.jstree.core.prototype.get_all_checked = function (full) {
                var obj = this.get_selected(), i, j;
                for (i = 0, j = obj.length; i < j; i++) {
                    obj = obj.concat(this.get_node(obj[i]).parents);
                }
                obj = $.grep(obj, function (v, i, a) {
                    return v != '#';
                });
                obj = obj.filter(function (itm, i, a) {
                    return i == a.indexOf(itm);
                });
                return full ? $.map(obj, $.proxy(function (i) {
                    return this.get_node(i);
                }, this)) : obj;
            };
            // 选中时间
            $('#treeview').bind("activate_node.jstree", function (obj, e) {
                checkedAll();
            });
            // 默认事件
            if ($("#treeview").length > 0) {
                checkedAll();
            }
            //全选和展开
            $(document).on("click", "#checkall", function () {
                $("#treeview").jstree($(this).prop("checked") ? "check_all" : "uncheck_all");
                checkedAll();
            });

            $(document).on("click", "#expandall", function () {
                $("#treeview").jstree($(this).prop("checked") ? "open_all" : "close_all");
            });

            $("select[name='row[pid]']").trigger("change");
        },

        /**
         * 更改省份时，修改城市
         * @author lamkakyun
         * @date 2018-11-12 13:52:44
         */
        change_province: function (province_id, all_cities, select_city_id) {
            select_city_id = select_city_id || 0;
            all_cities = JSON.parse(all_cities);
            $('#city_id').find('option').remove();

            var regexp = new RegExp('^' + province_id, 'i');
            for (var i = 0; i < all_cities.length; i++) {
                var _city_id = all_cities[i].city_id;
                var _city_name = all_cities[i].city;

                if (regexp.test(_city_id)) {
                    if (_city_id == select_city_id) {
                        $('#city_id').append('<option value="' + _city_id + '" selected>' + _city_name + '</option>');
                    }
                    else {
                        $('#city_id').append('<option value="' + _city_id + '">' + _city_name + '</option>');
                    }
                }
            }

            $('#city_id').selectpicker('refresh');
        },

        /**
         * 更改 银行时，修改支行信息
         * @author lamkakyun
         * @date 2018-11-12 13:52:34
         */
        change_bank: function (bank_id, province_id, city_id, sub_bank_id) {
            sub_bank_id = sub_bank_id || 0;
            if (!bank_id) return false;
            $('#sub_bank_id').find('option').remove();

            $.ajax({
                url: '/index/Ajax/getSubBanks',
                type: 'POST',
                data: {bank_id: bank_id, province_id: province_id, city_id: city_id},
                dataType: 'JSON',
                success: function (ret) {
                    if (ret.code == 0) {
                        for (d of ret.data) {
                            if (sub_bank_id == d.sub_branch_id) {
                                $('#sub_bank_id').append('<option value="' + d.sub_branch_id + '" selected>' + d.sub_branch_name + '</option>');
                            }
                            else {
                                $('#sub_bank_id').append('<option value="' + d.sub_branch_id + '">' + d.sub_branch_name + '</option>');
                            }
                        }
                        $('#sub_bank_id').selectpicker('refresh');
                    }
                }
            })
        },


        /**
         * 添加子账户
         * @author lamkakyun
         * @date 2018-11-12 13:53:10
         */
        add_sub_account: function (thisobj) {
            var clone_element = $('#element_example').find('.sub_account_div').clone();

            $('#balance_div').after(clone_element);
        },

        /**
         * 删除子账号
         * @author lamkakyun
         * @date 2018-11-12 14:13:32
         */
        remove_sub_account: function (thisobj) {
            thisobj.parent().parent().remove();
        },
        // 删除已经存在的自账号
        remove_eidt_account: function (_this, id) {
            layer.confirm("确认要移除此余额吗？", {
                btn: ['确定', '取消'],
            }, function (index, layeo) {
                layer.close(index);
                $.ajax({
                    url: _this.attr('data-url'),
                    type: 'POST',
                    data: {funds_id: id},
                    dataType: 'JSON',
                    success: function (ret) {
                        if (ret.code != 1) {
                            layer.alert(ret.msg || "请求失败");
                            return false;
                        }
                        layer.msg(ret.msg);
                        indexController.remove_sub_account(_this);
                    }
                })
            });
        },
        /**
         * 更改 账号 平台 (默认的平台 是 P 卡)
         * @author lamkakyun
         * @date 2018-11-12 14:34:57
         */
        change_platform: function (thisobj) {
            var platform = thisobj.val();
            if (platform == 'bank_card') {
                $('.bank_div').show();
                $('.account_div').hide();
                $('.sub_account_div').show();
                $('.sub_account_div').find('.sub_account').hide();
            }
            else {
                $('.bank_div').hide();
                $('.account_div').show();
                $('.sub_account_div').show();
                $('.sub_account_div').find('.sub_account').show();
            }
            if (platform == 'shop_account') {
                $('.ebay_div').show();
            }
            else {
                $('.ebay_div').hide();
            }
        },
        change_type: function (_this) {
            var values = _this.val();
            if (values == 3) {
                $(".bank_type_div").show();
            } else {
                $(".bank_type_div").hide();
            }
        },
        /**
         * @author lamkakyun
         * @date 2018-11-13 14:09:12
         */
        add_account: function (this_form) {
            $.ajax({
                url: this_form.attr('action'),
                type: 'POST',
                data: this_form.serializeArray(),
                dataType: 'JSON',
                success: function (ret) {
                    if (ret.code < 0) {
                        fms.events.error(ret.msg);
                        return false;
                    }
                    layer.alert(ret.msg, {closeBtn: 0}, function (index) {
                        parent.layer.close(parent.layer.getFrameIndex(window.name));
                        parent.layer.close(index);
                        parent.window.location.reload();
                        window.location.reload();
                    });
                }
            })
        }
        ,

        /**
         * 更改 第三方支付类型
         * @author lamkakyun
         * @date 2018-11-13 14:09:10
         */
        change_pay_type: function (thisobj) {
            var type = thisobj.val();
            var url = '/index/account/index/receipt?type=' + type;
            window.location.href = url;
        }
        ,

        /**
         * 显示 收款账号详情
         * @author lamkakyun
         * @date 2018-11-13 16:00:26
         */
        show_receipt_detail: function (thisobj) {

        }
        ,


        /**
         * 修改账户所属管理员
         * @author lamkakyun
         * @date 2018-11-15 17:18:39
         */
        update_account_admin: function (id, thisobj) {
            var check_ids = id ? [id] : fms.get_checked_id();
            if (check_ids.length == 0) return layer.msg('请选择账号');

            var url = thisobj.attr('data-url');
            url += '?account_id=' + check_ids.join(',');

            var title = thisobj.attr('data-title');

            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['400px', '300px'],
                content: url,
            });
        }
        ,

        /**
         * 修改提款费率
         * @author lamkakyun
         * @date 2018-11-15 17:23:54
         */
        update_out_rate: function (id, thisobj) {
            var check_ids = id ? [id] : fms.get_checked_id();
            if (check_ids.length == 0) return layer.msg('请选择账号');

            var url = thisobj.attr('data-url');
            url += '?account_id=' + check_ids.join(',');

            var title = thisobj.attr('data-title');

            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['400px', '300px'],
                content: url,
            });
        }
        ,

        /**
         * 修改账户状态
         * @author lamkakyun
         * @date 2018-11-15 17:24:16
         */
        update_account_status: function (id, thisobj) {
            var check_ids = id ? [id] : fms.get_checked_id();
            if (check_ids.length == 0) return layer.msg('请选择账号');

            var url = thisobj.attr('data-url');
            url += '?account_id=' + check_ids.join(',');

            var title = thisobj.attr('data-title');

            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['400px', '300px'],
                content: url,
            });
        }
        ,

        /**
         * 转账
         * @author lamkakyun
         * @date 2018-11-16 18:22:48
         */
        transfer_money: function (id, thisobj) {
            var check_ids = id ? [id] : fms.get_checked_id();
            if (check_ids.length == 0) return layer.msg('请选择账号');

            var url = thisobj.attr('data-url');
            url += '?fund_id=' + check_ids.join(',');

            var title = thisobj.attr('data-title');

            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['800px', '600px'],
                content: url
            });
        }
        ,

        /**
         * 提现
         * @author lamkakyun
         * @date 2018-11-16 18:24:06
         */
        withdraw_money: function (id, thisobj) {
            var check_ids = id ? [id] : fms.get_checked_id();
            if (check_ids.length == 0) return layer.msg('请选择账号');

            var url = thisobj.attr('data-url');
            url += '?fund_id=' + check_ids.join(',');

            var title = thisobj.attr('data-title');

            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['800px', '600px'],
                content: url
            });
        }
        ,


        /**
         * 平账
         * @author lamkakyun
         * @date 2018-11-16 18:24:23
         */
        fix_money: function (id, thisobj) {
            var check_ids = id ? [id] : fms.get_checked_id();
            if (check_ids.length == 0) return layer.msg('请选择账号');

            var url = thisobj.attr('data-url');
            url += '?fund_id=' + check_ids.join(',');

            var title = thisobj.attr('data-title');

            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['870px', '600px'],
                content: url,
            });
        }
        ,

        /**
         * 新建模板
         * @author lamkakyun
         * @date 2018-11-17 10:20:12
         */
        new_template: function () {
            var title = "新建模板";
            var url = "/index/funds.store/newTempl"
            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['600px', '400px'],
                content: url
            });
        }
        ,

        change_tmpl_account_type: function (thisobj) {
            var account_type = thisobj.val();
            console.log(account_type)
            if (account_type == 2) {
                $('.bank-div').show();
            }
            else {
                $('.bank-div').hide();
            }
        }
        ,

        chanage_transfer_template: function (thisobj) {
            var e = thisobj.find('option:selected');
            var tpl_data = JSON.parse(e.attr('data-template'));

            $('#receipt_username').val(tpl_data.account_user);
            $('#receipt_account').val(tpl_data.account);

            $('#t_account_type').find('option').attr('selected', false);
            $('#t_account_type').find('option[value=' + tpl_data.type + ']').attr('selected', 'selected');
            $('#t_account_type').selectpicker('refresh');

            if (tpl_data.type == 2) {
                $('.bank_row').show();
                $('#bank_of_account').val(tpl_data.type_attr);
            }
            else {
                $('.bank_row').hide();
                $('#bank_of_account').val('');
            }
        }
        ,
        change_account_type: function (thisobj) {
            var opt = thisobj.val();
            if (opt == 2) {
                $('.bank_row').show();
            }
            else $('.bank_row').hide();
        }
        ,
        change_transfer_type: function (thisobj) {
            var type = thisobj.val();
            if (type == 1) {
                $('.inner-div').show();
                $('.outer-div').hide();
            }
            else {
                $('.inner-div').hide();
                $('.outer-div').show();
            }
        }
        ,
        change_pay_account: function (thisobj) {
            var opt = thisobj.find('option:selected');
            $('#account_name').val(opt.attr('data-account'));
        }
        ,
        change_receipt_account: function (thisobj) {
            var opt = thisobj.find('option:selected');
            $('#r_account_name').val(opt.attr('data-account'));
        }
        ,
        change_currency: function (thisobj) {
            var currency = thisobj.val();
            $('.transation_currency').text(currency);
        }
        ,
        /**
         * 修改转账金额
         * @author lamkakyun
         * @date 2018-11-19 10:34:28
         */
        change_transfer_money: function (thisobj) {
            var money_amount = $('#money_amount').val();
            var transaction_fee = parseInt($('#transaction_fee').val());
            transaction_fee = transaction_fee ? transaction_fee : 0;
            $('.r_money_amount').val(money_amount - transaction_fee);
        }
        ,
        change_balance: function (thisobj) {
            var parent = thisobj.parent();
            var balance = parent.find('.balance').val();
            var true_balance = thisobj.val();

            console.log(balance, true_balance);
            parent.find('.balance-diff').val(balance - true_balance);
        }
        ,
        /**
         * 确认到账
         * @author lamkakyun
         * @date
         * @return void
         */
        confirm_money: function (id, thisobj) {
            var check_ids = id ? [id] : fms.get_checked_id();
            if (check_ids.length == 0) return layer.msg('请选择账号');

            var url = thisobj.attr('data-url');
            url += '?id=' + check_ids.join(',');

            var title = thisobj.attr('data-title');

            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['400px', '300px'],
                content: url,
            });
        }
        ,
        change_fund: function (thisobj) {
            var fund_id = thisobj.val();
            var currency = thisobj.find('option:selected').attr('data-currency');
            $('#title').val(fund_id);
            $('.transation_currency').text(currency);
        }
        ,
        change_withdraw_fund: function (thisobj) {
            var fund_id = thisobj.val();
            var currency = thisobj.find('option:selected').attr('data-currency');
            var fund = thisobj.find('option:selected').attr('data-fund');

            $('.balance').val(fund);
            $('.balance_currency').val(currency);
        }
        ,
        import_flow_detail: function (thisobj) {
            var url = thisobj.attr('data-url');
            var title = thisobj.attr('data-title');

            layer.open({
                type: 2,
                title: title,
                shadeClose: true,
                shade: 0.8,
                maxmin: true,
                moveOut: true,
                zIndex: layer.zIndex,
                area: ['800px', '600px'],
                content: url,
            });
        },
        change_flow_account_type: function (thisobj) {
            var type = thisobj.val();
            $.ajax({
                url: thisobj.attr('data-url'),
                type: 'POST',
                data: {type: type},
                dataType: 'JSON',
                success: function (ret) {
                    $('#account_id').find('option').remove();
                    if (ret.code == 0) {
                        for (d of ret.data) {
                            $('#account_id').append('<option value="' + d.id + '">' + d.title + '(' + d.account + ')' + '</option>');
                        }
                    }
                    $('#account_id').selectpicker('refresh');
                }
            });
        },
        show_import_msg: function (thisobj) {
            layer.alert(thisobj.attr('data-msg'), {
                'area': ['700px', '500px'],
            });
        },

        show_full_remark: function (thisobj) {
            layer.alert(thisobj.attr('data-remark'));
        },

        change_exchange_account: function(thisobj) 
        {
            $('.receipt_transation_currency').text(thisobj.find('option:selected').attr('data-currency'));
        },

        change_exchange_money: function(thisobj)
        {
            var money_amount = $('#money_amount').val();
            var rates = $('#rates').val();
            var transaction_fee = $('#transaction_fee').val();

            var price_reg = /^(-?\d+)(\.\d+)?$/;

            if (price_reg.test(money_amount) && price_reg.test(rates) && price_reg.test(transaction_fee))
            {
                var to_money = (money_amount - transaction_fee) * rates;
                to_money = to_money.toFixed(4);
                $('#r_money_amount').val(to_money);
            }
        },
    }
;

$(function () {
    // 初始化
    indexController.inits();
    // 菜单管理
    indexController.rule();
    // 系统配置
    indexController.config();
    // 角色管理
    indexController.group();
});