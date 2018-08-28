<?php

namespace app\home\controller;

use think\Lang;

class Sellervrbill extends BaseSeller {

    /**
     * 每次导出多少条记录
     * @var unknown
     */
    const EXPORT_SIZE = 1000;

    /**
     * 结算列表
     *
     */
    public function index() {
        $vrbill_model = model('vrbill');
        $condition = array();
        $condition['vrob_store_id'] = session('store_id');
        $vrob_no = input('param.vrob_no');
        if (preg_match('/^20\d{5,12}$/', $vrob_no)) {
            $condition['vrob_no'] = $vrob_no;
        }
        $bill_state = input('param.bill_state');
        if (is_numeric($bill_state)) {
            $condition['vrob_state'] = intval($bill_state);
        }
        $bill_list = $vrbill_model->getVrorderbillList($condition, '*', 12, 'vrob_state asc,vrob_no asc');
        $this->assign('bill_list', $bill_list);
        
        $this->assign('show_page', $vrbill_model->page_info->render());
        /* 设置卖家当前菜单 */
        $this->setSellerCurMenu('Sellervrbill');
        /* 设置卖家当前栏目 */
        $this->setSellerCurItem('index');
        return $this->fetch($this->template_dir.'index');
    }

    /**
     * 查看结算单详细
     *
     */
    public function show_bill() {
        $vrob_no = input('param.vrob_no');
        if (!preg_match('/^20\d{5,12}$/', $vrob_no)) {
            $this->error('参数错误');
        }
        if (substr($vrob_no, 6) != session('store_id')) {
            $this->error('参数错误');
        }
        $vrbill_model = model('vrbill');
        $vrorder_model = model('vrorder');
        $bill_info = $vrbill_model->getVrorderbillInfo(array('vrob_no' => $vrob_no));
        if (!$bill_info) {
            $this->error('参数错误');
        }

        $condition = array();
        $condition['store_id'] = $bill_info['vrob_store_id'];
        $query_order_no = input('param.query_order_no');
        if (preg_match('/^\d{8,20}$/', $query_order_no)) {
            //取order_id
            $order_info = $vrorder_model->getVrorderInfo(array('order_sn' => $query_order_no), 'order_id');
            $condition['order_id'] = $order_info['order_id'];
        }
        $query_start_date = input('param.query_start_date');
        $query_end_date = input('param.query_end_date');
        $if_start_date = preg_match('/^20\d{2}-\d{2}-\d{2}$/', $query_start_date);
        $if_end_date = preg_match('/^20\d{2}-\d{2}-\d{2}$/', $query_end_date);
        $start_unixtime = $if_start_date ? strtotime($query_start_date) : null;
        $end_unixtime = $if_end_date ? strtotime($query_end_date) : null;
        if ($if_start_date || $if_end_date) {
            $condition_time = array('between', array($start_unixtime, $end_unixtime));
        } else {
            $condition_time = array('between', "{$bill_info['vrob_startdate']},{$bill_info['vrob_enddate']}");
        }
        $type = input('param.type');
        if ($type == 'timeout') {
            //计算未使用已过期不可退兑换码列表
            $condition['vr_state'] = 0;
            $condition['vr_invalid_refund'] = 0;
            $condition['vr_indate'] = $condition_time;
        } else {
            //计算已使用兑换码列表
            $condition['vr_state'] = 1;
            $condition['vr_usetime'] = $condition_time;
        }
        $code_list = $vrorder_model->getVrordercodeList($condition, '*', 20, 'rec_id desc');

        //然后取订单编号
        $order_id_array = array();
        if (is_array($code_list)) {
            foreach ($code_list as $code_info) {
                $order_id_array[] = $code_info['order_id'];
            }
        }
        $condition = array();
        $condition['order_id'] = array('in', $order_id_array);
        $order_list = $vrorder_model->getVrorderList($condition);
        $order_new_list = array();
        if (!empty($order_list)) {
            foreach ($order_list as $v) {
                $order_new_list[$v['order_id']]['order_sn'] = $v['order_sn'];
                $order_new_list[$v['order_id']]['buyer_name'] = $v['buyer_name'];
            }
        }
        $this->assign('order_list', $order_new_list);
        $this->assign('code_list', $code_list);
        $this->assign('show_page', $vrorder_model->page_info->render());
        
        $this->assign('bill_info', $bill_info);
        /* 设置卖家当前菜单 */
        $this->setSellerCurMenu('Sellervrbill');
        /* 设置卖家当前栏目 */
        $this->setSellerCurItem('show_bill');
        return $this->fetch($this->template_dir.'show_bill');
    }

    /**
     * 打印结算单
     *
     */
    public function bill_print() {
        $vrob_no = input('param.vrob_no');
        if (!preg_match('/^20\d{5,12}$/', $vrob_no)) {
            $this->error('参数错误');
        }
        if (substr($vrob_no, 6) != session('store_id')) {
            $this->error('参数错误');
        }
        $vrbill_model = model('vrbill');
        $condition = array();
        $condition['vrob_no'] = $vrob_no;
        $condition['vrob_state'] = BILL_STATE_SUCCESS;
        $bill_info = $vrbill_model->getVrorderbillInfo($condition);
        if (!$bill_info) {
            $this->error('参数错误');
        }

        $this->assign('bill_info', $bill_info);
        return $this->fetch($this->template_dir.'bill_print');
    }

    /**
     * 店铺确认出账单
     *
     */
    public function confirm_bill() {
        $vrob_no = input('param.vrob_no');
        if (!preg_match('/^20\d{5,12}$/', $vrob_no)) {
            ds_show_dialog('参数错误', '', 'error');
        }
        $vrbill_model = model('vrbill');
        $condition = array();
        $condition['vrob_no'] = $vrob_no;
        $condition['vrob_store_id'] = session('store_id');
        $condition['vrob_state'] = BILL_STATE_CREATE;
        $update = $vrbill_model->editVrorderbill(array('vrob_state' => BILL_STATE_STORE_COFIRM), $condition);
        if ($update) {
            ds_show_dialog('确认成功，请等待系统审核', '', 'succ');
        } else {
            ds_show_dialog(lang('ds_common_op_fail'), 'reload', 'error');
        }
    }


    /**
     *    栏目菜单
     */
    function getSellerItemList() {
        $menu_array[] = array(
            'name' => 'account_list',
            'text' => '账号列表',
            'url' => url('Selleraccount/account_list'),
        );

        if (request()->action() === 'account_add') {
            $menu_array[] = array(
                'name' => 'account_add',
                'text' => '添加账号',
                'url' => url('Selleraccount/account_add'),
            );
        }
        if (request()->action() === 'group_edit') {
            $menu_array[] = array(
                'name' => 'account_edit',
                'text' => '编辑账号',
                'url' => url('Selleraccount/account_edit'),
            );
        }

        return $menu_array;
    }

}
