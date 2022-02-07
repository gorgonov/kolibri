<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserPm extends Arser
{
    private const HOME = 'https://paksmet.ru';

    /**
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link'].'?sort=name'; //показать все товары
        $document = new Doc($link, true);

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
        $url = [];
        $links = $document->find('div.name.list_product_nam a');
        foreach ($links as $el) {
            $url[] = $el->href;
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
        $topic = $data['topic'];
        foreach ($topic as $item) {
            $data['topic'] = $item;
            $this->model_extension_module_arser_product->addProduct($data);
        }

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
        $ar['aImgLink'] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
//        $ar['sku'] = $this->getSku($document);
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

    /**
     * @param  Doc  $doc
     * @return string
     * @throws InvalidSelectorException
     */
    private function getDescription(Doc $doc): string
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
            $res = removeHtmlComments($el->html());

            $res .= $dop;
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
        return [];

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
     * @param  false  $is_digit
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttribute(Doc $doc, $attrName, $is_digit = false)
    {
        $el = $doc->first("#tab1 li:contains({$attrName})");
        if ($el) {
            $res = str_replace($attrName, '', $el->text());
            if ($is_digit) {
                $res = digit($res);
            }
            return [$attrName => $res];
        }

        return false;
    }

    private function getTopic(Doc $document)
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
}
