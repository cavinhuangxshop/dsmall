<?php

namespace app\common\model;


use think\Model;

class Snstracelog extends Model
{
    public $page_info;
    /**
     * 新增动态
     * @access public
     * @author csdeshang
     * @param $data 添加数据信息数组
     * @return bool
     */
    public function addSnstracelog($data)
    {
        if (empty($data)) {
            return false;
        }
        //处理文本中@信息
        if ($data['tracelog_title']) {
            preg_match_all("/@(.+?)([\s|:|：]|$)/is", $data['tracelog_title'], $matches);
            if (!empty($matches[1])) {
                //查询会员信息
                $member_model = model('member');
                $member_list = $member_model->getMemberList(array('member_name' => array('in', $matches[1])));
                foreach ($member_list as $k => $v) {
                    $data['tracelog_title'] = preg_replace("/@(" . $v['member_name'] . ")([\s|:|：]|$)/is", '<a href=\"%siteurl%/Membersnshome/index.html?mid=' . $v['member_id'] . '\" target="_blank">@${1}</a>${2}', $data['tracelog_title']);
                }
            }
            unset($matches);
        }
        $result= db('snstracelog')->insertGetId($data);
        return $result;
    }

    /**
     * 动态记录列表
     * @access public
     * @author csdeshang 
     * @param type $condition 条件
     * @param type $page 分页
     * @param type $field 字段
     * @return type
     */
    public function getSnstracelogList($condition, $page = '', $field = '*')
    {
        $where = $this->getCondition($condition);
        $order = isset($condition['order']) ? $condition['order'] : 'snstracelog.tracelog_id desc';
        $limit = isset($condition['limit'])? $condition['limit'] : '';
        if($page) {
            $result=db('snstracelog')->alias('snstracelog')->field($field)->where($where)->order($order)->paginate($page,false,['query' => request()->param()]);
            $this->page_info = $result;
            return $result->items();
        }else{
            return db('snstracelog')->alias('snstracelog')->field($field)->where($where)->order($order)->limit($limit)->select();
        }
    }

    /**
     * 获取动态详细
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @return type
     */
    public function getOneSnstracelog($condition)
    {
        return db('snstracelog')->where($condition)->find();

    }

  
    /**
     * 更新动态记录
     * @access public
     * @author csdeshang
     * @param type $data 更新数据
     * @param type $condition 更新条件
     * @return boolean
     */
    public function editSnstracelog($data, $condition)
    {
        if (empty($data)) {
            return false;
        }
        //得到条件语句
        $where = $this->getCondition($condition);
        $result=db('snstracelog')->alias('snstracelog')->where($where)->update($data);
        return $result;
    }

    /**
     * 删除动态
     * @access public
     * @author csdeshang
     * @param type $condition
     * @return boolean
     */
    public function delSnstracelog($condition)
    {
        if (empty($condition)) {
            return false;
        }
        $where = '1=1 ';
        if (isset($condition['tracelog_id'])&&$condition['tracelog_id'] != '') {
            $where .= " and tracelog_id='{$condition['tracelog_id']}' ";
        }
        if (isset($condition['tracelog_id_in'])&&$condition['tracelog_id_in'] != '') {
            $where .= " and tracelog_id in('{$condition['tracelog_id_in']}') ";
        }
        if (isset($condition['tracelog_memberid'])&&$condition['tracelog_memberid'] != '') {
            $where .= " and tracelog_memberid='{$condition['tracelog_memberid']}' ";
        }
        return db('snstracelog')->where($where)->delete();
    }

    /**
     * 动态总数
     * @access public
     * @author csdeshang
     * @param type $condition 条件
     * @return type 
     */
    public function getSnstracelogCount($condition)
    {
        //得到条件语句
        $where = $this->getCondition($condition);
        $count = db('snstracelog')->alias('snstracelog')->where($where)->count();
        return $count;
    }

