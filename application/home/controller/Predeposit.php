<?php

/**
 * 预存款管理
 */

namespace app\home\controller;

use think\Lang;
use think\Validate;

class Predeposit extends BaseMember {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/predeposit.lang.php');
    }

    /**
     * 充值添加
     */
    public function recharge_add() {
        if (!request()->isPost()) {
            /* 设置买家当前菜单 */
            $this->setMemberCurMenu('predeposit');
            /* 设置买家当前栏目 */
            $this->setMemberCurItem('recharge_add');
            return $this->fetch($this->template_dir . 'pd_recharge_add');
        } else {
            $pdr_amount = abs(floatval(input('post.pdr_amount')));
            if ($pdr_amount <= 0) {
                $this->error(lang('predeposit_recharge_add_pricemin_error'));
            }
            $predeposit_model = model('predeposit');
            $data = array();
            $data['pdr_sn'] = $pay_sn = makePaySn(session('member_id'));
            $data['pdr_member_id'] = session('member_id');
            $data['pdr_member_name'] = session('member_name');
            $data['pdr_amount'] = $pdr_amount;
            $data['pdr_addtime'] = TIMESTAMP;
            $insert = $predeposit_model->addPdRecharge($data);
            if ($insert) {
                //转向到商城支付页面
                $this->redirect(url('Buy/pd_pay', ['pay_sn' => $pay_sn]));
            }
        }
    }

    /**
     * 平台充值卡
     */
    public function rechargecard_add() {
        if (!request()->isPost()) {
            /* 设置买家当前菜单 */
            $this->setMemberCurMenu('predeposit');
            /* 设置买家当前栏目 */
            $this->setMemberCurItem('rechargecard_add');
            return $this->fetch($this->template_dir . 'rechargecard_add');
            return;
        } else {
            $sn = (string) input('post.rc_sn');
            if (!$sn || strlen($sn) > 50) {
                $this->error('平台充值卡卡号不能为空且长度不能大于50');
                exit;
            }

            try {
                $res=model('predeposit')->addRechargecard($sn, $this->member_info);
                if($res['message']){
                    $this->error($res['message']);
                }
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            $this->success('平台充值卡使用成功', url('Predeposit/rcb_log_list'));
        }
    }

    /**
     * 充值列表
     */
    public function index() {
        $condition = array();
        $condition['pdr_member_id'] = session('member_id');
        $pdr_sn = input('pdr_sn');
        if (!empty($pdr_sn)) {
            $condition['pdr_sn'] = $pdr_sn;
        }

        $predeposit_model = model('predeposit');
        $predeposit_list = $predeposit_model->getPdRechargeList($condition, 10, '*', 'pdr_id desc');

        $this->assign('predeposit_list', $predeposit_list);
        $this->assign('show_page', $predeposit_model->page_info->render());

        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('predeposit');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('recharge_list');
        return $this->fetch($this->template_dir . 'pd_recharge_list');
    }

    /**
     * 查看充值详细
     *
     */
    public function recharge_show() {
        $pdr_id = intval(input('param.id'));
        if ($pdr_id <= 0) {
            ds_show_dialog(lang('predeposit_parameter_error'), '', 'error');
        }

        $predeposit_model = model('predeposit');
        $condition = array();
        $condition['pdr_member_id'] = session('member_id');
        $condition['pdr_id'] = $pdr_id;
        $condition['pdr_payment_state'] = 1;
        $info = $predeposit_model->getPdRechargeInfo($condition);
        if (!$info) {
            ds_show_dialog(lang('predeposit_record_error'), '', 'error');
        }
        $this->assign('info', $info);
        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('predeposit');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('recharge_show');
        return $this->fetch($this->template_dir . 'recharge_show');
    }

    /**
     * 删除充值记录
     *
     */
    public function recharge_del() {
        $pdr_id = intval(input('param.id'));
        if ($pdr_id <= 0) {
            ds_show_dialog(lang('predeposit_parameter_error'), '', 'error');
        }

        $predeposit_model = model('predeposit');
        $condition = array();
        $condition['pdr_member_id'] = session('member_id');
        $condition['pdr_id'] = $pdr_id;
        $condition['pdr_payment_state'] = 0;
        $result = $predeposit_model->delPdRecharge($condition);
        if ($result) {
            ds_show_dialog(lang('ds_common_del_succ'), 'reload', 'succ', 'CUR_DIALOG.close()');
        } else {
            ds_show_dialog(lang('ds_common_del_fail'), '', 'error');
        }
    }

    /**
     * 预存款变更日志
     */
    public function pd_log_list() {
        $condition = array();
        $condition['lg_member_id'] = session('member_id');

        $predeposit_model = model('predeposit');
        $predeposit_list = $predeposit_model->getPdLogList($condition, 10, '*', 'lg_id desc');

        $this->assign('show_page', $predeposit_model->page_info->render());
        $this->assign('predeposit_list', $predeposit_list);
        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('predeposit');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('loglist');
        return $this->fetch($this->template_dir . 'pd_log_list');
    }

    /**
     * 充值卡余额变更日志
     */
    public function rcb_log_list() {
        $rcblog_model = model('rcblog');
        $rcblog_list = $rcblog_model->getRechargecardBalanceLogList(array('member_id' => session('member_id')), 10, 'rcblog_id desc');
        
        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('predeposit');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('rcb_log_list');
        $this->assign('show_page', $rcblog_model->page_info->render());
        $this->assign('rcblog_list', $rcblog_list);
        return $this->fetch($this->template_dir . 'rcb_log_list');
    }

    /**
     * 申请提现
     */
    public function pd_cash_add() {
        if (request()->isPost()) {
            $obj_validate = new Validate();
            $pdc_amount=abs(floatval(input('post.pdc_amount')));
            $data=[
                'pdc_amount' =>$pdc_amount,
                'pdc_bank_name'  =>input('post.pdc_bank_name'),
                'pdc_bank_no'  =>input('post.pdc_bank_no'),
                'pdc_bank_user'  =>input('post.pdc_bank_user'),
                'password'      =>input('post.password')
            ];

            $rule=[
                ['pdc_amount','require|min:0.01',lang('predeposit_cash_add_pricemin_error')],
                ['pdc_bank_name','require',lang('predeposit_cash_add_shoukuanbanknull_error')],
                ['pdc_bank_no','require',lang('predeposit_cash_add_shoukuanaccountnull_error')],
                ['pdc_bank_user','require',lang('predeposit_cash_add_shoukuannamenull_error')],
                ['password','require','请输入支付密码']
            ];

            $error = $obj_validate->check($data,$rule);
            if (!$error) {
                ds_show_dialog($obj_validate->getError(), '', 'error');
            }

            $predeposit_model = model('predeposit');
            $member_model = model('member');
            $member_info = $member_model->getMemberInfoByID(session('member_id'));
            //验证支付密码
            if (md5(input('post.password')) != $member_info['member_paypwd']) {
                ds_show_dialog('支付密码错误', '', 'error');
            }
            //验证金额是否足够
            if (floatval($member_info['available_predeposit']) < $pdc_amount) {
                ds_show_dialog(lang('predeposit_cash_shortprice_error'), url('Predeposit/pd_cash_list'), 'error');
            }
            try {
                $predeposit_model->startTrans();
                $pdc_sn = makePaySn(session('member_id'));
                $data = array();
                $data['pdc_sn'] = $pdc_sn;
                $data['pdc_member_id'] = session('member_id');
                $data['pdc_member_name'] = session('member_name');
                $data['pdc_amount'] = $pdc_amount;
                $data['pdc_bank_name'] = input('post.pdc_bank_name');
                $data['pdc_bank_no'] = input('post.pdc_bank_no');
                $data['pdc_bank_user'] = input('post.pdc_bank_user');
                $data['pdc_addtime'] = TIMESTAMP;
                $data['pdc_payment_state'] = 0;
                $insert = $predeposit_model->addPdcash($data);
                if (!$insert) {
                    ds_show_dialog(lang('predeposit_cash_add_fail'),'','error');
                }
                //冻结可用预存款
                $data = array();
                $data['member_id'] = $member_info['member_id'];
                $data['member_name'] = $member_info['member_name'];
                $data['amount'] = $pdc_amount;
                $data['order_sn'] = $pdc_sn;
                $predeposit_model->changePd('cash_apply', $data);
                $predeposit_model->commit();
                ds_show_dialog(lang('predeposit_cash_add_success'), url('Predeposit/pd_cash_list'), 'succ', 'CUR_DIALOG.close()');
            } catch (Exception $e) {
                $predeposit_model->rollback();
                ds_show_dialog($e->getMessage(), url('Predeposit/pd_cash_list'), 'error');
            }
        }
    }

    /**
     * 提现列表
     */
    public function pd_cash_list() {
        $condition = array();
        $condition['pdc_member_id'] = session('member_id');

        $sn_search = input('post.sn_search');
        if (!empty($sn_search)) {
            $condition['pdc_sn'] = $sn_search;
        }
        $paystate_search = input('post.paystate_search');
        if (isset($paystate_search)) {
            $condition['pdc_payment_state'] = intval($paystate_search);
        }

        $pdcash_list = db('pdcash')->where($condition)->order('pdc_id desc')->paginate();
        $this->assign('pdcash_list', $pdcash_list);
        $this->assign('show_page', $pdcash_list->render());

        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('predeposit');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('cashlist');
        return $this->fetch($this->template_dir . 'pd_cash_list');
    }

    /**
     * 提现记录详细
     */
    public function pd_cash_info() {
        $pdc_id = intval(input('param.id'));
        if ($pdc_id <= 0) {
            $this->error(lang('predeposit_parameter_error'), 'Home/predeposit/pd_cash_list');
        }
        $predeposit_model = model('predeposit');
        $condition = array();
        $condition['pdc_member_id'] = session('member_id');
        $condition['pdc_id'] = $pdc_id;
        $info = $predeposit_model->getPdcashInfo($condition);
        if (empty($info)) {
            $this->error(lang('predeposit_record_error'), 'Home/predeposit/pd_cash_list');
        }

       $this->setMemberCurItem('cashinfo');
        $this->setMemberCurMenu('predeposit');
        $this->assign('info', $info);
        return $this->fetch($this->template_dir . 'pd_cash_info');
    }

    /**
     *    栏目菜单
     */
    function getMemberItemList() {
        $item_list = array(
            array(
                'name' => 'loglist',
                'text' => lang('明细列表'),
                'url' => url('Predeposit/pd_log_list'),
            ),
            array(
                'name' => 'recharge_list',
                'text' => lang('充值列表'),
                'url' => url('Predeposit/index'),
            ),
            array(
                'name' => 'cashlist',
                'text' => lang('提现列表'),
                'url' => url('Predeposit/pd_cash_list'),
            ),
            array(
                'name' => 'rcb_log_list',
                'text' => lang('充值卡余额'),
                'url' => url('Predeposit/rcb_log_list'),
            ),
        );

        if (request()->action() == 'rechargeinfo') {
            $item_list[] = array(
                'name' => 'rechargeinfo',
                'text' => lang('ds_member_path_predeposit_rechargeinfo'),
                'url' => url('Predeposit/rechargeinfo'),
            );
        }

        if (request()->action() == 'recharge_add') {
            $item_list[] = array(
                'name' => 'recharge_add',
                'text' => lang('在线充值'),
                'url' => url('Predeposit/recharge_add'),
            );
        }

        if (request()->action() == 'rechargecard_add') {
            $item_list[] = array(
                'name' => 'rechargecard_add',
                'text' => lang('充值卡充值'),
                'url' => url('Predeposit/rechargecard_add'),
            );
        }

        if (request()->action() == 'cashadd') {
            $item_list[] = array(
                'name' => 'cashadd',
                'text' => lang('ds_member_path_predeposit_cashadd'),
                'url' => url('Predeposit/cashadd'),
            );
        }

        if (request()->action() == 'cashinfo') {
            $item_list[] = array(
                'name' => 'cashinfo',
                'text' => lang('ds_member_path_predeposit_cashinfo'),
                'url' => url('Predeposit/cashinfo'),
            );
        }


        return $item_list;
    }

}
