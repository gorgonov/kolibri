<?php

class ModelExtensionModuleArserImportSetting extends Model
{
    public function createTables()
    {
    }

    /**
     * Сохраняет изменения
     *
     * @param $site_id
     * @param $data
     */
    public function editSetting($site_id, $data)
    {
        $this->db->query("
INSERT ar_import_column
(site_id, name, barcode, sku, weight, volume, quantity, header_rows, number_packages, id_type, price)
VALUES (" . $site_id
            . ", '" . $this->db->escape($data['name'])
            . "', '" . $this->db->escape($data['barcode'])
            . "', '" . $this->db->escape($data['sku'])
            . "', '" . $this->db->escape($data['weight'])
            . "', '" . $this->db->escape($data['volume'])
            . "', '" . $this->db->escape($data['quantity'])
            . "', '" . $this->db->escape($data['header_rows'])
            . "', '" . $this->db->escape($data['number_packages'])
            . "', '" . $this->db->escape($data['id_type'])
            . "', '" . $this->db->escape($data['price'])
            . "') ON DUPLICATE KEY
 UPDATE 
 site_id = " . $site_id
            . ", name='" . $this->db->escape($data['name'])
            . "', barcode='" . $this->db->escape($data['barcode'])
            . "', sku='" . $this->db->escape($data['sku'])
            . "', weight='" . $this->db->escape($data['weight'])
            . "', volume='" . $this->db->escape($data['volume'])
            . "', quantity='" . $this->db->escape($data['quantity'])
            . "', header_rows='" . $this->db->escape($data['header_rows'])
            . "', number_packages='" . $this->db->escape($data['number_packages'])
            . "', id_type='" . $this->db->escape($data['id_type'])
            . "', price='" . $this->db->escape($data['price'])
            . "'");
    }

    /**
     * возвращает настройки импорта для сайта
     *
     * @param $site_id
     * @return mixed
     */
    public function getSetting($site_id)
    {
        $query = $this->db->query("SELECT DISTINCT * FROM ar_import_column s WHERE s.site_id = '" . (int)$site_id . "'");

        return $query->row;
    }
}

?>