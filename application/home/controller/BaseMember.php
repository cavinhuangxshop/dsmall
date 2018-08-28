<?php

/**
 * 买家
 */

namespace app\home\controller;
use think\Lang;

class BaseMember extends BaseHome {

    protected $member_info = array();   // 会员信息

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/basemember.lang.php');
        /* 不需要登录就能访问的方法 */
        if (!in_array(request()->controller() ,array('cart')) && !in_array(request()->action(), array('ajax_load', 'add', 'del')) && !session('member_id')) {
            $ref_url = request_uri();
            $this->redirect(HOME_SITE_URL.'/Login/login.html?ref_url='.urlencode($ref_url));
        }
        //会员中心模板路径
        $this->template_dir = 'default/member/' . strtolower(request()->controller()) . '/';
        $this->member_info = $this->getMemberAndGradeInfo(true);
        $this->assign('member_info', $this->member_info);
    }

    /**
     *    当前选中的栏目
     */
    protected function setMemberCurItem($curitem = '') {
        $this->assign('member_item', $this->getMemberItemList());
        $this->assign('curitem', $curitem);
    }

    /**
     *    当前选中的子菜单
     */
    protected function setMemberCurMenu($cursubmenu = '') {
        $member_menu = $this->getMemberMenuList();
        $this->assign('member_menu', $member_menu);
        $curmenu = '';
        foreach ($member_menu as $key => $menu) {
            foreach ($menu['submenu'] as $subkey => $submenu) {
                if ($submenu['name'] == $cursubmenu) {
                    $curmenu = $menu['name'];
                    $nav = $submenu['text'];
                }
            }
        }
        
        // 面包屑
        $nav_link = array();
        $nav_link[] = array('title' => lang('homepage'), 'link' => HOME_SITE_URL);
        if ($curmenu == '') {
            $nav_link[] = array('title' => '我的商城');
        } else {
            $nav_link[] = array('title' => '我的商城', 'link' => url('Member/index'));
            $nav_link[] = array('title' => $nav);
        }


        $this->assign('nav_link_list', $nav_link);


        //当前一级菜单
        $this->assign('curmenu', $curmenu);
        //当前二级菜单
        $this->assign('cursubmenu', $cursubmenu);
    }

    /*
     * 获取卖家栏目列表,针对控制器下的栏目
     */

    protected function getMemberItemList() {
        return array();
    }

    /*
     * 获取卖家菜单列表
     */

    private function getMemberMenuList() {
        $menu_list = array(
            'info' =>
            array(
                'name' => 'info',
                'text' => '资料管理',
                'url' => url('Memberinformation/index'),
                'submenu' => array(
                    array('name' => 'member_information', 'text' => '账户信息', 'url' => url('Memberinformation/index'),),
                    array('name' => 'member_security', 'text' => '账户安全', 'url' => url('Membersecurity/index'),),
                    array('name' => 'member_address', 'text' => '收货地址', 'url' => url('Memberaddress/index'),),
                    array('name' => 'member_message', 'text' => '我的消息', 'url' => url('Membermessage/message'),),
                    array('name' => 'member_snsfriend', 'text' => '我的好友', 'url' => url('Membersnsfriend/index'),),
                    array('name' => 'member_goodsbrowse', 'text' => '我的足迹', 'url' => url('Membergoodsbrowse/listinfo'),),
                    array('name' => 'member_connect', 'text' => '第三方账号登录', 'url' => url('Memberconnect/qqbind'),),
                )
            ),
            'trade' =>
            array(
                'name' => 'trade',
                'text' => '交易管理',
                'url' => url('Memberorder/index'),
                'submenu' => array(
                    array('name' => 'member_order', 'text' => '实物订单', 'url' => url('Memberorder/index'),),
                    array('name' => 'member_vr_order', 'text' => '虚拟订单', 'url' => url('Membervrorder/index'),),
                    array('name' => 'member_favorites', 'text' => '我的收藏', 'url' => url('Memberfavorites/fglist'),),
                    array('name' => 'member_evaluate', 'text' => '交易评价/晒单', 'url' => url('Memberevaluate/index'),),
                    array('name' => 'predeposit', 'text' => '账户余额', 'url' => url('Predeposit/index'),),
                    array('name' => 'member_points', 'text' => '我的积分', 'url' => url('Memberpoints/index'),),
                    array('name' => 'member_voucher', 'text' => '我的代金券', 'url' => url('Membervoucher/index'),),
                )
            ),
            'server' =>
            array(
                'name' => 'server',
                'text' => '客户服务',
                'url' => url('Memberrefund/index'),
                'submenu' => array(
                    array('name' => 'member_refund', 'text' => '退款及退货', 'url' => url('Memberrefund/index'),),
                    array('name' => 'member_complain', 'text' => '交易投诉', 'url' => url('Membercomplain/index'),),
                    array('name' => 'member_consult', 'text' => '商品咨询', 'url' => url('Memberconsult/index'),),
                    array('name' => 'member_inform', 'text' => '违规举报', 'url' => url('Memberinform/index'),),
                    array('name' => 'member_mallconsult', 'text' => '平台客服', 'url' => url('Membermallconsult/index'),),
                )
            ),
            'inviter' =>
            array(
                'name' => 'inviter',
                'text' => '会员推广',
                'url' => url('Memberinviter/index'),
                'submenu' => array(
                    array('name' => 'inviter_poster', 'text' => '推广海报', 'url' => url('Memberinviter/index'),),
                    array('name' => 'inviter_user', 'text' => '推广会员', 'url' => url('Memberinviter/user'),),
                    array('name' => 'inviter_order', 'text' => '推广佣金', 'url' => url('Memberinviter/order'),),
                )
            ),
            array(
                'name' => 'sns',
                'text' => '应用管理',
                'url' => url('Memberflea/index'),
                'submenu' => array(
                    array('name' => 'member_flea', 'text' => '我的闲置', 'url' => url('Memberflea/index'),),
                    array('name' => 'member_snshome', 'text' => '个人主页', 'url' => url('Membersnshome/index'),),
                )
            ),

        );
        return $menu_list;
    }
}

?>
