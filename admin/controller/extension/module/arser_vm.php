<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserVm extends Arser
{
    private const HOME = 'https://nsk.vmebel24.ru';

    /**
     * раскрываем группы
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     * @throws InvalidSelectorException
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link'].'?limit=1000'; //показать все товары

        $document = new Doc($link, true);
        $linkProducts = $this->getLinkProduct($document); // получим ссылки на продукты

        $data = [];
        foreach ($linkProducts as $item) {
            $data[] = [
                'site_id' => $linkGroup['site_id'],
                'category_list' => $linkGroup['category_list'],
                'category1c' => $linkGroup['category1c'],
                'link' => $item,
                'is_group' => 0,
                'status' => 'new',
            ];
        }
        $this->model_extension_module_arser_link->addLinks($data);
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
        $links = $document->find('a.products-list__img-item')
            ?: $document->find('div.catalog-section a.catalog_item__name');
        foreach ($links as $el) {
            $url[] = self::HOME . $el->href;
        }

        $url = array_unique($url);

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
        $ar['description'] = $this->getDescription($document);
        $ar['aImgLink'] = $this->getImg($document);
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

        if ($slide = $document->find('.detail_item__picture__block__big a')) {
            foreach ($slide as $item) {
                $res[] = self::HOME . $item->href;
            }
        }

        $res = array_unique($res);

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
//        if ($el = $doc->first('div[tab=description]')) {
//            $res .= $el->html();
//        }
//
        if ($el = $doc->first('div[tab=properties] div.detail_item__info__panes__item__inner')) {
            $res .= $el->html();
        }

        if ($el = $doc->first('div.detail_item__info__props__table')) {
            $res .= $el->html();
        }

        return $res;
    }

    /**
     * @param  string  $str
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttr(string $str)
    {
        $attrList = [];
        $doc = new Doc($str);

        if ($attr = $this->getAttribute($doc, 'Высота')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Глубина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Ширина')) {
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
     * @param  false  $is_digit
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttribute(Doc $doc, $attrName, $is_digit = false)
    {
        // если характеристики в таблице
        $el = $doc->first("dt:contains({$attrName})");
        if ($el) {
            $res = $el->nextSibling('dd')->text();
            return [$attrName => $res];
        }
        // если характеристики в дивах вариант 1
        $el = $doc->first("div.detail_item__info__props__table__row__name:contains({$attrName})");
        if ($el) {
            $res = $el->nextSibling('div.detail_item__info__props__table__row__value')->text();
            return [$attrName => $res];
        }
        // если характеристики в дивах вариант 2
        $el = $doc->first("div.detail_item__info__panes__item__inner__element__title:contains({$attrName})");
        if ($el) {
            $res = $el->nextSibling('div.detail_item__info__panes__item__inner__element__value')->text();
            return [$attrName => $res];
        }

        return false;
    }

    /**
     * @param  Doc  $document
     * @return \DiDom\Element|DOMElement|string|null
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document) {
        $res = $document->first('h1::text');
        $res = trim(str_replace('в Новосибирске', '', $res));

        return $res;
    }
}
