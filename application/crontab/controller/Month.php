<?php

namespace app\crontab\controller;

class Month extends BaseCron {

    /**
     * 默认方法
     */
    public function index(){
        $this->_create_bill();
    }

    private function _create_bill() {

        //更新订单商品佣金值
        $this->_order_commis_rate_update();

        $bill_model = model('bill');

        //实物订单结算
        try {
            $bill_model->startTrans();
            $this->_real_order();
            $bill_model->commit();
        } catch (Exception $e) {
            $bill_model->rollback();
            $this->log('实物账单:'.$e->getMessage());
        }
        //虚拟订单结算
        try {
            $bill_model->startTrans();
            $this->_vr_order();
            $bill_model->commit();
        } catch (Exception $e) {
            $bill_model->rollback();
            $this->log('虚拟账单:'.$e->getMessage());
        }

    }

    /**
     * 生成上月账单[实物订单]
     */
    private function _real_order() {
        $order_model = model('order');
        $bill_model = model('bill');
        $order_statis_max_info = $bill_model->getOrderstatisInfo(array(),'os_enddate','os_month desc');
        //计算起始时间点，自动生成以月份为单位的空结算记录
        if (!$order_statis_max_info){
            $order_min_info = $order_model->getOrderInfo(array(),array(),'min(add_time) as add_time');
            $start_unixtime = is_numeric($order_min_info['add_time']) ? $order_min_info['add_time'] : TIMESTAMP;
        } else {
            $start_unixtime = $order_statis_max_info['os_enddate'];
        }
        $data = array();
        $i = 1;
        $start_unixtime = strtotime(date('Y-m-01 00:00:00', $start_unixtime));
        $current_time = strtotime(date('Y-m-01 00:00:01',TIMESTAMP));
        
        while (($time = strtotime('-'.$i.' month',$current_time)) >= $start_unixtime) {
            if (date('Ym',$start_unixtime) == date('Ym',$time)) {
                //如果两个月份相等检查库是里否存在
                $order_statis = $bill_model->getOrderstatisInfo(array('os_month'=>date('Ym',$start_unixtime)));
                if ($order_statis) {
                    break;
                }
            }
            $first_day_unixtime = strtotime(date('Y-m-01 00:00:00', $time));	//该月第一天0时unix时间戳
            $last_day_unixtime = strtotime(date('Y-m-01 23:59:59', $time)." +1 month -1 day"); //该月最后一天最后一秒时unix时间戳
            $key = count($data);
            $os_month = date('Ym',$first_day_unixtime);
            $data[$key]['os_month'] = $os_month;
            $data[$key]['os_year'] = date('Y',$first_day_unixtime);
            $data[$key]['os_startdate'] = $first_day_unixtime;
            $data[$key]['os_enddate'] = $last_day_unixtime;
            
            //生成所有店铺月订单出账单
            $this->_create_real_order_bill($data[$key]);
            $fileds = 'sum(ob_order_totals) as ob_order_totals,sum(ob_shipping_totals) as ob_shipping_totals,
                    sum(ob_order_return_totals) as ob_order_return_totals,
                    sum(ob_commis_totals) as ob_commis_totals,sum(ob_commis_return_totals) as ob_commis_return_totals,
                    sum(ob_store_cost_totals) as ob_store_cost_totals,sum(ob_result_totals) as ob_result_totals';
            $order_bill_info = $bill_model->getOrderbillInfo(array('os_month'=>$os_month),$fileds);
            $data[$key]['os_order_totals'] = floatval($order_bill_info['ob_order_totals']);
            $data[$key]['os_shipping_totals'] = floatval($order_bill_info['ob_shipping_totals']);
            $data[$key]['os_order_returntotals'] = floatval($order_bill_info['ob_order_return_totals']);
            $data[$key]['os_commis_totals'] = floatval($order_bill_info['ob_commis_totals']);
            $data[$key]['os_commis_returntotals'] = floatval($order_bill_info['ob_commis_return_totals']);
            $data[$key]['os_store_costtotals'] = floatval($order_bill_info['ob_store_cost_totals']);
            $data[$key]['os_result_totals'] = floatval($order_bill_info['ob_result_totals']);
            $i++;
        }
        krsort($data);
        foreach ($data as $v) {
            $insert = $bill_model->addOrderstatis($v);
            if (!$insert) {
                exception('生成平台月出账单['.$v['os_month'].']失败');
            }
        }
    }

