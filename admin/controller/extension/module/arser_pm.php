<?php

use DiDom\Document;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserPm extends Controller
{
    private const HOME = 'https://paksmet.ru';

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
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     */
    private function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link'].'?sort=name'; //показать все товары
        $document = new DiDom\Document($link, true);

        $linkProducts = $this->getLinkProduct($document); // получим ссылки на продукты

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

        $status = 'go';
        try {
            $this->parseProduct($productLinks[0]);
        } catch (Exception $exception) {
            $status = $exception->getMessage();
        }
        $link_count = $this->model_extension_module_arser_link->getLinkCount($siteId);

        $json = [
            'link' => $productLinks[0],
            'link_count' => $link_count['all'],
            'link_product_count' => ($link_count['ok'] ?? 0) + ($link_count['bad'] ?? 0),
            'status' => $status,
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
        if (empty($data)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];
        $topic = $data['topic'];
        foreach ($topic as $item) {
            $data['topic'] = $item;
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
        $links = $document->find('div.name.list_product_nam a');
        foreach ($links as $el) {
            $url[] = $el->href;
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
        $ar['topic'] = $this->getTopic($document);
        $ar['aImgLink'] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
//        $ar['sku'] = $this->getSku($document);
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
        $slide = $document->find('#list_product_image_middle a');
        foreach ($slide as $item) {
            $res[] = $item->href;
        }

        if ($el = $document->first('.jshop_img_description')) {
            $dopImg = $el->find('p img');
            foreach ($dopImg as $item) {
                $res[] = self::HOME . $item->src;
            }
        }

        return $res;
    }

    private function getDescription(DiDom\Document $doc): string
    {
        $res = '';
        if ($el = $doc->first('.jshop_img_description')) {
            // удалим лишнее
            $dop = '';
            $instruction = $el->firstInDocument('p.atr-title a');
            if ($instruction) {
                $dop = $instruction->html();
                $instruction->remove();
            }

            if ($excess = $el->firstInDocument('h1')) {
                $excess->remove();
            };
            if ($excess = $el->firstInDocument('.span4')) {
                $excess->remove();
            };
            if ($excess = $el->firstInDocument('.protuct-icon-item')) {
                $excess->remove();
            };
            if ($excess = $el->firstInDocument('p:contains(Варианты сборки)')) {
                $excess->closest('div')->remove();
            };

            while ($excess = $el->firstInDocument('.atr-title:contains(Размер)')) {
                $excess->closest('div')->remove();
            }
            $res = $this->removeHtmlComments($el->html());

            $res .= $dop;
        }

        return $res;
    }

    private function getAttr(string $str)
    {
        $attrList = [];
        return $attrList;

        $doc = new DiDom\Document($str);

        if ($attr = $this->getAttribute($doc, 'Ширина', true)) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Высота', true)) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Глубина', true)) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Длина', true)) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Размер спального места')) {
            $attrList = array_merge($attrList, $attr);
        }

        return $attrList;
    }

    /**
     * @param  Document  $doc
     * @param $attrName
     * @param  false  $is_digit
     * @return array|false
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getAttribute(DiDom\Document $doc, $attrName, $is_digit = false)
    {
        $el = $doc->first("#tab1 li:contains({$attrName})");
        if ($el) {
            $res = str_replace($attrName, '', $el->text());
            if ($is_digit) {
                $res = preg_replace('/[^0-9]/', '', $res);
            }
            return [$attrName => $res];
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
        $res = [];
        if ($el = $document->first('.jshop_img_description')) {
            $productSize = $el->find('.product-size span');
            foreach ($productSize as $item) {
                $res[] = $item->text();
            }
        } else {
            $res[] = $document->first('h1::text');
        }

        return $res;
    }

    private function getSku(Document $document)
    {
        $res = $document->first('div.product__id::text');
        $res = preg_replace('/[^0-9]/', '', $res);

        return $res;
    }

    /**
     * удаляет комментарии из html-разметки
     * @param $html
     * @return array|string|string[]|null
     */
    private function removeHtmlComments($html)
    {
        $res = $html;
        $startPos = mb_strpos($html, '<!--');
        $endPos = mb_strpos($html, '-->');
        while ($startPos !== false && $endPos !== false) {
            $res = mb_substr($res, 0, $startPos - 1).mb_substr($html, $endPos + 3);
            $startPos = mb_strpos($res, '<!--');
            $endPos = mb_strpos($res, '-->');
        }

//        $res = preg_replace('/<!--(.*?)-->/', '', $html);
        return $res;
    }

}
