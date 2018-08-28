<?php

namespace app\home\controller;

use think\Lang;
use think\Validate;

class Membersnsindex extends BaseMember {

    const MAX_RECORDNUM = 20; //允许插入新记录的最大条数(注意在sns中该常量是一样的，注意与member_snshome中的该常量一致)

    public function _initialize() {
        parent::_initialize();
        $this->assign('relation', '3');
        Lang::load(APP_PATH . 'home/lang/zh-cn/member_sns.lang.php');
        Lang::load(APP_PATH . 'home/lang/zh-cn/member_sharemanage.lang.php');
        //允许插入新记录的最大条数
        $this->assign('max_recordnum', self::MAX_RECORDNUM);
    }

    /**
     * SNS首页
     */
    public function index() {

        //查询谁来看过我
        $visitor_model = model('snsvisitor');
        $visitme_list = $visitor_model->getSnsvisitorList(array('snsvisitor_ownermid' => session('member_id'), 'limit' => 9));
        if (!empty($visitme_list)) {
            foreach ($visitme_list as $k => $v) {
                $v['adddate_text'] = $this->formatDate($v['snsvisitor_addtime']);
                $v['addtime_text'] = @date('H:i', $v['snsvisitor_addtime']);
                $visitme_list[$k] = $v;
            }
        }
        //查询我访问过的人
        $visitother_list = $visitor_model->getSnsvisitorList(array('snsvisitor_mid' => session('member_id'), 'limit' => 9));
        if (!empty($visitother_list)) {
            foreach ($visitother_list as $k => $v) {
                $v['adddate_text'] = $this->formatDate($v['snsvisitor_addtime']);
                $visitother_list[$k] = $v;
            }
        }
        $this->assign('visitme_list', $visitme_list);
        $this->assign('visitother_list', $visitother_list);
        return $this->fetch($this->template_dir . 'member_snsindex');
    }

    private function formatDate($time) {
        $handle_date = @date('Y-m-d', $time); //需要格式化的时间
        $reference_date = @date('Y-m-d', time()); //参照时间
        $handle_date_time = strtotime($handle_date); //需要格式化的时间戳
        $reference_date_time = strtotime($reference_date); //参照时间戳
        if ($reference_date_time == $handle_date_time) {
            $timetext = @date('H:i', $time); //今天访问的显示具体的时间点
        } elseif (($reference_date_time - $handle_date_time) == 60 * 60 * 24) {
            $timetext = lang('sns_yesterday');
        } elseif ($reference_date_time - $handle_date_time == 60 * 60 * 48) {
            $timetext = lang('sns_beforeyesterday');
        } else {
            $month_text = lang('ds_month');
            $day_text = lang('ds_day');
            $timetext = @date("m{$month_text}d{$day_text}", $time);
        }
        return $timetext;
    }

    /**
     * 添加SNS分享心情
     */
    public function addtrace() {

        $param = array(
            'content' => input('param.content')
        );
        //验证数据  BEGIN
        $rule = [
            ['content', 'require|min:0|max:140', lang('sns_sharemood_content_null') . "|" . lang('sns_content_beyond') . "|" . lang('sns_content_beyond')],
        ];
        $validate = new Validate($rule);
        $validate_result = $validate->check($param);
        if (!$validate_result) {
            $this->error($validate->getError());
        }

        if (intval(cookie('weibonum')) >= self::MAX_RECORDNUM) {
            if (!captcha_check(input('post.captcha'))) {
                //验证失败
                $this->error('验证码错误');
            }
        }



        //查询会员信息
        $member_model = model('member');
        $member_info = $member_model->getMemberInfo(array('member_id' => session('member_id'), 'member_state' => 1));
        if (empty($member_info)) {
            ds_show_dialog(lang('sns_member_error'), '', 'error');
        }
        $snstracelog_model = model('snstracelog');
        $insert_arr = array();
        $insert_arr['tracelog_originalid'] = '0';
        $insert_arr['tracelog_originalmemberid'] = '0';
        $insert_arr['tracelog_memberid'] = session('member_id');
        $insert_arr['tracelog_membername'] = session('member_name');
        $insert_arr['tracelog_memberavatar'] = $member_info['member_avatar'];
        $insert_arr['tracelog_title'] = input('param.content');
        $insert_arr['tracelog_content'] = '';
        $insert_arr['tracelog_addtime'] = time();
        $insert_arr['tracelog_state'] = '0';
        $insert_arr['tracelog_privacy'] = intval(input('param.privacy')) > 0 ? intval(input('param.privacy')) : 0;
        $insert_arr['tracelog_commentcount'] = 0;
        $insert_arr['tracelog_copycount'] = 0;
        $result = $snstracelog_model->addSnstracelog($insert_arr);
        if ($result) {
            //建立cookie
            if (cookie('weibonum') != null && intval(cookie('weibonum')) > 0) {
                cookie('weibonum', intval(cookie('weibonum')) + 1, 2 * 3600); //保存2小时
            } else {
                cookie('weibonum', 1, 2 * 3600); //保存2小时
            }
            $js = "var obj = $(\"#weiboform\").find(\"[ds_type='formprivacytab']\");$(obj).find('span').removeClass('selected');$(obj).find('ul li:nth-child(1)').find('span').addClass('selected');";
            $js .= "$(\"#content_weibo\").val('');$(\"#privacy\").val('0');$('#friendtrace').lazyshow({url:\"" . HOME_SITE_URL . "/Membersnshome/tracelist.html?curpage=1\",'iIntervalId':true});";
            ds_show_dialog(lang('sns_share_succ'), '', 'succ', $js);
        } else {
            ds_show_dialog(lang('sns_share_fail'), '', 'error');
        }
    }