    /**
     * 生成所有店铺月订单出账单[实物订单]
     *
     * @param int $data
     */
    private function _create_real_order_bill($data){
        $order_model = model('order');
        $bill_model = model('bill');
        $store_model = model('store');
    
        //批量插件order_bill表
        //         $condition = array();
        //         $condition['order_state'] = ORDER_STATE_SUCCESS;
        //         $condition['finnshed_time'] = array(array('egt',$data['os_startdate']),array('elt',$data['os_enddate']),'and');
        //         取出有最终成交订单的店铺ID数量（ID不重复）
        //         $store_count =  $order_model->getOrderInfo($condition,array(),'count(DISTINCT store_id) as c');
        //         $store_count = $store_count['c'];
    
        //取店铺表数量(因为可能存在无订单，但有店铺活动费用，所以不再从订单表取店铺数量)
        $store_count = $store_model->getStoreCount(array());
        
        //分批生成该月份的店铺空结算表，每批生成300个店铺
        $insert = false;
        for ($i=0;$i<=$store_count;$i=$i+300){
            $store_list = $store_model->getStoreList(array(),null,'','store_id',"{$i},300");
            if ($store_list){
                //自动生成以月份为单位的空结算记录
                $data_bill = array();
                
                foreach($store_list as $store_info){
                    $data_bill['ob_no'] = $data['os_month'].$store_info['store_id'];
                    $data_bill['ob_startdate'] = $data['os_startdate'];
                    $data_bill['ob_enddate'] = $data['os_enddate'];
                    $data_bill['os_month'] = $data['os_month'];
                    $data_bill['ob_state'] = 0;
                    $data_bill['ob_store_id'] = $store_info['store_id'];
                    if (!$bill_model->getOrderbillInfo(array('ob_no'=>$data_bill['ob_no']))) {
                        $insert = $bill_model->addOrderbill($data_bill);
                        if (!$insert) {
                            exception('生成账单['.$data_bill['ob_no'].']失败');
                        }
                        //对已生成空账单进行销量、退单、佣金统计
                        $update = $this->_calc_real_order_bill($data_bill);
                        if (!$update){
                            exception('更新账单['.$data_bill['ob_no'].']失败');
                        }
                        
                        // 发送店铺消息
                        $param = array();
                        $param['code'] = 'store_bill_affirm';
                        $param['store_id'] = $store_info['store_id'];
                        $param['param'] = array(
                            'state_time' => date('Y-m-d H:i:s', $data_bill['ob_startdate']),
                            'end_time' => date('Y-m-d H:i:s', $data_bill['ob_enddate']),
                            'bill_no' => $data_bill['ob_no']
                        );
                        \mall\queue\QueueClient::push('sendStoremsg', $param);
                    }
                }
            }
        }
    }

