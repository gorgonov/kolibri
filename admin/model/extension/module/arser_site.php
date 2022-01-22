<?php

class ModelExtensionModuleArserSite extends Model
{
    /**
     * Создание и заполнение таблицы сайтов для парсинга
     */
    public function createTables(){
        $this->db->query("CREATE TABLE IF NOT EXISTS `ar_site` (" .
        "`id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID'," .
        "`name` varchar(30) NOT NULL COMMENT 'Наименование'," .
        "`link` varchar(255) NOT NULL COMMENT 'Базовая ссылка'," .
        "`modulname` varchar(255) NOT NULL COMMENT 'Имя модуля обработки'," .
        "`minid` int unsigned NOT NULL COMMENT 'Стартовый id продукта'," .
        "`maxid` int unsigned NOT NULL COMMENT 'Финишный id продукта'," .
        "`mult` decimal(4,2) unsigned NOT NULL COMMENT 'Множитель'," .
        "`status` enum('ok','new','del','price','get','parse') NOT NULL COMMENT 'ok,new,del,price,get,parse'," .
        "`message` varchar(255) NOT NULL," .
        "PRIMARY KEY (`id`)" .
        ") ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8 COMMENT='Справочник сайтов для парсинга';");


        $this->db->query("INSERT INTO `ar_site` (`id`, `name`, `link`, `modulname`, `minid`, `maxid`, `mult`, `status`, `message`) VALUES " .
        "(1, 'Карлсон', 'https://carlson24.ru', 'carlson', 25000, 29999, 1.00, 'new', '')," .
        "(7, 'Мекко', 'https://mekkomeb.ru/', 'mekko', 15000, 19999, 1.10, '', '')," .
        "(8, 'AltayMebel', 'http://altaimebel22.ru', 'altaymebel', 10000, 14999, 1.10, 'ok', '')," .
        "(9, 'Ваша мебель', 'https://nsk.vmebel24.ru/', 'vmebel', 20000, 24999, 1.10, 'ok', '')," .
        "(10, 'Три кроватки (вручную)', 'http://3krovatki.ru/', 'noModule', 30000, 34999, 1.10, '', '')," .
        "(11, 'Деникс ', 'https://denx.su/', 'denx', 35000, 39999, 1.10, 'ok', '')," .
        "(12, 'Е1 шкафы (вручную) ', 'https://www.e-1.ru/', 'noModule', 40000, 44999, 1.10, '', '')," .
        "(13, 'барнаул мебельный (вручную) ', 'noLink', 'noModule', 45000, 49999, 1.10, '', '')," .
        "(14, 'Пазитивчик пуфики', 'https://pazitif.com/', 'pazitif', 55000, 59999, 1.10, '', '')," .
        "(15, 'Есэндвич', 'https://esandwich.ru', 'esandwich', 60000, 79999, 1.30, 'new', '')," .
        "(16, 'Есэндвич ричи', 'https://esandwich.ru', 'esandwich1', 60000, 79999, 1.10, 'ok', '')," .
        "(17, 'Есэндвич ИЦ', 'https://esandwich.ru', 'esandwich2', 60000, 79999, 1.10, 'ok', '')," .
        "(18, 'Есэндвич DSV', 'https://esandwich.ru', 'esandwich3', 60000, 79999, 1.10, 'ok', '')," .
        "(19, 'Есэндвич  -Ол', 'https://esandwich.ru', 'esandwich4', 60000, 79999, 1.10, 'ok', '')," .
        "(20, 'Есэндвич  г.Волгодонск', 'https://esandwich.ru', 'esandwich5', 60000, 79999, 1.10, 'ok', '')," .
        "(21, 'Есэндвич  Gnt', 'https://esandwich.ru', 'esandwich6', 60000, 79999, 1.10, 'ok', '')," .
        "(22, 'Есэндвич  г.Глазов', 'https://esandwich.ru', 'esandwich7', 60000, 79999, 1.10, 'ok', '')," .
        "(23, 'ОЛ-мекко', 'https://olmeko.ru', 'Olmeko', 90000, 95000, 1.10, 'new', '');");

    }

    /**
     * Удаление сайта
     *
     * @param $site_id
     */
    public function deleteSite($site_id)
    {
        $this->db->query("DELETE FROM ar_site WHERE id = '" . (int)$site_id . "'");
    }

