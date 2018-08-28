<?php

namespace app\common\model;

use think\Model;

class Snscomment extends Model {

    public $page_info;

    /**
     * 新增评论
     * @access public
     * @author csdeshang
     * @param array $data 添加信息数组
     * @return 返回结果
     */
    public function addSnscomment($data) {
        $result = db('snscomment')->insertGetId($data);
        return $result;
    }

    /**
     * 评论记录列表
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @param type $page 分页
     * @param type $field 字段
     * @param type $order 排序
     * @return type
     */
    public function getSnscommentList($condition, $page = '', $field = '*',$order='snscomment_id desc') {
        $limit = isset($condition['limit']) ? $condition['limit'] : '';
        if($limit){
            unset($condition['limit']);
        }
        if ($page) {
            $res = db('snscomment')->where($condition)->field($field)->order($order)->paginate($page,false,['query' => request()->param()]);
            $this->page_info = $res;
            return $res->items();
        } else {
            return db('snscomment')->where($condition)->field($field)->order($order)->limit($limit)->select();
        }
    }

    /**
     * 评论总数
     * @access public
     * @author csdeshang
     * @param array $condition 条件
     * @return int
     */
    public function getSnscommentCount($condition) {
        return db('snscomment')->where($condition)->count();
    }

    /**
     * 获取评论详细
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @param type $field 字段
     * @return type
     */
    public function getOneSnscomment($condition, $field = '*') {
        return db('snscomment')->where($condition)->find();
    }

    /**
     * 删除评论
     * @access public
     * @author csdeshang
     * @param array $condition 条件
     * @return boolean
     */
    public function delSnscomment($condition) {
        if (empty($condition)) {
            return false;
        }
        return db('snscomment')->where($condition)->delete();
    }

    /**
     * 更新评论记录
     * @access public
     * @author csdeshang
     * @param type $data 更新数据
     * @param type $condition 条件
     * @return boolean
     */
    public function editSnscomment($data, $condition) {
        if (empty($data)) {
            return false;
        }
        $result = db('snscomment')->where($condition)->update($data);
        return $result;
    }


}