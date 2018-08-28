<?php
namespace app\admin\controller;

use think\Lang;
use think\Db;
class Flearegion extends AdminControl
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH . 'admin/lang/zh-cn/region.lang.php');
        Lang::load(APP_PATH . 'admin/lang/zh-cn/flea.lang.php');
    }
    /**
     * 地区列表
     *
     * @param
     * @return
     */
    public function flea_region() {
        /**
         * 实例化模型
         */
        $fleaarea_model = model('fleaarea');
        /**
         * 增加 修改 地区信息
         */
        if (request()->isPost()) {
            /**
             * 是否生成缓存的标识
             */
            $new_cache = true;
            /**
             * 新增地区
             */
            if (isset($_POST['new_area_name']) && is_array($_POST['new_area_name'])) {
                foreach ($_POST['new_area_name'] as $k => $v) {
                    if (!empty($v)) {
                        $insert_array = array();
                        $insert_array['fleaarea_name'] = $v;
                        $insert_array['fleaarea_parent_id'] = input('post.fleaarea_parent_id');
                        $insert_array['fleaarea_sort'] = intval($_POST['new_area_sort'][$k]);
                        $insert_array['fleaarea_deep'] = input('post.child_area_deep');
                        $fleaarea_model->addFleaarea($insert_array);
                        $new_cache = true;
                    }
                }
            }
            /**
             * 修改地区
             */
            if (isset($_POST['area_name']) && is_array($_POST['area_name'])) {
                foreach ($_POST['area_name'] as $k => $v) {
                    if (!empty($v)) {
                        $insert_array = array();
                        $insert_array['fleaarea_id'] = $k;
                        $insert_array['fleaarea_name'] = $v;
                        $insert_array['fleaarea_sort'] = intval($_POST['area_sort'][$k]);
                        $fleaarea_model->editFleaarea($insert_array);
                        $new_cache = true;
                    }
                }
            }
            /**
             * 删除地区
             */
            $hidden_del_id = input('post.hidden_del_id');
            if (!empty($hidden_del_id)) {
                $hidden_del_id = trim($hidden_del_id, '|');
                $del_id = explode('|', $hidden_del_id);
                $fleaarea_model->delFleaarea($del_id, input('post.child_area_deep'));
                $new_cache = true;
            }

            /**
             * 更新缓存
             */
            if ($new_cache === true) {
                \fleacache::getCache('flea_area', array('deep' => input('post.child_area_deep'), 'new' => '1'));
            }

            $this->success(lang('region_index_modify_succ'));
        } else {
            /**
             * 导航地区内容
             */
            /**
             * 一级
             */
            $province_list = \fleacache::getCache('flea_area', array('deep' => '1'));
            $child_area_deep = 1;
            /**
             * 二级
             */
            $city_list = array();
            $district_list = array();
            if (input('param.province')) {
                $cache_data = \fleacache::getCache('flea_area', array('deep' => '2'));
                if (is_array($cache_data)) {

                    foreach ($cache_data as $k => $v) {
                        if ($v['fleaarea_parent_id'] == intval(input('param.province'))) {
                            $city_list[] = $v;
                        }
                    }
                }
                unset($cache_data);
                $child_area_deep = 2;
                /**
                 * 三级
                 */
                if (input('param.city')) {
                    $cache_data = \fleacache::getCache('flea_area', array('deep' => '3'));
                    if (is_array($cache_data)) {

                        foreach ($cache_data as $k => $v) {
                            if ($v['fleaarea_parent_id'] == intval(input('param.city'))) {
                                $district_list[] = $v;
                            }
                        }
                    }
                    unset($cache_data);
                    $child_area_deep = 3;
                    /**
                     * 四级
                     */
                    if (input('param.district')) {
                        $child_area_deep = 4;
                    }
                }
            }
            /**
             * 地区列表
             */
            $condition['fleaarea_parent_id'] = input('param.fleaarea_parent_id') ? input('param.fleaarea_parent_id') : '0';
            $area_list = $fleaarea_model->getFleaareaList($condition);
            $this->assign('province', input('param.province') ? input('param.province') : '');
            $this->assign('city', input('param.city'));
            $this->assign('district', input('param.district'));

            $this->assign('province_list', $province_list);
            $this->assign('city_list', $city_list);
            $this->assign('district_list', $district_list);
            $this->assign('fleaarea_parent_id', input('param.fleaarea_parent_id') ? input('param.fleaarea_parent_id') : '0');
            $this->assign('area_list', $area_list);
            $this->assign('child_area_deep', $child_area_deep);
            $this->setAdminCurItem('index');
            return $this->fetch('index');
        }
    }

    /**
     * 导入地区
     *
     * @param
     * @return
     */
    public function flea_region_import() {
        /**
         * 实例化模型
         */
        $fleaarea_model = model('fleaarea');
        if (request()->isPost()) {
            /**
             * 导入
             */
            if (!empty($_FILES['csv'])) {
                $fp = @fopen($_FILES['csv']['tmp_name'], 'rb');
                /**
                 * 父ID
                 */
                $area_parent_id_1 = 0;

                while (!feof($fp)) {
                    $data = fgets($fp, 4096);
                    switch (strtoupper(input('post.charset'))) {
                        case 'UTF-8':
                            if (strtoupper(CHARSET) !== 'UTF-8') {
                                $data = iconv('UTF-8', strtoupper(CHARSET), $data);
                            }
                            break;
                        case 'GBK':
                            if (strtoupper(CHARSET) !== 'GBK') {
                                $data = iconv('GBK', strtoupper(CHARSET), $data);
                            }
                            break;
                    }
                    if (!empty($data)) {
                        $data = str_replace('"', '', $data);
                        /**
                         * 逗号去除
                         */
                        $tmp_array = array();
                        $tmp_array = explode(',', $data);
                        /**
                         * 第一位是序号，后面的是内容，最后一位名称
                         */
                        $tmp_deep = 'flea_area_parent_id_' . (count($tmp_array) - 1);
                        $insert_array = array();
                        $insert_array['fleaarea_sort'] = $tmp_array[0];
                        $insert_array['fleaarea_parent_id'] = $$tmp_deep;
                        $insert_array['fleaarea_name'] = $tmp_array[count($tmp_array) - 1];
                        $insert_array['fleaarea_deep'] = count($tmp_array) - 1;
                        $area_id = $fleaarea_model->addFleaarea($insert_array);
                        /**
                         * 赋值这个深度父ID
                         */
                        $tmp = 'flea_area_parent_id_' . count($tmp_array);
                        $$tmp = $area_id;
                    }
                }
                /**
                 * 重新生成缓存
                 */
                for ($i = 1; $i <= 4; $i++) {
                    $tmp = 'flea_area_parent_id_' . $i;
                    if (isset($$tmp) && intval($$tmp) >= 0) {
                        \fleacache::getCache('flea_area', array('deep' => intval($i), 'new' => '1'));
                    }
                }
                $this->success(lang('region_import_succ'), 'Flearegion/flea_region');
            } else {
                $this->error(lang('region_import_csv_null'));
            }
        } else {
            return $this->fetch('import');
        }
    }

    /**
     * 导入默认地区
     *
     * @param
     * @return
     */
    public function flea_import_default_area() {
        $file = PUBLIC_PATH.'/examples/flea_area.sql';
        if (!is_file($file)){
            ds_json_encode(10001, lang('region_import_csv_null'));
        }

        $handle = @fopen($file, "r");
        $tmp_sql = '';
        if ($handle) {

            Db::query("TRUNCATE TABLE `".config('database.prefix')."fleaarea`");
            while (!feof($handle)) {

                $buffer = fgets($handle);
                if (trim($buffer) != ''){
                    $tmp_sql .= $buffer;
                    if (substr(rtrim($buffer),-1) == ';'){
                        if (preg_match('/^(INSERT)\s+(INTO)\s+/i', ltrim($tmp_sql)) && substr(rtrim($buffer),-2) == ');'){
                            //标准的SQL语句，将被执行
                        }else{
                            //不能组成标准的SQL语句，继续向下一行取内容，直到组成合法的SQL为止
                            continue;
                        }
                        if (!empty($tmp_sql)){
                            if (strtoupper(CHARSET) == 'GBK'){
                                $tmp_sql = iconv('UTF-8',strtoupper(CHARSET),$tmp_sql);
                            }
                            $tmp_sql = str_replace("`#__fleaarea`","`".config('database.prefix')."fleaarea`",$tmp_sql);
                            Db::query($tmp_sql);
                            $tmp_sql = '';
                        }
                    }
                }
            }
            @fclose($handle);
            /**
             * 重新生成缓存
             */
            for ($i=1;$i<=4;$i++){
                $tmp = 'flea_area_parent_id_'.$i;
                if (isset($$tmp) && intval($$tmp) >= 0){
                    \fleacache::getCache('flea_area',array('deep'=>intval($i),'new'=>'1'));
                }
            }
            ds_json_encode(10000, lang('region_import_succ'));
        }else {
            ds_json_encode(10001, lang('region_import_csv_null'));
        }
    }
    
    
    protected function getAdminItemList()
    {
        $menu_array = array(
            array(
                'name' => 'index', 'text' => lang('ds_manage'), 'url' => url('Flearegion/flea_region')
            ),
            array(
                'name' => 'import', 'text' => lang('flea_region_import'), 'url' => "javascript:dsLayerConfirm('".url('Flearegion/flea_import_default_area')."','导入将清空现有的所有地区数据\n导入前建议先备份数据库地区数据！\n确定要导入吗?')"
            ),
        );
        return $menu_array;
    }
}