    /**
     * Вставляет новую строку о сайте для парсинга
     *
     * @param $data
     * @return mixed
     */
    public function addSite($data)
    {
        foreach ($data as $key => $datum) {
            $data[$key] = $this->rusQuote($datum);
        }
        $sql = "INSERT INTO ar_site SET name = '" . $this->db->escape($data['name'])
            . "', min_id = '" . $this->db->escape($data['min_id'])
            . "', modulname = '" . $this->db->escape($data['modulname'])
            . "', provider = '" . $this->db->escape($data['provider'])
            . "', stock = '" . $this->db->escape($data['stock'])
            . "', manufacturer = '" . $this->db->escape($data['manufacturer'])
            . "', maker = '" . $this->db->escape($data['maker'])
            . "', jan = '" . $this->db->escape($data['jan'])
            . "', model = '" . $this->db->escape($data['model'])
            . "', execution_period = '" . $this->db->escape($data['execution_period'])
            . "', prefix = '" . $this->db->escape($data['prefix'])
            . "', group_name = '" . $this->db->escape($data['group_name'])
            ."'";

        $this->db->query($sql);

        $site_id = $this->db->getLastId();

        return $site_id;
    }

    /**
     * Сохраняет изменения
     *
     * @param $site_id
     * @param $data
     */
    public function editSite($site_id, $data)
    {
        foreach ($data as $key => $datum) {
            $data[$key] = $this->rusQuote($datum);
        }
        $this->db->query("UPDATE ar_site SET name = '" . $this->db->escape($data['name'])
            . "', min_id = '" . $this->db->escape($data['min_id'])
            . "', modulname = '" . $this->db->escape($data['modulname'])
            . "', provider = '" . $this->db->escape($data['provider'])
            . "', stock = '" . $this->db->escape($data['stock'])
            . "', manufacturer = '" . $this->db->escape($data['manufacturer'])
            . "', maker = '" . $this->db->escape($data['maker'])
            . "', jan = '" . $this->db->escape($data['jan'])
            . "', model = '" . $this->db->escape($data['model'])
            . "', execution_period = '" . $this->db->escape($data['execution_period'])
            . "', prefix = '" . $this->db->escape($data['prefix'])
            . "', group_name = '" . $this->db->escape($data['group_name'])
            ."'"
            . " WHERE id = '" . $site_id . "'");
    }

    protected function rusQuote(string $str): string
    {
        $str = str_replace("&quot;", '"', $str);
        return preg_replace( '/"([^"]*)"/', "«$1»", $str );
    }

    /**
     * Обновляет сообщение для сайта (стадия обработки)
     *
     * @param $site_id
     * @param $mes
     */
    public function setMessageSite($site_id, $mes)
    {
        $this->db->query("UPDATE ar_site SET message = '" . $mes . "'"
            . " WHERE id = '" . $site_id . "'");
    }

    /**
     * устанавливает статус парсинга для сайта
     * @param $site_id
     * @param $status
     */
    public function setStatusSite($site_id, $status)
    {
        $this->db->query("UPDATE ar_site SET status = '" . $status . "'"
            . " WHERE id = '" . $site_id . "'");
    }

    /**
     * возвращает информацию о сайте для парсинга
     *
     * @param $site_id
     * @return mixed
     */
    public function getSite($site_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM ar_site s WHERE s.id = '" . (int)$site_id . "'");

        return $query->row;
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function getSites($data = array())
    {
        $sql = "SELECT * FROM ar_site s";

        if (!empty($data['filter_name'])) {
            $sql .= " AND s.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $sql .= " AND s.status = '" . (int)$data['filter_status'] . "'";
        }

//        $sql .= " GROUP BY p.product_id";

        $sort_data = [
            'name',
            'link',
            'modulname',
            'provider',
            'stock',
            'status',
            'min_id',
            'productcount',
            'sort_order',
        ];

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY s.name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * возвращает количество сайтов для парсинга.
     *
     * @return mixed
     */
    public function getTotalSites()
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM ar_site");

        return $query->row['total'];
    }

    public function getMinId($site_id)
    {
        $query = $this->db->query("SELECT * FROM ar_site WHERE id=" . $site_id);

        return $query->row['min_id'];
    }

    /**
     * Пересчет количества продуктов и запись в ar_site
     */
    public function recalcProducts()
    {
        $this->db->query("UPDATE ar_site a SET productcount = (SELECT COUNT(p.id) FROM ar_product p WHERE a.id=p.site_id);");
    }

    /**
     * Запись настроек в базу данных
     */
    public function SaveSettings()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('arser', $this->request->post);
    }

    /**
     * Получение настроек из базы данных
     * @return array
     */
    public function LoadSettings()
    {

        return array(
            'arser_status' => $this->config->get('arser_status'),
            'arser_import_path' => $this->config->get('arser_import_path')
        );
    }
}
?>