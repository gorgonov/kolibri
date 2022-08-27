<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserBts extends Arser
{
    private const HOME = 'https://mebelony.ru';

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
        $links = $document->find('a.card__link');
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
        $ar['sku'] = $this->getSku($document);
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
        $slide = $document->find('div.product__thumbs-wrapper div.product__thumbs-slide');
        foreach ($slide as $item) {
            $el = $item->attr('style');
            $aTmp = explode("'", $el);
            $res[] = 'https:'.$aTmp[1];
        }

        $slide = $document->find('img.product__slide-img.product__slide-img_rectangle');
        foreach ($slide as $item) {
            $res[] = 'https:' . $item->src;
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
        if ($el = $doc->first('div.product__info-item')->html()) {
            $res .= $el;
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
        if ($attr = $this->getAttribute($doc, 'Материал корпуса')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал фасада')) {
            $attrList = array_merge($attrList, $attr);
        }

        return $attrList;
    }

    /**
     * @param  Doc  $doc
     * @param  string  $attrName
     * @param  bool  $is_digit
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttribute(Doc $doc, string $attrName, bool $is_digit = false)
    {
        $el = $doc->first("div.product__info-props:contains({$attrName})");
        if ($el) {
            $res = $el->first('strong')->text();
            if ($is_digit) {
                $res = preg_replace('/[^0-9]/', '', $res);
            }
            return [$attrName => $res];
        }

        return false;
    }

    /**
     * @param  Doc  $document
     * @return string|null
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document): ?string
    {
        $res = $document->first('h1.title::text');

        return $res;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getSku(Doc $document): string
    {
        $res = $document->first('div.product__id::text');
        $res = preg_replace('/[^0-9]/', '', $res);

        return $res;
    }
}
