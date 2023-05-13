<?php

class ModelExtensionModuleArserProduct extends Model
{

    public function upload($filename, $importSetting)
    {
        try {
            // we use the PHPExcel package from https://github.com/PHPOffice/PHPExcel
            $cwd = getcwd();
            $dir = version_compare(VERSION, '3.0', '>=') ? 'library/export_import' : 'PHPExcel';
            chdir(DIR_SYSTEM . $dir);
            require_once('Classes/PHPExcel.php');
            chdir($cwd);

            // parse uploaded spreadsheet file
            $inputFileType = PHPExcel_IOFactory::identify($filename);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objReader->setReadDataOnly(true);
            $reader = $objReader->load($filename);

            // не нужен, оставлен на будущее: может пригодиться
//            if (!$this->validateUpload($reader, $site_id)) { // проверим, хорош ли файл?
//                return false;
//            }

            $this->uploadProducts($reader, $importSetting);

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
                $this->log->write('PHP ' . get_class($e) . ':  ' . $errstr . ' in ' . $errfile . ' on line ' . $errline);
            }
            return false;
        }
    }

    public function deleteProducts(array $productIds)
    {
        $idList = implode(',', $productIds);
        $sql = "SELECT DISTINCT s.* FROM ar_product s WHERE s.id IN ({$idList})";
        $products = $this->db->query($sql);
        foreach ($products->rows as $product) {
            $link = $product['link'];
            $sql = "
                UPDATE ar_link SET status = 'new' WHERE link='{$link}';
            ";
            $this->db->query($sql);
        }
        $sql = " DELETE FROM ar_product ap WHERE ap.id IN ({$idList}) ";
        $this->db->query($sql);
    }

    public function deleteProductsBySite(int $siteId)
    {
        $sql = " DELETE FROM ar_product WHERE site_id={$siteId} ";

        $this->db->query($sql);

        $sql = "
            UPDATE ar_link SET status = 'new' WHERE site_id={$siteId};
        ";
        $this->db->query($sql);
    }

    public function addProduct(array $data)
    {
        extract($data);
        $id = $this->getNextId($site_id);
        $weight = $weight ?? '';
        $sku = $sku ?? $id;
        $price = $price ?? 0;

        $images_link = serialize($aImgLink);
        $attr = serialize($attr);
        $product_option = isset($product_option) ? serialize($product_option) : '';

        $sql = "
                INSERT IGNORE INTO ar_product SET 
                    id = {$id},
                    site_id = {$site_id},
                    link = '{$link}',
                    sku = '{$sku}',                              
                    name = '{$topic}',                              
                    description = '{$description}',                              
                    images_link = '{$images_link}',   
                    status = 'ok',
                    category1c = '{$category1c}',                               
                    category = '{$category}',                               
                    weight = '{$weight}',                               
                    attr = '{$attr}',                               
                    price = '{$price}',                               
                    `product_option` = '{$product_option}'                               
        ";

        try {
            $query = $this->db->query($sql);
        } catch (Exception $e)  {
            echo $e->getMessage();
            // todo убрать
            echo '<pre>';
            echo '$sql=';
            print_r($sql);
            echo PHP_EOL;
            die();
        }
    }

    public function clearProducts($productIds)
    {
        $idList = implode(',', $productIds);
        $needColumns = ['barcode', 'weight', 'volume', 'quantity', 'price', 'number_packages'];
        $sqlField = [];
        foreach ($needColumns as $key => $column) {
            $sqlField[] = $column . "=''";
        }

        $sql = "UPDATE ar_product SET " . implode(',', $sqlField) . " WHERE id IN ({$idList})";
        $this->db->query($sql);
    }

    public function clearProductsBySite($siteId)
    {
        $needColumns = ['barcode', 'weight', 'volume', 'quantity', 'price', 'number_packages'];
        $sqlField = [];
        foreach ($needColumns as $key => $column) {
            $sqlField[] = $column . "=''";
        }

        $sql = "UPDATE ar_product SET " . implode(',', $sqlField) . " WHERE site_id={$siteId}";
        $this->db->query($sql);
    }

    public function getProduct($productId)
    {
        $sql = "SELECT DISTINCT p.* FROM ar_product p WHERE p.id={$productId}";
        return $this->db->query($sql)->rows;
    }

    protected function validateUpload(&$reader, $site_id)
    {
        $ok = true;

        // проверим имена листов книги
        if (!$this->validateWorksheetNames($reader)) {
            $this->log->write($this->language->get('error_worksheets'));
            $ok = false;
        }

        $this->load->model('extension/module/arser_site');
        $site_info = $this->model_extension_module_arser_site->getSite($this->request->get['id']);

        // worksheets must have correct id
        if (!$this->validateIdProducts($reader, $site_info)) {
            $this->log->write($this->language->get('error_products_header'));
            $ok = false;
        }

        return $ok;
    }

    protected function validateWorksheetNames(&$reader)
    {
        // должно быть 10 листов
        if ($reader->getSheetCount() != 10) {
            return false;
        }

        $allowed_worksheets = array(
            'Products',
            'AdditionalImages',
            'Specials',
            'Discounts',
            'Rewards',
            'ProductOptions',
            'ProductOptionValues',
            'ProductAttributes',
            'ProductFilters',
            'ProductSEOKeywords',
        );
        $all_worksheets_ignored = true;
        $worksheets = $reader->getSheetNames();
        foreach ($worksheets as $worksheet) {
            if (in_array($worksheet, $allowed_worksheets)) {
                $all_worksheets_ignored = false;
                break;
            }
        }
        if ($all_worksheets_ignored) {
            return false;
        }
        return true;
    }

    protected function validateIdProducts(&$reader, $site_info)
    {
        // найти максимальный и минимальный id
        $worksheet = $reader->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $maxValue = $worksheet->getCell('A2')->getValue();
        $minValue = $maxValue;
        for ($row = 2; $row <= $lastRow; $row++) {
            $maxValue = max($maxValue, $worksheet->getCell('A' . $row)->getValue());
            $minValue = min($minValue, $worksheet->getCell('A' . $row)->getValue());
        }

        return $maxValue <= $site_info['maxid'] and $minValue >= $site_info['minid'];
    }


    /**
     * Загрузка свойств продукта
     * наименование, штрихкод, вес, объем, количество, цена, ску, кол-во в упаковке
     * @param PHPExcel_IOFactory::reader $reader
     * @param integer $siteId
     * @throws PHPExcel_Exception
     */
    protected function uploadProducts(&$reader, $importSetting)
    {
        // get worksheet, if not there return immediately
        $data = $reader->getSheet(0);
        if ($data == null) {
            return;
        }
        $siteId = $importSetting['site_id'];
        $headerRows = $importSetting['header_rows'];
        $allowed = ['name', 'barcode', 'weight', 'volume', 'quantity', 'price', 'sku', 'number_packages'];
        $needColumns = array_filter(
            $importSetting,
            fn ($key) => in_array($key, $allowed) && $importSetting[$key] >= 0,
            ARRAY_FILTER_USE_KEY
        );

        // проверим корректность настроек
        if (($importSetting['id_type'] == 1) && !isset($needColumns['sku'])) {
            return;
        } elseif (($importSetting['id_type'] == 2) && !isset($needColumns['name'])) {
            return;
        } elseif (($importSetting['id_type'] == 3) && !isset($needColumns['name'])) {
            return;
        }

        if ($importSetting['id_type'] == 1) {
            $idColumnName = 'sku';
        } elseif ($importSetting['id_type'] == 2) {
            $idColumnName = 'name';
        } elseif ($importSetting['id_type'] == 3) {
            $idColumnName = 'name';
        }

        $k = $data->getHighestRow();
        for ($i = $headerRows+1; $i < $k; $i += 1) {
            $sku = trim($data->getCellByColumnAndRow($needColumns[$idColumnName], $i)->getValue());
            if (empty($sku)) {
                continue;
            }
            $sqlField = [];
            foreach ($needColumns as $key => $column) {
                $str = trim($data->getCellByColumnAndRow($column, $i)->getValue());
                if ($key == 'quantity') {
                    $str = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
//                        $str = preg_replace('!\d+!', '', $str);
                }
                if (!in_array($key, ['name', 'sku'])) {
                    $sqlField[] = $key . "='" . $str . "'";
                }
            }

            if ($importSetting['id_type'] == 1) {
                $where = "sku='{$sku}'";
            } elseif ($importSetting['id_type'] == 2) {
                $where = "name='{$sku}'";
            } elseif ($importSetting['id_type'] == 3) {
                $sku = str_replace(" ","",$sku);
                $sku = str_replace(".","",$sku);
                $sku = str_replace("-","",$sku);
                $where = "REGEXP_REPLACE(name, '[ .-]','') like '%{$sku}%'"; // выбрасываем все точки и пробелы
            }

            // построение запроса
            $sql = "UPDATE ar_product SET " . implode(',', $sqlField) . " WHERE {$where} AND site_id={$siteId}";
            $this->db->query($sql);
        }
    }

    /**
     * Количество продуктов сайта
     * @param int $site_id
     * @return array
     */
    public function getProductCount(int $site_id, $search='')
    {
        $sql1 = '';
        if (!empty($search)) {
            $sql1 .= " AND (sku LIKE '%" . $search . "%'";
            $sql1 .= " OR category LIKE '%" . $search . "%'";
            $sql1 .= " OR category1c LIKE '%" . $search . "%'";
            $sql1 .= " OR name LIKE '%" . $search . "%'";
            $sql1 .= " OR barcode LIKE '%" . $search . "%'";
            $sql1 .= " OR weight LIKE '%" . $search . "%'";
            $sql1 .= " OR volume LIKE '%" . $search . "%'";
            $sql1 .= " OR quantity LIKE '%" . $search . "%'";
            $sql1 .= " OR number_packages LIKE '%" . $search . "%'";
            $sql1 .= " OR price LIKE '%" . $search . "%')";
        }

        $sql = "
SELECT status, count(*) count_product
FROM ar_product
WHERE site_id={$site_id} {$sql1}
group by status
UNION SELECT 'all', count(*) count_product
FROM ar_product
WHERE site_id={$site_id} {$sql1}
        ";

        $query = $this->db->query($sql);
        $result = [];
        foreach ($query->rows as $row) {
            $result[$row['status']] = $row['count_product'];
        }

        return $result;
    }

    /**
     * возвращает продукты сайта с учетом фильтра (статуса)
     *
     * @param int $site_id
     * @param string $filter
     * @return mixed
     */
    public function getProducts(int $site_id, array $data=['filter'=>'all'])
    {
        $sql = "SELECT DISTINCT * FROM ar_product s WHERE s.site_id = '" . (int)$site_id . "'";

        if ($data['filter'] != 'all') {
            $sql .= "AND status='" . $data['filter'] . "'";
        }

        if (!empty($data['search'])) {
            $sql .= " AND (sku LIKE '%" . $data['search'] . "%'";
            $sql .= " OR category LIKE '%" . $data['search'] . "%'";
            $sql .= " OR category1c LIKE '%" . $data['search'] . "%'";
            $sql .= " OR name LIKE '%" . $data['search'] . "%'";
            $sql .= " OR barcode LIKE '%" . $data['search'] . "%'";
            $sql .= " OR weight LIKE '%" . $data['search'] . "%'";
            $sql .= " OR volume LIKE '%" . $data['search'] . "%'";
            $sql .= " OR quantity LIKE '%" . $data['search'] . "%'";
            $sql .= " OR number_packages LIKE '%" . $data['search'] . "%'";
            $sql .= " OR price LIKE '%" . $data['search'] . "%')";
        }

        $sort_data = [
            'id',
            'sku',
            'name',
            'status',
            'message',
        ];

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY s.id";
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

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getTotalProducts()
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM ar_product");

        return $query->row['total'];
    }

    private function getNextId($site_id): int
    {
        $id = $this->getLastId($site_id);
        if ($id) {
            return $id+1;
        }

        $this->load->model('extension/module/arser_site');
        $id = $this->model_extension_module_arser_site->getMinId($site_id);

        return $id;
    }

    private function getLastId($site_id)
    {
        $sql = "SELECT MAX(id) last_id FROM ar_product p WHERE p.site_id = '" . (int)$site_id . "'";
        $result = $this->db->query($sql);

        return $result->row['last_id'];
    }
}