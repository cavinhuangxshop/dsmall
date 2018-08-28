<?php
namespace app\home\controller;

use think\Lang;
use think\Validate;

class Selleraccount extends BaseSeller {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/selleraccount.lang.php');
    }
    

    public function account_list() {
        $seller_model = model('seller');
        $condition = array(
            'store_id' => session('store_id'),
            'sellergroup_id' => array('gt', 0)
        );
        
        $seller_list = $seller_model->getSellerList($condition);
        $this->assign('seller_list', $seller_list);

        $sellergroup_model = model('sellergroup');
        $seller_group_list = $sellergroup_model->getSellergroupList(array('store_id' => session('store_id')));
        $seller_group_array = array_under_reset($seller_group_list, 'sellergroup_id');
        $this->assign('seller_group_array', $seller_group_array);

        /* 设置卖家当前菜单 */
        $this->setSellerCurMenu('selleraccount');
        /* 设置卖家当前栏目 */
        $this->setSellerCurItem('account_list');
        return $this->fetch($this->template_dir.'account_list');
    }

    public function account_add() {
        if (!request()->isPost()) {
            $sellergroup_model = model('sellergroup');
            $seller_group_list = $sellergroup_model->getSellergroupList(array('store_id' => session('store_id')));
            if (empty($seller_group_list)) {
                $this->error('请先建立账号组', url('Selleraccountgroup/group_add'));
            }
            $this->assign('seller_group_list', $seller_group_list);
            /* 设置卖家当前菜单 */
            $this->setSellerCurMenu('selleraccount');
            /* 设置卖家当前栏目 */
            $this->setSellerCurItem('account_add');
            return $this->fetch($this->template_dir . 'account_add');
        } else {
            $member_name = input('post.member_name');
            $password = input('post.password');
            $member_info = $this->_check_seller_member($member_name, $password);
            if (!$member_info) {
                ds_show_dialog('用户验证失败', 'reload', 'error');
            }

            $seller_name = input('post.seller_name');
            if ($this->_is_seller_name_exist($seller_name)) {
                ds_show_dialog('卖家账号已存在', 'reload', 'error');
            }

            $group_id = intval(input('post.group_id'));

            $seller_info = array(
                'seller_name' => $seller_name,
                'member_id' => $member_info['member_id'],
                'sellergroup_id' => $group_id,
                'store_id' => session('store_id'),
                'is_admin' => 0
            );
            $seller_model = model('seller');
            $result = $seller_model->addSeller($seller_info);

            if ($result) {
                $this->recordSellerlog('添加账号成功，账号编号' . $result);
                ds_show_dialog(lang('ds_common_op_succ'), url('Selleraccount/account_list'), 'succ');
            } else {
                $this->recordSellerlog('添加账号失败');
                ds_show_dialog(lang('ds_common_save_fail'), url('Selleraccount/account_list'), 'error');
            }
        }
    }

    public function account_edit() {
        if (!request()->isPost()) {
            $seller_id = intval(input('param.seller_id'));
            if ($seller_id <= 0) {
                $this->error('参数错误');
            }
            $seller_model = model('seller');
            $seller_info = $seller_model->getSellerInfo(array('seller_id' => $seller_id));
            if (empty($seller_info) || intval($seller_info['store_id']) !== intval(session('store_id'))) {
                $this->error('账号不存在');
            }
            $this->assign('seller_info', $seller_info);

            $sellergroup_model = model('sellergroup');
            $seller_group_list = $sellergroup_model->getSellergroupList(array('store_id' => session('store_id')));
            if (empty($seller_group_list)) {
                $this->error('请先建立账号组', url('Selleraccountgroup/group_add'));
            }
            $this->assign('seller_group_list', $seller_group_list);

            /* 设置卖家当前菜单 */
            $this->setSellerCurMenu('selleraccount');
            /* 设置卖家当前栏目 */
            $this->setSellerCurItem('account_edit');
            return $this->fetch($this->template_dir . 'account_edit');
        } else {
            $param = array('sellergroup_id' => intval(input('post.group_id')));
            $condition = array(
                'seller_id' => intval(input('post.seller_id')),
                'store_id' => session('store_id')
            );
            $seller_model = model('seller');
            $result = $seller_model->editSeller($param, $condition);
            if ($result) {
                $this->recordSellerlog('编辑账号成功，账号编号：' . input('post.seller_id'));
                ds_show_dialog(lang('ds_common_op_succ'), url('Selleraccount/account_list'), 'succ');
            } else {
                $this->recordSellerlog('编辑账号失败，账号编号：' . input('post.seller_id'), 0);
                ds_show_dialog(lang('ds_common_save_fail'), url('Selleraccount/account_list'), 'error');
            }
        }
    }


    public function account_del() {
        $seller_id = intval(input('post.seller_id'));
        if($seller_id > 0) {
            $condition = array();
            $condition['seller_id'] = $seller_id;
            $condition['store_id'] = session('store_id');
            $seller_model = model('seller');
            $result = $seller_model->delSeller($condition);
            if($result) {
                $this->recordSellerlog('删除账号成功，账号编号'.$seller_id);
                ds_show_dialog(lang('ds_common_op_succ'),'reload','succ');
            } else {
                $this->recordSellerlog('删除账号失败，账号编号'.$seller_id);
                ds_show_dialog(lang('ds_common_save_fail'),'reload','error');
            }
        } else {
            ds_show_dialog(lang('wrong_argument'),'reload','error');
        }
    }

    public function check_seller_name_exist() {
        $seller_name = input('get.seller_name');
        $result = $this->_is_seller_name_exist($seller_name);
        if($result) {
            echo 'true';
        } else {
            echo 'false';
        }
    }

    private function _is_seller_name_exist($seller_name) {
        $condition = array();
        $condition['seller_name'] = $seller_name;
        $seller_model = model('seller');
        return $seller_model->isSellerExist($condition);
    }

    public function check_seller_member() {
        $member_name = input('get.member_name');
        $password = input('get.password');
        $result = $this->_check_seller_member($member_name, $password);
        if($result) {
            echo 'true';
        } else {
            echo 'false';
        }
    }

    private function _check_seller_member($member_name, $password) {
        $member_info = $this->_check_member_password($member_name, $password);
        if($member_info && !$this->_is_seller_member_exist($member_info['member_id'])) {
            return $member_info;
        } else {
            return false;
        }
    }

    private function _check_member_password($member_name, $password) {
        $condition = array();
        $condition['member_name']	= $member_name;
        $condition['member_password']	= md5($password);
        $member_model = model('member');
        $member_info = $member_model->getMemberInfo($condition);
        return $member_info;
    }

    private function _is_seller_member_exist($member_id) {
        $condition = array();
        $condition['member_id'] = $member_id;
        $seller_model = model('seller');
        return $seller_model->isSellerExist($condition);
    }

    
    /**
     *    栏目菜单
     */
    function getSellerItemList() {
        $menu_array[] = array(
            'name' => 'account_list',
            'text' => '账号列表',
            'url' => url('Selleraccount/account_list'),
        );

        if (request()->action() === 'account_add') {
            $menu_array[] = array(
                'name' => 'account_add',
                'text' => '添加账号',
                'url' => url('Selleraccount/account_add'),
            );
        }
        if (request()->action() === 'group_edit') {
            $menu_array[] = array(
                'name' => 'account_edit',
                'text' => '编辑账号',
                'url' => url('Selleraccount/account_edit'),
            );
        }
        
        return $menu_array;
    }
    
    
}
