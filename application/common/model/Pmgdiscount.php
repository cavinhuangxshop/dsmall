<?php

namespace app\common\model;

use think\Model;

class Pmgdiscount extends Model {

    public function getPmgdiscountInfoByGoodsInfo($goods_info) {
        //判断店铺是否开启会员折扣
        $store = db('store')->where('store_id',$goods_info['store_id'])->find();
        if($store['store_mgdiscount_state'] != 1){
            return ;
        }
        //查看此商品是否单独设置了折扣
        if($goods_info['goods_mgdiscount'] != ''){
            return unserialize($goods_info['goods_mgdiscount']);
        }
        //当店铺设置了店铺会员等级折扣
        if($store['store_mgdiscount'] != ''){
            return unserialize($store['store_mgdiscount']);
        }
        return;
    }

}
