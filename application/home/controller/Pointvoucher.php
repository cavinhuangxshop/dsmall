<?php

namespace app\home\controller;
use think\Lang;

class Pointvoucher extends BasePointShop
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH.'home/lang/zh-cn/voucher.lang.php');
        if (config('voucher_allow') != 1){
            ds_show_dialog(lang('voucher_pointunavailable'),HOME_SITE_URL,'error');
        }
    }

    public function index(){
        $this->pointvoucher();
        return $this->fetch($this->template_dir.'pointvoucher');
    }
    /**
     * 代金券列表
     */
    public function pointvoucher(){
        //查询会员及其附属信息
        parent::pointshopMInfo();

        $voucher_model = model('voucher');

        //代金券模板状态
        $templatestate_arr = $voucher_model->getTemplateState();

        //查询会员信息
        $member_info = model('member')->getMemberInfoByID(session('member_id'));

        //查询代金券列表
        $where = array();
        $where['vouchertemplate_state'] = $templatestate_arr['usable'][0];
        $where['vouchertemplate_enddate'] = array('gt',time());
        if (intval(input('storeclass_id')) > 0){
            $where['vouchertemplate_sc_id'] = intval(input('storeclass_id'));
        }
        if (intval(input('price')) > 0){
            $where['vouchertemplate_price'] = intval(input('price'));
        }
        //查询仅我能兑换和所需积分
        $points_filter = array();
        if (intval(input('isable')) == 1){
            $points_filter['isable'] = $member_info['member_points'];
        }
        if (intval(input('points_min')) > 0){
            $points_filter['min'] = intval(input('points_min'));
        }
        if (intval(input('points_max')) > 0){
            $points_filter['max'] = intval(input('points_max'));
        }
        if (count($points_filter) > 0){
            asort($points_filter);
            if (count($points_filter) > 1){
                $points_filter = array_values($points_filter);
                $where['vouchertemplate_points'] = array('between',array($points_filter[0],$points_filter[1]));
            } else {
                if ($points_filter['min']){
                    $where['vouchertemplate_points'] = array('egt',$points_filter['min']);
                } elseif ($points_filter['max']) {
                    $where['vouchertemplate_points'] = array('elt',$points_filter['max']);
                } elseif ($points_filter['isable']) {
                    $where['vouchertemplate_points'] = array('elt',$points_filter['isable']);
                }
            }
        }
        //排序
        switch (input('orderby')){
            case 'exchangenumdesc':
                $orderby = 'vouchertemplate_giveout desc,';
                break;
            case 'exchangenumasc':
                $orderby = 'vouchertemplate_giveout asc,';
                break;
            case 'pointsdesc':
                $orderby = 'vouchertemplate_points desc,';
                break;
            case 'pointsasc':
                $orderby = 'vouchertemplate_points asc,';
                break;
            default:
                $orderby = '';
        }
        $orderby .= 'vouchertemplate_id desc';
        $voucherlist = $voucher_model->getVouchertemplateList($where, '*', 0, 18, $orderby);
        $this->assign('voucherlist',$voucherlist);
        $this->assign('show_page', $voucher_model->page_info->render());

        //查询代金券面额
        $pricelist = $voucher_model->getVoucherPriceList();
        $this->assign('pricelist',$pricelist);

        //查询店铺分类
        $store_class = rkcache('storeclass', true);
        $this->assign('store_class', $store_class);

        //分类导航
        $nav_link = array(
            0=>array('title'=>lang('homepage'),'link'=>HOME_SITE_URL),
            1=>array('title'=>'积分中心','link'=>url('Pointshop/index')),
            2=>array('title'=>'代金券列表')
        );
        $this->assign('nav_link_list', $nav_link);
    }
    /**
     * 兑换代金券
     */
    public function voucherexchange(){
        $vid = intval(input('param.vid'));
        if(session('is_login') != '1'){
            $js = "login_dialog();";
            ds_show_dialog('','','js',$js);
        }elseif (input('param.dialog')){
            $js = "CUR_DIALOG = ajax_form('vexchange', '".lang('home_voucher_exchangtitle')."', '".url('Pointvoucher/voucherexchange',['vid'=>$vid])."', 550);";
            ds_show_dialog('','','js',$js);
            die;
        }
        $result = true;
        $message = "";
        if ($vid <= 0){
            $result = false;
            lang('wrong_argument');
        }
        if ($result){
            //查询可兑换代金券模板信息
            $template_info = model('voucher')->getCanChangeTemplateInfo($vid,intval(session('member_id')),intval(session('store_id')));
            if ($template_info['state'] == false){
                $result = false;
                $message = $template_info['msg'];
            }else {
                //查询会员信息
                $member_info = model('member')->getMemberInfoByID(session('member_id'));
                $this->assign('member_info',$member_info);
                $this->assign('template_info',$template_info['info']);
            }
        }
        $this->assign('message',$message);
        $this->assign('result',$result);
        echo $this->fetch($this->template_dir.'exchange');exit;
    }
    /**
     * 兑换代金券保存信息
     *
     */
    public function voucherexchange_save(){
        if(session('is_login') != '1'){
            $js = "login_dialog();";
            ds_show_dialog('','','js',$js);
        }
        $vid = intval(input('post.vid'));
        $js = "DialogManager.close('vexchange');";
        if ($vid <= 0){
            ds_show_dialog(lang('wrong_argument'),'','error',$js);
        }
        $voucher_model = model('voucher');
        //验证是否可以兑换代金券
        $data = $voucher_model->getCanChangeTemplateInfo($vid,intval(session('member_id')),intval(session('store_id')));
        if ($data['state'] == false){
            ds_show_dialog($data['msg'],'','error',$js);
        }
        //添加代金券信息
        $data = $voucher_model->exchangeVoucher($data['info'],session('member_id'),session('member_name'));
        if ($data['state'] == true){
            ds_show_dialog($data['msg'],'','succ',$js);
        } else {
            ds_show_dialog($data['msg'],'','error',$js);
        }
    }
    
}