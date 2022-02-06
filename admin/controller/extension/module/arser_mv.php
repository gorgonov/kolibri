<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserMv extends Arser
{
//    private const HOME = 'https://мавимебель.рф'; // или https://xn--80accmbpybd8n.xn--p1ai
    private const HOME = 'https://xn--80accmbpybd8n.xn--p1ai';

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
        $links = $document->find('a.image_cell__img');
        foreach ($links as $el) {
            $url[] = self::HOME.$el->href;
        }

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
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getProductInfo(Doc $document): array
    {
        $ar = [];
        $ar['topic'] = $this->getTopic($document);
//        $ar['sku'] = $this->getSku($document);
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
        $res = [];
        $slide = $document->find('div#gallery a');
        foreach ($slide as $item) {
            $res[] = self::HOME . $item->href;
        }

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
        if ($el = $doc->first('div#tab1')) {
            $res .= $el->html();
        }

        return $res;
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
     * @param  Doc  $doc
     * @param $attrName
     * @param  bool  $is_digit
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttribute(Doc $doc, $attrName, bool $is_digit = false)
    {
        $el = $doc->first("#tab1 li:contains({$attrName})");
        if ($el) {
            $res = str_replace($attrName,'',$el->text());
            if ($is_digit) {
                $res = digit($res);
            }
            return [$attrName => $res];
        }

        return false;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document): string
    {
        $res = $document->first('h1::text');

        return $res;
    }
}
