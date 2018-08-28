<?php
namespace app\home\controller;
use think\Lang;
class Membersnshome extends BaseSns {

    const MAX_RECORDNUM = 20; //允许插入新记录的最大条数(注意在sns中该常量是一样的，注意与member_snshome中的该常量一致)
    
    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/member_sns.lang.php');
        Lang::load(APP_PATH . 'home/lang/zh-cn/sns_home.lang.php');
        $where = array();
        $where['name'] = !empty($this->master_info['member_truename']) ? $this->master_info['member_truename'] : $this->master_info['member_name'];
        model('seo')->type('sns')->param($where)->show();
        //允许插入新记录的最大条数
        $this->assign('max_recordnum', self::MAX_RECORDNUM);
    }
    
    

    /**
     * SNS首页
     */
    public function index() {
        $this->get_visitor(); // 获取访客
        $this->sns_messageboard(); // 留言版

        // 分享的商品
        $where = array();
        $where['sharegoods_memberid'] = $this->master_id;
        $where['sharegoods_isshare'] = 1;
        switch ($this->relation) {
            case 2:
                $where['sharegoods_privacy'] = array('in', array(0, 1));
                break;
            case 1:
            default:
                $where['sharegoods_privacy'] = 0;
                break;
        }
        $goodslist = db('snssharegoods')->alias('snssharegoods')
                ->join('__SNSGOODS__ snsgoods','snssharegoods.sharegoods_goodsid = snsgoods.snsgoods_goodsid')
                ->where($where)
                ->order('snssharegoods.sharegoods_addtime desc')
                ->limit(3)
                ->select();
        if (session('is_login') == '1' && !empty($goodslist)) {
            foreach ($goodslist as $k => $v) {
                if (!empty($v['snsgoods_likemember'])) {
                    $v['snsgoods_likemember_arr'] = explode(',', $v['snsgoods_likemember']);
                    $v['snsgoods_havelike'] = in_array(session('member_id'), $v['snsgoods_likemember_arr']) ? 1 : 0;
                }
                $goodslist[$k] = $v;
            }
        }
        $this->assign('goodslist', $goodslist);

        // 我的图片
        $pic_list = db('snsalbumpic')->where(array('member_id' => $this->master_id))->order('ap_id desc')->limit(3)->select();
        $this->assign('pic_list', $pic_list);

        // 分享的店铺
        $condition = array();
        $condition['snssharestore.sharestore_memberid'] = $this->master_id;
        switch ($this->relation) {
            case 3:
                $condition['snssharestore.sharestore_privacy'] = "";
                break;
            case 2:
                $condition['snssharestore.sharestore_privacy'] = array('in',array('0','1'));
                break;
            case 1:
                $condition['snssharestore.sharestore_privacy'] = "0";
                break;
            default:
                $condition['snssharestore.sharestore_privacy'] = "0";
                break;
        }
        $sharestore_model = model('snssharestore');
        $storelist = $sharestore_model->getSnssharestoreList($condition);
        $storelist_new = array();
        if (!empty($storelist)) {
            //获得店铺ID
            $storeid_arr = '';
            foreach ($storelist as $k => $v) {
                $storelist_new[$v['store_id']] = $v;
            }
            $storeid_arr = array_keys($storelist_new);
            //查询店铺推荐商品
            $goods_model = model('goods');
            $goodslist = $goods_model->getGoodsOnlineList(array('store_id' => array('in', $storeid_arr)), 'goods_id,goods_name,goods_image,store_id');
            if (!empty($goodslist)) {
                foreach ($goodslist as $k => $v) {
                    $v['goodsurl'] = url('Goods/index',['goods_id' => $v['goods_id']]);
                    $storelist_new[$v['store_id']]['goods'][] = $v;
                }
            }
        }
        //信息输出
        $this->assign('storelist', $storelist_new);

        $where = array();
        $where['tracelog_memberid'] = $this->master_id;
        $where['tracelog_state'] = 0;
        switch ($this->relation) {
            case 2:
                $where['tracelog_privacy'] = array('in', array(0, 1));
                break;
            case 1:
            default:
                $where['tracelog_privacy'] = 0;
        }
        $tracelog_list = db('snstracelog')->where($where)->order('tracelog_id desc')->limit(4)->select();
        if (!empty($tracelog_list)) {
            foreach ($tracelog_list as $k => $v) {
                if ($v['tracelog_title']) {
                    $v['tracelog_title'] = str_replace("%siteurl%", HOME_SITE_URL . DS, $v['tracelog_title']);
                    $v['tracelog_title_forward'] = '|| @' . $v['tracelog_membername'] . lang('ds_colon') . preg_replace("/<a(.*?)href=\"(.*?)\"(.*?)>@(.*?)<\/a>([\s|:|：]|$)/is", '@${4}${5}', $v['tracelog_title']);
                }
                if (!empty($v['tracelog_content'])) {
                    //替换内容中的siteurl
                    $v['tracelog_content'] = str_replace("%siteurl%", HOME_SITE_URL . DS, $v['tracelog_content']);
                }
                $tracelog_list[$k] = $v;
            }
        }
        $this->assign('tracelog_list', $tracelog_list);

        $this->assign('type', 'snshome');
        $this->assign('menu_sign', 'snshome');
        return $this->fetch($this->template_dir.'sns_home');
    }

    /**
     * 获取分享和喜欢商品列表
     */
    public function shareglist() {
        
        $type = trim(input('param.type'));
        
        //查询分享商品信息
        //动态列表
        $condition = array();
        $condition['snssharegoods.sharegoods_memberid'] = $this->master_id;
        switch ($this->relation) {
            case 3:
                $condition['snssharegoods.sharegoods_privacy'] = '';
                break;
            case 2:
                $condition['snssharegoods.sharegoods_privacy'] = array('in',array('0','1'));
                break;
            case 1:
                $condition['snssharegoods.sharegoods_privacy'] = "0";
                break;
            default:
                $condition['snssharegoods.sharegoods_privacy'] = "0";
                break;
        }
        if ($type == 'like') {
            $condition['snssharegoods.sharegoods_islike'] = "1"; //喜欢的商品
            $order = " snssharegoods.sharegoods_likeaddtime desc";
        } else {
            $condition['snssharegoods.sharegoods_isshare'] = "1"; //分享的商品
            $order = " snssharegoods.sharegoods_addtime desc";
        }
        $sharegoods_model = model('snssharegoods');
        $goodslist = $sharegoods_model->getSnssharegoodsList($condition, 20,$order);
        if ($type != 'like' && !empty($goodslist)) {
            $shareid_array = array();
            foreach ($goodslist as $val) {
                $shareid_array[] = $val['sharegoods_id'];
            }
            $pic_array = db('snsalbumpic')->field('count(item_id) as count,item_id,ap_cover')->where(array('ap_type' => 1, 'item_id' => array('in', $shareid_array)))->group('item_id')->select();
            if (!empty($pic_array)) {
                $pic_list = array();
                foreach ($pic_array as $val) {
                    $val['ap_cover'] = UPLOAD_SITE_URL . '/' . ATTACH_MALBUM . '/' . $this->master_id . '/' . str_ireplace('.', '_1024.', $val['ap_cover']);
                    $pic_list[$val['item_id']] = $val;
                }
                $this->assign('pic_list', $pic_list);
            }
        }
        if (session('is_login') == '1' && !empty($goodslist)) {
            foreach ($goodslist as $k => $v) {
                if (!empty($v['snsgoods_likemember'])) {
                    $v['snsgoods_likemember_arr'] = explode(',', $v['snsgoods_likemember']);
                    $v['snsgoods_havelike'] = in_array(session('member_id'), $v['snsgoods_likemember_arr']) ? 1 : 0;
                }
                $goodslist[$k] = $v;
            }
        }
        //信息输出
        $this->assign('goodslist', $goodslist);
        $this->assign('show_page', $sharegoods_model->page_info->render());
        $this->assign('menu_sign', 'sharegoods');
        
        
        if ($type == 'like') {
            return $this->fetch($this->template_dir.'sns_likegoodslist');
        } else {
            return $this->fetch($this->template_dir.'sns_sharegoodslist');
        }
    }

    /**
     * 分享和喜欢商品详细页面
     */
    public function goodsinfo() {
        $share_id = intval(input('param.id'));
        if ($share_id <= 0) {
            ds_show_dialog(lang('wrong_argument'), url('Membersnshome/index',['mid'=>$this->master_id]), 'error');
        }
        //查询分享和喜欢商品信息
        $sharegoods_model = model('snssharegoods');
        $condition = array();
        $condition['snssharegoods.sharegoods_id'] = $share_id;
        $condition['snssharegoods.sharegoods_memberid'] = $this->master_id;
        $sharegoods_list = $sharegoods_model->getSnssharegoodsList($condition);
        unset($condition);
        if (empty($sharegoods_list)) {
            ds_show_dialog(lang('wrong_argument'), url('Membersnshome/index',['mid'=>$this->master_id]), 'error');
        }
        $sharegoods_info = $sharegoods_list[0];
        if (!empty($sharegoods_info['snsgoods_goodsimage'])) {
            $image_arr = explode('_small', $sharegoods_info['snsgoods_goodsimage']);
            $sharegoods_info['snsgoods_goodsimage'] = $image_arr[0];
        }
        $sharegoods_info['snsgoods_goodsurl'] = url('Goods/index',['goods_id' => $sharegoods_info['snsgoods_goodsid']]);
        if (session('is_login') == '1') {
            if (!empty($sharegoods_info['snsgoods_likemember'])) {
                $sharegoods_info['snsgoods_likemember_arr'] = explode(',', $sharegoods_info['snsgoods_likemember']);
                $sharegoods_info['snsgoods_havelike'] = in_array(session('member_id'), $sharegoods_info['snsgoods_likemember_arr']) ? 1 : 0;
            }
        }

        if (input('param.type') != 'like') {
            // 买下秀图片
            $pic_list = db('snsalbumpic')->where(array('member_id' => $this->master_id, 'ap_type' => 1, 'item_id' => $share_id))->select();
            if (!empty($pic_list)) {
                foreach ($pic_list as $key => $val) {
                    $pic_list[$key]['ap_cover'] = UPLOAD_SITE_URL . '/' . ATTACH_MALBUM . '/' . $this->master_id . '/' . str_ireplace('.', '_1024.', $val['ap_cover']);
                }
                $this->assign('pic_list', $pic_list);
            }
        }

        $where = array();
        $where['sharegoods_memberid'] = $this->master_id;
        $where['sharegoods_id'] = array('neq', $share_id);
        if (input('param.type') == 'like') {
            $where['sharegoods_islike'] = 1;
        } else {
            $where['sharegoods_isshare'] = 1;
        }

        // 更多分享/喜欢商品
        
        $sharegoods_list = db('snssharegoods')->alias('snssharegoods')->join('__SNSGOODS__ snsgoods','snssharegoods.sharegoods_goodsid=snsgoods.snsgoods_goodsid')->where($where)->limit(9)->select();
        
        $this->assign('sharegoods_list', $sharegoods_list);
        $this->assign('sharegoods_info', $sharegoods_info);
        $this->assign('menu_sign', 'sharegoods');
        return $this->fetch($this->template_dir.'sns_goodsinfo');
    }

    /**
     * 评论前10条记录
     */
    public function commenttop() {
        $snscomment_model = model('snscomment');
        //查询评论总数
        $condition = array();
        $condition['snscomment_originalid'] = input('param.id');
        $condition['snscomment_originaltype'] = input('param.type'); //原帖类型 0表示动态信息 1表示分享商品 2表示喜欢商品
        $condition['snscomment_state'] = "0"; //0表示正常，1表示屏蔽
        $countnum = $snscomment_model->getSnscommentCount($condition);
        //动态列表
        $condition['limit'] = "10";
        $commentlist = $snscomment_model->getSnscommentList($condition);
        $showmore = '0'; //是否展示更多的连接
        if ($countnum > count($commentlist)) {
            $showmore = '1';
        }
        $this->assign('countnum', $countnum);
        $this->assign('showmore', $showmore);
        $this->assign('showtype', 1); //页面展示类型 0表示分页 1表示显示前几条
        $this->assign('tid', input('param.id'));
        $this->assign('type', input('param.type'));
        $this->assign('commentlist', $commentlist);
        echo $this->fetch($this->template_dir.'sns_commentlist');exit;
    }

    /**
     * 评论列表
     */
    public function commentlist() {
        $snscomment_model = model('snscomment');
        //查询评论总数
        $condition = array();
        $condition['snscomment_originalid'] = input('param.id');
        $condition['snscomment_originaltype'] = input('param.type'); //原帖类型 0表示动态信息 1表示分享商品
        $condition['snscomment_state'] = "0"; //0表示正常，1表示屏蔽
        $countnum = $snscomment_model->getSnscommentCount($condition);
        //评价列表
        $commentlist = $snscomment_model->getSnscommentList($condition, 10);

        $this->assign('countnum', $countnum);
        $this->assign('tid', input('param.id'));
        $this->assign('type', input('param.type'));
        $this->assign('showtype', '0'); //页面展示类型 0表示分页 1表示显示前几条
        //验证码
        $this->assign('commentlist', $commentlist);
        $this->assign('show_page', $snscomment_model->page_info->render());
        echo $this->fetch($this->template_dir.'sns_commentlist');exit;
    }

    /**
     * 获取店铺列表(不登录就可以查看)
     */
    public function storelist() {
        //查询分享店铺信息
        //动态列表
        $condition = array();
        $condition['snssharestore.sharestore_memberid'] = $this->master_id;
        switch ($this->relation) {
            case 3:
                $condition['snssharestore.sharestore_privacy'] = "";
                break;
            case 2:
                $condition['snssharestore.sharestore_privacy'] = array('in',array('0','1'));
                break;
            case 1:
                $condition['snssharestore.sharestore_privacy'] = "0";
                break;
            default:
                $condition['snssharestore.sharestore_privacy'] = "0";
                break;
        }
        $sharestore_model = model('snssharestore');
        $storelist = $sharestore_model->getSnssharestoreList($condition, 10);
        $storelist_new = array();
        if (!empty($storelist)) {
            //获得店铺ID
            $storeid_arr = '';
            foreach ($storelist as $k => $v) {
                $storelist_new[$v['store_id']] = $v;
            }
            $storeid_arr = array_keys($storelist_new);
            //查询店铺推荐商品
            $goods_model = model('goods');
            $goodslist = $goods_model->getGoodsOnlineList(array('store_id' => array('in', $storeid_arr), 'goods_commend' => 1), 'goods_id,store_id,goods_name,goods_image');
            if (!empty($goodslist)) {
                foreach ($goodslist as $k => $v) {
                    $v['goodsurl'] = url('Goods/index',['goods_id' => $v['goods_id']]);
                    $storelist_new[$v['store_id']]['goods'][] = $v;
                }
            }
            foreach ($storeid_arr as $val) {
                $storelist_new[$val]['goods_count'] = $goods_model->getGoodsCommonCount(array('store_id' => $val));
            }
        }
        //信息输出
        $this->assign('storelist', $storelist_new);
        $this->assign('show_page', $sharestore_model->page_info->render());
        $this->assign('menu_sign', 'sharestore');
        return $this->fetch($this->template_dir.'sns_storelist');
    }

    /**
     * 动态列表页面
     */
    public function trace() {
        $this->get_visitor(); // 获取访客
        $this->sns_messageboard(); // 留言版
        $is_owner = false; //是否为主人自己
        if (session('member_id') == intval(input('param.mid'))) {
            $is_owner = true;
        }
        $this->assign('is_owner', $is_owner);
        $this->assign('menu_sign', 'snstrace');
        return $this->fetch($this->template_dir.'sns_hometrace');
    }

    /**
     * 某会员的SNS动态列表
     */
    public function tracelist() {
        $snstracelog_model = model('snstracelog');
        $condition = array();
        $condition['tracelog_memberid'] = $this->master_id;
        switch ($this->relation) {
            case 3:
                $condition['tracelog_privacyin'] = "";
                break;
            case 2:
                $condition['tracelog_privacyin'] = "0','1";
                break;
            case 1:
                $condition['tracelog_privacyin'] = "0";
                break;
            default:
                $condition['tracelog_privacyin'] = "0";
                break;
        }
        $condition['tracelog_state'] = "0";
        $count = $snstracelog_model->getSnstracelogCount($condition);
        
        $delaypage = intval(input('param.delaypage')) > 0 ? intval(input('param.delaypage')) : 1; //本页延时加载的当前页数
        $lazy_arr = lazypage(10, $delaypage, $count, true, input('param.page'), 30,input('param.page')*30);
        //动态列表
        $condition['limit'] = $lazy_arr['limitstart'] . "," . $lazy_arr['delay_eachnum'];
        $tracelog_list = $snstracelog_model->getSnstracelogList($condition,10);
        if (!empty($tracelog_list)) {
            foreach ($tracelog_list as $k => $v) {
                if ($v['tracelog_title']) {
                    $v['tracelog_title'] = str_replace("%siteurl%", HOME_SITE_URL . DS, $v['tracelog_title']);
                    $v['tracelog_title_forward'] = '|| @' . $v['tracelog_membername'] . lang('ds_colon') . preg_replace("/<a(.*?)href=\"(.*?)\"(.*?)>@(.*?)<\/a>([\s|:|：]|$)/is", '@${4}${5}', $v['tracelog_title']);
                }
                if (!empty($v['tracelog_content'])) {
                    //替换内容中的siteurl
                    $v['tracelog_content'] = str_replace("%siteurl%", HOME_SITE_URL . DS, $v['tracelog_content']);
                }
                $tracelog_list[$k] = $v;
            }
        }
        $this->assign('hasmore', $lazy_arr['hasmore']);
        $this->assign('tracelog_list', $tracelog_list);
        $this->assign('show_page', $snstracelog_model->page_info->render());
        $this->assign('type', 'home');
        $this->assign('menu_sign', 'snstrace');
        echo $this->fetch($this->template_dir.'sns_tracelist');exit;
    }

    /**
     * 一条SNS动态及其评论
     */
    public function traceinfo() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('wrong_argument'), '', 'error');
        }
        //查询动态详细
        $snstracelog_model = model('snstracelog');
        $condition = array();
        $condition['tracelog_id'] = "$id";
        $condition['tracelog_memberid'] = "{$this->master_id}";
        switch ($this->relation) {
            case 3:
                $condition['tracelog_privacyin'] = "";
                break;
            case 2:
                $condition['tracelog_privacyin'] = "0','1";
                break;
            case 1:
                $condition['tracelog_privacyin'] = "0";
                break;
            default:
                $condition['tracelog_privacyin'] = "0";
                break;
        }
        $condition['tracelog_state'] = "0";
        $tracelist = $snstracelog_model->getSnstracelogList($condition);
        $traceinfo = array();
        if (!empty($tracelist)) {
            $traceinfo = $tracelist[0];
            if ($traceinfo['tracelog_title']) {
                $traceinfo['tracelog_title'] = str_replace("%siteurl%", HOME_SITE_URL . DS, $traceinfo['tracelog_title']);
                $traceinfo['tracelog_title_forward'] = '|| @' . $traceinfo['tracelog_membername'] . ':' . preg_replace("/<a(.*?)href=\"(.*?)\"(.*?)>@(.*?)<\/a>([\s|:|：]|$)/is", '@${4}${5}', $traceinfo['tracelog_title']);
            }
            if (!empty($traceinfo['tracelog_content'])) {
                //替换内容中的siteurl
                $traceinfo['tracelog_content'] = str_replace("%siteurl%", HOME_SITE_URL . DS, $traceinfo['tracelog_content']);
            }
        }
        $this->assign('traceinfo', $traceinfo);
        $this->assign('menu_sign', 'snshome');
        return $this->fetch($this->template_dir.'sns_traceinfo');
    }

    /**
     * 追加买家秀
     */
    public function add_share() {
        $sid = intval(input('param.sid'));
        if ($sid > 0) {
            // 查询已秀图片
            $where = array();
            $where['member_id'] = session('member_id');
            $where['ap_type'] = 1;
            $where['item_id'] = $sid;
            $pic_list = db('snsalbumpic')->where($where)->select();
            $this->assign('pic_list', $pic_list);
        }
        $sharegoods_info = db('snsgoods')->find(intval(input('param.gid')));
        $this->assign('sharegoods_info', $sharegoods_info);
        $this->assign('sid', $sid);
        echo $this->fetch($this->template_dir.'sns_addshare');exit;
    }

    /**
     * ajax图片上传
     */
    public function image_upload() {
        $ap_id = intval(input('post.apid'));
        /**
         * 相册
         */
        $default_class = db('snsalbumclass')->where(array('member_id' => session('member_id'), 'ac_isdefault' => 1))->find();
        if (empty($default_class)) { // 验证时候存在买家秀相册，不存在添加。
            $default_class = array();
            $default_class['ac_name'] = lang('sns_buyershow');
            $default_class['member_id'] = $this->master_id;
            $default_class['ac_des'] = lang('sns_buyershow_album_des');
            $default_class['ac_sort'] = '255';
            $default_class['ac_isdefault'] = 1;
            $default_class['ac_uploadtime'] = time();
            $default_class['ac_id'] = db('snsalbumclass')->insert($default_class);
        }

        // 验证图片数量
        $count = db('snsalbumpic')->where(array('member_id' => session('member_id')))->count();
        if (config('malbum_max_sum') != 0 && $count >= config('malbum_max_sum')) {
            $output = array();
            $output['error'] = lang('sns_upload_img_max_num_error');
            $output = json_encode($output);
            echo $output;
            die;
        }

        /**
         * 上传图片
         */
        $upload_filename = trim(input('post.id'));
        $file_object = request()->file($upload_filename);
        $upload_path = BASE_UPLOAD_PATH . DS . ATTACH_MALBUM . DS . session('member_id') . DS;
        $file_name = session('member_id').'_'.date('YmdHis') . rand(10000, 99999);
        if ($ap_id > 0) {
            $pic_info = db('snsalbumpic')->find($ap_id);
            if (!empty($pic_info)){
                // 原图存在设置图片名称为原图名称
                $file_name = $pic_info['ap_cover'];
            }
        }
        $upload_result = $file_object->rule('uniqid')->validate(['ext' => ALLOW_IMG_EXT])->move($upload_path, $file_name);
        if ($upload_result) {
            $file_name = $upload_result->getFilename();
            //生成缩略图
            ds_create_thumb($upload_path, $file_name, '240,1024', '240,1024', '_small,_normal');
        } else {
            $output = array();
            $output['error'] = $file_object->getError();
            $output = json_encode($output);
            echo $output;
            die;
        }
        


        if ($ap_id <= 0) {  // 如果原图存在，则不需要在插入数据库
            list($width, $height, $type, $attr) = getimagesize(BASE_UPLOAD_PATH . DS . ATTACH_MALBUM . DS . session('member_id') . DS . $file_name);

            $insert = array();
            $insert['ap_name'] = $file_name;
            $insert['ac_id'] = $default_class['ac_id'];
            $insert['ap_cover'] = $file_name;
            $insert['ap_size'] = intval($_FILES[$upload_filename]['size']);
            $insert['ap_spec'] = $width . 'x' . $height;
            $insert['ap_uploadtime'] = time();
            $insert['member_id'] = session('member_id');
            $insert['ap_type'] = 1;
            $insert['item_id'] = intval(input('post.sid'));
            $result = db('snsalbumpic')->insert($insert);
        }
        $data = array();
        $data['file_name'] = $file_name;
        $data['file_id'] = $ap_id > 0 ? $pic_info['ap_id'] : $result;

        /**
         * 整理为json格式
         */
        $output = json_encode($data);
        echo $output;
        die;
    }

    /**
     * ajax删除图片
     */
    public function del_sharepic() {
        $ap_id = intval(input('param.apid'));
        $data = array();
        if ($ap_id > 0) {
            $data['type'] = 'false';
            $conditon = array('ap_id' => $ap_id, 'member_id' => session('member_id'));
            $snsalbumpic_info = db('snsalbumpic')->where($conditon)->find();
            if($snsalbumpic_info){
                $upload_file = BASE_UPLOAD_PATH . DS . ATTACH_MALBUM . DS . session('member_id');
                //删除图片
                ds_unlink($upload_file, $snsalbumpic_info['ap_cover']);
                
                $result = db('snsalbumpic')->where($conditon)->delete();
                if($result){
                    $data['type'] = 'true';
                }
            }
        } else {
            $data['type'] = 'false';
        }
        /**
         * 整理为json格式
         */
        $output = json_encode($data);
        echo $output;
        die;
    }

}