    /**
     * 添加分享已买到的宝贝
     */
    public function sharegoods() {

        if (request()->isPost()) {

            $choosegoodsid = intval(input('param.choosegoodsid'));
            if($choosegoodsid<=0){
                ds_show_dialog(lang('error'), '', 'error');
            }
            $validate_arr = array(
                'choosegoodsid' => $choosegoodsid,
                'comment' => input('param.comment'),
            );
            $rule = [
                ['choosegoodsid', 'require|min:1', lang('sns_sharegoods_goodserror') . '|' . lang('sns_sharegoods_choose')],
                ['comment', 'require|length:0,140', lang('sns_content_beyond') . '|' . lang('sns_content_beyond')],
            ];

            $obj_validate = new Validate();
            $validate_result = $obj_validate->check($validate_arr, $rule);
            if (!$validate_result) {
                ds_show_dialog($obj_validate->getError(), '', 'error');
            }

            //发帖数超过最大次数出现验证码
            if (intval(cookie('weibonum')) >= self::MAX_RECORDNUM) {
                if (!captcha_check(input('post.captcha'))) {
                    //验证失败
                    ds_show_dialog(lang('wrong_checkcode'), '', 'error');
                }
            }




            //查询会员信息
            $member_model = model('member');
            $member_info = $member_model->getMemberInfo(array('member_id' => session('member_id'), 'member_state' => 1));
            if (empty($member_info)) {
                ds_show_dialog(lang('sns_member_error'), '', 'error');
            }
            //查询商品信息
            $goods_model = model('goods');
            $goods_info = $goods_model->getGoodsOnlineInfoForShare(intval(input('post.choosegoodsid')));
            if (empty($goods_info)) {
                ds_show_dialog(lang('sns_sharegoods_goodserror'), '', 'error');
            }
            $sharegoods_model = model('snssharegoods');
            //判断该商品是否已经存在分享或者喜欢记录
            $sharegoods_info = $sharegoods_model->getSnssharegoodsInfo(array('sharegoods_memberid' => session('member_id'), 'sharegoods_goodsid' => "{$goods_info['goods_id']}"));
            $result = false;
            if (empty($sharegoods_info)) {
                //添加分享商品信息
                $insert_arr = array();
                $insert_arr['sharegoods_goodsid'] = $goods_info['goods_id'];
                $insert_arr['sharegoods_memberid'] = session('member_id');
                $insert_arr['sharegoods_membername'] = session('member_name');
                $insert_arr['sharegoods_content'] = input('post.comment') ? input('post.comment') : lang('sns_sharegoods_title');
                $insert_arr['sharegoods_addtime'] = time();
                $insert_arr['sharegoods_privacy'] = intval(input('post.gprivacy')) > 0 ? intval(input('post.gprivacy')) : 0;
                $insert_arr['sharegoods_commentcount'] = 0;
                $insert_arr['sharegoods_isshare'] = 1;
                $result = $sharegoods_model->addSnssharegoods($insert_arr);
                unset($insert_arr);
            } else {
                //更新分享商品信息
                $update_arr = array();
                $update_arr['sharegoods_content'] = input('post.comment') ? input('post.comment') : lang('sns_sharegoods_title');
                $update_arr['sharegoods_addtime'] = time();
                $update_arr['sharegoods_privacy'] = intval(input('post.gprivacy')) > 0 ? intval(input('post.gprivacy')) : 0;
                $update_arr['sharegoods_isshare'] = 1;
                $result = $sharegoods_model->editSnssharegoods($update_arr, array('sharegoods_id' => $sharegoods_info['sharegoods_id']));
                unset($update_arr);
            }
            if ($result) {
                //商品缓存数据更新
                //生成缓存的键值
                $hash_key = $goods_info['goods_id'];
                //先查找$hash_key缓存
                if ($_cache = rcache($hash_key, 'product')) {
                    $_cache['sharenum'] = intval($_cache['sharenum']) + 1;
                    //缓存商品信息
                    wcache($hash_key, $_cache, 'product');
                }
                //更新SNS商品表信息
                $snsgoods_model = model('snsgoods');
                $snsgoods_info = $snsgoods_model->getSnsgoodsInfo(array('snsgoods_goodsid' => "{$goods_info['goods_id']}"));
                if (empty($snsgoods_info)) {
                    //添加SNS商品
                    $insert_arr = array();
                    $insert_arr['snsgoods_goodsid'] = $goods_info['goods_id'];
                    $insert_arr['snsgoods_goodsname'] = $goods_info['goods_name'];
                    $insert_arr['snsgoods_goodsimage'] = $goods_info['goods_image'];
                    $insert_arr['snsgoods_goodsprice'] = $goods_info['goods_price'];
                    $insert_arr['snsgoods_storeid'] = $goods_info['store_id'];
                    $insert_arr['snsgoods_storename'] = $goods_info['store_name'];
                    $insert_arr['snsgoods_addtime'] = time();
                    $insert_arr['snsgoods_likenum'] = 0;
                    $insert_arr['snsgoods_sharenum'] = 1;
                    $snsgoods_model->addSnsgoods($insert_arr);
                    unset($insert_arr);
                } else {
                    //更新SNS商品
                    $update_arr = array();
                    $update_arr['snsgoods_sharenum'] = intval($snsgoods_info['snsgoods_sharenum']) + 1;
                    $snsgoods_model->editSnsgoods($update_arr, array('snsgoods_goodsid' => "{$goods_info['goods_id']}"));
                }
                //添加分享动态
                $snstracelog_model = model('snstracelog');
                $insert_arr = array();
                $insert_arr['tracelog_originalid'] = '0';
                $insert_arr['tracelog_originalmemberid'] = '0';
                $insert_arr['tracelog_memberid'] = session('member_id');
                $insert_arr['tracelog_membername'] = session('member_name');
                $insert_arr['tracelog_memberavatar'] = $member_info['member_avatar'];
                $insert_arr['tracelog_title'] = input('post.comment') ? input('post.comment') : lang('sns_sharegoods_title');
                $content_str = '';
                $content_str .= "<div class=\"fd-media\">
					<div class=\"goodsimg\"><a target=\"_blank\" href=\"" . url('Goods/index',['goods_id' => $goods_info['goods_id']]) . "\"><img src=\"" . goods_thumb($goods_info, 240) . "\" onload=\"javascript:ResizeImage(this,120,120);\" alt=\"{$goods_info['goods_name']}\"></a></div>
					<div class=\"goodsinfo\">
						<dl>
							<dt><a target=\"_blank\" href=\"" . url('Goods/index',['goods_id' => $goods_info['goods_id']]) . "\">" . $goods_info['goods_name'] . "</a></dt>
							<dd>" . lang('sns_sharegoods_price') . lang('ds_colon') . lang('currency') . $goods_info['goods_price'] . "</dd>
							<dd>" . lang('sns_sharegoods_freight') . lang('ds_colon') . lang('currency') . $goods_info['goods_freight'] . "</dd>
	                  		<dd dstype=\"collectbtn_{$goods_info['goods_id']}\"><a href=\"javascript:void(0);\" onclick=\"javascript:collect_goods(\'{$goods_info['goods_id']}\',\'succ\',\'collectbtn_{$goods_info['goods_id']}\');\">" . lang('sns_sharegoods_collect') . "</a></dd>
	                  	</dl>
	                  </div>
	             </div>";
                $insert_arr['tracelog_content'] = $content_str;
                $insert_arr['tracelog_addtime'] = time();
                $insert_arr['tracelog_state'] = '0';
                $insert_arr['tracelog_privacy'] = intval(input('post.gprivacy')) > 0 ? intval(input('post.gprivacy')) : 0;
                $insert_arr['tracelog_commentcount'] = 0;
                $insert_arr['tracelog_copycount'] = 0;
                $result = $snstracelog_model->addSnstracelog($insert_arr);
                //建立cookie
                if (cookie('weibonum') != null && intval(cookie('weibonum')) > 0) {
                    cookie('weibonum', intval(cookie('weibonum')) + 1, 2 * 3600); //保存2小时
                } else {
                    cookie('weibonum', 1, 2 * 3600); //保存2小时
                }
                //站外分享功能
                if (config('share_isuse') == 1) {
                    $snsbinding_model = model('snsbinding');
                    //查询该用户的绑定信息
                    $bind_list = $snsbinding_model->getUsableApp(session('member_id'));
                    //分享内容数组
                    $params = array();
                    $params['title'] = lang('sns_sharegoods_title');
                    $params['url'] = url('Goods/index',['goods_id' => $goods_info['goods_id']]);
                    $params['comment'] = $goods_info['goods_name'] . input('post.comment');
                    $params['images'] = goods_thumb($goods_info, 240);
                    //分享之qqweibo
                    if (!empty(input('post.checkapp_qqweibo')) && !empty(input('post.checkapp_qqweibo')) && $bind_list['qqweibo']['isbind'] == true) {
                        $snsbinding_model->addQQWeiboPic($bind_list['qqweibo'], $params);
                    }
                    //分享之sinaweibo
                    if (!empty(input('post.checkapp_sinaweibo')) && !empty(input('post.checkapp_sinaweibo')) && $bind_list['sinaweibo']['isbind'] == true) {
                        $snsbinding_model->addSinaWeiboUpload($bind_list['sinaweibo'], $params);
                    }
                }
                //输出js
                $js = "DialogManager.close('sharegoods');var countobj=$('[ds_type=\'sharecount_{$goods_info['goods_id']}\']');$(countobj).html(parseInt($(countobj).text())+1);";
                $url = '';
                if (input('param.irefresh')) {
                    $js .= "$('#friendtrace').lazyshow({url:\"" . HOME_SITE_URL . "/Membersnsindex/tracelist.html?curpage=1\",'iIntervalId':true});";
                } else {
                    $url = 'reload';
                }
                ds_show_dialog(lang('sns_share_succ'), $url, 'succ', $js);
            } else {
                ds_show_dialog(lang('sns_share_fail'), $url, 'error');
            }
        } else {
            //查询已购买商品信息
            $order_model = model('order');
            $condition = array();
            $condition['buyer_id'] = session('member_id');
            $ordergoods_list = $order_model->getOrdergoodsList($condition);
            unset($condition);
            $order_goodsid = array();
            if (!empty($ordergoods_list)) {
                foreach ($ordergoods_list as $v) {
                    $order_goodsid[] = $v['goods_id'];
                }
            }

            // 查询收藏商品
            $favorites_list = model('favorites')->getFavoritesList(array('member_id' => session('member_id'), 'fav_type' => 'goods'),'fav_id');
            $favorites_goodsid = array();
            if (!empty($favorites_list)) {
                foreach ($favorites_list as $v) {
                    $favorites_goodsid[] = $v['fav_id'];
                }
            }

            $goods_id = array_merge($order_goodsid, $favorites_goodsid);
            //查询商品信息
            $goods_model = model('goods');
            $condition = array();
            $condition['goods_id'] = array('in', $goods_id);
            $goods_list = $goods_model->getGoodsOnlineList($condition, 'goods_id,goods_name,goods_image,store_id');
            if (!empty($goods_list)) {
                foreach ($goods_list as $k => $v) {
                    if (in_array($v['goods_id'], $order_goodsid)) {
                        $goods_list[$k]['order'] = true;
                    }
                    if (in_array($v['goods_id'], $favorites_goodsid)) {
                        $goods_list[$k]['favorites'] = true;
                    }
                }
            }
            if (config('share_isuse') == 1) {
                $snsbinding_model = model('snsbinding');
                $app_arr = $snsbinding_model->getUsableApp(session('member_id'));
                $this->assign('app_arr', $app_arr);
            }
            $this->assign('goods_list', $goods_list);
            echo $this->fetch($this->template_dir . 'member_snssharegoods');exit;
        }
    }

