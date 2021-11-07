<?php

class ModelExtensionModuleArserSite extends Model
{
    public function createTables(){
        $this->db->query(
            "
create table ar_link
(
    id            int unsigned auto_increment comment 'id'
        primary key,
    site_id       int unsigned  not null comment 'id сайта',
    category_list varchar(255)  not null comment 'id категорий через запятую',
    link          varchar(255)  not null comment 'Ссылка на товар/группу товаров',
    is_group      int default 0 null comment '1, если группа (страница, где ищем ссылки на товары)',
    constraint ar_links_category_list_uindex
        unique (category_list),
    constraint ar_links_link_uindex
        unique (link)
)
    comment 'Ссылки для парсинга';
            "
        );
    }

    public function deleteSite($site_id)
    {
        $this->db->query("DELETE FROM ar_link WHERE siet_id = '" . (int)$site_id . "'");
    }

    /**
     * @param int $site_id
     * @param array $data
     * @param int $erase
     * @return bool
     */
    public function addLinks(int $site_id, array $data, int $erase = 1): bool
    {
        if ($erase) {
            $this->deleteSite($site_id);
        }

        foreach ($data as $datum) {
            $this->db->query("INSERT INTO ar_site SET site_id = " . $site_id
                . "', category_list = '" . $this->db->escape($data['category_list'])
                . "', link = '" . $this->db->escape($data['link'])
                . "', is_group = " . $this->db->escape($data['is_group'])
            );
        }

        return true;
    }

//    public function editSite($site_id, $data)
//    {
//        $this->db->query("UPDATE ar_site SET name = '" . $this->db->escape($data['name'])
//            . "', link = '" . $this->db->escape($data['link'])
//            . "', modulname = '" . $this->db->escape($data['modulname'])
//            . "', minid = " . $this->db->escape($data['minid'])
//            . ", maxid = " . $this->db->escape($data['maxid'])
//            . ", mult = " . $this->db->escape($data['mult'])
//            . ", status = '" . $this->db->escape($data['status'])."'"
//            . " WHERE id = '" . $site_id . "'");
//    }

    public function getLink($site_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM ar_site s WHERE s.id = '" . (int)$site_id . "'");

        return $query->row;
    }
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

        $sort_data = array(
            'name',
            'link',
            'modulname',
            'minid',
            'maxid',
            'mult',
            'status',
            'sort_order'
        );

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
    public function getTotalSites()
    {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM ar_site");

        return $query->row['total'];
    }

    // Пересчет количества продуктов и запись в ar_site
    public function recalcProducts()
    {
        $this->db->query("UPDATE ar_site a SET productcount = (SELECT COUNT(p.id) FROM ar_product p WHERE a.id=p.site_id);");
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
}

?>