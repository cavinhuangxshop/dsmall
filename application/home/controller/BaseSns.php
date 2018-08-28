<?php

namespace app\home\controller;
use think\Lang;
class BaseSns extends BaseHome {

    protected $relation = 0; //浏览者与主人的关系：0 表示游客 1 表示一般普通会员 2表示朋友 3表示自己4表示已关注主人
    protected $master_id = 0; //主人编号

    const MAX_RECORDNUM = 20; //允许插入新记录的最大条数

    protected $master_info;

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/basesns.lang.php');
        //会员中心模板路径
        $this->template_dir = 'default/sns/' . strtolower(request()->controller()) . '/';

        //验证会员及与主人关系
        $this->check_relation();

        //查询会员信息
        $this->getMemberAndGradeInfo(false);

        $this->master_info = $this->get_member_info();
        $this->assign('master_info', $this->master_info);
        
        //添加访问记录
        $this->add_visit();

        //我的关注
        $this->my_attention();

        //允许插入新记录的最大条数
        $this->assign('max_recordnum', self::MAX_RECORDNUM);

        $this->showCartCount();

        $this->assign('nav_list', rkcache('nav', true));
    }

    /**
     * 格式化时间
     * @param string $time时间戳
     */
    protected function formatDate($time) {
        $handle_date = @date('Y-m-d', $time); //需要格式化的时间
        $reference_date = @date('Y-m-d', time()); //参照时间
        $handle_date_time = strtotime($handle_date); //需要格式化的时间戳
        $reference_date_time = strtotime($reference_date); //参照时间戳
        if ($reference_date_time == $handle_date_time) {
            $timetext = @date('H:i', $time); //今天访问的显示具体的时间点
        } elseif (($reference_date_time - $handle_date_time) == 60 * 60 * 24) {
            $timetext = lang('sns_yesterday');
        } elseif ($reference_date_time - $handle_date_time == 60 * 60 * 48) {
            $timetext = lang('sns_beforeyesterday');
        } else {
            $month_text = lang('ds_month');
            $day_text = lang('ds_day');
            $timetext = @date("m{$month_text}d{$day_text}", $time);
        }
        return $timetext;
    }

    /**
     * 会员信息
     *
     * @return array
     */
    public function get_member_info() {
        if ($this->master_id <= 0) {
            $this->error(lang('wrong_argument'));
        }
        $member_info = model('member')->getMemberInfoByID($this->master_id);
        if (empty($member_info)) {
            $this->error(lang('wrong_argument'), 'membersnshome/index');
        }
        //粉丝数
        $fan_count = db('snsfriend')->where(array('friend_tomid' => $this->master_id))->count();
        $member_info['fan_count'] = $fan_count;
        //关注数
        $attention_count = db('snsfriend')->where(array('friend_frommid' => $this->master_id))->count();
        $member_info['attention_count'] = $attention_count;
        //兴趣标签
        $mtag_list = db('snsmembertag')->alias('sns_membertag')->field('mtag_name')->join('__SNSMTAGMEMBER__ sns_mtagmember', 'sns_membertag.mtag_id=sns_mtagmember.mtag_id', 'LEFT')->where(array('sns_mtagmember.member_id' => $this->master_id))->select();
        $tagname_array = array();
        if (!empty($mtag_list)) {
            foreach ($mtag_list as $val) {
                $tagname_array[] = $val['mtag_name'];
            }
        }
        $member_info['tagname'] = $tagname_array;
        return $member_info;
    }

    /**
     * 访客信息
     */
    protected function get_visitor() {
        //查询谁来看过我
        $visitme_list = db('snsvisitor')->where(array('snsvisitor_ownermid' => $this->master_id))->limit(9)->order('snsvisitor_addtime desc')->select();
        if (!empty($visitme_list)) {
            foreach ($visitme_list as $k => $v) {
                $v['adddate_text'] = $this->formatDate($v['snsvisitor_addtime']);
                $v['addtime_text'] = @date('H:i', $v['snsvisitor_addtime']);
                $visitme_list[$k] = $v;
            }
        }
        $this->assign('visitme_list', $visitme_list);
        if ($this->relation == 3) { // 主人自己才有我访问过的人
            //查询我访问过的人
            $visitother_list = db('snsvisitor')->where(array('snsvisitor_mid' => $this->master_id))->limit(9)->order('snsvisitor_addtime desc')->select();
            if (!empty($visitother_list)) {
                foreach ($visitother_list as $k => $v) {
                    $v['adddate_text'] = $this->formatDate($v['snsvisitor_addtime']);
                    $v['addtime_text'] = @date('H:i', $v['snsvisitor_addtime']);
                    $visitother_list[$k] = $v;
                }
            }
            $this->assign('visitother_list', $visitother_list);
        }
    }

    /**
     * 验证会员及主人关系
     */
    private function check_relation() {
        //验证主人会员编号
        $this->master_id = intval(input('param.mid'));
        if ($this->master_id <= 0) {
            if (session('is_login') == 1) {
                $this->master_id = session('member_id');
            } else {
                $this->redirect(HOME_SITE_URL.'/Login/login.html?ref_url='.urlencode(url('Membersnshome/index')));
            }
        }
        $this->assign('master_id', $this->master_id);

        //判断浏览者与主人的关系
        if (session('is_login') == '1') {
            if ($this->master_id == session('member_id')) {//主人自己
                $this->relation = 3;
            } else {
                $this->relation = 1;
                //查询好友表
                $friend_arr = db('snsfriend')->where(array('friend_frommid' => session('member_id'), 'friend_tomid' => $this->master_id))->find();
                if (!empty($friend_arr) && $friend_arr['friend_followstate'] == 2) {
                    $this->relation = 2;
                } elseif ($friend_arr['friend_followstate'] == 1) {
                    $this->relation = 4;
                }
            }
        }
        $this->assign('relation', $this->relation);
    }

    /**
     * 增加访问记录
     */
    private function add_visit() {
        //记录访客
        if (session('is_login') == '1' && $this->relation != 3) {
            //访客为会员且不是空间主人则添加访客记录
            $visitor_info = db('member')->where('member_id',session('member_id'))->find();
            if (!empty($visitor_info)) {
                //查询访客记录是否存在
                $existevisitor_info = db('snsvisitor')->where(array('snsvisitor_ownermid' => $this->master_id, 'snsvisitor_mid' => $visitor_info['member_id']))->find();
                if (!empty($existevisitor_info)) {//访问记录存在则更新访问时间
                    $update_arr = array();
                    $update_arr['snsvisitor_addtime'] = time();
                    db('snsvisitor')->update(array('snsvisitor_id' => $existevisitor_info['snsvisitor_id'], 'snsvisitor_addtime' => time()));
                } else {//添加新访问记录
                    $insert_arr = array();
                    $insert_arr['snsvisitor_mid'] = $visitor_info['member_id'];
                    $insert_arr['snsvisitor_mname'] = $visitor_info['member_name'];
                    $insert_arr['snsvisitor_mavatar'] = $visitor_info['member_avatar'];
                    $insert_arr['snsvisitor_ownermid'] = $this->master_info['member_id'];
                    $insert_arr['snsvisitor_ownermname'] = $this->master_info['member_name'];
                    $insert_arr['snsvisitor_ownermavatar'] = $this->master_info['member_avatar'];
                    $insert_arr['snsvisitor_addtime'] = time();
                    db('snsvisitor')->insert($insert_arr);
                }
            }
        }

        //增加主人访问次数
        $cookie_str = cookie('visitor');
        $cookie_arr = array();
        $is_increase = false;
        if (empty($cookie_str)) {
            //cookie不存在则直接增加访问次数
            $is_increase = true;
        } else {
            //cookie存在但是为空则直接增加访问次数
            $cookie_arr = explode('_', $cookie_str);
            if (!in_array($this->master_id, $cookie_arr)) {
                $is_increase = true;
            }
        }
        if ($is_increase == true) {
            //增加访问次数
            db('member')->where('member_id',$this->master_id)->update(array('member_snsvisitnum' => array('exp', 'member_snsvisitnum+1')));
            //设置cookie，24小时之内不再累加
            $cookie_arr[] = $this->master_id;
            cookie('visitor', implode('_', $cookie_arr), 24 * 3600); //保存24小时
        }
    }

    //我的关注
    private function my_attention() {
        if (intval(session('member_id')) > 0) {
            $my_attention = db('snsfriend')->where(array('friend_frommid' => session('member_id')))->order('friend_addtime desc')->limit(4)->select();
            $this->assign('my_attention', $my_attention);
        }
    }

    /**
     * 留言板
     */
    protected function sns_messageboard() {
        $where = array();
        $where['from_member_id'] = array('neq', 0);
        $where['to_member_id'] = $this->master_id;
        $where['message_state'] = array('neq', 2);
        $where['message_parent_id'] = 0;
        $where['message_type'] = 2;
        $message_list = db('message')->where($where)->order('message_id desc')->limit(10)->select();
        if (!empty($message_list)) {
            $pmsg_id = array();
            foreach ($message_list as $key => $val) {
                $pmsg_id[] = $val['message_id'];
                $message_list[$key]['message_time'] = $this->formatDate($val['message_time']);
            }
            $where = array();
            $where['message_parent_id'] = array('in', $pmsg_id);
            $rmessage_array = db('message')->where($where)->select();
            $rmessage_list = array();
            if (!empty($rmessage_array)) {
                foreach ($rmessage_array as $key => $val) {
                    $val['message_time'] = $this->formatDate($val['message_time']);
                    $rmessage_list[$val['message_parent_id']][] = $val;
                }
                foreach ($rmessage_list as $key => $val) {
                    $rmessage_list[$key] = array_slice($val, -3, 3);
                }
            }
            $this->assign('rmessage_list', $rmessage_list);
        }
        $this->assign('message_list', $message_list);
    }

}

?>
