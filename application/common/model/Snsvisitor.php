<?php

/**
 * SNS访客
 *
 */

namespace app\common\model;

use think\Model;

class Snsvisitor extends Model {

    public $page_info;

    /**
     * 新增访客
     * @access public
     * @author csdeshang
     * @param type $data 数据
     * @return type
     */
    public function addSnsvisitor($data) {
        return db('snsvisitor')->insertGetId($data);
    }

    /**
     * 访客列表
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @param type $page 分页
     * @param type $field 字段
     * @param type $order 排序
     * @return type
     */
    public function getSnsvisitorList($condition, $page = '', $field = '*',$order='snsvisitor_addtime desc') {
        $result = db('snsvisitor')->field($field)->where($condition)->order($order)->paginate($page, false, ['query' => request()->param()]);
        $this->page_info = $result;
        $list = $result->items();
        return $list;
    }

    /**
     * 获取访客记录详细
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @param type $field 字段
     * @return type
     */
    public function getOneSnsvisitor($condition, $field = '*') {
        return db('snsvisitor')->where($condition)->field($field)->select();
    }

    /**
     * 更新访客记录
     * @access public
     * @author csdeshang
     * @param type $data 数据
     * @param type $condition 条件
     * @return boolean
     */
    public function editSnsvisitor($data, $condition) {
        if (empty($data)) {
            return false;
        }
        $result = db('snsvisitor')->where($condition)->update($data);
        return $result;
    }


}
