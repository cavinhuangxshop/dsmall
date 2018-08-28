<?php

/**
 * 卖家拼团管理
 */

namespace app\home\controller;

use think\Lang;

class Sellerpromotionpintuan extends BaseSeller {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/sellerpromotionpintuan.lang.php');
        if (intval(config('promotion_allow')) !== 1) {
            $this->error(lang('promotion_unavailable'), 'seller/index');
        }
    }

    public function index() {
        $pintuanquota_model = model('ppintuanquota');
        $ppintuan_model = model('ppintuan');

        if (check_platform_store()) {
            $this->assign('isPlatformStore', true);
        } else {
            $current_pintuan_quota = $pintuanquota_model->getPintuanquotaCurrent(session('store_id'));
            $this->assign('current_pintuan_quota', $current_pintuan_quota);
        }

        $condition = array();
        $condition['store_id'] = session('store_id');
        if ((input('param.pintuan_name'))) {
            $condition['pintuan_name'] = array('like', '%' . input('param.pintuan_name') . '%');
        }
        if ((input('param.state'))) {
            $condition['pintuan_state'] = intval(input('param.state'));
        }
        $pintuan_list = $ppintuan_model->getPintuanList($condition, 10, 'pintuan_state desc, pintuan_end_time desc');
        $this->assign('pintuan_list', $pintuan_list);
        $this->assign('show_page', $ppintuan_model->page_info->render());
        $this->assign('pintuan_state_array', $ppintuan_model->getPintuanStateArray());

        $this->setSellerCurMenu('Sellerpromotionpintuan');
        $this->setSellerCurItem('pintuan_list');
        return $this->fetch($this->template_dir . 'index');
    }

    /**
     * 添加拼团活动
     * */
    public function pintuan_add() {
        if (!request()->isPost()) {
            if (check_platform_store()) {
                $this->assign('isPlatformStore', true);
            } else {
                $this->assign('isPlatformStore', false);
                $pintuanquota_model = model('ppintuanquota');
                $current_pintuan_quota = $pintuanquota_model->getPintuanquotaCurrent(session('store_id'));
                if (empty($current_pintuan_quota)) {
                    $this->error(lang('pintuan_quota_current_error1'));
                }
                $this->assign('current_pintuan_quota', $current_pintuan_quota);
            }

            //输出导航
            $this->setSellerCurMenu('Sellerpromotionpintuan');
            $this->setSellerCurItem('pintuan_add');
            return $this->fetch($this->template_dir . 'pintuan_add');
        } else {
            //验证输入
            $pintuan_name = trim(input('post.pintuan_name'));
            $start_time = strtotime(input('post.start_time'));
            $end_time = strtotime(input('post.end_time'));
            $pintuan_limit_number = intval(input('post.pintuan_limit_number'));
            if ($pintuan_limit_number <= 1) {
                $pintuan_limit_number = 2;
            }
            //成团时限
            $pintuan_limit_hour = intval(input('post.pintuan_limit_hour'));
            if ($pintuan_limit_hour <= 0) {
                $pintuan_limit_hour = 1;
            }
            //购买限制
            $pintuan_limit_quantity = intval(input('post.pintuan_limit_quantity'));
            if ($pintuan_limit_quantity <= 0) {
                $pintuan_limit_quantity = 1;
            }
            //购买折扣
            $pintuan_zhe = intval(input('post.pintuan_zhe'));
            if ($pintuan_zhe <= 0 || $pintuan_zhe >= 10) {
                $pintuan_zhe = 1;
            }

            if (empty($pintuan_name)) {
                ds_show_dialog(lang('pintuan_name_error'));
            }
            if ($start_time >= $end_time) {
                ds_show_dialog(lang('greater_than_start_time'));
            }

            if (!check_platform_store()) {
                //获取当前套餐
                $pintuanquota_model = model('ppintuanquota');
                $current_pintuan_quota = $pintuanquota_model->getPintuanquotaCurrent(session('store_id'));
                if (empty($current_pintuan_quota)) {
                    ds_show_dialog('没有可用拼团套餐,请先购买套餐');
                }
                $quota_start_time = intval($current_pintuan_quota['pintuanquota_starttime']);
                $quota_end_time = intval($current_pintuan_quota['pintuanquota_endtime']);
                if ($start_time < $quota_start_time) {
                    ds_show_dialog(sprintf(lang('pintuan_add_start_time_explain'), date('Y-m-d', $current_pintuan_quota['pintuanquota_starttime'])));
                }
                if ($end_time > $quota_end_time) {
                    ds_show_dialog(sprintf(lang('pintuan_add_end_time_explain'), date('Y-m-d', $current_pintuan_quota['pintuanquota_endtime'])));
                }
            }

            //获取提交的数据
            $goods_id = intval(input('post.pintuan_goods_id'));
            if (empty($goods_id)) {
                ds_show_dialog(lang('param_error'));
            }
            $goods_model = model('goods');
            $goods_info = $goods_model->getGoodsInfoByID($goods_id);
            if (empty($goods_info) || $goods_info['store_id'] != session('store_id')) {
                ds_show_dialog(lang('param_error'));
            }

            //判断此商品是否在拼团中，拼团中的商品是不可以参加活动
            $result = $this->_check_allow_pintuan($goods_info['goods_commonid']);
            if($result!=TRUE){
                ds_show_dialog($result['message']);
            }
            
            
            //生成活动
            $ppintuan_model = model('ppintuan');
            $param = array();
            $param['pintuan_name'] = $pintuan_name;
            $param['pintuanquota_id'] = isset($current_pintuan_quota['pintuanquota_id']) ? $current_pintuan_quota['pintuanquota_id'] : 0;
            $param['pintuan_starttime'] = $start_time;
            $param['pintuan_end_time'] = $end_time;
            $param['store_id'] = session('store_id');
            $param['store_name'] = session('store_name');
            $param['member_id'] = session('member_id');
            $param['member_name'] = session('member_name');
            $param['pintuan_zhe'] = $pintuan_zhe;
            $param['pintuan_limit_number'] = $pintuan_limit_number;
            $param['pintuan_limit_hour'] = $pintuan_limit_hour;
            $param['pintuan_limit_quantity'] = $pintuan_limit_quantity;
            $param['pintuan_goods_id'] = $goods_info['goods_id'];
            $param['pintuan_goods_name'] = $goods_info['goods_name'];
            $param['pintuan_goods_commonid'] = $goods_info['goods_commonid'];
            $param['pintuan_image'] = $goods_info['goods_image'];
            $result = $ppintuan_model->addPintuan($param);
            if ($result) {
                $this->recordSellerlog('添加拼团活动，活动名称：' . $pintuan_name . '，活动编号：' . $result);
                $ppintuan_model->_dGoodsPintuanCache($goods_info['goods_commonid']);#清除缓存
                ds_show_dialog(lang('pintuan_add_success'), url('Sellerpromotionpintuan/index'), 'succ', '', 3);
            } else {
                ds_show_dialog(lang('pintuan_add_fail'));
            }
        }
    }

    /**
     * 编辑拼团活动
     * */
    public function pintuan_edit() {
        if (!request()->isPost()) {
            if (check_platform_store()) {
                $this->assign('isPlatformStore', true);
            } else {
                $this->assign('isPlatformStore', false);
            }
            $ppintuan_model = model('ppintuan');

            $pintuan_info = $ppintuan_model->getPintuanInfoByID(input('param.pintuan_id'));
            if (empty($pintuan_info) || !$pintuan_info['editable']) {
                $this->error(lang('param_error'));
            }

            $this->assign('pintuan_info', $pintuan_info);

            //输出导航
            $this->setSellerCurMenu('Sellerpromotionpintuan');
            $this->setSellerCurItem('pintuan_edit');
            return $this->fetch($this->template_dir . 'pintuan_add');
        } else {
            $pintuan_id = input('param.pintuan_id');

            $ppintuan_model = model('ppintuan');

            $pintuan_info = $ppintuan_model->getPintuanInfoByID($pintuan_id, session('store_id'));
            if (empty($pintuan_info) || !$pintuan_info['editable']) {
                $this->error(lang('param_error'));
            }

            //验证输入
            $pintuan_name = trim(input('post.pintuan_name'));
            if (empty($pintuan_name)) {
                ds_show_dialog(lang('pintuan_name_error'));
            }

            $pintuan_limit_number = intval(input('post.pintuan_limit_number'));
            if ($pintuan_limit_number <= 1) {
                $pintuan_limit_number = 2;
            }
            //成团时限
            $pintuan_limit_hour = intval(input('post.pintuan_limit_hour'));
            if ($pintuan_limit_hour <= 0) {
                $pintuan_limit_hour = 1;
            }
            //购买限制
            $pintuan_limit_quantity = intval(input('post.pintuan_limit_quantity'));
            if ($pintuan_limit_quantity <= 0) {
                $pintuan_limit_quantity = 1;
            }
            //购买折扣
            $pintuan_zhe = intval(input('post.pintuan_zhe'));
            if ($pintuan_zhe <= 0 || $pintuan_zhe >= 10) {
                $pintuan_zhe = 1;
            }

            //生成活动
            $param = array();
            $param['pintuan_name'] = $pintuan_name;
            $param['pintuan_zhe'] = $pintuan_zhe;
            $param['pintuan_limit_number'] = $pintuan_limit_number;
            $param['pintuan_limit_hour'] = $pintuan_limit_hour;
            $param['pintuan_limit_quantity'] = $pintuan_limit_quantity;


            $result = $ppintuan_model->editPintuan($param, array('pintuan_id' => $pintuan_id));
            if ($result) {
                $this->recordSellerlog('编辑拼团活动，活动名称：' . $pintuan_name . '，活动编号：' . $pintuan_id);
                $ppintuan_model->_dGoodsPintuanCache($pintuan_info['pintuan_goods_commonid']);#清除缓存
                ds_show_dialog(lang('ds_common_op_succ'), url('Sellerpromotionpintuan/index'), 'succ', '', 3);
            } else {
                ds_show_dialog(lang('ds_common_op_fail'));
            }
        }
    }

    /**
     * 拼团活动 提前结束
     */
    public function pintuan_end() {
        $pintuan_id = intval(input('post.pintuan_id'));
        $ppintuan_model = model('ppintuan');

        $pintuan_info = $ppintuan_model->getPintuanInfoByID($pintuan_id, session('store_id'));
        if (!$pintuan_info) {
            ds_show_dialog(lang('param_error'));
        }

        /**
         * 指定拼团活动结束
         */
        $result = $ppintuan_model->endPintuan(array('pintuan_id' => $pintuan_id));

        if ($result) {
            $this->recordSellerlog('拼团活动提前结束，活动名称：' . $pintuan_info['pintuan_name'] . '活动编号：' . $pintuan_id);
            $ppintuan_model->_dGoodsPintuanCache($pintuan_info['pintuan_goods_commonid']);#清除缓存
            ds_show_dialog(lang('ds_common_op_succ'), url('Sellerpromotionpintuan/index'), 'succ');
        } else {
            ds_show_dialog(lang('ds_common_op_fail'));
        }
    }

    /**
     * 查看拼团开团信息
     * @return type
     */
    public function pintuan_manage() {
        
        $ppintuan_model = model('ppintuan');
        $ppintuangroup_model = model('ppintuangroup');
        $ppintuanorder_model = model('ppintuanorder');
        $pintuan_id = intval(input('param.pintuan_id'));
        //判断此拼团是否属于店铺
        $ppintuan = $ppintuan_model->getPintuanInfo(array('store_id'=> session('store_id'),'pintuan_id'=>$pintuan_id));
        if(empty($ppintuan)){
            $this->error(lang('param_error'));
        }
        $condition = array();
        $condition['pintuan_id'] = $pintuan_id;
        $condition['pintuangroup_state'] = intval(input('param.pintuangroup_state'));
        $ppintuangroup_list = $ppintuangroup_model->getPpintuangroupList($condition, 10); #获取开团信息
        foreach ($ppintuangroup_list as $key => $ppintuangroup) {
            //获取开团订单下的参团订单
            $condition = array();
            $condition['pintuangroup_id'] = $ppintuangroup['pintuangroup_id'];
            $ppintuangroup_list[$key]['order_list'] = $ppintuanorder_model->getPpintuanorderList($condition);
        }
        $this->assign('show_page', $ppintuangroup_model->page_info->render());
        $this->assign('pintuangroup_list', $ppintuangroup_list);
        $this->assign('pintuangroup_state_array', $ppintuangroup_model->getPintuangroupStateArray());
        $this->setSellerCurMenu('Sellerpromotionpintuan');
        $this->setSellerCurItem('pintuan_manage');
        return $this->fetch($this->template_dir . 'pintuan_manage');
    }

    /**
     * 拼团套餐购买
     * */
    public function pintuan_quota_add() {
        //输出导航
        $this->setSellerCurMenu('Sellerpromotionpintuan');
        $this->setSellerCurItem('pintuan_quota_add');
        return $this->fetch($this->template_dir . 'pintuan_quota_add');
    }

    /**
     * 拼团套餐购买保存
     * */
    public function pintuan_quota_add_save() {
        $pintuan_quota_quantity = intval(input('post.pintuan_quota_quantity'));
        if ($pintuan_quota_quantity <= 0 || $pintuan_quota_quantity > 12) {
            ds_show_dialog(lang('pintuan_quota_quantity_error'));
        }
        //获取当前价格
        $current_price = intval(config('promotion_pintuan_price'));
        //获取该用户已有套餐
        $pintuanquota_model = model('ppintuanquota');
        $current_pintuan_quota = $pintuanquota_model->getPintuanquotaCurrent(session('store_id'));
        $pintuan_add_time = 86400 * 30 * $pintuan_quota_quantity;
        if (empty($current_pintuan_quota)) {
            //生成套餐
            $param = array();
            $param['member_id'] = session('member_id');
            $param['member_name'] = session('member_name');
            $param['store_id'] = session('store_id');
            $param['store_name'] = session('store_name');
            $param['pintuanquota_starttime'] = TIMESTAMP;
            $param['pintuanquota_endtime'] = TIMESTAMP + $pintuan_add_time;
            $pintuanquota_model->addPintuanquota($param);
        } else {
            $param = array();
            $param['pintuanquota_endtime'] = array('exp', 'pintuanquota_endtime + ' . $pintuan_add_time);
            $pintuanquota_model->editPintuanquota($param, array('pintuanquota_id' => $current_pintuan_quota['pintuanquota_id']));
        }

        //记录店铺费用
        $this->recordStorecost($current_price * $pintuan_quota_quantity, '购买拼团');

        $this->recordSellerlog('购买' . $pintuan_quota_quantity . '份拼团套餐，单价' . $current_price . lang('ds_yuan'));

        ds_show_dialog(lang('pintuan_quota_add_success'), url('Sellerpromotionpintuan/index'), 'succ');
    }

    /**
     * 选择活动商品
     * */
    public function search_goods() {
        $goods_model = model('goods');
        $condition = array();
        $condition['store_id'] = session('store_id');
        $condition['goods_name'] = array('like', '%' . input('param.goods_name') . '%');
        $goods_list = $goods_model->getGoodsCommonListForPromotion($condition, '*', 8, 'groupbuy');
        $this->assign('goods_list', $goods_list);
        $this->assign('show_page', $goods_model->page_info->render());
        echo $this->fetch($this->template_dir . 'search_goods');
        exit;
    }

    public function pintuan_goods_info() {
        $goods_commonid = intval(input('param.goods_commonid'));

        $data = array();
        $data['result'] = true;



        //判断此商品是否已经参加拼团，
        $result = $this->_check_allow_pintuan($goods_commonid);
        if($result['result'] != TRUE){
            echo json_encode($result);
            die;
        }
        
        //获取商品具体信息用于显示
        $goods_model = model('goods');
        $condition = array();
        $condition['goods_commonid'] = $goods_commonid;
        $goods_list = $goods_model->getGoodsOnlineList($condition);

        if (empty($goods_list)) {
            $data['result'] = false;
            $data['message'] = lang('param_error');
            echo json_encode($data);
            die;
        }


        $goods_info = $goods_list[0];
        $data['goods_id'] = $goods_info['goods_id'];
        $data['goods_name'] = $goods_info['goods_name'];
        $data['goods_price'] = $goods_info['goods_price'];
        $data['goods_image'] = goods_thumb($goods_info, 240);
        $data['goods_href'] = url('Goods/index', array('goods_id' => $goods_info['goods_id']));

        echo json_encode($data);
        die;
    }

    /*
     * 判断此商品是否已经参加拼团
     */
    private function _check_allow_pintuan($goods_commonid) {
        $condition = array();
        $condition['pintuan_goods_commonid'] = $goods_commonid;
        $condition['pintuan_state'] = 1;
        $pintuan = model('ppintuan')->getPintuanInfo($condition);
        $result['result'] = TRUE;
        if (!empty($pintuan)) {
            $result['result'] = FALSE;
            $result['message'] = lang('此商品在拼团中');
        }
        return $result;
    }

    protected function getSellerItemList() {
        $menu_array = array(
            array(
                'name' => 'pintuan_list', 'text' => lang('pintuan_active_list'),
                'url' => url('Sellerpromotionpintuan/index')
            ),
        );
        switch (request()->action()) {
            case 'pintuan_add':
                $menu_array[] = array(
                    'name' => 'pintuan_add', 'text' => lang('pintuan_add'),
                    'url' => url('Sellerpromotionpintuan/pintuan_add')
                );
                break;
            case 'pintuan_edit':
                $menu_array[] = array(
                    'name' => 'pintuan_edit', 'text' => lang('pintuan_edit'), 'url' => 'javascript:;'
                );
                break;
            case 'pintuan_quota_add':
                $menu_array[] = array(
                    'name' => 'pintuan_quota_add', 'text' => lang('pintuan_quota_add'),
                    'url' => url('Sellerpromotionpintuan/pintuan_quota_add')
                );
                break;
            case 'pintuan_manage':
                $menu_array[] = array(
                    'name' => 'pintuan_manage', 'text' => lang('pintuan_manage'),
                    'url' => url('Sellerpromotionpintuan/pintuan_manage', 'pintuan_id=' . input('param.pintuan_id'))
                );
                break;
        }
        return $menu_array;
    }

}