    /**
     * 将条件数组组合为SQL语句的条件部分
     * @access public
     * @author csdeshang
     * @param type $condition_array 条件数组
     * @return type
     */
    private function getCondition($condition_array)
    {
        $condition_sql = '1=1';
        //自增编号
        if (isset($condition_array['tracelog_id'])&& $condition_array['tracelog_id'] != '') {
            $condition_sql .= " and snstracelog.tracelog_id = '{$condition_array['tracelog_id']}' ";
        }
        //自增IDin
        if (isset($condition_array['traceid_in'])&&$condition_array['traceid_in'] != '') {
            $condition_sql .= " and snstracelog.tracelog_id in('{$condition_array['traceid_in']}') ";
        }
        //原帖ID
        if (isset($condition_array['tracelog_originalid'])&&$condition_array['tracelog_originalid'] != '') {
            $condition_sql .= " and snstracelog.tracelog_originalid = '{$condition_array['tracelog_originalid']}' ";
        }
        //原帖IDin
        if (isset($condition_array['tracelog_originalid_in'])&&$condition_array['tracelog_originalid_in'] != '') {
            $condition_sql .= " and snstracelog.tracelog_originalid in('{$condition_array['tracelog_originalid_in']}')";
        }
        //会员编号
        if (isset($condition_array['tracelog_memberid'])&&$condition_array['tracelog_memberid'] != '') {
            $condition_sql .= " and snstracelog.tracelog_memberid = '{$condition_array['tracelog_memberid']}' ";
        }
        //会员名like
        if (isset($condition_array['tracelog_membernamelike'])&&$condition_array['tracelog_membernamelike'] != '') {
            $condition_sql .= " and snstracelog.tracelog_membername like '%{$condition_array['tracelog_membernamelike']}%' ";
        }
        //查看状态
        if (isset($condition_array['tracelog_state'])&&$condition_array['tracelog_state'] != '') {
            $condition_sql .= " and snstracelog.tracelog_state = '{$condition_array['tracelog_state']}' ";
        }
        //允许查看的动态
        if (isset($condition_array['allowshow'])&&$condition_array['allowshow'] != '') {
            $allowshowsql_arr = array();
            //自己的动态
            if (isset($condition_array['allowshow_memberid'])&&$condition_array['allowshow_memberid'] != '') {
                $allowshowsql_arr[0] = " (snstracelog.tracelog_memberid = '{$condition_array['allowshow_memberid']}')";
            }
            //查看我关注的人权限为所有人可见的动态
            if (isset($condition_array['allowshow_followerin'])&&$condition_array['allowshow_followerin'] != '') {
                $allowshowsql_arr[1] .= " (snstracelog.tracelog_privacy=0 and snstracelog.tracelog_memberid in('{$condition_array['allowshow_followerin']}'))";
            }
            //查看好友的权限为好友可见的动态
            if (isset($condition_array['allowshow_friendin'])&&$condition_array['allowshow_friendin'] != '') {
                $allowshowsql_arr[2] .= " (snstracelog.tracelog_privacy=1 and snstracelog.tracelog_memberid in('{$condition_array['allowshow_friendin']}'))";
            }
            $condition_sql .= " and (" . implode(' or ', $allowshowsql_arr) . ")";
        }
        //隐私权限
        if (isset($condition_array['tracelog_privacyin'])&&$condition_array['tracelog_privacyin'] != '') {
            $condition_sql .= " and `snstracelog`.tracelog_privacy in('{$condition_array['tracelog_privacyin']}')";
        }
        //添加时间
        if (isset($condition_array['stime'])&&$condition_array['stime'] != '') {
            $condition_sql .= " and `snstracelog`.tracelog_addtime >= {$condition_array['stime']}";
        }
        if (isset($condition_array['etime'])&&$condition_array['etime'] != '') {
            $condition_sql .= " and `snstracelog`.tracelog_addtime <= {$condition_array['etime']}";
        }
        //内容或者标题
        if (isset($condition_array['tracelog_contentortitle'])&&$condition_array['tracelog_contentortitle'] != '') {
            $condition_sql .= " and (`snstracelog`.tracelog_title like '%{$condition_array['tracelog_contentortitle']}%' or `snstracelog`.tracelog_content like '%{$condition_array['tracelog_contentortitle']}%') ";
        }
        return $condition_sql;
    }
}