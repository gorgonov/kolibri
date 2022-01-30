<?php

namespace Arser;
use DiDom\Document;

class Arser extends \Controller
{
    private const HOME = 'none';

    /**
     * Парсим группы товаров (получаем ссылки на конкретные товары)
     *
     * @throws Exception
     */
    public function openGroup()
    {
        ini_set('memory_limit', '3500M');

        $siteId = $this->request->get['site_id'];
        $this->load->model('extension/module/arser_link');
        $linksGroup = $this->model_extension_module_arser_link->getGroupLink($siteId, 1);

        if (count($linksGroup) == 0) { // все группы раскрыты
            $link_product_count = count($this->model_extension_module_arser_link->getGroupLink($siteId, 0));
            $json = [
                'link_group_count' => 0,
                'link_product_count' => $link_product_count,
                'status' => 'finish',
            ];
            echo json_encode($json);
            return;
        }

        $this->parseGroup($linksGroup[0]);
        $link_group_count = count($this->model_extension_module_arser_link->getGroupLink($siteId, 1));
        $link_product_count = count($this->model_extension_module_arser_link->getGroupLink($siteId, 0));

        $json = [
            'link_group_count' => $link_group_count,
            'link_product_count' => $link_product_count,
            'status' => 'go',
        ];
        echo json_encode($json);
        return;
    }

    /**
     * раскрываем группы
     * добавим линки на продукты и удалим группу
     * обязательно должен быть перекрыт в дочернем методе
     * @param array $linkGroup
     */
    protected function parseGroup(array $linkGroup)
    {
        echo __METHOD__ . ' должен быть перекрыт!';
        die();
    }

    /**
     * Парсим следующий товар (arser_link.status='new'), добавляем его в arser_product
     * @throws Exception
     */
    public function parseNextProduct()
    {
        $siteId = $this->request->get['site_id'];
        $this->load->model('extension/module/arser_link');
        $productLinks = $this->model_extension_module_arser_link->getNextLink($siteId);

        if (count($productLinks) == 0) { // все товары парсены
            $json = [
                'link_count' => count($productLinks),
                'link_product_count' => count($productLinks),
                'status' => 'finish',
            ];
            echo json_encode($json);
            return;
        }

        $this->parseProduct($productLinks[0]);
        $link_count = $this->model_extension_module_arser_link->getLinkCount($siteId);

        $json = [
            'link_count' => $link_count['all'],
            'link_product_count' => ($link_count['ok'] ?? 0) + ($link_count['bad'] ?? 0),
            'status' => 'go',
        ];
        echo json_encode($json);
        return;
    }

    /**
     * @param $link
     * @return bool|int|string
     * @throws \Exception
     */
    protected function getUrl($link)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $link,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Cookie: ' . random_bytes(59)
            ),
        ));

        $response = curl_exec($curl);
        /* Check for 404 (file not found). */
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            $response = $httpCode;
        }
        curl_close($curl);
        return $response;
    }

    /**
     * Получаем информацию о продукте
     * @param array $link
     */
    protected function parseProduct(array $link)
    {
        echo __METHOD__ . ' должен быть перекрыт!';
        die();
    }

    private function sizeNormal(array $ar): string
    {
        $description = $ar['description'];
        $document = (new DiDom\Document($description));
        $td_name = $document->first('span.extra_fields_name:contains("Габариты")');
        $td_value = $td_name->nextSibling('span');

        $el = $document->first('span.extra_fields_name:contains("Габариты")');
        if ($el) {

            preg_match_all('!\d+!', $el->nextSibling('span')->text(), $numbers);
            $ar["Ширина"] = $numbers[0][0] ?? 0;
            $ar["Глубина"] = $numbers[0][1] ?? 0;
            $ar["Высота"] = $numbers[0][2] ?? 0;
        }

        $td_name->setInnerHtml('Габариты (Ш*В*Г)');
        $td_value->setInnerHtml($ar["Ширина"] . '*' . $ar["Высота"] . '*' . $ar["Глубина"]);

        return $document->toElement()->innerHtml();
    }

    private function imgRemove(string $description): string
    {
        $document = (new DiDom\Document($description));
        $img = $document->first('.jshop_prod_description img');
        if ($img) {
            $img->remove();
        }

        return $document->toElement()->innerHtml();
    }
}
