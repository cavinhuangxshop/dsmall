<?php

/**
 * SNS商品
 * 
 */

namespace app\common\model;

use think\Model;

class Snssharegoods extends Model {

    /**
     * 新增分享商品
     * @access public
     * @author csdeshang
     * @param type $data 数据
     * @return boolean
     */
    public function addSnssharegoods($data) {
        if (empty($data)) {
            return false;
        }
        return db('snssharegoods')->insertGetId($data);
    }

    /**
     * 查询分享商品详细
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @param type $field 字段
     * @return type
     */
    public function getSnssharegoodsInfo($condition, $field = '*') {
        return db('snssharegoods')->where($condition)->field($field)->find();
    }

    /**
     * 更新分享商品信息
     * @access public
     * @author csdeshang
     * @param type $data 更新数据
     * @param type $condition 条件
     * @return boolean
     */
    public function editSnssharegoods($data, $condition) {
        if (empty($data)) {
            return false;
        }
        $result = db('snssharegoods')->where($condition)->update($data);
        return $result;
    }

    /**
     * 分享商品记录列表
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @param type $page 分页
     * @param type $order 排序
     * @return type
     */
    public function getSnssharegoodsList($condition, $page = '', $order = 'snssharegoods.sharegoods_addtime desc') {
        $result = db('snssharegoods')->alias('snssharegoods')
                ->join('__SNSGOODS__ snsgoods', 'snssharegoods.sharegoods_goodsid=snsgoods.snsgoods_goodsid')
                ->where($condition)
                ->order($order)
                ->paginate($page, false, ['query' => request()->param()]);
        $this->page_info = $result;
        return $result->items();
    }
    

    /**
     * 删除分享商品
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @return boolean
     */
    public function delSnssharegoods($condition) {
        if (empty($condition)) {
            return false;
        }
        return db('snssharegoods')->where($condition)->delete();
    }


}
