<?php

namespace app\common\model;

use think\Model;

class Orderinviter extends Model {
    
    /**
     * 支付给钱
     * @access public
     * @author csdeshang
     * @param type $order_id 订单编号
     */
    public function giveMoney($order_id) {
        $orderinviter_list = db('orderinviter')->where('orderinviter_order_id', $order_id)->select();
        if ($orderinviter_list) {
            $predeposit_model = model('predeposit');
            foreach ($orderinviter_list as $val) {
                try {
                    $predeposit_model->startTrans();
                    $data = array();
                    $data['member_id'] = $val['orderinviter_member_id'];
                    $data['member_name'] = $val['orderinviter_member_name'];
                    $data['amount'] = $val['orderinviter_money'];
                    $data['order_sn'] = $val['orderinviter_order_sn'];
                    $data['lg_desc'] = $val['orderinviter_remark'];
                    $predeposit_model->changePd('order_inviter', $data);
                    db('orderinviter')->where('orderinviter_id', $val['orderinviter_id'])->update(['orderinviter_valid' => 1]);
                    $predeposit_model->commit();
                } catch (Exception $e) {
                    $predeposit_model->rollback();
                }
            }
        }
    }

}
