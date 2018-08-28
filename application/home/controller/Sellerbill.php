<?php

namespace app\home\controller;

use think\Lang;

class Sellerbill extends BaseSeller {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/sellerbill.lang.php');
    }

    /**
     * 结算列表
     *
     */
    public function index() {
        $bill_model = model('bill');
        $condition = array();
        $condition['ob_store_id'] = session('store_id');

        $ob_no = input('param.ob_no');
        if (preg_match('/^20\d{5,12}$/', $ob_no)) {
            $condition['ob_no'] = $ob_no;
        }
        $bill_state = intval(input('bill_state'));
        if ($bill_state) {
            $condition['ob_state'] = $bill_state;
        }

        
        $bill_list = $bill_model->getOrderbillList($condition, '*', 12, 'ob_state asc,ob_no asc');
        $this->assign('bill_list', $bill_list);
        $this->assign('show_page', $bill_model->page_info->render());

        /* 设置卖家当前菜单 */
        $this->setSellerCurMenu('Sellerbill');
        /* 设置卖家当前栏目 */
        $this->setSellerCurItem('seller_slide');
        return $this->fetch($this->template_dir.'index');
    }

    /**
     * 查看结算单详细
     *
     */
    public function show_bill() {
        $ob_no = input('param.ob_no');
        if (!preg_match('/^20\d{5,12}$/', $ob_no)) {
            $this->error('参数错误');
        }
        if (substr($ob_no, 6) != session('store_id')) {
            $this->error('参数错误');
        }
        $bill_model = model('bill');
        $bill_info = $bill_model->getOrderbillInfo(array('ob_no' => $ob_no));
        if (!$bill_info) {
            $this->error('参数错误');
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
        if ($if_start_date || $if_end_date) {
            $order_condition['finnshed_time'] = array('between', array($start_unixtime, $end_unixtime));
        } else {
            $order_condition['finnshed_time'] = array('between', "{$bill_info['ob_startdate']},{$bill_info['ob_enddate']}");
        }
        $query_order_no = input('get.query_order_no');
        $type = input('param.type');
        if ($type == 'refund') {
            if (preg_match('/^\d{8,20}$/', $query_order_no)) {
                $order_condition['refund_sn'] = $query_order_no;
            }
            //退款订单列表
            $refundreturn_model = model('refundreturn');
            $refund_condition = array();
            $refund_condition['seller_state'] = 2;
            $refund_condition['store_id'] = $bill_info['ob_store_id'];
            $refund_condition['goods_id'] = array('gt', 0);
            $refund_condition['admin_time'] = $order_condition['finnshed_time'];
            if (preg_match('/^\d{8,20}$/', $query_order_no)) {
                $refund_condition['refund_sn'] = $query_order_no;
            }

            $refund_list = $refundreturn_model->getRefundreturnList($refund_condition, 20, '*,ROUND(refund_amount*commis_rate/100,2) as commis_amount');
            if (is_array($refund_list) && count($refund_list) == 1 && $refund_list[0]['refund_id'] == '') {
                $refund_list = array();
            }
            //取返还佣金
            $this->assign('refund_list', $refund_list);
            $this->assign('show_page', $refundreturn_model->page_info->render());

            $sub_tpl_name = 'show_refund_list';
            /* 设置卖家当前菜单 */
            $this->setSellerCurMenu('Sellerbill');
            /* 设置卖家当前栏目 */
            $this->setSellerCurItem('seller_slide');
        } elseif ($type == 'cost') {
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
            /* 设置卖家当前菜单 */
            $this->setSellerCurMenu('Sellerbill');
            /* 设置卖家当前栏目 */
            $this->setSellerCurItem('seller_slide');
        } else {

            if (preg_match('/^\d{8,20}$/', $query_order_no)) {
                $order_condition['order_sn'] = $query_order_no;
            }
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

            $order_list_page = db('order')->where($order_condition)->paginate(20,false,['query' => request()->param()]);
            $this->assign('show_page', $order_list_page->render());
            $sub_tpl_name = 'show_order_list';
            /* 设置卖家当前菜单 */
            $this->setSellerCurMenu('Sellerbill');
            /* 设置卖家当前栏目 */
            $this->setSellerCurItem('seller_slide');
        }
        $this->assign('bill_info', $bill_info);

        return $this->fetch($this->template_dir.$sub_tpl_name);
    }

    /**
     * 打印结算单
     *
     */
    public function bill_print() {
        $ob_no = input('param.ob_no');
        if (!preg_match('/^20\d{5,12}$/', $ob_no)) {
            $this->error('参数错误');
        }
        if (substr($ob_no, 6) != session('store_id')) {
            $this->error('参数错误');
        }
        $bill_model = model('bill');
        $condition = array();
        $condition['ob_no'] = $ob_no;
        $condition['ob_state'] = BILL_STATE_SUCCESS;
        $bill_info = $bill_model->getOrderbillInfo($condition);
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
        $ob_no = input('param.ob_no');
        if (!preg_match('/^20\d{5,12}$/', $ob_no)) {
            ds_show_dialog('参数错误', '', 'error');
        }
        $bill_model = model('bill');
        $condition = array();
        $condition['ob_no'] = $ob_no;
        $condition['ob_store_id'] = session('store_id');
        $condition['ob_state'] = BILL_STATE_CREATE;
        $update = $bill_model->editOrderbill(array('ob_state' => BILL_STATE_STORE_COFIRM), $condition);
        if ($update) {
            ds_show_dialog('确认成功', 'reload', 'succ');
        } else {
            ds_show_dialog(lang('ds_common_op_fail'), 'reload', 'error');
        }
    }



    /**
     * 用户中心右边，小导航
     *
     * @param string $menu_type 导航类型
     * @param string $menu_key 当前导航的menu_key
     * @return
     */
    function getSellerItemList() {
        $ob_no = input('param.ob_no');
        if (request()->action()=='index') {
            $menu_array = array(
                array(
                    'name' => 'list',
                    'text' => '实物订单结算',
                    'url' => url('Sellerbill/index')
                ),
            );
        }else if(request()->action()=='show_bill'){
            $menu_array = array(
                array(
                    'name' => 'order_list',
                    'text' => '订单列表',
                    'url' => url('Sellerbill/show_bill', ['ob_no' => $ob_no])
                ),
                array(
                    'name' => 'refund_list',
                    'text' => '退款订单',
                    'url' => url('Sellerbill/show_bill', ['type'=>'refund','ob_no' => $ob_no])
                ),
                array(
                    'name' => 'cost_list',
                    'text' => '促销费用',
                    'url' => url('Sellerbill/show_bill', ['type'=>'cost','ob_no' => $ob_no])
                ),
                
            );
        }
        return $menu_array;
    }

}

?>
