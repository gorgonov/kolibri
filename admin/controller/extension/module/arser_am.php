<?php

use DiDom\Document;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserAm extends Controller
{
    private const HOME = 'https://mobi-mebel.ru';

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
        // хотим показать все продукты, 500 хватит?
        $document = new DiDom\Document($linkGroup['link'], true);

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
        }
        $this->model_extension_module_arser_link->addLinks($data);
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

    private function getUrlProduct($data)
    {
        $item_id = $data['item_id'];
        $element_id = $data['element_id'];

        $rand = rand(100, 999);
        $template_id = $data['template_id'];
        $identifier = $data['identifier'];
//        $color = 'венге+тёмный';
//        $color = 'ясень шимо светлый';
        $color = $data['color'];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://altaimebel22.ru/?option=com_zoo&controller=default&task=callelement&element={$element_id}&method=ajaxChangeVariant&item_id={$item_id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "rand={$rand}
            &option=com_zoo
            &tmpl=component
            &format=raw
            &args[template][{$template_id}]=full
            &args[values][{$identifier}][value]={$color}",

            CURLOPT_HTTPHEADER => array(
                'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:94.0) Gecko/20100101 Firefox/94.0',
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'cache-control: no-cache',
                'X-Requested-With: XMLHttpRequest',
                'Origin: https://altaimebel22.ru',
                'Connection: keep-alive',
                'Referer: https://altaimebel22.ru/shop/item/krovatkseniya-1600-2000',
                'Cookie: 1418c2a6a4505526d894d3dbcf888205=29328c0c2c7f0f81a2883c72c2a36e91; MCS_CITY_NAME=%D0%90%D0%BB%D1%82%D0%B0%D0%B9%D1%81%D0%BA%D0%B8%D0%B9+%D0%BA%D1%80%D0%B0%D0%B9; MCS_CITY_NAME=%D0%90%D0%BB%D1%82%D0%B0%D0%B9%D1%81%D0%BA%D0%B8%D0%B9+%D0%BA%D1%80%D0%B0%D0%B9',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'TE: trailers'
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

        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];
        // Получаем массив продуктов со страницы
        $product = $this->getProductInfo($document);
        if (empty($product)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $dopImg = $this->getDopImg($document);
        $data['description'] = $product['description'];
        $data['attr'] = $product['attr'];
        foreach ($product['aImgLink'] as $item) {
            $data['aImgLink'] = [$item['image']];
            if ($dopImg) {
                $data['aImgLink'] = array_merge($data['aImgLink'], $dopImg);
            }
            $data['topic'] = $product['topic'];
            if ($item['color'] !== 'none') {
                $data['topic'] .= ' ('.$item['color'].')';
            }
            $this->model_extension_module_arser_product->addProduct($data);
        }
        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
    }

    private function getLinkProduct(DiDom\Document $document): array
    {
        $url = [];
        $links = $document->find('a.jbimage-link');
        foreach ($links as $el) {
            $url[] = $el->attr('href');
        }

        return $url;
    }


    /**
     * @param  Document  $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getProductInfo(DiDom\Document $document): array
    {
        $ar = [];
        $el = $document->first('h1.item-title');
        if (!$el) {
            return [];
        }
        $ar["topic"] = trim($document->first('h1.item-title')->text()); // Заголовок товара

        $ar['aImgLink'] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
        $ar['attr'] = $this->getAttr($ar['description']);
        if (!isset($ar['attr']['Размер спального места'])) { // если не нашли в описании, то ищем в названии
            $re = '/(\d+)[^\d]+(\d+)/';
            $pos = strpos($ar["topic"], '(');
            if ($pos !== false) {
                $str = substr($ar["topic"], $pos);

                if (preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0)) {
                    $ar['attr']['Размер спального места'] = $matches[0][1].'*'.$matches[0][2];
                }
            }
        }

        return $ar;
    }

    /**
     * @param  \DiDom\Element  $element
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getImg(DiDom\Document $document): array
    {
        $colorList = [];

        $variants = $this->getVariants($document);
        $item_id = $variants[0]['item_id'];
        $element_id = $variants[0]['element_id'];

        $el = $document->first('.jbprice-color');
        if (empty($el)) {
            $colorList[] = [
                'color' => 'none',
                'image' => $document->first('div.image a')->attr('href')
            ];
            return $colorList;
        }
//        $identifier = $el->attr('data-identifier');
        $elementList = $el->toDocument()->find('.jbzoo-colors input');
        foreach ($elementList as $el) {
            $color = $el->attr('value');
            $name = $el->attr('name');
            $aAttr = preg_split("/[\[\]]+/", $name);
            $template_id = $aAttr[0];
            $identifier = $aAttr[1];

            $res = $this->getUrlProduct(compact('item_id', 'element_id', 'identifier', 'color', 'template_id'));
            $json = json_decode($res);

            $colorList[] = [
                'color' => $color,
                'image' => $json->image->jselementfulllist2->popup
            ];
        }

        return $colorList;
    }

    private function getTopic(DiDom\Document $doc): string
    {
        $res = '';
        $item = $doc->first('.module_description b');
        if ($item) {
            $res = $item->text();
        }

        return $res;
    }

    private function getDopImg(DiDom\Document $document)
    {
        $res = [];
        $items = $document->find('div.middle-line div.image a');
        if (count($items) > 1) {
            foreach ($items as $key => $item) {
                if ($key>0) {
                    $res[] = $item->getAttribute('href');
                }
            }
        }

        return $res;
    }

    private function getDescription(DiDom\Document $doc): string
    {
        if ($el = $doc->first('.properties')) {
            $str = $el->html();
            $str = str_replace("'", '', $str);
            return $str;
        } else {
            return '';
        }

    }

    private function getAttr(string $str)
    {
        $attrList = [];
        $str = strip_tags($str);
        $str = htmlentities($str);
        $str = str_replace("&nbsp;", ' ', $str);

        if ($attr = $this->getAttribute($str, 'высота')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($str, 'ширина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($str, 'длина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($str, 'глубина')) {
            $attrList = array_merge($attrList, $attr);
        }
        $pos = mb_strpos($str, 'Размер спального места');
        if ($pos !== false) {
            $str1 = mb_substr($str, $pos);
            $attr1 = $this->getAttribute($str1, 'ширина');
            $attr2 = $this->getAttribute($str1, 'длина');
            $attr = [
                'Размер спального места' =>
                    $attr1['ширина'].'*'.$attr2['длина']
            ];
            $attrList = array_merge($attrList, $attr);
        }

        return $attrList;
    }

    private function getAttribute($str, $attrName)
    {
        $pos = mb_strpos($str, $attrName);
        if ($pos === false) {
            return null;
        }

        $str1 = mb_substr($str, $pos, 30);
        return [$attrName => (int)(explode(' ', $str1)[1])];
    }

    private function getVariants(Document $document)
    {
        $aTmp = [];
        $re = '/({[^}]+})}/m';
        $a = $document->find('div.price-block script');
        foreach ($a as $item) {
            $str = strip_tags($item);
            preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
            if (isset($matches[1][1])) {
                $obj = json_decode($matches[1][1]);
                if (!is_null($obj)) {
                    $aTmp[] = [
                        'item_id' => $obj->item_id,
                        'element_id' => $obj->element_id,
                    ];
                }
            } elseif (isset($matches[0][1])) {
                $obj = json_decode($matches[0][1]);
                $aTmp[] = [
                    'item_id' => $obj->item_id,
                    'element_id' => $obj->element_id,
                ];
            }
        }

        return $aTmp;
    }
}