    /**
     * 分享店铺
     */
    public function sharestore() {
        if (request()->isPost()) {


            $choosestoreid = intval(input('param.choosestoreid'));
            $validate_arr = array(
                'choosestoreid' => $choosestoreid,
                'comment' => input('param.comment'),
            );
            $rule = [
                ['choosestoreid', 'require|min:1', lang('sns_sharestore_choose') . '|' . lang('sns_sharestore_choose')],
                ['comment', 'require|length:0,140', lang('sns_comment_null') . '|' . lang('sns_content_beyond')],
            ];
            $obj_validate = new Validate();
            $validate_result = $obj_validate->check($validate_arr, $rule);
            if (!$validate_result) {
                ds_show_dialog($obj_validate->getError(), '', 'error');
            }

            //发帖数超过最大次数出现验证码
            if (intval(cookie('weibonum')) >= self::MAX_RECORDNUM) {
                if (!captcha_check(input('post.captcha'))) {
                    ds_show_dialog(lang('wrong_checkcode'), '', 'error');
                }
            }



            //查询会员信息
            $member_model = model('member');
            $member_info = $member_model->getMemberInfo(array('member_id' => session('member_id'), 'member_state' => 1));
            if (empty($member_info)) {
                ds_show_dialog(lang('sns_member_error'), '', 'error');
            }
            //查询店铺信息
            $store_model = model('store');
            $store_info = $store_model->getStoreInfoByID(input('post.choosestoreid'));
            if (empty($store_info)) {
                ds_show_dialog(lang('sns_store_error'), '', 'error');
            }
            $sharestore_model = model('snssharestore');
            //判断该商品是否已经分享过
            $sharestore_info = $sharestore_model->getSnssharestoreInfo(array('sharestore_memberid' => session('member_id'), 'sharestore_storeid' => "{$store_info['store_id']}"));
            $result = false;
            if (empty($sharestore_info)) {
                //添加分享商品信息
                $insert_arr = array();
                $insert_arr['sharestore_storeid'] = $store_info['store_id'];
                $insert_arr['sharestore_storename'] = $store_info['store_name'];
                $insert_arr['sharestore_memberid'] = session('member_id');
                $insert_arr['sharestore_membername'] = session('member_name');
                $insert_arr['sharestore_content'] = input('post.comment');
                $insert_arr['sharestore_addtime'] = time();
                $insert_arr['sharestore_privacy'] = intval(input('post.sprivacy')) > 0 ? intval(input('post.sprivacy')) : 0;
                $result = $sharestore_model->addSnssharestore($insert_arr);
                unset($insert_arr);
            } else {
                //更新分享商品信息
                $update_arr = array();
                $update_arr['sharestore_content'] = input('post.comment');
                $update_arr['sharestore_addtime'] = time();
                $update_arr['sharestore_privacy'] = intval(input('post.sprivacy')) > 0 ? intval(input('post.sprivacy')) : 0;
                $condition = array();
                $condition['sharestore_id'] = $sharestore_info['sharestore_id'];
                $result = $sharestore_model->editSnssharestore($update_arr, $condition);
                unset($update_arr);
            }
            if ($result) {
                //添加分享动态
                $snstracelog_model = model('snstracelog');
                $insert_arr = array();
                $insert_arr['tracelog_originalid'] = '0';
                $insert_arr['tracelog_originalmemberid'] = '0';
                $insert_arr['tracelog_memberid'] = session('member_id');
                $insert_arr['tracelog_membername'] = session('member_name');
                $insert_arr['tracelog_memberavatar'] = $member_info['member_avatar'];
                $insert_arr['tracelog_title'] = input('post.comment') ? input('post.comment') : lang('sns_sharestore_title');
                $content_str = '';
                $store_info['store_avatar'] = empty($store_info['store_avatar']) ? UPLOAD_SITE_URL . DS . ATTACH_COMMON . DS . config('default_store_avatar') : UPLOAD_SITE_URL . DS . ATTACH_STORE . DS . $store_info['store_avatar'];
                $store_info['store_url'] = url('Store/index',['store_id' => $store_info['store_id']]);
                $content_str .= "<div class=\"fd-media\">
					<div class=\"goodsimg\"><a target=\"_blank\" href=\"{$store_info['store_url']}\"><img src=\"{$store_info['store_avatar']}\" onload=\"javascript:ResizeImage(this,120,120);\" alt=\"{$store_info['store_name']}\"></a></div>
					<div class=\"goodsinfo\">
						<dl>
							<dt><a target=\"_blank\" href=\"{$store_info['store_url']}\">" . $store_info['store_name'] . "</a></dt>
	                  		<dd dstype=\"storecollectbtn_{$store_info['store_id']}\"><a href=\"javascript:void(0);\" onclick=\"javascript:collect_store(\'{$store_info['store_id']}\',\'succ\',\'storecollectbtn_{$store_info['store_id']}\');\">" . lang('sns_sharestore_collect') . "</a></dd>
	                  	</dl>
	                  </div>
	             </div>";
                $insert_arr['tracelog_content'] = $content_str;
                $insert_arr['tracelog_addtime'] = time();
                $insert_arr['tracelog_state'] = '0';
                $insert_arr['tracelog_privacy'] = intval(input('post.sprivacy')) > 0 ? intval(input('post.sprivacy')) : 0;
                $insert_arr['tracelog_commentcount'] = 0;
                $insert_arr['tracelog_copycount'] = 0;
                $result = $snstracelog_model->addSnstracelog($insert_arr);
                //建立cookie
                if (cookie('weibonum') != null && intval(cookie('weibonum')) > 0) {
                    cookie('weibonum', intval(cookie('weibonum')) + 1, 2 * 3600); //保存2小时
                } else {
                    cookie('weibonum', 1, 2 * 3600); //保存2小时
                }
                //站外分享功能
                if (config('share_isuse') == 1) {
                    $snsbinding_model = model('snsbinding');
                    //查询该用户的绑定信息
                    $bind_list = $snsbinding_model->getUsableApp(session('member_id'));
                    //分享内容数组
                    $params = array();
                    $params['title'] = lang('sns_sharestore_title');
                    $params['url'] = url('Store/index',['store_id' => $store_info['store_id']]);
                    $params['comment'] = $store_info['store_name'] . input('post.comment');
                    $params['images'] = empty($store_info['store_avatar']) ? UPLOAD_SITE_URL . DS . ATTACH_COMMON . DS . config('default_store_avatar') : UPLOAD_SITE_URL . DS . ATTACH_STORE . DS . $store_info['store_avatar'];
                    //分享之qqweibo
                    if (!empty(input('post.checkapp_qqweibo')) && !empty(input('post.checkapp_qqweibo')) && $bind_list['qqweibo']['isbind'] == true) {
                        $snsbinding_model->addQQWeiboPic($bind_list['qqweibo'], $params);
                    }
                    //分享之sinaweibo
                    if (!empty(input('post.checkapp_sinaweibo')) && !empty(input('post.checkapp_sinaweibo')) && $bind_list['sinaweibo']['isbind'] == true) {
                        $snsbinding_model->addSinaWeiboUpload($bind_list['sinaweibo'], $params);
                    }
                }
                //输出js
                $js = "DialogManager.close('sharestore');";
                $url = '';
                if (input('param.irefresh')) {
                    $js.="$('#friendtrace').lazyshow({url:\"" . HOME_SITE_URL . "/Membersnsindex/tracelist.html?curpage=1\",'iIntervalId':true});";
                } else {
                    $url = 'reload';
                }
                ds_show_dialog(lang('sns_share_succ'), $url, 'succ', $js);
            } else {
                ds_show_dialog(lang('sns_share_fail'), $url, 'error');
            }
        } else {
            //查询收藏店铺信息
            $favorites_model = model('favorites');
            $condition = array();
            $condition['member_id'] = session('member_id');
            $favorites_list = $favorites_model->getStoreFavoritesList($condition);
            unset($condition);
            $store_list = array();
            if (!empty($favorites_list)) {
                $store_id = array();
                foreach ($favorites_list as $v) {
                    $store_id[] = $v['fav_id'];
                }
                //查询商品信息
                $store_model = model('store');
                $condition = array();
                $condition['store_id'] = array('in', $store_id);
                $store_list = $store_model->getStoreOnlineList($condition);
            }

            if (config('share_isuse') == 1) {
                $snsbinding_model = model('snsbinding');
                $app_arr = $snsbinding_model->getUsableApp(session('member_id'));
                $this->assign('app_arr', $app_arr);
            }
            $this->assign('store_list', $store_list);
            echo $this->fetch($this->template_dir . 'member_snssharestore');
            exit;
        }
    }

