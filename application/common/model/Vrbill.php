<?php

namespace app\common\model;

use think\Model;

//以下是定义结算单状态
//默认
if (!defined('BILL_STATE_CREATE')) {
    define('BILL_STATE_CREATE', 1);
}
//店铺已确认
if (!defined('BILL_STATE_STORE_COFIRM')) {
    define('BILL_STATE_STORE_COFIRM', 2);
}
//平台已审核
if (!defined('BILL_STATE_SYSTEM_CHECK')) {
    define('BILL_STATE_SYSTEM_CHECK', 3);
}
//结算完成
if (!defined('BILL_STATE_SUCCESS')) {
    define('BILL_STATE_SUCCESS', 4);
}

class Vrbill extends Model {

    public $page_info;

    /**
     * 取得平台月结算单
     * @access public
     * @author csdeshang
     * @param array $condition 条件
     * @param str $fields 字段
     * @param int $pagesize 分页
     * @param str $order 排序
     * @param int $limit 限制
     * @return array
     */
    public function getVrorderstatisList($condition = array(), $fields = '*', $pagesize = null, $order = '', $limit = null) {
        if ($pagesize) {
            $res = db('vrorderstatis')->where($condition)->field($fields)->order($order)->paginate($pagesize,false,['query' => request()->param()]);
            $this->page_info = $res;
            return $res->items();
        } else {
            return db('vrorderstatis')->where($condition)->field($fields)->order($order)->limit($limit)->select();
        }
    }

    /**
     * 取得平台月结算单条信息
     * @access public
     * @author csdeshang
     * @param array $condition 条件
     * @param str $fields 字段
     * @param str $order 排序
     * @return array
     */
    public function getVrorderstatisInfo($condition = array(), $fields = '*', $order = null) {
        return db('vrorderstatis')->where($condition)->field($fields)->order($order)->find();
    }

    /**
     * 取得店铺月结算单列表
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @param type $fields 字段
     * @param type $pagesize 分页
     * @param type $order 排序
     * @param type $limit 限制
     * @return type
     */
    public function getVrorderbillList($condition = array(), $fields = '*', $pagesize = null, $order = '', $limit = null) {
        if ($pagesize) {
            $res = db('vrorderbill')->where($condition)->field($fields)->order($order)->paginate($pagesize,false,['query' => request()->param()]);
            $this->page_info = $res;
            return $res->items();
        } else {
            return db('vrorderbill')->where($condition)->field($fields)->order($order)->limit($limit)->select();
        }
    }

    /**
     * 取得店铺月结算单单条
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @param type $fields 字段
     * @return type
     */
    public function getVrorderbillInfo($condition = array(), $fields = '*') {
        return db('vrorderbill')->where($condition)->field($fields)->find();
    }

    /**
     * 取得订单数量
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @return type
     */
    public function getVrorderbillCount($condition) {
        return db('vrorderbill')->where($condition)->count();
    }
    /**
     * 增加
     * @access public
     * @author csdeshang
     * @param type $data 数据
     * @return type
     */
    public function addVrorderstatis($data) {
        return db('vrorderstatis')->insert($data);
    }
    /**
     * 增加
     * @access public
     * @author csdeshang
     * @param type $data 测试内容
     * @return type
     */
    public function addVrorderbill($data) {
        return db('vrorderbill')->insert($data);
    }
    /**
     * 编辑更新
     * @access public
     * @author csdeshang
     * @param type $data
     * @param type $condition 条件
     * @return type
     */
    public function editVrorderbill($data, $condition = array()) {
        return db('vrorderbill')->where($condition)->update($data);
    }

}
