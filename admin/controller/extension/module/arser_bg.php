<?php

use DiDom\Document;

class ControllerExtensionModuleArserBg extends Controller
{
    private const HOME = 'http://xn--90aafiboze2m.xn--p1ai';

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
     * @param array $linkGroup
     */
    private function parseGroup(array $linkGroup)
    {
//        try {
        $this->loadDidom();
        // 1 страница
        $document = new DiDom\Document($linkGroup['link'], true);

        $linkProducts = $this->getLinkProduct($document); // получим ссылки на продукты (отличаются цветом)

        // добавим линки на продукты и удалим группу
        $data = [];
        foreach ($linkProducts as $item) {
            if (!empty($item)) {
                $data[] = [
                    'site_id' => $linkGroup['site_id'],
                    'category_list' => $linkGroup['category_list'],
                    'link' => self::HOME . $item,
                    'is_group' => 0,
                    'category1c' => $linkGroup['category1c'],
                    'status' => 'new',
                ];
            }
        }
        $this->model_extension_module_arser_link->addLinks($data);
        $this->model_extension_module_arser_link->deleteLinks([$linkGroup['id']]);

        return;
    }

    private function getUrl($link)
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
                'Cookie: f261601ce57a4ee01f9efde6727e03e5=srlhf2krg7d51dejkdmkkhcsd1'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }

    /**
     * Получаем информацию о продукте
     * @param array $link
     */
    private function parseProduct(array $link)
    {
        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

        $this->loadDidom();

        $result = $this->getUrl($link['link']);

        $document = (new DiDom\Document($result));
        if (!$document) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Не удалось прочитать страницу');
            return;
        }

        // Общая информация о продукте
        $data = $this->getProductInfo($document);
        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];

        $links = $this->getLinkProductColor($document); // содержит массив в формате
        // [[id] => [title, imageLink], ... ]

        $common_image = $data['aImgLink'] ?? [];
        $common_topic = $data['topic'] ?? '';

        foreach ($links as $item) {
            $data['topic'] = "{$common_topic} ({$item['title']})";
            $data['aImgLink'] = array_merge([$item['imageLink']], $common_image);
            $this->model_extension_module_arser_product->addProduct($data);
        }
        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
//        } catch (Exception $e) {
//            // установить статус у текущей групповой ссылки 'bad'
//            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', $e->getMessage() . ' in ' . $e->getLine());
//        }
    }

    private function loadDidom()
    {
        $cwd = getcwd();
        chdir(DIR_SYSTEM . 'library/DiDom');
        require_once('ClassAttribute.php');
        require_once('Document.php');
        require_once('Node.php');
        require_once('Element.php');
        require_once('Encoder.php');
        require_once('Errors.php');
        require_once('Query.php');
        require_once('StyleAttribute.php');
        require_once('Exceptions/InvalidSelectorException.php');
        chdir($cwd);
    }

    private function getLinkProductColor(DiDom\Document $document): array
    {
        $url = [];
        $links = $document->find('#list_product_image_thumb img');
        foreach ($links as $el) {
            $id = self::normalSum($el->getAttribute('onclick'));
            $img = $document->first('#main_image_' . $id)->attr('src');
            $title = $el->getAttribute('title');
            $url[$id] = ['title' => $title, 'imageLink' => $img];
        }

        return $url;
    }

    private function getLinkProduct(DiDom\Document $document): array
    {
        $url = [];
        $links = $document->find('a.list-product-item');
        foreach ($links as $el) {
            $url[] = $el->attr('href');
        }

        return $url;
    }

    /**
     * @param Document $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getProductInfo(DiDom\Document $document): array
    {
        $ar = [];
        $ar["topic"] = trim($document->first('.productfull h2')->text());
        $ar["sku"] = $document->first('#manufacturer_code')
        ? trim($document->first('#manufacturer_code')->text())
        : '';

        // картинки
        $ar["aImgLink"] =
            $document->first('.jshop_prod_description img')
                ? [self::HOME . $document->first('.jshop_prod_description img')->attr('src')]
                : [];

        $ar['description'] = '';

        $sTmp = $document->first('.jshop_prod_description');
        $ar['description'] .= $sTmp ? $sTmp->html() : '';

        $sTmp = $document->first('.extra_fields');
        $ar['description'] .= $sTmp ? $sTmp->html() : '';

        $sTmp = $document->first('.productweight');
        $ar['description'] .= $sTmp ? $sTmp->html() : '';

        $ar['attr'] = $this->getAttributes($document);

        $ar['description'] = $this->sizeNormal($ar);
        $ar['description'] = $this->imgRemove($ar['description']);

        $el = $document->first('#block_weight');
        if ($el) {
            $ar["weight"] = trim(self::normalSum($el->text()));
        }

        return $ar;
    }

    /**
     * @param string $str
     * @return string
     */
    private function trimScript(string $str): string
    {
        return preg_split("/<script>/", $str)[0];
    }

    /**
     * @param Document $doc
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getAttributes(DiDom\Document $doc): array
    {
        $aTmp = [];

        $el = $doc->first('span.extra_fields_name:contains("Габариты")');
        if ($el) {
            preg_match_all('!\d+!', $el->nextSibling('span')->text(), $numbers);
            @$aTmp["Ширина"] = $numbers[0][0];
            @$aTmp["Глубина"] = $numbers[0][1];
            @$aTmp["Высота"] = $numbers[0][2];
        }

        return $aTmp;
    }

    /**
     * @param $sum - строка, содержащая цифры и текст
     * @return int - возвращает целое число, состоящее из цифр $sum
     */
    private static function normalSum($sum)
    {
        $result = (int)preg_replace("/[^0-9]/", '', $sum);
        return $result;
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
