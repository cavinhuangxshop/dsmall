<?php

namespace app\common\model;

use think\Model;

class Snsgoods extends Model {

    /**
     * 查询SNS商品详细
     * @access public
     * @author csdeshang
     * @param array $condition 条件
     * @param string $field 字段
     * @return array
     */
    public function getSnsgoodsInfo($condition, $field = '*') {
        $result = db('snsgoods')->field($field)->where($condition)->find();
        return $result;
    }

    /**
     * 新增SNS商品
     * @access public
     * @author csdeshang
     * @param $data 添加数据数组
     * @return bool
     */
    public function addSnsgoods($data) {
        return db('snsgoods')->insert($data);
    }

    /**
     * 更新SNS商品信息
     * @access public
     * @author csdeshang
     * @param type $data 数据
     * @param type $condition 条件
     * @return type
     */
    public function editSnsgoods($data, $condition) {
        return db('snsgoods')->where($condition)->update($data);
    }


}

?>
