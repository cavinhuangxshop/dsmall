<?php
namespace app\admin\controller;
use think\Validate;
use think\Lang;
class Fleaclass extends AdminControl
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH . 'admin/lang/zh-cn/flea.lang.php');
    }
    
    public function goods_class(){
        $this->flea_class();
    }
    /**
     * 分类管理
     */
    public function flea_class(){
        $fleaclass_model = model('fleaclass');
        /**
         * 删除,编辑
         */
        if (request()->isPost()){
            /**
             * 删除
             */
            if (input('post.submit_type') == 'del'){
                if (!empty($_POST['check_fleaclass_id'])){
                    if (is_array($_POST['check_fleaclass_id'])){
                        $del_array = $fleaclass_model->getChildClass($_POST['check_fleaclass_id']);
                        if (is_array($del_array)){
                            foreach ($del_array as $k => $v){
                                $fleaclass_model->delFleaclass($v['fleaclass_id']);
                            }
                        }
                    }
                    $this->success(lang('goods_class_index_del_succ'));
                }else {
                   $this->error(lang('goods_class_index_choose_del'));
                }
            }
            /**
             * 编辑
             */
            if (input('post.submit_type') == 'brach_edit'){
                if (!empty($_POST['check_fleaclass_id'])){
                    $this->assign('id',implode(',',$_POST['check_fleaclass_id']));
                    $this->setAdminCurItem('brach_edit');
                    return $this->fetch('brach_edit');
                }else {
                   $this->error(lang('goods_class_index_choose_edit'));
                }
            }
            /**
             * 首页显示
             */
            if(input('post.submit_type') == 'index_show' or input('post.submit_type') == 'index_hide'){
                if (!empty($_POST['check_fleaclass_id'])){
                    if (is_array($_POST['check_fleaclass_id'])){
                        $param	= array();
                        $param['fleaclass_index_show']	= input('post.submit_type') == 'index_show'?'1':'0';
                        foreach ($_POST['check_fleaclass_id'] as $k=>$v){
                            $param['fleaclass_id']	= $v;
                            $fleaclass_model->editFleaclass($param);
                        }
                    }
                    $this->success(lang('goods_class_index_in_homepage').(input('post.submit_type') == 'index_show'?lang('goods_class_index_display'):lang('goods_class_index_hide')).lang('goods_class_index_succ'));
                }else {
                    $this->error(lang('goods_class_index_choose_in_homepage').(input('post.submit_type') == 'index_show'?lang('goods_class_index_display'):lang('goods_class_index_hide')).lang('goods_class_index_content'));
                }
            }
        }
        /**
         * 父ID
         */
        $parent_id = input('fleaclass_parent_id')?input('fleaclass_parent_id'):0;
        /**
         * 列表
         */
        $tmp_list = $fleaclass_model->getTreeClassList(4);
        $class_list=array();
        if (is_array($tmp_list)){
            foreach ($tmp_list as $k => $v){
                if ($v['fleaclass_parent_id'] == $parent_id){
                    /**
                     * 判断是否有子类
                     */
                    if (isset($tmp_list[$k+1]) && ($tmp_list[$k+1]['deep'] > $v['deep'])){
                        $v['have_child'] = 1;
                    }else{
                        $v['have_child'] = 0;
                    }
                    $class_list[] = $v;
                }
            }
        }
        if (input('ajax') == '1'){
            $output = json_encode($class_list);
            print_r($output);
            exit;
        }else {
            $this->assign('class_list',$class_list);
            $this->setAdminCurItem('index');
            return $this->fetch('index');
        }
    }

    /**
     * 保存批量修改分类
     */
    public function brach_edit_save(){
        if (input('post.fleaclass_show') == '-1'){
            $this->success(lang('goods_class_batch_edit_succ'),'fleaclass/flea_class');
        }
        if (request()->isPost()){
            $fleaclass_model = model('fleaclass');

            $array = explode(',',$_POST['id']);
            if (is_array($array)){
                foreach ($array as $k => $v){
                    $update_array = array();
                    $update_array['fleaclass_id'] = $v;
                    $update_array['fleaclass_show'] = input('post.fleaclass_show');

                    $fleaclass_model->editFleaclass($update_array);
                }
                $this->success(lang('goods_class_batch_edit_succ'));
            }else {
                $this->success(lang('goods_class_batch_edit_wrong_content'));
            }
        }else {
            $this->success(lang('goods_class_batch_edit_wrong_content'));
        }
    }
    /**
     * 商品分类添加
     */
    public function goods_class_add(){
        $fleaclass_model = model('fleaclass');
        if (request()->isPost()){
            /**
             * 验证
             */
            $obj_validate = new Validate();
            $data=[
                'fleaclass_name' =>input('post.fleaclass_name'),
                'fleaclass_sort'  =>input('post.fleaclass_sort')
            ];

            $rule=[
                ['fleaclass_name','require',lang('goods_class_add_name_null')],
                ['fleaclass_sort','require|number',lang('goods_class_add_sort_int')]
            ];
            $error=$obj_validate->check($data,$rule);
            if (!$error){
                $this->error($obj_validate->getError());
            }else {

                $insert_array = array();
                $insert_array['fleaclass_name'] = input('post.fleaclass_name');
                $insert_array['fleaclass_parent_id'] = input('post.fleaclass_parent_id');
                $insert_array['fleaclass_sort'] = input('post.fleaclass_sort');
                $insert_array['fleaclass_show'] = input('post.fleaclass_show');
                $insert_array['fleaclass_index_show'] = input('post.fleaclass_index_show');

                $result = $fleaclass_model->addFleaclass($insert_array);
                if ($result){
                    dsLayerOpenSuccess(lang('goods_class_add_succ'));
                }else {
                   $this->error(lang('goods_class_add_fail'));
                }
            }
        }
        /**
         * 父类列表，只取到第三级
         */
        $parent_list = $fleaclass_model->getTreeClassList(3);
        if (is_array($parent_list)){
            foreach ($parent_list as $k => $v){
                $parent_list[$k]['fleaclass_name'] = str_repeat("&nbsp;",$v['deep']*2).$v['fleaclass_name'];
            }
        }

        $this->assign('fleaclass_parent_id',input('fleaclass_parent_id'));
        $this->assign('parent_list',$parent_list);
        $this->setAdminCurItem('add');
        return $this->fetch('add');
    }

    /**
     * 编辑
     */
    public function goods_class_edit() {

        $fleaclass_model = model('fleaclass');
        if (request()->isPost()) {
                /**
                 * 验证
                 */
                $obj_validate = new Validate();
                $data = [
                    'fleaclass_name' => input('post.fleaclass_name'),
                    'fleaclass_sort' => input('post.fleaclass_sort')
                ];

                $rule = [
                    ['fleaclass_name', 'require', lang('goods_class_add_name_null')],
                    ['fleaclass_sort', 'require|number', lang('goods_class_add_sort_int')]
                ];
                $error = $obj_validate->check($data, $rule);
                if (!$error) {
                    $this->error($obj_validate->getError());
                } else {

                    $update_array = array();
                    $update_array['fleaclass_id'] = input('post.fleaclass_id');
                    $update_array['fleaclass_name'] = input('post.fleaclass_name');

                    $fleaclass_parent_id = intval(input('post.fleaclass_parent_id'));
                    if ($fleaclass_parent_id) {
                        $update_array['fleaclass_parent_id'] = $fleaclass_parent_id;
                    }
                    $update_array['fleaclass_sort'] = input('post.fleaclass_sort');
                    $update_array['fleaclass_show'] = input('post.fleaclass_show');
                    $update_array['fleaclass_index_show'] = input('post.fleaclass_index_show');

                    $result = $fleaclass_model->editFleaclass($update_array);
                    if ($result) {
                        dsLayerOpenSuccess(lang('goods_class_batch_edit_ok'));
                    } else {
                        $this->error(lang('goods_class_batch_edit_fail'));
                    }
                }
        } else {
            $class_array = $fleaclass_model->getOneFleaclass(input('param.fleaclass_id'));
            if (empty($class_array)) {
                $this->error(lang('goods_class_batch_edit_paramerror'));
            }

            /**
             * 父类列表，只取到第三级
             */
            $parent_list = $fleaclass_model->getTreeClassList(3);
            if (is_array($parent_list)) {
                $unset_sign = false;
                foreach ($parent_list as $k => $v) {
                    if ($v['fleaclass_id'] == $class_array['fleaclass_id']) {
                        $deep = $v['deep'];
                        $unset_sign = true;
                    }
                    if ($unset_sign == true) {
                        if ($v['deep'] == $deep && $v['fleaclass_id'] != $class_array['fleaclass_id']) {
                            $unset_sign = false;
                        }
                        if ($v['deep'] > $deep || $v['fleaclass_id'] == $class_array['fleaclass_id']) {
                            unset($parent_list[$k]);
                        }
                    } else {
                        $parent_list[$k]['fleaclass_name'] = str_repeat("&nbsp;", $v['deep'] * 2) . $v['fleaclass_name'];
                    }
                }
            }

            $this->assign('parent_list', $parent_list);
            $this->assign('class_array', $class_array);
            $this->setAdminCurItem('add');
            return $this->fetch('edit');
        }
    }

    /**
     * 分类导入
     */
    public function goods_class_import(){
        /**
         * 实例化模型
         */
        $fleaclass_model = model('fleaclass');
        /**
         * 导入
         */
        if (request()->isPost()){
            /**
             * 得到导入文件后缀名
             */
            $csv_name=explode('.',$_FILES['csv']['name']);
            $file_type = end($csv_name);
            if (!empty($_FILES['csv']) && !empty($_FILES['csv']['name']) && $file_type == 'csv'){
                $fp = @fopen($_FILES['csv']['tmp_name'],'rb');
                /**
                 * 父ID
                 */
                $parent_id_1 = 0;

                while (!feof($fp)) {
                    $data = fgets($fp, 4096);
                    switch (strtoupper(input('post.charset'))){
                        case 'UTF-8':
                            if (strtoupper(CHARSET) !== 'UTF-8'){
                                $data = iconv('UTF-8',strtoupper(CHARSET),$data);
                            }
                            break;
                        case 'GBK':
                            if (strtoupper(CHARSET) !== 'GBK'){
                                $data = iconv('GBK',strtoupper(CHARSET),$data);
                            }
                            break;
                    }

                    if (!empty($data)){
                        $data	= str_replace('"','',$data);
                        /**
                         * 逗号去除
                         */
                        $tmp_array = array();
                        $tmp_array = explode(',',$data);
                        if($tmp_array[0] == 'sort_order')continue;
                        /**
                         * 第一位是序号，后面的是内容，最后一位名称
                         */
                        $tmp_deep = 'parent_id_'.(count($tmp_array)-1);

                        $insert_array = array();
                        $insert_array['fleaclass_sort'] = $tmp_array[0];
                        $insert_array['fleaclass_parent_id'] = $$tmp_deep;
                        $insert_array['fleaclass_name'] = $tmp_array[count($tmp_array)-1];
                        $fleaclass_id = $fleaclass_model->addFleaclass($insert_array);
                        /**
                         * 赋值这个深度父ID
                         */
                        $tmp = 'parent_id_'.count($tmp_array);
                        $$tmp = $fleaclass_id;
                    }
                }
                /**
                 * 重新生成缓存
                 */
                $this->success(lang('goods_class_import_succ'),'fleaclass/flea_class');
            }else {
                $this->error(lang('goods_class_import_csv_null'));
            }
        }
        $this->setAdminCurItem('import');
        return $this->fetch('import');
    }

    /**
     * 分类导出
     */
    public function goods_class_export() {
        /**
         * 导出
         */
        if (request()->isPost()) {
            /**
             * 实例化模型
             */
            $fleaclass_model = model('fleaclass');
            /**
             * 分类信息
             */
            $class_list = $fleaclass_model->getTreeClassList();

            @header("Content-type: application/unknown");
            @header("Content-Disposition: attachment; filename=flea_class.csv");
            if (is_array($class_list)) {
                foreach ($class_list as $k => $v) {
                    $tmp = array();
                    /**
                     * 序号
                     */
                    $tmp['fleaclass_sort'] = $v['fleaclass_sort'];
                    /**
                     * 深度
                     */
                    for ($i = 1; $i <= ($v['deep'] - 1); $i++) {
                        $tmp[] = '';
                    }
                    /**
                     * 分类名称
                     */
                    $tmp['fleaclass_name'] = $v['fleaclass_name'];
                    /**
                     * 转码 utf-gbk
                     */
                    if (strtoupper(CHARSET) == 'UTF-8') {
                        switch (input('post.if_convert')) {
                            case '1':
                                $tmp_line = iconv('UTF-8', 'GB2312//IGNORE', join(',', $tmp));
                                break;
                            case '0':
                                $tmp_line = join(',', $tmp);
                                break;
                        }
                    } else {
                        $tmp_line = join(',', $tmp);
                    }
                    $tmp_line = str_replace("\r\n", '', $tmp_line);
                    echo $tmp_line . "\r\n";
                }
            }
            exit;
        } else {
            $this->setAdminCurItem('export');
            return $this->fetch('export');
        }
    }

    /**
     * 删除分类
     */
    public function goods_class_del(){
        $fleaclass_model = model('fleaclass');
        $fleaclass_id = input('get.fleaclass_id');
        if ($fleaclass_id > 0){
            /* 删除分类 */
            $fleaclass_model->delFleaclass($fleaclass_id);
            ds_json_encode(10000, lang('goods_class_index_del_succ'));
        }else {
            ds_json_encode(10001, lang('goods_class_index_choose_del'));
        }
    }
    /**
     * ajax操作
     */
    public function ajax(){
        switch (input('branch')){
            /**
             * 验证是否有重复的名称
             */
            case 'goods_class_name':
                $fleaclass_model = model('fleaclass');
                $class_array = $fleaclass_model->getOneFleaclass(input('id'));

                $condition['fleaclass_name'] = input('value');
                $condition['fleaclass_parent_id'] = $class_array['fleaclass_parent_id'];
                $condition['fleaclass_id'] = array('not in',input('param.id'));
                $class_list = $fleaclass_model->getFleaclassList($condition);
                if (empty($class_list)){
                    $update_array = array();
                    $update_array['fleaclass_id'] = input('id');
                    $update_array['fleaclass_name'] = input('value');
                    $fleaclass_model->editFleaclass($update_array);
                    echo 'true';exit;
                }else {
                    echo 'false';exit;
                }
                break;
            /**
             * 分类 排序 显示 设置
             */
            case 'goods_class_sort':
            case 'goods_class_show':
            case 'goods_class_index_show':
                $fleaclass_model = model('fleaclass');
                $update_array = array();
                $update_array['fleaclass_id'] = input('id');
                $update_array[input('column')] = input('value');
                $fleaclass_model->editFleaclass($update_array);
                echo 'true';exit;
                break;
            /**
             * 添加、修改操作中 检测类别名称是否有重复
             */
            case 'check_class_name':
                $fleaclass_model = model('fleaclass');
                $condition['fleaclass_name'] = input('param.fleaclass_name');
                $condition['fleaclass_parent_id'] = input('param.fleaclass_parent_id');
                $condition['fleaclass_id'] = array('not in',input('param.fleaclass_id'));
                $class_list = $fleaclass_model->getFleaclassList($condition);
                if (empty($class_list)){
                    echo 'true';exit;
                }else {
                    echo 'false';exit;
                }
                break;
        }
    }
    protected function getAdminItemList()
    {
        $menu_array = array(
            array(
                'name' => 'index', 'text' => '管理', 'url' => url('Fleaclass/flea_class')
            ),
            array(
                'name' => 'add', 'text' => '新增', 'url' => "javascript:dsLayerOpen('".url('Fleaclass/goods_class_add')."','新增')"
            ),
            array(
                'name' => 'export', 'text' => '导出', 'url' => url('Fleaclass/goods_class_export')
            ),
            array(
                'name' => 'import', 'text' => '导入', 'url' => url('Fleaclass/goods_class_import')
            ),
        );
        return $menu_array;
    }
}