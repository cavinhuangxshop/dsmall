<?php

/**
 * 买家相册
 */

namespace app\home\controller;

use think\Lang;
use think\Model;

class Snsalbum extends BaseSns {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/snsalbum.lang.php');
        $this->assign('menu_sign', 'snsalbum');

        $where = array();
        $where['name'] = !empty($this->master_info['member_truename']) ? $this->master_info['member_truename'] : $this->master_info['member_name'];
        model('seo')->type('sns')->param($where)->show();
    }

    public function index() {
        $this->album_cate();
        exit;
    }

    /**
     * 相册分类列表
     *
     */
    public function album_cate() {
        // 验证是否存在默认相册
        $where = array();
        $where['member_id'] = $this->master_id;
        $where['ac_isdefault'] = 1;
        $class_info = db('snsalbumclass')->where($where)->find();
        if (empty($class_info)) {
            $insert = array();
            $insert['ac_name'] = lang('sns_buyershow');
            $insert['member_id'] = $this->master_id;
            $insert['ac_des'] = lang('sns_buyershow_album_des');
            $insert['ac_sort'] = 1;
            $insert['ac_isdefault'] = 1;
            $insert['ac_uploadtime'] = time();
            db('snsalbumclass')->insert($insert);
        }

        /**
         * 相册分类
         */
        $where = array(); // 条件
        $where['member_id'] = $this->master_id;
        $order = 'ac_sort asc';
        // 相册
        $ac_list_object = db('snsalbumclass')->where($where)->order($order)->paginate(10);
        $ac_list = $ac_list_object->items();
        $count = 0; // 图片总数量
        if (!empty($ac_list)) {
            // 相册中商品数量
            $ap_count = db('snsalbumpic')->field('count(ap_id) as count,ac_id')->where($where)->group('ac_id')->select();
            $ap_count = array_under_reset($ap_count, 'ac_id', 1);
            foreach ($ac_list as $key => $val) {
                if (isset($ap_count[$val['ac_id']])) {
                    $count += intval($ap_count[$val['ac_id']]['count']);
                    $ac_list[$key]['count'] = $ap_count[$val['ac_id']]['count'];
                } else {
                    $ac_list[$key]['count'] = 0;
                }
            }
        }
        $this->assign('count', $count);
        $this->assign('ac_list', $ac_list);
        $this->assign('show_page', $ac_list_object->render());
        echo $this->fetch($this->template_dir . 'sns_album_list');
    }

    /**
     * 相册分类添加
     *
     */
    public function album_add() {
        $class_count = db('snsalbumclass')->where(array('member_id' => $this->master_id))->count();
        $this->assign('class_count', $class_count);
        echo $this->fetch($this->template_dir . 'sns_album_class_add');
        exit;
    }

    /**
     * 相册保存
     *
     */
    public function album_add_save() {
        if (request()->isPost()) {
            $class_count = db('snsalbumclass')->where(array('member_id' => session('member_id')))->count();
            if ($class_count >= 10) {
                ds_show_dialog(lang('album_class_save_max_10'), url('Snsalbum/index'), 'error');
            }
            $insert = array();
            $insert['ac_name'] = input('post.name');
            $insert['member_id'] = session('member_id');
            $insert['ac_des'] = input('post.description');
            $insert['ac_sort'] = input('post.sort');
            $insert['ac_uploadtime'] = TIMESTAMP;

            $return = db('snsalbumclass')->insert($insert);
            if ($return) {
                ds_show_dialog(lang('album_class_save_succeed'), url('Snsalbum/index'), 'succ', empty(input('get.inajax')) ? '' : 'CUR_DIALOG.close();');
            }
        }
        ds_show_dialog(lang('album_class_save_lose'));
    }

    /**
     * 相册分类编辑
     */
    public function album_edit() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            echo lang('album_parameter_error');
            exit;
        }
        $where = array();
        $where['ac_id'] = $id;
        $where['member_id'] = session('member_id');
        $class_info = db('snsalbumclass')->where($where)->find();
        $this->assign('class_info', $class_info);

        echo $this->fetch($this->template_dir . 'sns_album_class_edit');
        exit;
    }

    /**
     * 相册分类编辑保存
     */
    public function album_edit_save() {
        $update = array();
        $update['ac_id'] = intval(input('post.id'));
        $update['ac_name'] = input('post.name');
        $update['ac_des'] = input('post.description');
        $update['ac_sort'] = input('post.sort');

        // 更新
        $re = db('snsalbumclass')->update($update);
        if ($re) {
            ds_show_dialog(lang('album_class_edit_succeed'), url('Snsalbum/index'), 'succ', empty(input('get.inajax')) ? '' : 'CUR_DIALOG.close();');
        } else {
            ds_show_dialog(lang('album_class_edit_lose'));
        }
    }

    /**
     * 相册删除
     */
    public function album_del() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            $this->error(lang('album_parameter_error'));
        }

        /**
         * 删除分类
         */
        $return = db('snsalbumclass')->where(array('ac_id' => $id, 'member_id' => session('member_id')))->delete();
        if ($return == 0) {
            ds_show_dialog(lang('album_class_file_del_lose'));
        }
        /**
         * 更新图片分类
         */
        $where = array();
        $where['ac_isdefault'] = 1;
        $where['member_id'] = session('member_id');
        $class_info = db('snsalbumclass')->where($where)->find();
        $return = db('snsalbumpic')->where('ac_id', $id)->update(array('ac_id' => $class_info['ac_id']));
        ds_show_dialog(lang('album_class_file_del_succeed'), url('Snsalbum/index'), 'succ');
    }

    /**
     * 图片列表
     */
    public function album_pic_list() {
        $id = intval(input('param.id'));
        if ($id <= 0) {
            $this->error(lang('album_parameter_error'));
        }

        $where = array();
        $where['ac_id'] = $id;
        $param['member_id'] = $this->master_id;
        $order = 'ap_id desc';
        $sort = input('param.sort');
        if ($sort != '') {
            switch ($sort) {
                case '0':
                    $order = 'ap_uploadtime desc';
                    break;
                case '1':
                    $order = 'ap_uploadtime asc';
                    break;
                case '2':
                    $order = 'ap_size desc';
                    break;
                case '3':
                    $order = 'ap_size asc';
                    break;
                case '4':
                    $order = 'ap_name desc';
                    break;
                case '5':
                    $order = 'ap_name asc';
                    break;
            }
        }
        $pic_list = db('snsalbumpic')->where($where)->order($order)->paginate(36,false,['query' => request()->param()]);
        $this->assign('pic_list', $pic_list);
        $this->assign('show_page', $pic_list->render());


        /**
         * 相册列表
         */
        $where = array();
        $where['member_id'] = $this->master_id;
        $class_array = db('snsalbumclass')->where($where)->select();
        if (empty($class_array)) {
            $this->error(lang('wrong_argument'));
        }

        // 整理
        $class_array = array_under_reset($class_array, 'ac_id');
        $class_list = $class_info = array();
        foreach ($class_array as $val) {
            if ($val['ac_id'] == $id) {
                $class_info = $val;
            } else {
                $class_list[] = $val;
            }
        }
        $this->assign('class_list', $class_list);
        $this->assign('class_info', $class_info);


        return $this->fetch($this->template_dir . 'sns_album_pic_list');
    }

    /**
     * 修改相册封面
     */
    public function change_album_cover() {
        $id = intval(input('get.id'));
        if ($id <= 0) {
            ds_show_dialog(lang('ds_common_op_fail'));
        }
        /**
         * 图片信息
         */
        $where = array();
        $where['ap_id'] = $id;
        $where['member_id'] = session('member_id');
        $pic_info = db('snsalbumpic')->where($where)->find();
        $update = array();
        $update['ac_cover'] = str_ireplace('.', '_240.', $pic_info['ap_cover']);
        $update['ac_id'] = $pic_info['ac_id'];
        $return = db('snsalbumclass')->update($update);
        if ($return) {
            ds_show_dialog(lang('ds_common_op_succ'), 'reload', 'succ');
        } else {
            ds_show_dialog(lang('ds_common_op_fail'));
        }
    }



    /**
     * 图片删除
     */
    public function album_pic_del() {
        $ids = input('param.id');
        if (empty($ids)) {
            ds_show_dialog(lang('album_parameter_error'));
        }
        if (!empty($ids) && is_array($ids)) {
            $id = $ids;
        } else {
            $id[] = intval($ids);
        }

        foreach ($id as $v) {
            $v = intval($v);
            if ($v <= 0)
                continue;
            $ap_info = db('snsalbumpic')->where(array('ap_id' => $v, 'member_id' => session('member_id')))->find();
            if (empty($ap_info))
                continue;
            @unlink(BASE_UPLOAD_PATH . DS . ATTACH_MALBUM . DS . session('member_id') . DS . $ap_info['ap_cover']);
            db('snsalbumpic')->delete($ap_info['ap_id']);
        }

        ds_show_dialog(lang('album_class_pic_del_succeed'), 'reload', 'succ');
    }


    /**
     * 上传图片
     *
     * @param
     * @return
     */
    public function swfupload() {
        $member_id = session('member_id');
        $class_id = intval(input('param.category_id'));
        if ($member_id <= 0 && $class_id <= 0) {
            echo json_encode(array('state' => 'false', 'message' => lang('sns_upload_pic_fail'), 'origin_file_name' => $_FILES["file"]["name"]));
            exit;
        }

        // 验证图片数量
        $count = db('snsalbumpic')->where(array('member_id' => $member_id))->count();
        if (config('malbum_max_sum') != 0 && $count >= config('malbum_max_sum')) {
            echo json_encode(array('state' => 'false', 'message' => lang('sns_upload_img_max_num_error'), 'origin_file_name' => $_FILES["file"]["name"]));
            exit;
        }

        /**
         * 上传图片
         */
        //上传文件保存路径
        $upload_file = BASE_UPLOAD_PATH . DS . ATTACH_MALBUM . DS . $member_id;
        if (!empty($_FILES['file']['name'])) {
            $file = request()->file('file');
            //设置特殊图片名称
            $file_name = $member_id . '_' . date('YmdHis') . rand(10000, 99999);
            $info = $file->rule('uniqid')->validate(['ext' => ALLOW_IMG_EXT])->move($upload_file, $file_name);
            if ($info) {
                $img_path = $info->getFilename();
            } else {
                // 目前并不出该提示
                $error = $file->getError();
                $data['state'] = 'false';
                $data['message'] = $error;
                $data['origin_file_name'] = $_FILES['file']['name'];
                echo json_encode($data);
                exit;
            }
        } else {
            //未上传图片不做后面处理
            exit;
        }

        list($width, $height, $type, $attr) = getimagesize($upload_file . DS . $img_path);

        $insert = array();
        $insert['ap_name'] = $img_path;
        $insert['ac_id'] = $class_id;
        $insert['ap_cover'] = $img_path;
        $insert['ap_size'] = intval($_FILES['file']['size']);
        $insert['ap_spec'] = $width . 'x' . $height;
        $insert['ap_uploadtime'] = time();
        $insert['member_id'] = $member_id;
        $result = db('snsalbumpic')->insertGetId($insert);
        $data = array();
        $data['file_id'] = $result;
        $data['file_name'] = $img_path;
        $data['origin_file_name'] = $_FILES["file"]["name"];
        $data['file_path'] = $img_path;
        $data['file_url'] = sns_thumb($img_path, 240);
        $data['state'] = 'true';
        /**
         * 整理为json格式
         */
        $output = json_encode($data);
        echo $output;
    }

    /**
     * ajax验证名称时候重复
     */
    public function ajax_check_class_name() {
        $ac_name = trim(input('get.ac_name'));
        if ($ac_name == '') {
            echo 'true';
            die;
        }
        $where = array();
        $where['ac_name'] = $ac_name;
        $where['member_id'] = session('member_id');
        ;
        $class_info = db('snsalbumclass')->where($where)->count();
        if (!empty($class_info)) {
            echo 'false';
            die;
        } else {
            echo 'true';
            die;
        }
    }

}

?>
