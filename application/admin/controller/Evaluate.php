<?php

namespace app\admin\controller;


use think\Lang;

class Evaluate extends AdminControl
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH.'admin/lang/zh-cn/evaluate.lang.php');
    }

    /**
     * 商品来自买家的评价列表
     */
    public function evalgoods_list() {
        $evaluategoods_model = model('evaluategoods');

        $condition = array();
        //商品名称
        if (input('param.goods_name')) {
            $condition['geval_goodsname'] = array('like', '%'.input('param.goods_name').'%');
        }
        //店铺名称
        if (input('param.store_name')) {
            $condition['geval_storename'] = array('like', '%'.input('param.store_name').'%');
        }
        if(input('param.stime')&&input('param.etime')) {
            $stime = strtotime(input('param.stime'));
            $etime = strtotime(input('param.etime'));
            $condition['geval_addtime'] = array('between', array($stime, $etime));
        }
        $evalgoods_list	= $evaluategoods_model->getEvaluategoodsList($condition, 10);

        $this->assign('show_page',$evaluategoods_model->page_info->render());
        $this->assign('evalgoods_list',$evalgoods_list);
        
        $this->assign('filtered', $condition ? 1 : 0); //是否有查询条件
        
        $this->setAdminCurItem('evalgoods_list');
        return $this->fetch('index');
    }

    /**
     * 删除商品评价
     */
    public function evalgoods_del() {
        $geval_id = intval(input('param.geval_id'));
        if ($geval_id <= 0) {
            ds_json_encode(10001, lang('param_error'));
        }
        $evaluategoods_model = model('evaluategoods');
        $result = $evaluategoods_model->delEvaluategoods(array('geval_id'=>$geval_id));
        if ($result) {
            $this->log('删除商品评价，评价编号'.$geval_id);
            ds_json_encode(10000, lang('ds_common_del_succ'));
        } else {
            ds_json_encode(10001, lang('ds_common_del_fail'));
        }
    }

    /**
     * 店铺动态评价列表
     */
    public function evalstore_list() {
        $evaluatestore_model = model('evaluatestore');

        $condition = array();
        //评价人
        if (input('param.from_name')) {
            $condition['seval_membername'] = array('like', '%'.input('param.from_name').'%');
        }
        //店铺名称
        if (input('param.store_name')) {
            $condition['seval_storename'] = array('like', '%'.input('param.store_name').'%');
        }
        if(input('param.stime')&&input('param.etime')) {
            $stime = strtotime(input('param.stime'));
            $etime = strtotime(input('param.etime'));
            $condition['seval_addtime'] = array('between', array($stime, $etime));
        }

        $evalstore_list	= $evaluatestore_model->getEvaluatestoreList($condition, 10);
        $this->assign('show_page',$evaluatestore_model->page_info->render());
        $this->assign('evalstore_list',$evalstore_list);
        $this->setAdminCurItem('evalstore_list');
        return $this->fetch();
    }

    /**
     * 删除店铺评价
     */
    public function evalstore_del() {
        $seval_id = intval(input('param.seval_id'));
        if ($seval_id <= 0) {
            ds_json_encode(10001, lang('param_error'));
        }
        $evaluatestore_model = model('evaluatestore');
        $result = $evaluatestore_model->delEvaluatestore(array('seval_id'=>$seval_id));
        if ($result) {
            $this->log('删除店铺评价，评价编号'.$seval_id);
            ds_json_encode(10000, lang('ds_common_del_succ'));
        } else {
            ds_json_encode(10001, lang('ds_common_del_fail'));
        }
    }
    protected function getAdminItemList()
    {
        $menu_array = array(
            array(
                'name' => 'evalgoods_list',
                'text' => lang('admin_evaluate_list'),
                'url' => url('Evaluate/evalgoods_list')
            ),
            array(
                'name' => 'evalstore_list',
                'text' => lang('admin_evalstore_list'),
                'url' => url('Evaluate/evalstore_list')
            )
        );
        return $menu_array;
    }
}