<?php
/**
 * Created by PhpStorm.
 *线下店铺
 */

namespace app\home\controller;


class Sellerlive extends BaseSeller
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /*
    * 线下商铺
    */
    public function index()
    {
        if (request()->isPost()) {//编辑商户信息

            $params = array();//参数
            $params['store_vrcode_prefix'] = preg_match('/^[a-zA-Z0-9]{1,3}$/', input('post.store_vrcode_prefix')) ? input('post.store_vrcode_prefix') : null;
            $params['live_store_name'] = input('post.live_store_name');
            $params['live_store_address'] = input('post.live_store_address');
            $params['live_store_tel'] = input('post.live_store_tel');
            $params['live_store_bus'] = input('post.live_store_bus');

            $store_model = model('store');
            $res = $store_model->editStore($params, array('store_id' => session('store_id')));

            if ($res) {
                ds_show_dialog('编辑成功', '', 'succ');
            }
            else {
                ds_show_dialog('编辑失败', '', 'error');
            }
        }else  {
            $store_model = model('store');
            $store = $store_model->getStoreInfo(array('store_id' => session('store_id')));
            if (empty($store)) {
                ds_show_dialog('该商家不存在', '', 'error');
            }

            $this->assign('store', $store);
            $this->setSellerCurItem('index');
            $this->setSellerCurMenu('sellerlive');
            $this->assign('baidu_ak', config('baidu_ak'));
            return $this->fetch($this->template_dir . 'index');
        }
    }

    /**
     * 用户中心右边，小导航
     *
     * @param string $menu_type 导航类型
     * @param string $menu_key 当前导航的menu_key
     * @return
     */
    protected function getSellerItemList()
    {
        $menu_array = array(
             array(
                'name' => 'index', 'text' => '线下商铺',
                'url' => url('Sellerlive/index')
            ),
        );
        return $menu_array;
    }
}