    /**
     * 计算某月内，某店铺的销量，退单量，佣金[实物订单]
     *
     * @param array $data_bill
     */
    private function _calc_real_order_bill($data_bill){
        $order_model = model('order');
        $bill_model = model('bill');
        $store_model = model('store');

        $order_condition = array();
        $order_condition['order_state'] = ORDER_STATE_SUCCESS;
        $order_condition['store_id'] = $data_bill['ob_store_id'];
        $order_condition['finnshed_time'] = array('between',"{$data_bill['ob_startdate']},{$data_bill['ob_enddate']}");

        $update = array();

        //订单金额
        $fields = 'sum(order_amount) as order_amount,sum(shipping_fee) as shipping_amount,store_name';
        $order_info =  $order_model->getOrderInfo($order_condition,array(),$fields);
        $update['ob_order_totals'] = floatval($order_info['order_amount']);
        //运费
        $update['ob_shipping_totals'] = floatval($order_info['shipping_amount']);
        //店铺名字
        $store_info = $store_model->getStoreInfoByID($data_bill['ob_store_id']);
        $update['ob_store_name'] = $store_info['store_name'];
    
        //佣金金额
        $order_info =  $order_model->getOrderInfo($order_condition,array(),'count(DISTINCT order_id) as count');
        $order_count = $order_info['count'];
        $commis_rate_totals_array = array();
        //分批计算佣金，最后取总和
        for ($i = 0; $i <= $order_count; $i = $i + 300){
            $order_list = $order_model->getOrderList($order_condition,'','order_id','',"{$i},300");
            $order_id_array = array();
            foreach ($order_list as $order_info) {
                $order_id_array[] = $order_info['order_id'];
            }
            if (!empty($order_id_array)){
                $order_goods_condition = array();
                $order_goods_condition['order_id'] = array('in',$order_id_array);
                $field = 'SUM(ROUND(goods_pay_price*commis_rate/100,2)) as commis_amount';
                $order_goods_info = $order_model->getOrdergoodsInfo($order_goods_condition,$field);
                $commis_rate_totals_array[] = $order_goods_info['commis_amount'];
            }else{
                $commis_rate_totals_array[] = 0;
            }
        }
        $update['ob_commis_totals'] = floatval(array_sum($commis_rate_totals_array));

        //退款总额
        $refundreturn_model = model('refundreturn');
        $refund_condition = array();
        $refund_condition['seller_state'] = 2;
        $refund_condition['store_id'] = $data_bill['ob_store_id'];
        $refund_condition['goods_id'] = array('gt',0);
        $refund_condition['admin_time'] = array(array('egt',$data_bill['ob_startdate']),array('elt',$data_bill['ob_enddate']),'and');
        $refund_info = $refundreturn_model->getRefundreturnInfo($refund_condition,'sum(refund_amount) as amount');
        $update['ob_order_return_totals'] = floatval($refund_info['amount']);

        //退款佣金
        $refund  =  $refundreturn_model->getRefundreturnInfo($refund_condition,'sum(ROUND(refund_amount*commis_rate/100,2)) as amount');
        if ($refund) {
            $update['ob_commis_return_totals'] = floatval($refund['amount']);
        } else {
            $update['ob_commis_return_totals'] = 0;
        }

        //店铺活动费用
        $storecost_model = model('storecost');
        $cost_condition = array();
        $cost_condition['storecost_store_id'] = $data_bill['ob_store_id'];
        $cost_condition['storecost_state'] = 0;
        $cost_condition['storecost_time'] = array(array('egt',$data_bill['ob_startdate']),array('elt',$data_bill['ob_enddate']),'and');
        $cost_info = $storecost_model->getStorecostInfo($cost_condition,'sum(storecost_price) as cost_amount');
        $update['ob_store_cost_totals'] = floatval($cost_info['cost_amount']);

        //本期应结
        $update['ob_result_totals'] = $update['ob_order_totals'] - $update['ob_order_return_totals'] -
        $update['ob_commis_totals'] + $update['ob_commis_return_totals']-
        $update['ob_store_cost_totals'];
        $update['ob_createdate'] = TIMESTAMP;
        $update['ob_state'] = 1;
        return $bill_model->editOrderbill($update,array('ob_no'=>$data_bill['ob_no']));
    }

