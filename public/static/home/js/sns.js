$(function(){
    //修改权限模块
    $("[ds_type='privacydiv']").on('mouseover', function() {
        $(this).find("[ds_type='privacytab']").show();
    });
    $("[ds_type='privacydiv']").on('mouseout', function() {
        $(this).find("[ds_type='privacytab']").hide();
    });
    $("[ds_type='privacyoption']").on('click', function() {
        var obj = $(this);
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        var action = "editprivacy";
        switch (data_str.action) {
            case 'store':
                action = "storeprivacy";
                break;
            default:
                action = "editprivacy";
                break;
        }
        ajaxget(HOMESITEURL + '/Membersnsindex/' + action + '.html?id=' + data_str.sid + '&privacy=' + data_str.v);
    });
    //表单权限模块
    $(document).off('mouseover',"[ds_type='formprivacydiv']").on('mouseover',"[ds_type='formprivacydiv']", function() {
        $(this).find("[ds_type='formprivacytab']").show();
    });
    $(document).off('mouseout',"[ds_type='formprivacydiv']").on('mouseout',"[ds_type='formprivacydiv']", function() {
        $(this).find("[ds_type='formprivacytab']").hide();
    });
    //选择权限
    $(document).off('click',"[ds_type='formprivacyoption']").on('click',"[ds_type='formprivacyoption']", function() {
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        var hiddenid = "privacy";
        if (data_str.hiddenid != '' && data_str.hiddenid != undefined) {
            hiddenid = data_str.hiddenid;
        }
        $(this).parent().find('span').removeClass('selected');
        $(this).find('span').addClass('selected');
        $("#" + hiddenid).val(data_str.v);
    });
    //分享单个商品
    $("[ds_type='sharegoods']").bind('click', function() {
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        ajaxget(HOMESITEURL + '/Membersnsindex/sharegoods_one.html?dialog=1&gid=' + data_str.gid);

    });
	//提交分享商品表单
    $(document).off('click',"#weibobtn_goods").on("click","#weibobtn_goods", function() {
        if ($("#sharegoods_form").valid()) {
            var cookienum = $.cookie('weibonum');
            cookienum = parseInt(cookienum);
            if (cookienum >= max_recordnum && $("#sg_seccode").css('display') == 'none') {
                //显示验证码
                $("#sg_seccode").show();
                $("#sg_seccode").find("[name='codeimage']").attr('src', HOMESITEURL + '/Seccode/makecode.html?t=' + Math.random());
            } else if (cookienum >= max_recordnum && $("#sg_seccode").find("[name='captcha']").val() == '') {
                showDialog('请填写验证码');
            } else {
                ajaxpost('sharegoods_form', '', '', 'onerror');
                //隐藏验证码
                $("#sg_seccode").hide();
                $("#sg_seccode").find("[name='codeimage']").attr('src', '');
                $("#sg_seccode").find("[name='captcha']").val('');
            }
        }
        return false;
    });
    //分享单个店铺
    $("[ds_type='sharestore']").bind('click', function() {
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        ajaxget(HOMESITEURL + '/Membersnsindex/sharestore_one.html?dialog=1&sid=' + data_str.sid);
    });
    //删除分享和喜欢的商品
    $(document).off('click',"[ds_type='delbtn']").on('click',"[ds_type='delbtn']", function() {
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        showDialog('您确定要删除该信息吗？', 'confirm', '', function() {
            ajaxget(HOMESITEURL + '/Membersnsindex/delgoods.html?id=' + data_str.sid + '&type=' + data_str.tabtype);
            return false;
        });
    });
    //喜欢操作
    $(document).off('click',"[ds_type='likebtn']").on('click',"[ds_type='likebtn']", function() {
        var obj = $(this);
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        ajaxget(HOMESITEURL + '/Membersnsindex/editlike.html?inajax=1&id=' + data_str.gid);
    });
    //展示和隐藏评论列表
    $(document).off('click',"[ds_type='fd_commentbtn']").on('click', "[ds_type='fd_commentbtn']",function() {
        var data = $(this).attr('data-param');
        eval("data = " + data);
        //隐藏转发模块
        $('#forward_' + data.txtid).hide();
        if ($('#tracereply_' + data.txtid).css("display") == 'none') {
            //加载评论列表
            $("#tracereply_" + data.txtid).load(HOMESITEURL + '/Membersnshome/commenttop.html?type=0&id=' + data.txtid + '&mid=' + data.mid);
            $('#tracereply_' + data.txtid).show();
        } else {
            $('#tracereply_' + data.txtid).hide();
        }
        return false;
    });
    
    //删除动态
    $(document).off('click',"[ds_type='fd_del']").on('click',"[ds_type='fd_del']", function() {
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        var url = HOMESITEURL + "/Membersnsindex/deltrace.html?id=" + data_str.txtid;
        if (data_str.type != undefined && data_str.type != '') {
            url = url + '&type=' + data_str.type;
        }
        showDialog('您确定要删除该信息吗？', 'confirm', '', function() {
            ajaxget(url);
            return false;
        });
    });
    
    //转发提交
    $(document).off('click',"[ds_type='forwardbtn']").on('click',"[ds_type='forwardbtn']", function() {
        var data = $(this).attr('data-param');
        eval("data = " + data);
        if ($("#forwardform_" + data.txtid).valid()) {
            var cookienum = $.cookie('forwardnum');
            cookienum = parseInt(cookienum);
            if (cookienum >= max_recordnum && $("#forwardseccode" + data.txtid).css('display') == 'none') {
                //显示验证码
                $("#forwardseccode" + data.txtid).show();
                $("#forwardseccode" + data.txtid).find("[name='codeimage']").attr('src', HOMESITEURL + '/Seccode/makecode.html?t=' + Math.random());
            } else if (cookienum >= max_recordnum && $("#forwardseccode" + data.txtid).find("[name='captcha']").val() == '') {
                showDialog('请填写验证码');
            } else {
                ajaxpost('forwardform_' + data.txtid, '', '', 'onerror');
                //隐藏验证码
                $("#forwardseccode" + data.txtid).hide();
                $("#forwardseccode" + data.txtid).find("[name='codeimage']").attr('src', '');
                $("#forwardseccode" + data.txtid).find("[name='captcha']").val('');
            }
        }
        return false;
    });
    
    //展示和隐藏转发表单
    $(document).off('click',"[ds_type='fd_forwardbtn']").on('click',"[ds_type='fd_forwardbtn']", function() {
        var data = $(this).attr('data-param');
        eval("data = " + data);
        //隐藏评论模块
        $('#tracereply_' + data.txtid).hide();
        if ($('#forward_' + data.txtid).css("display") == 'none') {
            //加载评论列表
            $('#forward_' + data.txtid).show();
            //添加字数提示
            if ($("#forwardcharcount" + data.txtid).html() == '') {
                $("#content_forward" + data.txtid).charCount({
                    allowed: 140,
                    warning: 10,
                    counterContainerID: 'forwardcharcount' + data.txtid,
                    firstCounterText: '还可以输入',
                    endCounterText: '字',
                    errorCounterText: '已经超出'
                });
            }
            //绑定表单验证
            $('#forwardform_' + data.txtid).validate({
                errorPlacement: function(error, element) {
                    element.next('.error').append(error);
                },
                rules: {
                    forwardcontent: {
                        maxlength: 140
                    }
                },
                messages: {
                    forwardcontent: {
                        maxlength: '不能超过140字'
                    }
                }
            });
        } else {
            $('#forward_' + data.txtid).hide();
        }
        return false;
    });
	
    // 查看大图
    $('[ds_type="thumb-image"]').off().on('click', function() {
        src = $(this).find('img').attr('src');
        big_src = src.replace('_small.', '_big.');
        $(this).parent().hide().next().children('[ds_type="origin-image"]').append('<img src="' + big_src + '" />').end().show();
    });
    $('[ds_type="origin-image"]').off().on('click', function() {
        $(this).html('').parent().hide().prev().show();
    });
});
function ajaxload_page(objname){
	$('#'+objname).find('.demo').ajaxContent({
		event:'click',
		loaderType:"img",
		loadingMsg:HOMESITEROOT+"/images/transparent.gif",
		target:'#'+objname
	});
}