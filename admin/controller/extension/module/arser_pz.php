<?php

use DiDom\Document;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserPz extends Controller
{
    private const HOME = 'https://pazitif.com';

    public function openGroup()
    {
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
     * @param  array  $linkGroup
     */
    private function parseGroup(array $linkGroup)
    {
//        try {
        loadDidom();
        $link = $linkGroup['link'] . '?SHOWALL_5=1';
        $document = new DiDom\Document($link, true);

        $linkProducts = $this->getLinkProduct($document); // получим ссылки на продукты (отличаются цветом)

        // добавим линки на продукты и удалим группу
        $data = [];
        foreach ($linkProducts as $item) {
            $data[] = [
                'site_id' => $linkGroup['site_id'],
                'category_list' => $linkGroup['category_list'],
                'link' => $item,
                'is_group' => 0,
                'category1c' => $linkGroup['category1c'],
                'status' => 'new',
            ];
            $this->model_extension_module_arser_link->addLinks($data);
        }

        $this->model_extension_module_arser_link->deleteLinks([$linkGroup['id']]);

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
            'link' => $productLinks[0],
            'link_count' => $link_count['all'],
            'link_product_count' => ($link_count['ok'] ?? 0) + ($link_count['bad'] ?? 0),
            'status' => 'go',
        ];
        echo json_encode($json);
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
        /* Check for 404 (file not found). */
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode == 404) {
            $response = 404;
        }
        curl_close($curl);
        return $response;
    }

    /**
     * Получаем информацию о продукте
     * @param  array  $link
     */
    private function parseProduct(array $link)
    {
        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

        loadDidom();

        $result = $this->getUrl($link['link']);

        if ($result == 404) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Страница не существует');
            return;
        }

        $document = (new DiDom\Document($result));
        if (!$document) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Не удалось прочитать страницу');
            return;
        }

        // Получаем массив продуктов со страницы
        $data = $this->getProductInfo($document);
        if (empty($data)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];

        $this->model_extension_module_arser_product->addProduct($data);
        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
    }

    /**
     * Получение ссылок на продукты (раскрываем группы)
     * @param  Document  $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getLinkProduct(DiDom\Document $document): array
    {
        // соберем ссылки на продукты
        $url = [];
        $links = $document->find('div.productColText a.name');
        foreach ($links as $el) {
            $url[] = self::HOME.$el->attr('href');
        }

        $url = array_unique($url);

        // получим ссылки на размер продукта
        $sizeLink = [];
        foreach ($url as $item) {
            $links = $this->getSizeLinks($item);
            $sizeLink = array_merge($sizeLink, $links);
        }


        // получим ссылки на цвет продукта
        $colorLink = [];
        foreach ($sizeLink as $item) {
            $link = $this->getColorLinks($item);
            $colorLink = array_merge($colorLink, $link);
        }

        $colorLink = array_unique($colorLink);

        return $colorLink;
    }


    /**
     * @param  Document  $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getProductInfo(DiDom\Document $document): array
    {
        $ar = [];
        $ar['topic'] = $document->first('h1.changeName')->text();
        $el = $document->first('li.elementSkuPropertyValue.selected');
        if ($el) {
            $ar['topic'] = $document->first('h1.changeName')->text();
        }

        $ar['sku'] = $document->first('span.changeArticle::text');

        $img = [];
        $elements = $document->find('div.slideBox a');
        foreach ($elements as $element) {
            $img[] = self::HOME . $element->href;
        }

        if (empty($img)) {
            if ($el = $document->first('#pictureContainer a')) {
                $img = [self::HOME . $el->href];
            }
        }

        $ar['aImgLink'] = $img;
        $ar['description'] = $this->getDescription($document);
        $ar['attr'] = $this->getAttr($ar['description']);

        return $ar;
    }

    /**
     * @param  \DiDom\Element  $element
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getImg(DiDom\Document $document): array
    {
        $res = [];
        $elementList = $document->find('.gallery-preview_list a');
        foreach ($elementList as $element) {
            $res[] = $element->attr('href');
        }

        return $res;
    }

    private function getDescription(DiDom\Document $doc): string
    {
        if ($el = $doc->first('div.detailPropertiesTable')) {
            // todo надо удалить строчки из документа
            $el->firstInDocument('span:contains("Производитель")')->closest('tr')->remove();
            $tr = $el->firstInDocument('span:contains("Для детей")');
            if ($tr) {
                $tr->closest('tr')->remove();
            }

            return $el->html();
        } else {
            return '';
        }
    }

    private function getAttr(string $str)
    {
        $attrList = [];
        $doc = new DiDom\Document($str);

        if ($attr = $this->getAttribute($doc, 'Высота')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Ширина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Длина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Глубина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал корпуса')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал фасада')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Максимально допустимый вес')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Размеры под матрац')) {
            $attrList = array_merge($attrList, ['Размер спального места' => $attr['Размеры под матрац']]);
        }

        return $attrList;
    }

    /**
     * @param  Document  $doc
     * @param $attrName
     * @return array|false
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getAttribute(DiDom\Document $doc, $attrName)
    {
        $el = $doc->first("td span:contains({$attrName})");
        if ($el) {
            return [$attrName => trim($el->closest('td')->nextSibling('td')->text())];
        }

        return false;
    }

    /**
     * собираем ссылки на разные цвета продукта
     * @param  string|null  $href
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getColorLinks(?string $href): array
    {
        $link = [];
        $doc = (new DiDom\Document($href, true));
        $elements = $doc->find('li.elementSkuPropertyValue[data-name="SKU_COLOR"] a');
        foreach ($elements as $element) {
            $link[] = self::HOME . $element->href;
        }

        return $link;
    }

    private function getSizeLinks(?string $href): array
    {
        $link = [];
        $doc = (new DiDom\Document($href, true));
        $elements = $doc->find('li.elementSkuPropertyValue[data-name="SIZE"] a');
        foreach ($elements as $element) {
            $link[] = self::HOME . $element->href;
        }

        return $link;
    }
}
