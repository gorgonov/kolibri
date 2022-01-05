<?php

use DiDom\Document;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserDt extends Controller
{
    private const HOME = 'https://www.utromebel.ru';

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
        $url = [];
        $links = $document->find('div.product_preview a');
        foreach ($links as $el) {
            $url[] = self::HOME.$el->attr('href');
        }

        $url = array_unique($url);

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
        $el = $document->first('h1.product-title');
        if (!$el) {
            return [];
        }
        $ar["topic"] = trim($el->text()); // Заголовок товара

        $ar['aImgLink'] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
        $ar['attr'] = $this->getAttr($ar['description']);
        $ar['product_option'] = $this->getOptions($document);

        return $ar;
    }

    private function getOptions(DiDom\Document $doc): array
    {
        $aTmp = [
            ['name' => 'Цвет корпуса', 'value' => 'анкор белый',],
            ['name' => 'Цвет корпуса', 'value' => 'анкор светлый'],
            ['name' => 'Цвет корпуса', 'value' => 'анкор темный'],
            ['name' => 'Цвет корпуса', 'value' => 'белый'],
            ['name' => 'Цвет корпуса', 'value' => 'белый глянец'],
            ['name' => 'Цвет корпуса', 'value' => 'венге'],
            ['name' => 'Цвет корпуса', 'value' => 'дуб бунратти'],
            ['name' => 'Цвет корпуса', 'value' => 'дуб выбеленный'],
            ['name' => 'Цвет корпуса', 'value' => 'дуб сонома'],
            ['name' => 'Цвет корпуса', 'value' => 'дуб французский'],
            ['name' => 'Цвет корпуса', 'value' => 'крем'],
            ['name' => 'Цвет корпуса', 'value' => 'ольха'],
            ['name' => 'Цвет корпуса', 'value' => 'орех итальянский'],
            ['name' => 'Цвет корпуса', 'value' => 'орех миланский'],
            ['name' => 'Цвет корпуса', 'value' => 'орех таволато'],
            ['name' => 'Цвет корпуса', 'value' => 'слива валлис'],
            ['name' => 'Цвет корпуса', 'value' => 'сосна астрид'],
            ['name' => 'Цвет корпуса', 'value' => 'сосна винтенберг'],
            ['name' => 'Цвет корпуса', 'value' => 'черный'],
            ['name' => 'Цвет корпуса', 'value' => 'шимо светлый'],
            ['name' => 'Цвет корпуса', 'value' => 'шимо темный'],
            ['name' => 'Цвет фасада', 'value' => 'аква'],
            ['name' => 'Цвет фасада', 'value' => 'анкор белый'],
            ['name' => 'Цвет фасада', 'value' => 'анкор светлый'],
            ['name' => 'Цвет фасада', 'value' => 'анкор темный'],
            ['name' => 'Цвет фасада', 'value' => 'белый'],
            ['name' => 'Цвет фасада', 'value' => 'белый глянец'],
            ['name' => 'Цвет фасада', 'value' => 'бетон пайн экзотик'],
            ['name' => 'Цвет фасада', 'value' => 'венге'],
            ['name' => 'Цвет фасада', 'value' => 'дуб аризона'],
            ['name' => 'Цвет фасада', 'value' => 'дуб бунратти'],
            ['name' => 'Цвет фасада', 'value' => 'дуб вотан'],
            ['name' => 'Цвет фасада', 'value' => 'дуб выбеленный'],
            ['name' => 'Цвет фасада', 'value' => 'дуб интра'],
            ['name' => 'Цвет фасада', 'value' => 'дуб сонома'],
            ['name' => 'Цвет фасада', 'value' => 'дуб французский'],
            ['name' => 'Цвет фасада', 'value' => 'желтый'],
            ['name' => 'Цвет фасада', 'value' => 'индиана эбони'],
            ['name' => 'Цвет фасада', 'value' => 'ирис'],
            ['name' => 'Цвет фасада', 'value' => 'камень темный'],
            ['name' => 'Цвет фасада', 'value' => 'красный'],
            ['name' => 'Цвет фасада', 'value' => 'крем'],
            ['name' => 'Цвет фасада', 'value' => 'лайм'],
            ['name' => 'Цвет фасада', 'value' => 'лимонный'],
            ['name' => 'Цвет фасада', 'value' => 'олива шоколад'],
            ['name' => 'Цвет фасада', 'value' => 'ольха'],
            ['name' => 'Цвет фасада', 'value' => 'оранж'],
            ['name' => 'Цвет фасада', 'value' => 'орех итальянский'],
            ['name' => 'Цвет фасада', 'value' => 'орех миланский'],
            ['name' => 'Цвет фасада', 'value' => 'орех таволато'],
            ['name' => 'Цвет фасада', 'value' => 'орех таволато'],
            ['name' => 'Цвет фасада', 'value' => 'пинк'],
            ['name' => 'Цвет фасада', 'value' => 'розовый кварц'],
            ['name' => 'Цвет фасада', 'value' => 'салатовый'],
            ['name' => 'Цвет фасада', 'value' => 'серенити'],
            ['name' => 'Цвет фасада', 'value' => 'синий'],
            ['name' => 'Цвет фасада', 'value' => 'салатовый'],
            ['name' => 'Цвет фасада', 'value' => 'слива валлис'],
            ['name' => 'Цвет фасада', 'value' => 'сосна астрид'],
            ['name' => 'Цвет фасада', 'value' => 'сосна винтенберг'],
            ['name' => 'Цвет фасада', 'value' => 'черный'],
            ['name' => 'Цвет фасада', 'value' => 'шимо светлый'],
            ['name' => 'Цвет фасада', 'value' => 'шимо темный'],
        ];

        return $aTmp;
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
        if ($el = $doc->first('table.product-properties')) {
            $doc = $el->toDocument();
            $doc->first('tr')->remove(); // удалим первую строку из таблицы
            $el = $doc->first('td a');
            if ($el) {
                $el->parent()->setInnerHtml(trim($el->text()));
            }
            $str = $this->removeHtmlComments($doc->html()); // удалим html-комментарий
            return $str;
        } else {
            return '';
        }
    }

    /**
     * удаляет комментарии из html-разметки
     * @param $html
     * @return array|string|string[]|null
     */
    private function removeHtmlComments($html)
    {
        return preg_replace('/<!--(.*?)-->/', '', $html);
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
        $el = $doc->first("td:contains({$attrName})");
        if ($el) {
            return [$attrName => trim($el->nextSibling('td')->text())];
        }

        $el = $doc->first("td a:contains({$attrName})");
        if ($el) {
            return [$attrName => trim($el->parent()->nextSibling('td')->text())];
        }

        return false;
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