    /**
     * 删除动态
     */
    public function deltrace() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        $snstracelog_model = model('snstracelog');
        //删除动态
        $condition = array();
        $condition['tracelog_id'] = "$id";
        $condition['tracelog_memberid'] = session('member_id');
        $result = $snstracelog_model->delSnstracelog($condition);
        if ($result) {
            //修改该动态的转帖信息
            $snstracelog_model->editSnstracelog(array('tracelog_originalstate' => '1'), array('tracelog_originalid' => "$id"));
            //删除对应的评论
            $snscomment_model = model('snscomment');
            $condition = array();
            $condition['snscomment_originalid'] = "$id";
            $condition['snscomment_originaltype'] = "0";
            $snscomment_model->delSnscomment($condition);
            if (input('param.type') == 'href') {
                ds_show_dialog(lang('ds_common_del_succ'), HOME_SITE_URL . '/Membersnshome/trace.html?mid=' . session('member_id'), 'succ');
            } else {
                $js = "location.reload();";
                ds_show_dialog(lang('ds_common_del_succ'), '', 'succ', $js);
            }
        } else {
            ds_show_dialog(lang('ds_common_del_fail'), '', 'error');
        }
    }

    /**
     * SNS动态列表
     */
    public function tracelist() {
        //查询关注以及好友列表
        $snsfriend_model = model('snsfriend');
        $friend_list = $snsfriend_model->getSnsfriendList(array('friend_frommid' => session('member_id')), '*', '', 'simple');
        $mutualfollowid_arr = array();
        $followid_arr = array();
        if (!empty($friend_list)) {
            foreach ($friend_list as $k => $v) {
                $followid_arr[] = $v['friend_tomid'];
                if ($v['friend_followstate'] == 2) {
                    $mutualfollowid_arr[] = $v['friend_tomid'];
                }
            }
        }
        $snstracelog_model = model('snstracelog');
        //条件
        $condition = array();
        $condition['allowshow'] = '1';
        $condition['allowshow_memberid'] = session('member_id');
        $condition['allowshow_followerin'] = "";
        if (!empty($followid_arr)) {
            $condition['allowshow_followerin'] = implode("','", $followid_arr);
        }
        $condition['allowshow_friendin'] = "";
        if (!empty($mutualfollowid_arr)) {
            $condition['allowshow_friendin'] = implode("','", $mutualfollowid_arr);
        }
        $condition['tracelog_state'] = "0";
        $count = $snstracelog_model->getSnstracelogCount($condition);
        //分页
        $delaypage = intval(input('param.delaypage')) > 0 ? intval(input('param.delaypage')) : 1; //本页延时加载的当前页数
        $lazy_arr = lazypage(10, $delaypage, $count, true, input('param.page'), 30,input('param.page')*30);
        //动态列表
        $condition['limit'] = $lazy_arr['limitstart'] . "," . $lazy_arr['delay_eachnum'];
        $tracelist = $snstracelog_model->getSnstracelogList($condition);
        
        
        if (!empty($tracelist)) {
            foreach ($tracelist as $k => $v) {
                if ($v['tracelog_title']) {
                    $v['tracelog_title'] = str_replace("%siteurl%", HOME_SITE_URL . DS, $v['tracelog_title']);
                    $v['tracelog_title_forward'] = '|| @' . $v['tracelog_membername'] . lang('ds_colon') . preg_replace("/<a(.*?)href=\"(.*?)\"(.*?)>@(.*?)<\/a>([\s|:|：]|$)/is", '@${4}${5}', $v['tracelog_title']);
                }
                if (!empty($v['tracelog_content'])) {
                    //替换内容中的siteurl
                    $v['tracelog_content'] = str_replace("%siteurl%", HOME_SITE_URL . DS, $v['tracelog_content']);
                }
                $tracelist[$k] = $v;
            }
        }
        $this->assign('hasmore', $lazy_arr['hasmore']);
        $this->assign('tracelist', $tracelist);
        $this->assign('show_page', $page->show());
        $this->assign('type', 'index');
        echo $this->fetch($this->template_dir . 'member_snstracelist');exit;
    }

    /**
     * 编辑分享商品的可见权限(主人登录后操作)
     */
    public function editprivacy() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        $sharegoods_model = model('snssharegoods');
        $condition = array();
        $condition['sharegoods_id'] = "$id";
        $condition['sharegoods_memberid'] = session('member_id');
        $privacy = in_array(input('param.privacy'), array(0, 1, 2)) ? input('param.privacy') : 0;
        $result = $sharegoods_model->editSnssharegoods(array('sharegoods_privacy' => "$privacy"), $condition);
        if ($result) {
            $privacy_item = $privacy + 1;
            $js = "var obj = $(\"#recordone_{$id}\").find(\"[ds_type='privacytab']\"); $(obj).find('span').removeClass('selected');$(obj).find('li:nth-child(" . $privacy_item . ")').find('span').addClass('selected');";
            ds_show_dialog(lang('sns_setting_succ'), '', 'succ', $js);
        } else {
            ds_show_dialog(lang('sns_setting_fail'), '', 'error');
        }
    }

    /**
     * 删除分享和喜欢商品
     */
    public function delgoods() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        $sharegoods_model = model('snssharegoods');
        //查询分享和喜欢商品信息
        $condition = array();
        $condition['sharegoods_id'] = "$id";
        $condition['sharegoods_memberid'] = session('member_id');
        if (input('param.type') == 'like') {//删除喜欢
            $condition['sharegoods_islike'] = "1";
        } elseif (input('param.type') == 'share') {
            $condition['sharegoods_isshare'] = "1";
        }
        $sharegoods_info = $sharegoods_model->getSnssharegoodsInfo($condition);
        if (empty($sharegoods_info)) {
            ds_show_dialog(lang('ds_common_del_fail'), '', 'error');
        }
        unset($condition);
        $update_arr = array();
        if (input('param.type') == 'like') {//删除喜欢
            $update_arr['sharegoods_islike'] = "0";
        } elseif (input('param.type') == 'share') {
            $update_arr['sharegoods_isshare'] = "0";
        }
        $result = $sharegoods_model->editSnssharegoods($update_arr, array('sharegoods_id' => $sharegoods_info['sharegoods_id']));
        if ($result) {
            //更新SNS商品喜欢次数
            if (input('param.type') == 'like') {
                $snsgoods_model = model('snsgoods');
                $snsgoods_info = $snsgoods_model->getSnsgoodsInfo(array('snsgoods_goodsid' => "{$sharegoods_info['sharegoods_goodsid']}"));
                if (!empty($snsgoods_info)) {
                    $update_arr = array();
                    $update_arr['snsgoods_likenum'] = (intval($snsgoods_info['snsgoods_likenum']) - 1) > 0 ? (intval($snsgoods_info['snsgoods_likenum']) - 1) : 0;
                    $likemember_arr = array();
                    if (!empty($snsgoods_info['snsgoods_likemember'])) {
                        $likemember_arr = explode(',', $snsgoods_info['snsgoods_likemember']);
                        unset($likemember_arr[array_search(session('member_id'), $likemember_arr)]);
                    }
                    $update_arr['snsgoods_likemember'] = implode(',', $likemember_arr);
                    $snsgoods_model->editSnsgoods($update_arr, array('snsgoods_goodsid' => "{$snsgoods_info['snsgoods_goodsid']}"));
                }
            }
            $js = "location.reload();";
            ds_show_dialog(lang('ds_common_del_succ'), '', 'succ', $js);
        } else {
            ds_show_dialog(lang('ds_common_del_fail'), '', 'error');
        }
    }

    /**
     * 删除分享店铺
     */
    public function delstore() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        $sharestore_model = model('snssharestore');
        //删除分享店铺信息
        $condition = array();
        $condition['sharestore_id'] = $id;
        $condition['sharestore_memberid'] = session('member_id');
        $result = $sharestore_model->delSnssharestore($condition);
        if ($result) {
            $js = "location.reload();";
            ds_show_dialog(lang('ds_common_del_succ'), '', 'succ', $js);
        } else {
            ds_show_dialog(lang('ds_common_del_fail'), '', 'error');
        }
    }

    /**
     * 编辑分享店铺的可见权限(主人登录后操作)
     */
    public function storeprivacy() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        $sharestore_model = model('snssharestore');
        $condition = array();
        $condition['sharestore_id'] = $id;
        $condition['sharestore_memberid'] = session('member_id');
        $privacy = in_array(input('param.privacy'), array(0, 1, 2)) ? input('param.privacy') : 0;
        $result = $sharestore_model->editSnssharestore(array('sharestore_privacy' => "$privacy"), $condition);
        if ($result) {
            $privacy_item = $privacy + 1;
            $js = "var obj = $(\"#recordone_{$id}\").find(\"[ds_type='privacytab']\"); $(obj).find('span').removeClass('selected');$(obj).find('li:nth-child(" . $privacy_item . ")').find('span').addClass('selected');";
            ds_show_dialog(lang('sns_setting_succ'), '', 'succ', $js);
        } else {
            ds_show_dialog(lang('sns_setting_fail'), '', 'error');
        }
    }

    /**
     * 添加评论(访客登录后操作)
     */
    public function addcomment() {
        $originalid = intval(input('param.originalid'));
        if ($originalid <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }

        $originaltype = intval(input('param.originaltype')) > 0 ? intval(input('param.originaltype')) : 0;

        $validate_arr = array(
            'commentcontent' => input('param.commentcontent'),
        );
        $rule = [
            ['commentcontent', 'require|length:0,140', lang('sns_comment_null') . '|' . lang('sns_content_beyond')],
        ];

        $obj_validate = new Validate();
        $validate_result = $obj_validate->check($validate_arr, $rule);
        if (!$validate_result) {
            ds_show_dialog($obj_validate->getError(), '', 'error');
        }

        //评论数超过最大次数出现验证码
        if (intval(cookie('commentnum')) >= self::MAX_RECORDNUM) {
            if (!captcha_check(input('post.captcha'))) {
                //验证失败
                ds_show_dialog(lang('wrong_null'), '', 'error');
            }
        }


        //查询会员信息
        $member_model = model('member');
        $member_info = $member_model->getMemberInfo(array('member_id' => session('member_id'), 'member_state' => 1));
        if (empty($member_info)) {
            ds_show_dialog(lang('sns_member_error'), '', 'error');
        }
        $owner_id = 0;
        if ($originaltype == 1) {
            //查询分享和喜欢商品信息
            $sharegoods_model = model('snssharegoods');
            $sharegoods_info = $sharegoods_model->getSnssharegoodsInfo(array('sharegoods_id' => "{$originalid}"));
            if (empty($sharegoods_info)) {
                ds_show_dialog(lang('sns_comment_fail'), '', 'error');
            }
            $owner_id = $sharegoods_info['sharegoods_memberid'];
        } else {
            //查询原帖信息
            $snstracelog_model = model('snstracelog');
            $tracelog_info = $snstracelog_model->getOneSnstracelog(array('tracelog_id' => "{$originalid}", 'tracelog_state' => '0'));
            if (empty($tracelog_info)) {
                ds_show_dialog(lang('sns_comment_fail'), '', 'error');
            }
            $owner_id = $tracelog_info['tracelog_memberid'];
        }
        $snscomment_model = model('snscomment');
        $insert_arr = array();
        $insert_arr['snscomment_memberid'] = session('member_id');
        $insert_arr['snscomment_membername'] = session('member_name');
        $insert_arr['snscomment_memberavatar'] = $member_info['member_avatar'];
        $insert_arr['snscomment_originalid'] = $originalid;
        $insert_arr['snscomment_originaltype'] = $originaltype;
        $insert_arr['snscomment_content'] = input('param.commentcontent');
        $insert_arr['snscomment_addtime'] = time();
        $insert_arr['snscomment_ip'] = request()->ip();
        $insert_arr['snscomment_state'] = '0'; //正常
        $result = $snscomment_model->addSnscomment($insert_arr);
        if ($result) {
            if ($originaltype == 1) {
                //更新商品的评论数
                $sharegoods_model->where('sharegoods_id',$originalid)->setInc('sharegoods_commentcount');
            } else {
                //更新动态统计信息
                $snstracelog_model->where('tracelog_id',$originalid)->setInc('tracelog_commentcount');
                if (intval($tracelog_info['tracelog_originalid']) == 0) {
                    $snstracelog_model->where('tracelog_id',$originalid)->setInc('tracelog_orgcommentcount');
                }
                //更新所有转帖的原帖评论次数
                if (intval($tracelog_info['tracelog_originalid']) == 0) {
                    $snstracelog_model->editSnstracelog(array('tracelog_orgcommentcount' => $tracelog_info['tracelog_orgcommentcount'] + 1), array('tracelog_originalid' => "$originalid"));
                }
            }
            //建立cookie
            if (cookie('commentnum') != null && intval(cookie('commentnum')) > 0) {
                cookie('commentnum', intval(cookie('commentnum')) + 1, 2 * 3600); //保存2小时
            } else {
                cookie('commentnum', 1, 2 * 3600); //保存2小时
            }
            $js = "$(\"#content_comment{$originalid}\").val('');";
            if (input('param.showtype') == 1) {
                $js .="$(\"#tracereply_{$originalid}\").load('" . HOME_SITE_URL . "/Membersnshome/commenttop.html?mid={$owner_id}&id={$originalid}&type={$originaltype}');";
            } else {
                $js .="$(\"#tracereply_{$originalid}\").load('" . HOME_SITE_URL . "/Membersnshome/commentlist.html?mid={$owner_id}&id={$originalid}&type={$originaltype}');";
            }
            ds_show_dialog(lang('sns_comment_succ'), '', 'succ', $js);
        }
    }

    /**
     * 删除评论(访客登录后操作)
     */
    public function delcomment() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        $snscomment_model = model('snscomment');
        //查询评论信息
        $snscomment_info = $snscomment_model->getOneSnscomment(array('snscomment_id' => "$id", 'snscomment_memberid' => session('member_id')));
        if (empty($snscomment_info)) {
            ds_show_dialog(lang('sns_comment_recorderror'), '', 'error');
        }
        //删除评论
        $condition = array();
        $condition['snscomment_id'] = "$id";
        $result = $snscomment_model->delSnscomment($condition);
        if ($result) {
            if ($snscomment_info['snscomment_originaltype'] == 1) {
                //更新商品评论数
                $sharegoods_model = model('snssharegoods');
                $sharegoods_model->where('sharegoods_id',$snscomment_info['snscomment_originalid'])->setDec('sharegoods_commentcount');
            } else {
                //更新动态统计信息
                $snstracelog_model = model('snstracelog');
                $snstracelog_model->where('tracelog_id',$snscomment_info['snscomment_originalid'])->setDec('tracelog_commentcount');
            }
            $js ="$('.comment-list [ds_type=\"commentrow_{$id}\"]').remove();";
            ds_show_dialog(lang('ds_common_del_succ'), '', 'succ', $js);
        } else {
            ds_show_dialog(lang('ds_common_del_fail'), '', 'error');
        }
    }

    /**
     * 喜欢商品(访客登录后操作)
     */
    public function editlike() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('sns_likegoods_choose'), '', 'error');
        }

        //查询会员信息
        $member_model = model('member');
        $member_info = $member_model->getMemberInfo(array('member_id' => session('member_id'), 'member_state' => 1));
        if (empty($member_info)) {
            ds_show_dialog(lang('sns_member_error'), '', 'error');
        }
        //查询商品信息
        $goods_model = model('goods');
        $goods_info = $goods_model->getGoodsOnlineInfoForShare($id);
        if (empty($goods_info)) {
            ds_show_dialog(lang('sns_goods_error'), '', 'error');
        }
        $sharegoods_model = model('snssharegoods');
        //判断该商品是否已经存在分享记录
        $sharegoods_info = $sharegoods_model->getSnssharegoodsInfo(array('sharegoods_memberid' => session('member_id'), 'sharegoods_goodsid' => "{$goods_info['goods_id']}"));
        if (!empty($sharegoods_info) && $sharegoods_info['sharegoods_islike'] == 1) {
            ds_show_dialog(lang('sns_likegoods_exist'), '', 'error');
        }
        if (empty($sharegoods_info)) {
            //添加分享商品信息
            $insert_arr = array();
            $insert_arr['sharegoods_goodsid'] = $goods_info['goods_id'];
            $insert_arr['sharegoods_memberid'] = session('member_id');
            $insert_arr['sharegoods_membername'] = session('member_name');
            $insert_arr['sharegoods_content'] = '';
            $insert_arr['sharegoods_likeaddtime'] = time();
            $insert_arr['sharegoods_privacy'] = 0;
            $insert_arr['sharegoods_commentcount'] = 0;
            $insert_arr['sharegoods_islike'] = 1;
            $result = $sharegoods_model->addSnssharegoods($insert_arr);
            unset($insert_arr);
        } else {
            //更新分享商品信息
            $update_arr = array();
            $update_arr['sharegoods_likeaddtime'] = time();
            $update_arr['sharegoods_islike'] = 1;
            $result = $sharegoods_model->editSnssharegoods($update_arr, array('sharegoods_id' => $sharegoods_info['sharegoods_id']));
            unset($update_arr);
        }
        if ($result) {
            //商品缓存数据更新
            //生成缓存的键值
            $hash_key = $goods_info['goods_id'];
            //先查找$hash_key缓存
            if ($_cache = rcache($hash_key, 'product')) {
                $_cache['likenum'] = intval($_cache['likenum']) + 1;
                //缓存商品信息
                wcache($hash_key, $_cache, 'product');
            }
            //更新SNS商品表信息
            $snsgoods_model = model('snsgoods');
            $snsgoods_info = $snsgoods_model->getSnsgoodsInfo(array('snsgoods_goodsid' => "{$goods_info['goods_id']}"));
            if (empty($snsgoods_info)) {
                //添加SNS商品
                $insert_arr = array();
                $insert_arr['snsgoods_goodsid'] = $goods_info['goods_id'];
                $insert_arr['snsgoods_goodsname'] = $goods_info['goods_name'];
                $insert_arr['snsgoods_goodsimage'] = $goods_info['goods_image'];
                $insert_arr['snsgoods_goodsprice'] = $goods_info['goods_price'];
                $insert_arr['snsgoods_storeid'] = $goods_info['store_id'];
                $insert_arr['snsgoods_storename'] = $goods_info['store_name'];
                $insert_arr['snsgoods_addtime'] = time();
                $insert_arr['snsgoods_likenum'] = 1;
                $insert_arr['snsgoods_likemember'] = session('member_id');
                $insert_arr['snsgoods_sharenum'] = 0;
                $snsgoods_model->addSnsgoods($insert_arr);
                unset($insert_arr);
            } else {
                //更新SNS商品
                $update_arr = array();
                $update_arr['snsgoods_likenum'] = intval($snsgoods_info['snsgoods_likenum']) + 1;
                $likemember_arr = array();
                if (!empty($snsgoods_info['snsgoods_likemember'])) {
                    $likemember_arr = explode(',', $snsgoods_info['snsgoods_likemember']);
                }
                $likemember_arr[] = session('member_id');
                $update_arr['snsgoods_likemember'] = implode(',', $likemember_arr);
                $snsgoods_model->editSnsgoods($update_arr, array('snsgoods_goodsid' => "{$goods_info['goods_id']}"));
            }
            //添加喜欢动态
            $snstracelog_model = model('snstracelog');
            $insert_arr = array();
            $insert_arr['tracelog_originalid'] = '0';
            $insert_arr['tracelog_originalmemberid'] = '0';
            $insert_arr['tracelog_memberid'] = session('member_id');
            $insert_arr['tracelog_membername'] = session('member_name');
            $insert_arr['tracelog_memberavatar'] = $member_info['member_avatar'];
            $insert_arr['tracelog_title'] = lang('sns_likegoods_title');
            $content_str = '';
            $content_str .= "<div class=\"fd-media\">
				<div class=\"goodsimg\"><a target=\"_blank\" href=\"" . url('Goods/index', ['goods_id' => $goods_info['goods_id']]) . "\"><img src=\"" . goods_thumb($goods_info, 240) . "\" onload=\"javascript:ResizeImage(this,120,120);\" alt=\"{$goods_info['goods_name']}\"></a></div>
				<div class=\"goodsinfo\">
					<dl>
						<dt><a target=\"_blank\" href=\"" . url('Goods/index', ['goods_id' => $goods_info['goods_id']]) . "\">" . $goods_info['goods_name'] . "</a></dt>
						<dd>" . lang('sns_sharegoods_price') . lang('ds_colon') . lang('currency') . $goods_info['goods_price'] . "</dd>
						<dd>" . lang('sns_sharegoods_freight') . lang('ds_colon') . lang('currency') . $goods_info['goods_freight'] . "</dd>
                  		<dd dstype=\"collectbtn_{$goods_info['goods_id']}\"><a href=\"javascript:void(0);\" onclick=\"javascript:collect_goods(\'{$goods_info['goods_id']}\',\'succ\',\'collectbtn_{$goods_info['goods_id']}\');\">" . lang('sns_sharegoods_collect') . "</a>&nbsp;&nbsp;(" . $goods_info['goods_collect'] . lang('sns_collecttip') . ")</dd>
                  	</dl>
                  </div>
             </div>";
            $insert_arr['tracelog_content'] = $content_str;
            $insert_arr['tracelog_addtime'] = time();
            $insert_arr['tracelog_state'] = '0';
            $insert_arr['tracelog_privacy'] = 0;
            $insert_arr['tracelog_commentcount'] = 0;
            $insert_arr['tracelog_copycount'] = 0;
            $result = $snstracelog_model->addSnstracelog($insert_arr);
            $js = "var obj = $(\"#likestat_{$goods_info['goods_id']}\"); $(\"#likestat_{$goods_info['goods_id']}\").find('i').addClass('noaction');$(obj).find('a').addClass('noaction'); var countobj=$('[ds_type=\'likecount_{$goods_info['goods_id']}\']');$(countobj).html(parseInt($(countobj).text())+1);";
            ds_show_dialog(lang('ds_common_op_succ'), '', 'succ', $js);
        } else {
            ds_show_dialog(lang('ds_common_op_fail'), '', 'error');
        }
    }

    /**
     * 添加转发
     */
    public function addforward() {
        $originalid = intval(input('param.originalid'));
        $validate_arr = array(
            'originalid' => $originalid,
            'forwardcontent' => input('param.forwardcontent'),
        );
        $rule = [
            ['originalid', 'require|min:1', lang('sns_forward_fail') . '|' . lang('sns_forward_fail')],
            ['forwardcontent', 'require|length:0,140', lang('sns_comment_null') . '|' . lang('sns_content_beyond')],
        ];
        $obj_validate = new Validate();
        $validate_result = $obj_validate->check($validate_arr, $rule);
        if (!$validate_result) {
            ds_show_dialog($obj_validate->getError(), '', 'error');
        }

        //发帖数超过最大次数出现验证码
        if (intval(cookie('forwardnum')) >= self::MAX_RECORDNUM) {
            if (!captcha_check(input('post.captcha'))) {
                ds_show_dialog(lang('wrong_checkcode'), '', 'error');
            }
        }


        //查询会员信息
        $member_model = model('member');
        $member_info = $member_model->getMemberInfo(array('member_id' => session('member_id'), 'member_state' => 1));
        if (empty($member_info)) {
            ds_show_dialog(lang('sns_member_error'), '', 'error');
        }
        //查询原帖信息
        $snstracelog_model = model('snstracelog');
        $tracelog_info = $snstracelog_model->getOneSnstracelog(array('tracelog_id' => "{$originalid}", 'tracelog_state' => "0"));
        if (empty($tracelog_info)) {
            ds_show_dialog(lang('sns_forward_fail'), '', 'error');
        }
        $insert_arr = array();
        $insert_arr['tracelog_originalid'] = $tracelog_info['tracelog_originalid'] > 0 ? $tracelog_info['tracelog_originalid'] : $originalid; //如果被转发的帖子为原帖的话，那么为原帖ID；如果被转发的帖子为转帖的话，那么为该转帖的原帖ID（即最初始帖子ID）
        $insert_arr['tracelog_originalmemberid'] = $tracelog_info['tracelog_originalid'] > 0 ? $tracelog_info['tracelog_originalmemberid'] : $tracelog_info['tracelog_memberid'];
        $insert_arr['tracelog_memberid'] = session('member_id');
        $insert_arr['tracelog_membername'] = session('member_name');
        $insert_arr['tracelog_memberavatar'] = $member_info['member_avatar'];
        $insert_arr['tracelog_title'] = input('post.forwardcontent') ? input('post.forwardcontent') : lang('sns_forward');
        if ($tracelog_info['tracelog_originalid'] > 0 || $tracelog_info['tracelog_from'] != 1) {
            $insert_arr['tracelog_content'] = addslashes($tracelog_info['tracelog_content']);
        } else {
            $content_str = "<div class=\"title\"><a href=\"%siteurl%/Membersnshome/index.html?mid={$tracelog_info['tracelog_memberid']}\" target=\"_blank\" class=\"uname\">{$tracelog_info['tracelog_membername']}</a>";
            $content_str .= lang('ds_colon') . "{$tracelog_info['tracelog_title']}</div>";
            $content_str .=addslashes($tracelog_info['tracelog_content']);
            $insert_arr['tracelog_content'] = $content_str;
        }
        $insert_arr['tracelog_addtime'] = time();
        $insert_arr['tracelog_state'] = '0';
        if ($tracelog_info['tracelog_privacy'] > 0) {
            $insert_arr['tracelog_privacy'] = 2; //因为动态可见权限跟转帖功能，本身就是矛盾的，为了防止可见度无法控制，所以如果原帖不为所有人可见，那么转帖的动态权限就为仅自己可见，否则为所有人可见
        } else {
            $insert_arr['tracelog_privacy'] = 0;
        }
        $insert_arr['tracelog_commentcount'] = 0;
        $insert_arr['tracelog_copycount'] = 0;
        $insert_arr['tracelog_orgcommentcount'] = $tracelog_info['tracelog_orgcommentcount'];
        $insert_arr['tracelog_orgcopycount'] = $tracelog_info['tracelog_orgcopycount'];
        $result = $snstracelog_model->addSnstracelog($insert_arr);
        if ($result) {
            //更新动态转发次数
            $update_arr = array();
            $update_arr['tracelog_copycount'] = array('exp' => 'tracelog_copycount+1');
            $update_arr['tracelog_orgcopycount'] = array('exp' => 'tracelog_orgcopycount+1');
            $condition = array();
            //原始贴和被转帖都增加转帖次数
            if ($tracelog_info['tracelog_originalid'] > 0) {
                $condition['tracelog_id'] = array('in',"{$tracelog_info['tracelog_originalid']}','{$originalid}");
            } else {
                $condition['tracelog_id'] = "$originalid";
            }
            $snstracelog_model->where($condition)->update($update_arr);
            unset($condition);
            //更新所有转帖的原帖转发次数
            $condition = array();
            //原始贴和被转帖都增加转帖次数
            if ($tracelog_info['tracelog_originalid'] > 0) {
                $condition['tracelog_originalid'] = "{$tracelog_info['tracelog_originalid']}";
            } else {
                $condition['tracelog_originalid'] = "$originalid";
            }
            $snstracelog_model->editSnstracelog(array('tracelog_orgcopycount' => $tracelog_info['tracelog_orgcopycount'] + 1), $condition);
            if (input('param.irefresh')) {
                //建立cookie
                if (cookie('forwardnum') != null && intval(cookie('forwardnum')) > 0) {
                    cookie('forwardnum', intval(cookie('forwardnum')) + 1, 2 * 3600); //保存2小时
                } else {
                    cookie('forwardnum', 1, 2 * 3600); //保存2小时
                }
                if (input('param.type') == 'home') {
                    $js = "$('#friendtrace').lazyshow({url:\"" . HOME_SITE_URL . "/Membersnshome/tracelist.html?mid={$tracelog_info['tracelog_memberid']}&curpage=1\",'iIntervalId':true});";
                } else if (input('param.type') == 'snshome') {
                    $js = "$('#forward_" . $originalid . "').hide();$('#friendtrace').lazyshow({url:\"" . HOME_SITE_URL . "/Membersnshome/tracelist.html?mid={$tracelog_info['tracelog_memberid']}&curpage=1\",'iIntervalId':true});";
                } else {
                    $js = "$('#friendtrace').lazyshow({url:\"" . HOME_SITE_URL . "/Membersnsindex/tracelist.html?curpage=1\",'iIntervalId':true});";
                }
                ds_show_dialog(lang('sns_forward_succ'), '', 'succ', $js);
            } else {
                ds_show_dialog(lang('sns_forward_succ'), '', 'succ');
            }
        } else {
            ds_show_dialog(lang('sns_forward_fail'), '', 'error');
        }
    }

    /**
     * 商品收藏页面和商品详细页面分享商品
     */
    public function sharegoods_one() {
        $gid = intval(input('param.gid'));
        if ($gid <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        if (input('param.dialog')) {
            $js = "CUR_DIALOG = ajax_form('sharegoods', '" . lang('sns_sharegoods_tofriend') . "', '" . HOME_SITE_URL . "/Membersnsindex/sharegoods_one.html?gid={$gid}', 480);";
            ds_show_dialog('', '', 'js', $js);
        }
        //查询商品信息
        $goods_info = model('goods')->getGoodsOnlineInfoForShare($gid);

        //判断系统是否开启站外分享功能
        if (config('share_isuse') == 1) {
            //站外分享接口
            $snsbinding_model = model('snsbinding');
            $app_arr = $snsbinding_model->getUsableApp(session('member_id'));
            $this->assign('app_arr', $app_arr);
        }
        $this->assign('goods_info', $goods_info);
        echo $this->fetch($this->template_dir . 'member_snssharegoods_one');exit;
    }

    /**
     * 店铺收藏页面分享店铺
     */
    public function sharestore_one() {
        $sid = intval(input('param.sid'));
        if ($sid <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        if (input('param.dialog')) {
            $js = "ajax_form('sharestore', '" . lang('sns_sharestore') . "', '" . HOME_SITE_URL . "/Membersnsindex/sharestore_one.html?sid={$sid}', 480);";
            ds_show_dialog('', '', 'js', $js);
        }
        //查询店铺信息
        $store_model = model('store');
        $store_info = $store_model->getStoreInfoByID($sid);
        if (empty($store_info) || $store_info['store_state'] == 0) {
            ds_show_dialog(lang('sns_sharestore_storeerror'), '', 'error');
        }
        $store_info['store_url'] = url('Store/index',['store_id' => $store_info['store_id']]);
        //判断系统是否开启站外分享功能
        if (config('share_isuse') == 1) {
            //站外分享接口
            $snsbinding_model = model('snsbinding');
            $app_arr = $snsbinding_model->getUsableApp(session('member_id'));
            $this->assign('app_arr', $app_arr);
        }
        $this->assign('store_info', $store_info);
        echo $this->fetch($this->template_dir . 'member_snssharestore_one');exit;
    }

}
