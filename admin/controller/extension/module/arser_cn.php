<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserCn extends Arser
{
    private const HOME = 'https://adelco24.ru';

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
        $links = $document->find('div.info a');
        foreach ($links as $el) {
            $url[] = self::HOME.'/'.$el->href;
        }

        $url = array_unique($url);

        return $url;
    }

    /**
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
        $ar['description'] = $this->getDescription($document);
        $ar['aImgLink'] = $this->getImg($document);
        $ar['attr'] = $this->getAttr($ar['description']);
        $ar['product_option'] = $this->getOptions($document);

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

        if ($slide = $document->find('a.lightview')) {
            foreach ($slide as $item) {
                $res[] = self::HOME.'/'.$item->href;
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
        if ($el = $doc->first('div.description')) {
            $res .= $el->html();
        }

        if ($el = $doc->first('div.long_description')) {
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

        // найдем Габариты
        $el = $doc->first('span:contains("Габаритные размеры")');
        if ($el) {
            $attrList = getSize($el->nextSibling()->text());
        }

        $el = $doc->first('span:contains("Спальное место")');
        if ($el) {
            $size = getSize($el->nextSibling()->text());
            if ($size) {
                $attrList = array_merge(
                    $attrList,
                    ['Размер спального места' => $size['Ширина'].'*'.$size['Длина']]
                );
            }
        }

        return $attrList;
    }

    /**
     * @param  Doc  $document
     * @return Element|DOMElement|string|null
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document)
    {
        return $document->first('h1::text');
    }

    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getOptions(Doc $document): array
    {
        $res = [];
        $excludeList = ['matras', 'bele', 'namatrasnik'];
        $selectList = $document->find('.options');
        if ($selectList) {
            foreach ($selectList as $item) {
                $classList = explode(' ', $item->attr('class'));
                if (array_intersect($excludeList, $classList)) {
                    continue;
                }
                $opList = $item->find('option');

                if ($opList) {
                    foreach ($opList as $op) {
                        $name = $op->attr('value');
                        if ($name !== '0') {
                            $price = $op->attr('data-price');
                            $res[] = ['name' => $name, 'value' => $price];
                        }
                    }
                } else {
                    $labelList = $item->find('label input');
                    if ($labelList) {
                        foreach ($labelList as $op) {
                            $name = $op->attr('value');
                            $price = $op->attr('data-price');
                            $res[] = ['name' => $name, 'value' => $price];
                        }
                    }
                }
            }
        }

        return $res;
    }
}
