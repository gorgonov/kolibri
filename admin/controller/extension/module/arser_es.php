<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserEs extends Arser
{
    private const HOME = 'https://eco-sleep.net';

    /**
     * @param  array  $linkGroup
     * @throws InvalidSelectorException
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link'].'/p/0?s[products_per_page]=1000';
        $document = new Doc($link, true);

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
    }

    /**
     * Получение ссылок на продукты (раскрываем группы)
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(Doc $document): array
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
     * Получаем информацию о продукте
     * @param  array  $link
     * @throws InvalidSelectorException
     */
    protected function parseProduct(array $link)
    {
        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

        loadDidom();

//        $result = $this->getUrl($link['link']);
        $url = $link['link'];
        $document = (new Doc($url, true));
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
        }
        $this->model_extension_module_arser_product->addProduct($data);
        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
    }

    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getProductInfo(Doc $document): array
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
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getImg(Doc $document): array
    {
        $res[] = self::HOME.$document->first('.product-image-a')->href;

        return $res;
    }

    /**
     * @param  Doc  $doc
     * @return string
     * @throws InvalidSelectorException
     */
    private function getDescription(Doc $doc): string
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
        $doc = new Doc($res);
        $doc->first('div.product-params-title:contains(Чехол)')->closest('div.product-params-tr')->remove();

        return $doc->html();
    }

    /**
     * @param  string  $str
     * @return array
     * @throws InvalidSelectorException
     */
    private function getAttr(string $str): array
    {
        $attrList = [];
        $doc = new Doc($str);

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
     * @param  Doc  $doc
     * @param $attrName
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttribute(Doc $doc, $attrName)
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
     * @throws InvalidSelectorException
     */
    private function getDopLinks(?string $href): array
    {
        $link = [];
        $doc = (new Doc($href, true));
        $elements = $doc->find('div.kind-image a');
        foreach ($elements as $element) {
            $link[] = self::HOME.$element->href;
        }

        return $link;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document): string
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
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getCase(Doc $document): array
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
     * @return string
     */
    private function normalCase(string $text): string
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
