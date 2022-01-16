<?php

use DiDom\Document;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserEs extends Controller
{
    private const HOME = 'https://eco-sleep.net';

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
        loadDidom();
        $link = $linkGroup['link'].'/p/0?s[products_per_page]=1000';
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

        // Получаем массив - информацию о продукте
        $data = $this->getProductInfo($document);
        $topic = $data['topic'];
        $case = $data['case'];
        if (empty($data)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];
        foreach ($case as $item) {
            $data['topic'] = $topic.' ('.$item.')';
            $this->model_extension_module_arser_product->addProduct($data);
        }

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
        $links = $document->find('a.product-image-img');
        foreach ($links as $el) {
            $url[] = self::HOME.$el->href;
        }

        // добавим ссылки на модификации
        $dopUrl = [];
        foreach ($url as $el) {
            $dopUrl = array_merge($dopUrl, $this->getDopLinks($el));
        }

        $url = array_merge($url, $dopUrl);
        sort($url);

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
        $ar['topic'] = $this->getTopic($document);
        $ar['case'] = $this->getCase($document);
        $ar['aImgLink'] = $this->getImg($document);
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
        $res[] = self::HOME.$document->first('.product-image-a')->href;

        return $res;
    }

    private function getDescription(DiDom\Document $doc): string
    {
        $res = '';
        if ($el = $doc->first('div#shop2-tabs-1')->html()) {
            $res .= $el;
        }
        if ($el = $doc->first('div#shop2-tabs-2')->html()) {
            $res .= $el;
        }

        $res .= '<p><b>Возможно изготовление в любом размере</b>';
        // надо удалить строку с чехлом
        $doc = new Document($res);
        $doc->first('div.product-params-title:contains(Чехол)')->closest('div.product-params-tr')->remove();

        return $doc->html();
    }

    private function getAttr(string $str)
    {
        $attrList = [];
        $doc = new DiDom\Document($str);

        if ($attr = $this->getAttribute($doc, 'Высота')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Макс. нагрузка на спальное место')) {
            // костыль, на круглые скобки ругался
            $attrList = array_merge($attrList, [
                'Макс. нагрузка на спальное место (кг)' => $attr['Макс. нагрузка на спальное место']
            ]);
        }
        if ($attr = $this->getAttribute($doc, 'Жесткость')) {
            $attrList = array_merge($attrList, $attr);
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
        $el = $doc->first("div.product-params-title:contains({$attrName})");
        if ($el) {
            return [$attrName => trim($el->nextSibling('div')->text())];
        }

        return false;
    }

    /**
     * собираем ссылки на разные цвета продукта
     * @param  string|null  $href
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getDopLinks(?string $href): array
    {
        $link = [];
        $doc = (new DiDom\Document($href, true));
        $elements = $doc->find('div.kind-image a');
        foreach ($elements as $element) {
            $link[] = self::HOME.$element->href;
        }

        return $link;
    }

    private function getTopic(Document $document)
    {
        $res = $document->first('h1::text');
        $curSizeEl = $document->first('ul.product-options li select option[selected]');
        if ($curSizeEl) {
            $size = (int)$curSizeEl->text();
            $res .= ' '.$size;
        } else {
            $curSizeEl = $document->first('ul.product-options li div.option-title:contains(Размер)');
            if ($curSizeEl) {
                $size = (int)$curSizeEl->nextSibling()->text();
                $res .= ' '.$size;
            }
        }

        return $res;
    }

    /**
     * варианты чехлов
     * @param  Document  $document
     * @return array
     */
    private function getCase(Document $document): array
    {
        $res = [];
        $el = $document->first('ul.product-options li div.option-title:contains(Чехол)');
        if ($el) {
            $case = $el->nextSibling();
            // это одиночный чехол или есть выбор?
            if ($op = $case->find('select option')) {
                foreach ($op as $item) {
                    $res[] = $this->normalCase($item->text());
                }
            } else {
                $res[] = $this->normalCase($case->text());
            }
        }
        return $res;
    }

    /**
     * @param  string  $text
     */
    private function normalCase(string $text)
    {
        $text = trim($text);
        if ($text == 'Жаккард "Стандарт"') {
            return 'Жаккард';
        }
        if ($text == 'Хлопковый Жаккард') {
            return 'Х/б';
        }

        return $text;
    }


}
