<?php

namespace app\home\controller;

use think\Lang;
use think\Validate;

class Sellervoucher extends BaseSeller {

    const SECONDS_OF_30DAY = 2592000;

    private $applystate_arr;
    private $quotastate_arr;
    private $templatestate_arr;

    public function _initialize() {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH . 'home/lang/zh-cn/sellervoucher.lang.php');
        if (config('voucher_allow') != 1) {
            $this->error(lang('voucher_unavailable'), 'seller/index');
        }
        //申请记录状态
        $this->applystate_arr = array(
            'new' => array(1, lang('voucher_applystate_new')), 'verify' => array(2, lang('voucher_applystate_verify')),
            'cancel' => array(3, lang('voucher_applystate_cancel'))
        );
        //套餐状态
        $this->quotastate_arr = array(
            'activity' => array(1, lang('voucher_quotastate_activity')),
            'cancel' => array(2, lang('voucher_quotastate_cancel')),
            'expire' => array(3, lang('voucher_quotastate_expire'))
        );
        //代金券模板状态
        $this->templatestate_arr = array(
            'usable' => array(1, lang('voucher_templatestate_usable')),
            'disabled' => array(2, lang('voucher_templatestate_disabled'))
        );
        $this->assign('applystate_arr', $this->applystate_arr);
        $this->assign('quotastate_arr', $this->quotastate_arr);
        $this->assign('templatestate_arr', $this->templatestate_arr);
    }

    public function templatelist() {
        //检查过期的代金券模板状态设为失效
        $this->check_voucher_template_expire();
        $voucher_model = model('voucher');

        if (check_platform_store()) {
            $this->assign('isPlatformStore', true);
        } else {
            //查询是否存在可用套餐
            $current_quota = $voucher_model->getVoucherquotaCurrent(session('store_id'));
            $this->assign('current_quota', $current_quota);
        }
        //查询列表
        $param = array();
        $param['vouchertemplate_store_id'] = session('store_id');
        if (trim(input('param.txt_keyword'))) {
            $param['vouchertemplate_title'] = array('like', '%' . trim(input('param.txt_keyword')) . '%');
        }
        $select_state = intval(input('param.select_state'));
        if ($select_state) {
            $param['vouchertemplate_state'] = $select_state;
        }
        if (input('param.txt_startdate')) {
            $param['vouchertemplate_enddate'] = array('egt', strtotime(input('param.txt_startdate')));
        }
        if (input('param.txt_enddate')) {
            $param['vouchertemplate_startdate'] = array('elt', strtotime(input('param.txt_enddate')));
        }

        $vouchertemplate_list = db('vouchertemplate')->where($param)->order('vouchertemplate_id desc')->paginate(10, false, ['query' => request()->param()]);
        $this->assign('show_page', $vouchertemplate_list->render());

        $vouchertemplate_list = $vouchertemplate_list->items();
        foreach ($vouchertemplate_list as $key => $val) {

            if (!$val['vouchertemplate_customimg'] || !file_exists(BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS . session('store_id') . DS . $val['vouchertemplate_customimg'])) {
                $vouchertemplate_list[$key]['vouchertemplate_customimg'] = UPLOAD_SITE_URL . DS . default_goodsimage(60);
            } else {
                $vouchertemplate_list[$key]['vouchertemplate_customimg'] = UPLOAD_SITE_URL . DS . ATTACH_VOUCHER . DS . session('store_id') . DS . $val['vouchertemplate_customimg'];
            }
        }

        $this->setSellerCurMenu('Sellervoucher');
        $this->setSellerCurItem('templatelist');
        $this->assign('vouchertemplate_list', $vouchertemplate_list);

        return $this->fetch($this->template_dir . 'index');
    }

    /**
     * 购买套餐
     */
    public function quotaadd() {
        if (request()->isPost()) {
            $quota_quantity = intval(input('post.quota_quantity'));
            if ($quota_quantity <= 0 || $quota_quantity > 12) {
                ds_show_dialog(lang('voucher_apply_num_error'));
            }
            //获取当前价格
            $current_price = intval(config('promotion_voucher_price'));

            $voucher_model = model('voucher');

            //获取该用户已有套餐
            $current_quota = $voucher_model->getVoucherquotaCurrent(session('store_id'));
            $quota_add_time = 86400 * 30 * $quota_quantity;
            if (empty($current_quota)) {
                //生成套餐
                $param = array();
                $param['voucherquota_memberid'] = session('member_id');
                $param['voucherquota_membername'] = session('member_name');
                $param['voucherquota_storeid'] = session('store_id');
                $param['voucherquota_storename'] = session('store_name');
                $param['voucherquota_starttime'] = TIMESTAMP;
                $param['voucherquota_endtime'] = TIMESTAMP + $quota_add_time;
                $param['voucherquota_state'] = 1;
                $reault = db('voucherquota')->insert($param);
            } else {
                $param = array();
                $param['voucherquota_endtime'] = array('exp', 'voucherquota_endtime + ' . $quota_add_time);
                $reault = db('voucherquota')->where(array('voucherquota_id' => $current_quota['voucherquota_id']))->update($param);
            }

            //记录店铺费用
            $this->recordStorecost($current_price * $quota_quantity, '购买代金券套餐');

            $this->recordSellerlog('购买' . $quota_quantity . '份代金券套餐，单价' . $current_price . lang('ds_yuan'));

            if ($reault) {
                ds_show_dialog(lang('voucher_apply_buy_succ'), url('Sellervoucher/templatelist'), 'succ');
            } else {
                ds_show_dialog(lang('ds_common_op_fail'), url('Sellervoucher/templatelist'));
            }
        } else {
            //输出导航
            $this->setSellerCurMenu('Sellervoucher');
            $this->setSellerCurItem('quotaadd');
            return $this->fetch($this->template_dir . 'quota_add');
        }
    }

    /*
     * 代金券模版添加
     */

    public function templateadd() {
        $voucher_model = model('voucher');
        $isPlatformStore = check_platform_store();
        $this->assign('isPlatformStore', $isPlatformStore);
        $quotainfo = array();
        if (!$isPlatformStore) {
            //查询当前可以使用的套餐
            $quotainfo = $voucher_model->getVoucherquotaCurrent(session('store_id'));
            if (empty($quotainfo)) {
                $this->error(lang('voucher_template_quotanull'), 'Sellervoucher/quotaadd');
            }

            //查询该套餐下代金券模板列表
            $count = db('vouchertemplate')->where(array('vouchertemplate_quotaid' => $quotainfo['voucherquota_id'], 'vouchertemplate_state' => $this->templatestate_arr['usable'][0]))->count();
            if ($count >= config('voucher_storetimes_limit')) {
                $message = sprintf(lang('voucher_template_noresidual'), config('voucher_storetimes_limit'));
                $this->error($message, 'Sellervoucher/templatelist');
            }
        }

        //查询面额列表
        $pricelist = db('voucherprice')->order('voucherprice asc')->select();
        if (empty($pricelist)) {
            $this->error(lang('voucher_template_pricelisterror'), 'Sellervoucher/templatelist');
        }
        if (request()->isPost()) {
            //验证提交的内容面额不能大于限额
            $obj_validate = new Validate();
            $data = [
                'txt_template_title' => input('post.txt_template_title'),
                'txt_template_total' => input('post.txt_template_total'),
                'select_template_price' => input('post.select_template_price'),
                'txt_template_limit' => input('post.txt_template_limit'),
                'txt_template_describe' => input('post.txt_template_describe'),
            ];

            $rule = [
                ['txt_template_title', 'require|length:1,50', lang('voucher_template_title_error')],
                ['txt_template_total', 'require|number', lang('voucher_template_total_error')],
                ['select_template_price', 'require|number', lang('voucher_template_price_error')],
                ['txt_template_limit', 'require', lang('voucher_template_limit_error')],
                ['txt_template_describe', 'require|length:1,255', lang('voucher_template_describe_error')]
            ];

            $res = $obj_validate->check($data, $rule);
            $error = '';
            if (!$res) {
                $error .= $obj_validate->getError();
            }
            //金额验证
            $price = intval(input('post.select_template_price')) > 0 ? intval(input('post.select_template_price')) : 0;
            foreach ($pricelist as $k => $v) {
                if ($v['voucherprice'] == $price) {
                    $chooseprice = $v; //取得当前选择的面额记录
                }
            }
            if (empty($chooseprice)) {
                $error .= lang('voucher_template_pricelisterror');
            }
            $limit = intval(input('post.txt_template_limit')) > 0 ? intval(input('post.txt_template_limit')) : 0;
            if ($price >= $limit)
                $error .= lang('voucher_template_price_error');
            if ($error) {
                ds_show_dialog($error, 'reload', 'error');
            } else {
                $insert_arr = array();
                $insert_arr['vouchertemplate_title'] = trim(input('post.txt_template_title'));
                $insert_arr['vouchertemplate_desc'] = trim(input('post.txt_template_describe'));
                $insert_arr['vouchertemplate_startdate'] = time(); //默认代金券模板的有效期为当前时间
                if (input('post.txt_template_enddate')) {
                    $enddate = strtotime(input('post.txt_template_enddate'));
                    if (!$isPlatformStore && $enddate > $quotainfo['voucherquota_endtime']) {
                        $enddate = $quotainfo['voucherquota_endtime'];
                    }
                    $insert_arr['vouchertemplate_enddate'] = $enddate;
                } else {//如果没有添加有效期则默认为套餐的结束时间
                    if ($isPlatformStore)
                        $insert_arr['vouchertemplate_enddate'] = time() + 2592000; // 自营店 默认30天到期
                    else
                        $insert_arr['vouchertemplate_enddate'] = $quotainfo['voucherquota_endtime'];
                }
                $insert_arr['vouchertemplate_price'] = $price;
                $insert_arr['vouchertemplate_limit'] = $limit;
                $insert_arr['vouchertemplate_store_id'] = session('store_id');
                $insert_arr['vouchertemplate_storename'] = session('store_name');
                $insert_arr['vouchertemplate_sc_id'] = intval(input('post.storeclass_id'));
                $insert_arr['vouchertemplate_creator_id'] = session('member_id');
                $insert_arr['vouchertemplate_state'] = $this->templatestate_arr['usable'][0];
                $insert_arr['vouchertemplate_total'] = intval(input('post.txt_template_total')) > 0 ? intval(input('post.txt_template_total')) : 0;
                $insert_arr['vouchertemplate_giveout'] = 0;
                $insert_arr['vouchertemplate_used'] = 0;
                $insert_arr['vouchertemplate_gettype'] = 1;
                $insert_arr['vouchertemplate_adddate'] = TIMESTAMP;
                $insert_arr['vouchertemplate_quotaid'] = isset($quotainfo['voucherquota_id']) ? $quotainfo['voucherquota_id'] : 0;
                $insert_arr['vouchertemplate_points'] = $chooseprice['voucherprice_defaultpoints'];
                $insert_arr['vouchertemplate_eachlimit'] = intval(input('post.eachlimit')) > 0 ? intval(input('post.eachlimit')) : 0;
                //自定义图片
                if (!empty($_FILES['customimg']['name'])) {

                    $uploaddir = BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER. DS . session('store_id') . DS;
                    $file_name = session('store_id') . '_' . date('YmdHis') . rand(10000, 99999);
                    $file_object = request()->file('customimg');
                    $info = $file_object->rule('uniqid')->validate(['ext' => ALLOW_IMG_EXT])->move($uploaddir, $file_name);
                    if ($info) {
                        $insert_arr['vouchertemplate_customimg'] = $info->getFilename();
                    }
                }
                $rs = db('vouchertemplate')->insert($insert_arr);
                if ($rs) {
                    ds_show_dialog(lang('ds_common_save_succ'), url('Sellervoucher/templatelist'), 'succ');
                } else {
                    ds_show_dialog(lang('ds_common_save_fail'), url('Sellervoucher/templatelist'), 'error');
                }
            }
        } else {
            //店铺分类
            $store_class = rkcache('storeclass', true);
            $this->assign('store_class', $store_class);
            //查询店铺详情
            $store_info = model('store')->getStoreInfoByID(session('store_id'));
            $this->assign('store_info', $store_info);

            $this->assign('type', 'add');
            $this->assign('quotainfo', $quotainfo);
            $this->assign('pricelist', $pricelist);
            
            $t_info = array(
                'vouchertemplate_title'=>'',
                'vouchertemplate_price'=>'',
                'vouchertemplate_total'=>'',
                'vouchertemplate_limit'=>'',
                'vouchertemplate_desc'=>'',
                'vouchertemplate_customimg'=>'',
                'vouchertemplate_enddate'=>'',
                'vouchertemplate_eachlimit'=>0,
                'vouchertemplate_sc_id'=>'',
            );
            $this->assign('t_info', $t_info);
            
            $this->setSellerCurMenu('Sellervoucher');
            $this->setSellerCurItem('templateadd');
            return $this->fetch($this->template_dir . 'templateadd');
        }
    }

    /*
     * 代金券模版编辑
     */

    public function templateedit() {
        $t_id = intval(input('param.tid'));
        if ($t_id <= 0) {
            $this->error(lang('wrong_argument'), url('Sellervoucher/templatelist'));
        }
        //查询模板信息
        $param = array();
        $param['vouchertemplate_id'] = $t_id;
        $param['vouchertemplate_store_id'] = session('store_id');
        $param['vouchertemplate_state'] = $this->templatestate_arr['usable'][0];
        $param['vouchertemplate_giveout'] = array('elt', '0');
        $param['vouchertemplate_enddate'] = array('gt', time());
        $t_info = db('vouchertemplate')->where($param)->find();
        if (empty($t_info)) {
            $this->error(lang('wrong_argument'), 'Sellervoucher/templatelist');
        }

        $isPlatformStore = check_platform_store();
        $this->assign('isPlatformStore', $isPlatformStore);
        $quotainfo = array();
        if (!$isPlatformStore) {
            //查询套餐信息
            $quotainfo = db('voucherquota')->where(array(
                        'voucherquota_id' => $t_info['vouchertemplate_quotaid'],
                        'voucherquota_storeid' => session('store_id')
                    ))->find();
            if (empty($quotainfo)) {
                $this->error(lang('voucher_template_quotanull'), 'Sellervoucher/quotaadd');
            }
        }

        //查询面额列表
        $pricelist = db('voucherprice')->order('voucherprice asc')->select();
        if (empty($pricelist)) {
            $this->error(lang('voucher_template_pricelisterror'), 'Sellervoucher/templatelist');
        }
        if (request()->isPost()) {
            //验证提交的内容面额不能大于限额
            $obj_validate = new Validate();
            $data = [
                'txt_template_title' => input('post.txt_template_title'),
                'txt_template_total' => input('post.txt_template_total'),
                'select_template_price' => input('post.select_template_price'),
                'txt_template_limit' => input('post.txt_template_limit'),
                'txt_template_describe' => input('post.txt_template_describe'),
            ];

            $rule = [
                ['txt_template_title', 'require|length:1,50', lang('voucher_template_title_error')],
                ['txt_template_total', 'require|number', lang('voucher_template_total_error')],
                ['select_template_price', 'require|number', lang('voucher_template_price_error')],
                ['txt_template_limit', 'require', lang('voucher_template_limit_error')],
                ['txt_template_describe', 'require|length:1,255', lang('voucher_template_describe_error')]
            ];

            $res = $obj_validate->check($data, $rule);
            $error = '';
            if (!$res) {
                $error .= $obj_validate->getError();
            }
            //金额验证
            $price = intval(input('post.select_template_price')) > 0 ? intval(input('post.select_template_price')) : 0;
            foreach ($pricelist as $k => $v) {
                if ($v['voucherprice'] == $price) {
                    $chooseprice = $v; //取得当前选择的面额记录
                }
            }
            if (empty($chooseprice)) {
                $error .= lang('voucher_template_pricelisterror');
            }
            $limit = intval(input('post.txt_template_limit')) > 0 ? intval(input('post.txt_template_limit')) : 0;
            if ($price >= $limit)
                $error .= lang('voucher_template_price_error');
            if ($error) {
                ds_show_dialog($error, 'reload', 'error');
            } else {
                $update_arr = array();
                $update_arr['vouchertemplate_title'] = trim(input('post.txt_template_title'));
                $update_arr['vouchertemplate_desc'] = trim(input('post.txt_template_describe'));
                if (input('post.txt_template_enddate')) {
                    $enddate = strtotime(input('post.txt_template_enddate'));
                    if (!$isPlatformStore && $enddate > $quotainfo['voucherquota_endtime']) {
                        $enddate = $quotainfo['voucherquota_endtime'];
                    }
                    $update_arr['vouchertemplate_enddate'] = $enddate;
                } else {//如果没有添加有效期则默认为套餐的结束时间
                    if ($isPlatformStore)
                        $update_arr['vouchertemplate_enddate'] = time() + 2592000; // 自营店 默认30天到期
                    else
                        $update_arr['vouchertemplate_enddate'] = $quotainfo['voucherquota_endtime'];
                }
                $update_arr['vouchertemplate_price'] = $price;
                $update_arr['vouchertemplate_limit'] = $limit;
                $update_arr['vouchertemplate_sc_id'] = intval(input('post.storeclass_id'));
                $update_arr['vouchertemplate_state'] = intval(input('post.tstate')) == $this->templatestate_arr['usable'][0] ? $this->templatestate_arr['usable'][0] : $this->templatestate_arr['disabled'][0];
                $update_arr['vouchertemplate_total'] = intval(input('post.txt_template_total')) > 0 ? intval(input('post.txt_template_total')) : 0;
                $update_arr['vouchertemplate_points'] = $chooseprice['voucherprice_defaultpoints'];
                $update_arr['vouchertemplate_eachlimit'] = intval(input('post.eachlimit')) > 0 ? intval(input('post.eachlimit')) : 0;
                //自定义图片
                if (!empty($_FILES['customimg']['name'])) {
                    $uploaddir = BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS .session('store_id'). DS;
                    $file_name = session('store_id') . '_' . date('YmdHis') . rand(10000, 99999);
                    $file_object = request()->file('customimg');
                    $info = $file_object->validate(['ext' => ALLOW_IMG_EXT])->move($uploaddir, $file_name);
                    if ($info) {
                        //删除原图
                        if (!empty($t_info['vouchertemplate_customimg'])) {//如果模板存在，则删除原模板图片
                            @unlink(BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER. DS .session('store_id') . DS . $t_info['vouchertemplate_customimg']);
                        }
                        $update_arr['vouchertemplate_customimg'] = $info->getFilename();
                    }
                }

                $rs = db('vouchertemplate')->where(array('vouchertemplate_id' => $t_info['vouchertemplate_id']))->update($update_arr);
                if ($rs) {
                    ds_show_dialog(lang('ds_common_op_succ'), url('Sellervoucher/templatelist'), 'succ');
                } else {
                    ds_show_dialog(lang('ds_common_op_fail'), url('Sellervoucher/templatelist'), 'error');
                }
            }
        } else {
            if (!$t_info['vouchertemplate_customimg'] || !file_exists(BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER. DS .session('store_id') . DS . $t_info['vouchertemplate_customimg'])) {
                $t_info['vouchertemplate_customimg'] = UPLOAD_SITE_URL . DS . default_goodsimage(240);
            } else {
                $t_info['vouchertemplate_customimg'] = UPLOAD_SITE_URL . DS . ATTACH_VOUCHER. DS .session('store_id') . DS . $t_info['vouchertemplate_customimg'];
            }
            $this->assign('type', 'edit');
            $this->assign('t_info', $t_info);

            //店铺分类
            $store_class = rkcache('storeclass', true);
            $this->assign('store_class', $store_class);
            //查询店铺详情
            $store_info = model('store')->getStoreInfoByID(session('store_id'));
            $this->assign('store_info', $store_info);

            $this->assign('quotainfo', $quotainfo);
            $this->assign('pricelist', $pricelist);
            $this->setSellerCurMenu('Sellervoucher');
            $this->setSellerCurItem('templateedit');

            return $this->fetch($this->template_dir . 'templateadd');
        }
    }

    /**
     * 删除代金券
     */
    public function templatedel() {
        $t_id = intval(input('param.tid'));
        if ($t_id <= 0) {
            $this->error(lang('wrong_argument'), url('Sellervoucher/templatelist'));
        }
        //查询模板信息
        $param = array();
        $param['vouchertemplate_id'] = $t_id;
        $param['vouchertemplate_store_id'] = session('store_id');
        $param['vouchertemplate_giveout'] = array('elt', '0'); //会员没领取过代金券才可删除
        $t_info = db('vouchertemplate')->where($param)->find();
        if (empty($t_info)) {
            $this->error(lang('wrong_argument'), 'Sellervoucher/templatelist');
        }
        $rs = db('vouchertemplate')->where(array('vouchertemplate_id' => $t_info['vouchertemplate_id']))->delete();
        if ($rs) {
            //删除自定义的图片
            if (trim($t_info['vouchertemplate_customimg'])) {
                @unlink(BASE_UPLOAD_PATH . DS . ATTACH_VOUCHER . DS . session('store_id') . DS . $t_info['vouchertemplate_customimg']);
            }
            ds_show_dialog(lang('ds_common_del_succ'), 'reload', 'succ');
        } else {
            ds_show_dialog(lang('ds_common_del_fail'));
        }
    }

    /**
     * 查看代金券详细
     */
    public function templateinfo() {
        $t_id = intval(input('param.tid'));
        if ($t_id <= 0) {
            $this->error(lang('wrong_argument'), 'Sellervoucher/templatelist');
        }
        //查询模板信息
        $param = array();
        $param['vouchertemplate_id'] = $t_id;
        $param['vouchertemplate_store_id'] = session('store_id');
        $t_info = db('vouchertemplate')->where($param)->find();
        $this->assign('t_info', $t_info);
        $this->setSellerCurMenu('Sellervoucher');
        $this->setSellerCurItem('templateinfo');
        return $this->fetch($this->template_dir . 'template_info');
    }

    /*
     * 把代金券模版设为失效
     */

    private function check_voucher_template_expire($voucher_template_id = '') {
        $where_array = array();
        if (empty($voucher_template_id)) {
            $where_array['vouchertemplate_enddate'] = array('lt', time());
        } else {
            $where_array['vouchertemplate_id'] = $voucher_template_id;
        }
        $where_array['vouchertemplate_state'] = $this->templatestate_arr['usable'][0];
        db('vouchertemplate')->where($where_array)->update(array('vouchertemplate_state' => $this->templatestate_arr['disabled'][0]));
    }

    /**
     * 用户中心右边，小导航
     *
     * @param string $menu_type 导航类型
     * @param string $menu_key 当前导航的menu_key
     * @return
     */
    protected function getSellerItemList() {
        $menu_array = array();
        switch (request()->action()) {
            case 'templatelist':
                $menu_array = array(
                    1 => array(
                        'name' => 'templatelist', 'text' => lang('ds_member_path_store_voucher'),
                        'url' => url('Sellervoucher/templatelist')
                    ),
                );
                break;
            case 'quotaadd':
                $menu_array = array(
                    array(
                        'name' => 'templatelist', 'text' => lang('ds_member_path_store_voucher'),
                        'url' => url('Sellervoucher/templatelist')
                    ), array(
                        'name' => 'quotaadd', 'text' => lang('voucher_applyadd'), 'url' => url('Sellervoucher/quotaadd')
                    )
                );
                break;
            case 'templateadd':
                $menu_array = array(
                    1 => array(
                        'name' => 'templatelist', 'text' => lang('ds_member_path_store_voucher'),
                        'url' => url('Sellervoucher/templatelist')
                    ), 2 => array(
                        'name' => 'templateadd', 'text' => lang('voucher_templateadd'),
                        'url' => url('Sellervoucher/templateadd')
                    ),
                );
                break;
            case 'templateedit':
                $menu_array = array(
                    1 => array(
                        'name' => 'templatelist', 'text' => lang('ds_member_path_store_voucher'),
                        'url' => url('Sellervoucher/templatelist')
                    ), 2 => array(
                        'name' => 'templateedit', 'text' => lang('voucher_templateedit'), 'url' => ''
                    ),
                );
                break;
            case 'templateinfo':
                $menu_array = array(
                    1 => array(
                        'name' => 'templatelist', 'text' => lang('ds_member_path_store_voucher'),
                        'url' => url('Sellervoucher/templatelist')
                    ), 2 => array(
                        'name' => 'templateinfo', 'text' => lang('voucher_templateinfo'), 'url' => ''
                    ),
                );
                break;
        }
        return $menu_array;
    }

}
