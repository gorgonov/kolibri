<?php

class ModelExtensionModuleArserLink extends Model
{
    /**
     * @param  array  $data
     * @return bool
     */
    public function addLinks(array $data): bool
    {
        foreach ($data as $datum) {
            $sql = "
                INSERT IGNORE INTO ar_link SET 
                    site_id = {$datum['site_id']},
                    category_list = '{$datum['category_list']}',
                    link = '{$datum['link']}',
                    is_group = {$datum['is_group']},
                    category1c = '{$datum['category1c']}',
                    status = '{$datum['status']}'
            ";
            $this->db->query($sql);
        }

        return true;
    }

    public function getGroupLink($site_id, $is_group = 1)
    {
        $sql = "SELECT DISTINCT * FROM ar_link WHERE site_id = {$site_id} AND is_group = {$is_group}";
        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getLink($site_id, array $data)
    {
        $sql = "SELECT DISTINCT * FROM ar_link WHERE site_id = '".(int)$site_id."'";

        if ($data['filter'] != 'all') {
            $sql .= "AND status='".$data['filter']."'";
        }

        if (!empty($data['search'])) {
            $sql .= "AND (id LIKE '%".$data['search']."%'";
            $sql .= " OR link LIKE '%".$data['search']."%'";
            $sql .= " OR category1c LIKE '%".$data['search']."%')";
        }

        $sort_data = [
            'id',
            'category_list',
            'link',
            'is_group',
            'category1c',
            'status',
        ];

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY ".$data['sort'];
        } else {
            $sql .= " ORDER BY id";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT ".(int)$data['start'].",".(int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    // Запись настроек в базу данных
    public function SaveSettings()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('arser', $this->request->post);
//        $this->model_setting_setting->editSetting('arser',
//            [
//                'arser_status' => 1,
//                'arser_import_path' => 'трататата',
//            ]);
//        $setting = $this->request->post;
//        $this->model_setting_setting->editSetting('arser', $setting['arser_status']);
//        $this->model_setting_setting->editSetting('arser', $setting['arser_import_path']);
    }

    // Загрузка настроек из базы данных
    public function LoadSettings()
    {
        return array(
            'arser_status' => $this->config->get('arser_status'),
            'arser_import_path' => $this->config->get('arser_import_path')
        );
    }

    public function upload($filename, $site_id)
    {
        try {
            // we use the PHPExcel package from https://github.com/PHPOffice/PHPExcel
            $cwd = getcwd();
            $dir = version_compare(VERSION, '3.0', '>=') ? 'library/export_import' : 'PHPExcel';
            chdir(DIR_SYSTEM.$dir);
            require_once('Classes/PHPExcel.php');
            chdir($cwd);

            // parse uploaded spreadsheet file

            //--- create php excel object ---
            ini_set('memory_limit', '3500M');
            $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
            $cacheSettings = array('memoryCacheSize' => '800MB');
            //set php excel settings
            PHPExcel_Settings::setCacheStorageMethod(
                $cacheMethod, $cacheSettings
            );

            $inputFileType = PHPExcel_IOFactory::identify($filename);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objReader->setReadDataOnly(true);
            $reader = $objReader->load($filename);

            // не нужен, оставлен на будущее: может пригодиться
//            if (!$this->validateUpload($reader, $site_id)) { // проверим, хорош ли файл?
//                return false;
//            }

            $this->uploadLinks($reader, $site_id);

            return true;
        } catch (Exception $e) {
            $errstr = $e->getMessage();
            $errline = $e->getLine();
            $errfile = $e->getFile();
            $errno = $e->getCode();
            $this->session->data['export_import_error'] = array(
                'errstr' => $errstr,
                'errno' => $errno,
                'errfile' => $errfile,
                'errline' => $errline
            );
            if ($this->config->get('config_error_log')) {
                $this->log->write('PHP '.get_class($e).':  '.$errstr.' in '.$errfile.' on line '.$errline);
            }
            return false;
        }
    }

    /**
     * Читаем ссылки из файла
     * @param  PHPExcel_IOFactory::reader $reader
     * @param  integer  $siteId
     * @throws PHPExcel_Exception
     */
    protected function uploadLinks(&$reader, $siteId)
    {
        /** @var PHPExcel_Worksheet $worksheet */
        $worksheet = $reader->getSheetByName('Группы');
        if (!is_null($worksheet)) {
            $maxRow = $worksheet->getHighestRow();
            $data = $worksheet->rangeToArray('A2:C'.$maxRow, null, false, false, false);
            foreach ($data as $row) {
                $category = (string)$row[0];
                $link = $row[1];
                $category1c = $row[2];
                if (!empty($link)) {
                    $sql = "
                    INSERT INTO ar_link SET 
                        site_id = {$siteId},
                        link = '{$link}',
                        category_list = '{$category}',
                        category1c = '{$category1c}',
                        is_group = 1,
                        status = 'new'                    
                    ";
                    try {
                        $this->db->query($sql);
                    } catch (Exception $exception) {
                        echo 'пропустили неверную ссылку '
                            .PHP_EOL.$exception->getMessage().PHP_EOL;
                    }
                }
            }
        }

        $worksheet = $reader->getSheetByName('Товары');
        if (!is_null($worksheet)) {
            $maxRow = $worksheet->getHighestRow();
            $data = $worksheet->rangeToArray('A2:C'.$maxRow, null, false, false, false);
            foreach ($data as $row) {
                $category = (string)$row[0];
                $link = $row[1];
                $category1c = $row[2];
                if (!empty($link)) {
                    $sql = "
                    INSERT INTO ar_link SET 
                        site_id = {$siteId},
                        link = '{$link}',
                        category_list = '{$category}',
                        category1c = '{$category1c}',
                        is_group = 0,
                        status = 'new'               
                ";
                    $this->db->query($sql);
                }
            }
        }
    }

    public function getLinkCount($siteId, $search = '')
    {
        $sql1 = '';
        if (!empty($search)) {
            $sql1 .= " AND (id LIKE '%".$search."%'";
            $sql1 .= " OR link LIKE '%".$search."%'";
            $sql1 .= " OR category1c LIKE '%".$search."%')";
        }

        $sql = "
SELECT status, count(*) count_link
FROM ar_link
WHERE site_id={$siteId} {$sql1}
group by status
UNION SELECT 'all', count(*) count_link
FROM ar_link
WHERE site_id={$siteId} {$sql1}
        ";


        $query = $this->db->query($sql);
        $result = [];
        foreach ($query->rows as $row) {
            $result[$row['status']] = $row['count_link'];
        }

        return $result;
    }

    public function deleteLinks($linkIds)
    {
        $idList = implode(',', $linkIds);
        $sql = " DELETE FROM ar_link WHERE id IN ({$idList}) ";
        $this->db->query($sql);
    }

    public function deleteLinksBySite($siteId)
    {
        $sql = " DELETE FROM ar_link WHERE site_id={$siteId} ";
        $this->db->query($sql);
    }

    public function setStatus($id, string $newStatus, $message = '')
    {
        $sql = "
            UPDATE ar_link SET status = '{$newStatus}', message='{$message}' WHERE id={$id};
        ";
        $this->db->query($sql);
    }

    public function getNextLink($siteId)
    {
        $sql = "SELECT DISTINCT * FROM ar_link WHERE site_id = {$siteId} AND is_group=0 AND status='new' LIMIT 1";
        $query = $this->db->query($sql);

        return $query->rows;
    }
}

?>