    /**
     * 生成上月账单[虚拟订单]
     */
    private function _vr_order() {
        $vrorder_model = model('vrorder');
        $vrbill_model = model('vrbill');
        $order_statis_max_info = $vrbill_model->getVrorderstatisInfo(array(),'vros_enddate','vros_month desc');
        //计算起始时间点，自动生成以月份为单位的空结算记录
        if (!$order_statis_max_info){
            $order_min_info = $vrorder_model->getVrorderInfo(array(),'min(add_time) as add_time');
            $start_unixtime = is_numeric($order_min_info['add_time']) ? $order_min_info['add_time'] : TIMESTAMP;
        } else {
            $start_unixtime = $order_statis_max_info['vros_enddate'];
        }
        $data = array();
        $i = 1;
        $start_unixtime = strtotime(date('Y-m-01 00:00:00', $start_unixtime));
        $current_time = strtotime(date('Y-m-01 00:00:01',TIMESTAMP));
        while (($time = strtotime('-'.$i.' month',$current_time)) >= $start_unixtime) {
            if (date('Ym',$start_unixtime) == date('Ym',$time)) {
                //如果两个月份相等检查库是里否存在
                $order_statis = $vrbill_model->getVrorderstatisInfo(array('vros_month'=>date('Ym',$start_unixtime)));
                if ($order_statis) {
                    break;
                }
            }
            $first_day_unixtime = strtotime(date('Y-m-01 00:00:00', $time));	//该月第一天0时unix时间戳
            $last_day_unixtime = strtotime(date('Y-m-01 23:59:59', $time)." +1 month -1 day"); //该月最后一天最后一秒时unix时间戳
            $key = count($data);
            $os_month = date('Ym',$first_day_unixtime);
            $data[$key]['vros_month'] = $os_month;
            $data[$key]['vros_year'] = date('Y',$first_day_unixtime);
            $data[$key]['vros_startdate'] = $first_day_unixtime;
            $data[$key]['vros_enddate'] = $last_day_unixtime;
    
            //生成所有店铺月订单出账单
            $this->_create_vr_order_bill($data[$key]);
    
            $fileds = 'sum(vrob_order_totals) as vrob_order_totals,
                    sum(vrob_commis_totals) as vrob_commis_totals,sum(vrob_result_totals) as vrob_result_totals';
            $order_bill_info = $vrbill_model->getVrorderbillInfo(array('vros_month'=>$os_month),$fileds);
            $data[$key]['vros_order_totals'] = floatval($order_bill_info['vrob_order_totals']);
            $data[$key]['vros_commis_totals'] = floatval($order_bill_info['vrob_commis_totals']);
            $data[$key]['vros_result_totals'] = floatval($order_bill_info['vrob_result_totals']);
            $i++;
        }
        krsort($data);
        foreach ($data as $v) {
            $insert = $vrbill_model->addVrorderstatis($v);
            if (!$insert) {
                exception('生成平台月出账单['.$v['vros_month'].']失败');
            }
        }
    }

