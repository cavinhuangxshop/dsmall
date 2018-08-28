<?php

namespace app\admin\controller;

use think\Lang;
use think\Validate;

class Vrbill extends AdminControl {
const EXPORT_SIZE = 1000;
    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'admin/lang/zh-cn/vrbill.lang.php');
    }

    /**
     * 所有月份销量账单
     *
     */
    public function index() {

        $condition = array();
        $query_year = input('get.query_year');
        if (preg_match('/^\d{4}$/', $query_year, $match)) {
            $condition['vros_year'] = $query_year;
        }
        $vrbill_model = model('vrbill');
        $bill_list = $vrbill_model->getVrorderstatisList($condition, '*', 12, 'vros_month desc');
        $this->assign('bill_list', $bill_list);
        $this->assign('show_page', $vrbill_model->page_info->render());
        
        $this->assign('filtered', $condition ? 1 : 0); //是否有查询条件

        $this->setAdminCurItem('index');
        return $this->fetch('index');
    }

    /**
     * 某月所有店铺销量账单
     *
     */
    public function show_statis() {
        $os_month = input('param.os_month');
        if (!empty($os_month) && !preg_match('/^20\d{4}$/', $os_month, $match)) {
            $this->error(lang('param_error'));
        }
        $vrbill_model = model('vrbill');
        $condition = array();
        if (!empty($os_month)) {
            $condition['vros_month'] = intval($os_month);
        }
        $bill_state = input('get.bill_state');
        if (is_numeric($bill_state)) {
            $condition['vrob_state'] = intval($bill_state);
        }
        $query_store = input('get.query_store');
        if (preg_match('/^\d{1,8}$/', $query_store)) {
            $condition['vrob_store_id'] = $query_store;
        } elseif ($query_store != '') {
            $condition['vrob_store_name'] = $query_store;
        }
        $bill_list = $vrbill_model->getVrorderbillList($condition, '*', 30, 'vrob_no desc');
        $this->assign('bill_list', $bill_list);
        $this->assign('show_page', $vrbill_model->page_info->render());

        $this->setAdminCurItem('show_statis');
        return $this->fetch('show_statis');
    }

    /**
     * 某店铺某月订单列表
     *
     */
    public function show_bill() {
        $ob_no = input('param.ob_no');
        if (!preg_match('/^20\d{5,12}$/', $ob_no, $match)) {
            $this->error(lang('param_error'));
        }
        $vrbill_model = model('vrbill');
        $bill_info = $vrbill_model->getVrorderbillInfo(array('vrob_no' => $ob_no));
        if (!$bill_info) {
            $this->error(lang('param_error'));
        }
        $vrorder_model = model('vrorder');
        $condition = array();
        $condition['store_id'] = $bill_info['vrob_store_id'];
        $query_type = input('param.query_type');
        if ($query_type == 'timeout') {
            //计算未使用已过期不可退兑换码列表
            $condition['vr_state'] = 0;
            $condition['vr_invalid_refund'] = 0;
            $condition['vr_indate'] = array('between', "{$bill_info['vrob_startdate']},{$bill_info['vrob_enddate']}");
        } else {
            //计算已使用兑换码列表
            $condition['vr_state'] = 1;
            $condition['vr_usetime'] = array('between', "{$bill_info['vrob_startdate']},{$bill_info['vrob_enddate']}");
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
                $order_new_list[$v['order_id']]['store_name'] = $v['store_name'];
                $order_new_list[$v['order_id']]['store_id'] = $v['store_id'];
            }
        }
        $this->assign('order_list', $order_new_list);
        $this->assign('code_list', $code_list);
        $this->assign('show_page', $vrorder_model->page_info->render());
        $this->assign('bill_info', $bill_info);
        return $this->fetch('show_bill');
    }

    public function bill_check() {
        $ob_no = input('param.ob_no');
        if (!preg_match('/^20\d{5,12}$/', $ob_no)) {
            $this->error(lang('param_error'));
        }
        $vrbill_model = model('vrbill');
        $condition = array();
        $condition['vrob_no'] = $ob_no;
        $condition['vrob_state'] = BILL_STATE_STORE_COFIRM;
        $update = $vrbill_model->editVrorderbill(array('vrob_state' => BILL_STATE_SYSTEM_CHECK), $condition);
        if ($update) {
            $this->log('审核账单,账单号：' . $ob_no, 1);
            $this->success('审核成功，账单进入付款环节');
        } else {
            $this->log('审核账单，账单号：' . $ob_no, 0);
            $this->error('审核失败');
        }
    }

    /**
     * 账单付款
     *
     */
    public function bill_pay() {
        $ob_no = input('param.ob_no');
        if (!preg_match('/^20\d{5,12}$/', $ob_no)) {
            $this->error(lang('param_error'));
        }
        $vrbill_model = model('vrbill');
        $condition = array();
        $condition['vrob_no'] = $ob_no;
        $condition['vrob_state'] = BILL_STATE_SYSTEM_CHECK;
        $bill_info = $vrbill_model->getVrorderbillInfo($condition);
        if (!$bill_info) {
            $this->error(lang('param_error'));
        }
        if (request()->isPost()) {
            if (!preg_match('/^20\d{2}-\d{2}-\d{2}$/', input('post.pay_date'))) {
                $this->error(lang('param_error'));
            }
            $input = array();
            $input['vrob_pay_content'] = input('post.pay_content');
            $input['vrob_paydate'] = strtotime(input('post.pay_date'));
            $input['vrob_state'] = BILL_STATE_SUCCESS;
            $update = $vrbill_model->editVrorderbill($input, $condition);
            if ($update) {
                // 发送店铺消息
                $param = array();
                $param['code'] = 'store_bill_gathering';
                $param['store_id'] = $bill_info['vrob_store_id'];
                $param['param'] = array(
                    'bill_no' => $bill_info['vrob_no']
                );
                \mall\queue\QueueClient::push('sendStoremsg', $param);

                $this->log('账单付款,账单号：' . $ob_no, 1);
                $this->success('保存成功', 'vrbill/show_statis?os_month=' . $bill_info['vros_month']);
            } else {
                $this->log('账单付款,账单号：' . $ob_no, 1);
                $this->error('保存失败');
            }
        } else {
            return $this->fetch('bill_pay');
        }
    }

    /**
     * 打印结算单
     *
     */
    public function bill_print() {
        $ob_no = input('param.ob_no');
        if (!preg_match('/^20\d{5,12}$/', $ob_no)) {
            $this->error(lang('param_error'));
        }
        $vrbill_model = model('vrbill');
        $condition = array();
        $condition['vrob_no'] = $ob_no;
        $condition['vrob_state'] = BILL_STATE_SUCCESS;
        $bill_info = $vrbill_model->getVrorderbillInfo($condition);
        if (!$bill_info) {
            $this->error(lang('param_error'));
        }

        $this->assign('bill_info', $bill_info);

        return $this->fetch('bill_print');
    }


    /**
     * 获取卖家栏目列表,针对控制器下的栏目
     */
    protected function getAdminItemList() {
        $menu_array = array(
            array(
                'name' => 'index',
                'text' => '管理',
                'url' => url('Vrbill/index')
            ),
        );
        return $menu_array;
    }

}

?>
