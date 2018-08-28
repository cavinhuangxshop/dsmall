<?php

/**
 * 分享店铺
 */

namespace app\common\model;

use think\Model;

class Snssharestore extends Model {

    public $page_info;

    /**
     * 新增分享店铺
     * @access public
     * @author csdeshang 
     * @param type $data 参数内容
     * @return boolean
     */
    public function addSnssharestore($data) {
        if (empty($data)) {
            return false;
        }
        return db('snssharestore')->insertGetId($data);
    }

    /**
     * 查询分享店铺详细
     * @access public
     * @author csdeshang 
     * @param type $condition 条件
     * @param type $field 字段
     * @return type
     */
    public function getSnssharestoreInfo($condition, $field = '*') {
        return db('snssharestore')->where($condition)->field($field)->find();
    }

    /**
     * 更新分享店铺信息
     * @access public
     * @author csdeshang 
     * @param type $data 数据
     * @param type $condition 条件
     * @return boolean
     */
    public function editSnssharestore($data, $condition) {
        if (empty($data)) {
            return false;
        }
        $result = db('snssharestore')->where($condition)->update($data);
        return $result;
    }

    /**
     * 分享店铺记录列表
     * @access public
     * @author csdeshang
     * @param $condition 条件
     * @param $page 分页
     * @param $field 查询字段
     * @return array 数组格式的返回结果
     */
    public function getSnssharestoreList($condition, $page = '', $field = '*') {
        $order = 'snssharestore.sharestore_addtime desc';
        if ($page) {
            $result = db('snssharestore')->alias('snssharestore')
                    ->field($field)
                    ->join('__STORE__ store', 'snssharestore.sharestore_storeid=store.store_id')
                    ->where($condition)
                    ->order($order)
                    ->paginate($page, false, ['query' => request()->param()]);
            $this->page_info = $result;
            return $result->items();
        } else {
            return db('snssharestore')->alias('snssharestore')
                            ->field($field)
                            ->join('__STORE__ store', 'snssharestore.sharestore_storeid=store.store_id')
                            ->where($condition)
                            ->order($order)
                            ->select();
        }
    }

    /**
     * 删除分享商品
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @return boolean
     */
    public function delSnssharestore($condition) {
        if (empty($condition)) {
            return false;
        }
        return db('snssharestore')->where($condition)->delete();
    }
}