    /**
     * 生成所有店铺月订单出账单[虚拟订单]
     *
     * @param int $data
     */
    private function _create_vr_order_bill($data){
        $vrorder_model = model('vrorder');
        $vrbill_model = model('vrbill');
        $store_model = model('store');
    
        //批量插入order_bill表
        $condition = array();
        $condition['order_state'] = array('egt',ORDER_STATE_PAY);
        $condition['payment_time'] = array(array('egt',$data['vros_startdate']),array('elt',$data['vros_enddate']),'and');
        //取出有最终成交订单的店铺ID数量（ID不重复）
        $order_info =  $vrorder_model->getVrorderInfo($condition,'count(DISTINCT store_id) as store_count');
        $store_count = $order_info['store_count'];
        //分批生成该月份的店铺空结算表，每批生成300个店铺
        $insert = false;
        for ($i=0;$i<=$store_count;$i=$i+300){
            $store_list = $vrorder_model->getVrorderList($condition,'','DISTINCT store_id','',"{$i},300");
            if ($store_list){
                //自动生成以月份为单位的空结算记录
                $data_bill = array();
                foreach($store_list as $store_info){
                    $data_bill['vrob_no'] = $data['vros_month'].$store_info['store_id'];
                    $data_bill['vrob_startdate'] = $data['vros_startdate'];
                    $data_bill['vrob_enddate'] = $data['vros_enddate'];
                    $data_bill['vros_month'] = $data['vros_month'];
                    $data_bill['vrob_state'] = 0;
                    $data_bill['vrob_store_id'] = $store_info['store_id'];
                    if (!$vrbill_model->getVrorderbillInfo(array('vrob_no'=>$data_bill['vrob_no']))) {
                        $insert = $vrbill_model->addVrorderbill($data_bill);
                        if (!$insert) {
                            exception('生成账单['.$data_bill['vrob_no'].']失败');
                        }
                        //对已生成空账单进行销量、佣金统计
                        $update = $this->_calc_vr_order_bill($data_bill);
                        if (!$update){
                            exception('更新账单['.$data_bill['vrob_no'].']失败');
                        }

                        // 发送店铺消息
                        $param = array();
                        $param['code'] = 'store_bill_affirm';
                        $param['store_id'] = $store_info['store_id'];
                        $param['param'] = array(
                                'state_time' => date('Y-m-d H:i:s', $data_bill['vrob_startdate']),
                                'end_time' => date('Y-m-d H:i:s', $data_bill['vrob_enddate']),
                                'bill_no' => $data_bill['vrob_no']
                        );
                        \mall\queue\QueueClient::push('sendStoremsg', $param);
                    }
                }
            }
        }
    }
    
    /**
     * 计算某月内，某店铺的销量，佣金
     *
     * @param array $data_bill
     */
    private function _calc_vr_order_bill($data_bill){
        $vrorder_model = model('vrorder');
        $vrbill_model = model('vrbill');
        $store_model = model('store');

        //计算已使用兑换码
        $order_condition = array();
        $order_condition['vr_state'] = 1;
        $order_condition['store_id'] = $data_bill['vrob_store_id'];
        $order_condition['vr_usetime'] = array('between',"{$data_bill['vrob_startdate']},{$data_bill['vrob_enddate']}");

        $update = array();

        //订单金额
        $fields = 'sum(pay_price) as order_amount,SUM(ROUND(pay_price*commis_rate/100,2)) as commis_amount';
        $order_info =  $vrorder_model->getVrordercodeInfo($order_condition, $fields);
        $update['vrob_order_totals'] = floatval($order_info['order_amount']);

        //佣金金额
        $update['vrob_commis_totals'] = $order_info['commis_amount'];

        //计算已过期不退款兑换码
        $order_condition = array();
        $order_condition['vr_state'] = 0;
        $order_condition['store_id'] = $data_bill['vrob_store_id'];
        $order_condition['vr_invalid_refund'] = 0;
        $order_condition['vr_indate'] = array('between',"{$data_bill['vrob_startdate']},{$data_bill['vrob_enddate']}");

        //订单金额
        $fields = 'sum(pay_price) as order_amount,SUM(ROUND(pay_price*commis_rate/100,2)) as commis_amount';
        $order_info =  $vrorder_model->getVrordercodeInfo($order_condition, $fields);
        $update['vrob_order_totals'] += floatval($order_info['order_amount']);

        //佣金金额
        $update['vrob_commis_totals'] += $order_info['commis_amount'];

        //店铺名
        $store_info = $store_model->getStoreInfoByID($data_bill['vrob_store_id']);
        $update['vrob_store_name'] = $store_info['store_name'];

        //本期应结
        $update['vrob_result_totals'] = $update['vrob_order_totals'] - $update['vrob_commis_totals'];
        $update['vrob_createdate'] = TIMESTAMP;
        $update['vrob_state'] = 1;
        return $vrbill_model->editVrorderbill($update,array('vrob_no'=>$data_bill['vrob_no']));
    }
    
}
?>
