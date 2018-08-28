<?php

namespace app\home\controller;

use think\Lang;
use think\Validate;

class Selleraccountgroup extends BaseSeller {

    public function _initialize() {
        parent::_initialize();
    }

    public function group_list() {
        $sellergroup_model = model('sellergroup');
        $seller_group_list = $sellergroup_model->getSellergroupList(array('store_id' => session('store_id')));
        $this->assign('seller_group_list', $seller_group_list);
        /* 设置卖家当前菜单 */
        $this->setSellerCurMenu('selleraccountgroup');
        /* 设置卖家当前栏目 */
        $this->setSellerCurItem('group_list');
        return $this->fetch($this->template_dir.'group_list');
    }

    public function group_add() {
        $seller_group_info = array(
            'sellergroup_id' => 0,
            'sellergroup_name' => '',
            'sellergroup_limits' => '',
            'smt_limits' => ''
        );
        $this->assign('group_info', $seller_group_info);
        $this->assign('group_limits', explode(',', $seller_group_info['sellergroup_limits']));
        $this->assign('smt_limits', explode(',', $seller_group_info['smt_limits']));

        // 店铺消息模板列表
        $smt_list = model('storemsgtpl')->getStoremsgtplList(array(), 'storemt_code,storemt_name');
        $this->assign('smt_list', $smt_list);

        /* 设置卖家当前菜单 */
        $this->setSellerCurMenu('selleraccountgroup');
        /* 设置卖家当前栏目 */
        $this->setSellerCurItem('group_add');
        return $this->fetch($this->template_dir.'group_add');
    }

    public function group_edit() {
        $group_id = intval(input('param.group_id'));
        if ($group_id <= 0) {
            $this->error('参数错误');
        }
        $sellergroup_model = model('sellergroup');
        $seller_group_info = $sellergroup_model->getSellergroupInfo(array('sellergroup_id' => $group_id, 'store_id' => session('store_id')));
        if (empty($seller_group_info)) {
            $this->error('组不存在');
        }
        $this->assign('group_info', $seller_group_info);
        $this->assign('group_limits', explode(',', $seller_group_info['sellergroup_limits']));
        $this->assign('smt_limits', explode(',', $seller_group_info['smt_limits']));

        // 店铺消息模板列表
        $smt_list = model('storemsgtpl')->getStoremsgtplList(array(), 'storemt_code,storemt_name');
        $this->assign('smt_list', $smt_list);


        /* 设置卖家当前菜单 */
        $this->setSellerCurMenu('selleraccountgroup');
        /* 设置卖家当前栏目 */
        $this->setSellerCurItem('group_edit');
        return $this->fetch($this->template_dir.'group_add');
    }

    public function group_save() {
        $seller_info = array();
        $group_id = intval(input('param.group_id'));

        $seller_info['sellergroup_name'] = input('post.seller_group_name');
        $seller_info['sellergroup_limits'] = implode(',', $_POST['limits']);
        $seller_info['smt_limits'] = empty($_POST['smt_limits']) ? '' : implode(',', $_POST['smt_limits']);
        $seller_info['store_id'] = session('store_id');
        
        
        $sellergroup_model = model('sellergroup');

        if (empty($group_id)) {
            $result = $sellergroup_model->addSellergroup($seller_info);
            $this->recordSellerlog('添加组成功，组编号' . $result);
            if($result){
                ds_show_dialog('添加成功', url('Selleraccountgroup/group_list'), 'succ');
            }else{
                ds_show_dialog('添加失败', url('Selleraccountgroup/group_list'), 'error');
            }
            
        } else {
            $condition = array();
            $condition['sellergroup_id'] = $group_id;
            $condition['store_id'] = session('store_id');
            $result = $sellergroup_model->editSellergroup($seller_info, $condition);
            $this->recordSellerlog('编辑组成功，组编号' . $group_id);
            if($result){
                ds_show_dialog('编辑成功', url('Selleraccountgroup/group_list'), 'succ');
            }else{
                ds_show_dialog('编辑失败', url('Selleraccountgroup/group_list'), 'error');
            }
            
        }
    }

    public function group_del() {
        $group_id = intval(input('param.group_id'));
        if ($group_id > 0) {
            $condition = array();
            $condition['sellergroup_id'] = $group_id;
            $condition['store_id'] = session('store_id');
            $sellergroup_model = model('sellergroup');
            $result = $sellergroup_model->delSellergroup($condition);
            if ($result) {
                $this->recordSellerlog('删除组成功，组编号' . $group_id);
                ds_show_dialog(lang('ds_common_op_succ'), 'reload', 'succ');
            } else {
                $this->recordSellerlog('删除组失败，组编号' . $group_id);
                ds_show_dialog(lang('ds_common_save_fail'), 'reload', 'error');
            }
        } else {
            ds_show_dialog(lang('wrong_argument'), 'reload', 'error');
        }
    }

    /**
     *    栏目菜单
     */
    function getSellerItemList() {
        $menu_array[] = array(
            'name' => 'group_list',
            'text' => '组列表',
            'url' => url('Selleraccountgroup/group_list'),
        );

        if (request()->action() === 'group_add') {
            $menu_array[] = array(
                'name' => 'group_add',
                'text' => '添加组',
                'url' => url('Selleraccountgroup/group_add'),
            );
        }
        if (request()->action() === 'group_edit') {
            $menu_array[] = array(
                'name' => 'group_edit',
                'text' => '编辑组',
                'url' => url('Selleraccountgroup/group_edit'),
            );
        }
        
        return $menu_array;
    }


}
