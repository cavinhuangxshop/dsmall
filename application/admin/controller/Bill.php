<?php

namespace app\admin\controller;

use think\Lang;
use think\Validate;

class Bill extends AdminControl {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'admin/lang/zh-cn/bill.lang.php');
    }

    /**
     * 所有月份销量账单
     *
     */
    public function index() {
        //检查是否需要生成上月及更早结算单的程序不再执行，执行量较大，放到任务计划中触发
        $condition = array();
        $query_year = input('get.query_year');
        if (preg_match('/^\d{4}$/', $query_year, $match)) {
            $condition['os_year'] = $query_year;
        }
        $bill_model = model('bill');
        $bill_list = $bill_model->getOrderstatisList($condition, '*', 12, 'os_month desc');
        $this->assign('bill_list', $bill_list);
        $this->assign('show_page', $bill_model->page_info->render());
        
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
        $bill_model = model('bill');
        $condition = array();
        if (!empty($os_month)) {
            $condition['os_month'] = intval($os_month);
        }
        $bill_state = input('get.bill_state');
        if (is_numeric($bill_state)) {
            $condition['ob_state'] = intval($bill_state);
        }
        $query_store = input('get.query_store');
        if (preg_match('/^\d{1,8}$/', $query_store)) {
            $condition['ob_store_id'] = $query_store;
        } elseif ($query_store != '') {
            $condition['ob_store_name'] = $query_store;
        }
        $bill_list = $bill_model->getOrderbillList($condition, '*', 30, 'ob_no desc');
        $this->assign('bill_list', $bill_list);
        $this->assign('show_page', $bill_model->page_info->render());

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
        $bill_model = model('bill');
        $bill_info = $bill_model->getOrderbillInfo(array('ob_no' => $ob_no));
        if (!$bill_info) {
            $this->error(lang('param_error'));
        }

        $order_condition = array();
        $order_condition['order_state'] = ORDER_STATE_SUCCESS;
        $order_condition['store_id'] = $bill_info['ob_store_id'];
        
        $query_start_date = input('get.query_start_date');
        $query_end_date = input('get.query_end_date');
        $if_start_date = preg_match('/^20\d{2}-\d{2}-\d{2}$/', $query_start_date);
        $if_end_date = preg_match('/^20\d{2}-\d{2}-\d{2}$/', $query_end_date);
        $start_unixtime = $if_start_date ? strtotime($query_start_date) : null;
        $end_unixtime = $if_end_date ? strtotime($query_end_date) : null;
        
        $end_unixtime = $if_end_date ? $end_unixtime + 86400 - 1 : null;
        if ($if_start_date || $if_end_date) {
            $order_condition['finnshed_time'] = array('between', "{$start_unixtime},{$end_unixtime}");
        } else {
            $order_condition['finnshed_time'] = array('between', "{$bill_info['ob_startdate']},{$bill_info['ob_enddate']}");
        }
        
        $query_type = input('param.query_type');
        if ($query_type == 'refund') {
            //退款订单列表
            $refundreturn_model = model('refundreturn');
            $refund_condition = array();
            $refund_condition['seller_state'] = 2;
            $refund_condition['store_id'] = $bill_info['ob_store_id'];
            $refund_condition['goods_id'] = array('gt', 0);
            $refund_condition['admin_time'] = $order_condition['finnshed_time'];
            $refund_list = $refundreturn_model->getRefundreturnList($refund_condition, 20, '*,ROUND(refund_amount*commis_rate/100,2) as commis_amount');
            if (is_array($refund_list) && count($refund_list) == 1 && $refund_list[0]['refund_id'] == '') {
                $refund_list = array();
            }
            //取返还佣金
            $this->assign('refund_list', $refund_list);
            $this->assign('show_page', $refundreturn_model->page_info->render());
            $sub_tpl_name = 'show_refund_list';
        } elseif ($query_type == 'cost') {

            //店铺费用
            $storecost_model = model('storecost');
            $cost_condition = array();
            $cost_condition['storecost_store_id'] = $bill_info['ob_store_id'];
            $cost_condition['storecost_time'] = $order_condition['finnshed_time'];
            $store_cost_list = $storecost_model->getStorecostList($cost_condition, 20);
            //取得店铺名字
            $store_info = model('store')->getStoreInfoByID($bill_info['ob_store_id']);
            $this->assign('cost_list', $store_cost_list);
            $this->assign('store_info', $store_info);
            $this->assign('show_page', $storecost_model->page_info->render());
            $sub_tpl_name = 'show_cost_list';
        } else {

            //订单列表
            $order_model = model('order');
            $order_list = $order_model->getOrderList($order_condition, 20);

            //然后取订单商品佣金
            $order_id_array = array();
            if (is_array($order_list)) {
                foreach ($order_list as $order_info) {
                    $order_id_array[] = $order_info['order_id'];
                }
            }
            $order_goods_condition = array();
            $order_goods_condition['order_id'] = array('in', $order_id_array);
            $field = 'SUM(ROUND(goods_pay_price*commis_rate/100,2)) as commis_amount,order_id';
            $commis_list = $order_model->getOrdergoodsList($order_goods_condition, $field, null, null, '', 'order_id', 'order_id');
            $this->assign('commis_list', $commis_list);
            $this->assign('order_list', $order_list);
            $this->assign('show_page', $order_model->page_info->render());
            $sub_tpl_name = 'show_order_list';
        }

        $this->assign('bill_info', $bill_info);
        $this->setAdminCurItem('show_bill');
        return $this->fetch($sub_tpl_name);
    }

    public function bill_check() {
        $ob_no = input('param.ob_no');
        if (!preg_match('/^20\d{5,12}$/', $ob_no)) {
            $this->error(lang('param_error'));
        }
        $bill_model = model('bill');
        $condition = array();
        $condition['ob_no'] = $ob_no;
        $condition['ob_state'] = BILL_STATE_STORE_COFIRM;
        $update = $bill_model->editOrderbill(array('ob_state' => BILL_STATE_SYSTEM_CHECK), $condition);
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
        $bill_model = model('bill');
        $condition = array();
        $condition['ob_no'] = $ob_no;
        $condition['ob_state'] = BILL_STATE_SYSTEM_CHECK;
        $bill_info = $bill_model->getOrderbillInfo($condition);
        if (!$bill_info) {
            $this->error(lang('param_error'));
        }
        if (request()->isPost()) {
            if (!preg_match('/^20\d{2}-\d{2}-\d{2}$/', input('param.pay_date'))) {
                $this->error(lang('param_error'));
            }
            $input = array();
            $input['ob_pay_content'] = input('pay_content');
            $input['ob_paydate'] = strtotime(input('param.pay_date'));
            $input['ob_state'] = BILL_STATE_SUCCESS;
            $update = $bill_model->editOrderbill($input, $condition);
            if ($update) {
                $storecost_model = model('storecost');
                $cost_condition = array();
                $cost_condition['storecost_store_id'] = $bill_info['ob_store_id'];
                $cost_condition['storecost_state'] = 0;
                $cost_condition['storecost_time'] = array('between', "{$bill_info['ob_startdate']},{$bill_info['ob_enddate']}");
                $storecost_model->editStorecost(array('storecost_state' => 1), $cost_condition);

                // 发送店铺消息
                $param = array();
                $param['code'] = 'store_bill_gathering';
                $param['store_id'] = $bill_info['ob_store_id'];
                $param['param'] = array(
                    'bill_no' => $bill_info['ob_no']
                );
                \mall\queue\QueueClient::push('sendStoremsg', $param);

                $this->log('账单付款,账单号：' . $ob_no, 1);
                $this->success('保存成功', 'bill/show_statis?os_month=' . $bill_info['os_month']);
            } else {
                $this->log('账单付款,账单号：' . $ob_no, 1);
                $this->error('保存失败');
            }
        } else {
            $this->setAdminCurItem('bill_pay');
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
        $bill_model = model('bill');
        $condition = array();
        $condition['ob_no'] = $ob_no;
        $condition['ob_state'] = BILL_STATE_SUCCESS;
        $bill_info = $bill_model->getOrderbillInfo($condition);
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
                'text' => '结算管理',
                'url' => url('Bill/index')
            ),
        );
        if (request()->action() == 'show_statis') {
            $title = !empty(input('param.os_month'))?input('param.os_month').'期':'';
            $menu_array[] = array(
                'name' => 'show_statis',
                'text' => $title.'商家账单列表',
                'url' => !empty($title) ? url('Bill/show_statis',['os_month'=>input('param.os_month')]) : url('Bill/show_statis'),
            );
        }
        if (request()->action() == 'show_bill') {
            $menu_array[] = array(
                'name' => 'show_bill',
                'text' => '账单明细',
                'url' => 'javascript:void(0)',
            );
        }
        return $menu_array;
    }
}

?>
