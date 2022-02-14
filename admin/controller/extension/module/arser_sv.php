<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserSv extends Arser
{
    private const HOME = 'https://online.sv-mebel.ru';

    /**
     * раскрываем группы
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     * @throws InvalidSelectorException
     */
    protected function parseGroup(array $linkGroup)
    {
        parent::parseGroup($linkGroup);
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
        $links = $document->find('div.proposal-item__info.proposal-item__info_catalog a');
        foreach ($links as $el) {
            if (substr($el->href,0,4) !== 'http') {
                $url[] = self::HOME . $el->href;
            } else {
                $url[] = $el->href;
            }
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
        $items = $this->getProductInfo($document);
        if (empty($items)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];
        foreach ($items as $item) {
            $data = array_merge($data,$item);
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
//        $description = $this->getDescription($document);
        $offers = $this->getOffers($document);
        foreach ($offers as $offer) {
            $img = [];
            foreach ($offer->SLIDER as $item) {
                $img[] = self::HOME.$item->SRC;
            }

            $description = html_entity_decode($offer->DISPLAY_PROPERTIES);
            $doc = new Doc($description);
            $sku = $this->getSku($doc);
            $attr = $this->getAttr($doc);

            $ar[] = [
                'description' => $description,
                'sku' => $sku,
                'topic' => html_entity_decode($offer->NAME),
                'aImgLink' => $img,
                'attr' => $attr,

            ];
        }

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

        if ($slide = $document->find('.product-page__img-slider-item a')) {
            foreach ($slide as $item) {
                $res[] = $item->href;
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
        return $doc->first('div.product-item-detail-tab-content')->html();
    }

    /**
     * @param  string  $str
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttr(Doc $doc)
    {
        $attrList = [];

        if ($attr = $this->getAttribute($doc, 'Высота')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Глубина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Ширина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал корпуса')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал фасада')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Тип направляющих')) {
            $attrList = array_merge($attrList, $attr);
        }

        $size1 = $this->getAttribute($doc, 'Ширина спального места', true);
        $size2 = $this->getAttribute($doc, 'Длина спального места', true);

        if ($size1 && $size2) {
            $attr = ['Размер спального места' => implode('*', [$size1,$size2])];
            $attrList = array_merge($attrList, $attr);
        }

        return $attrList;
    }

    /**
     * @param  Doc  $doc
     * @param $attrName
     * @param  false  $is_string
     * @return array|false|string
     * @throws InvalidSelectorException
     */
    private function getAttribute(Doc $doc, $attrName, $is_string = false)
    {
        $el = $doc->first("dt:contains({$attrName})");
        if ($el) {
            $res = $el->nextSibling('dd')->text();
            if ($is_string) {
                return $res;
            } else {
                return [$attrName => $res];
            }
        }


        return false;
    }

    private function getSku(Doc $document)
    {
        return $this->getAttribute($document, 'Артикул', true);
    }

    /**
     * @param  Doc  $document
     * @return mixed
     * @throws InvalidSelectorException
     */
    private function getOffers(Doc $document)
    {
        $tmp = $document->first('script:contains(JCCatalogElement)::text');
        if (empty($tmp)) {
            return [];
        }
        $startPos = mb_strpos($tmp, "{'CONFIG'");
        $endPos = mb_strrpos($tmp, '}');
        $tmp = mb_substr($tmp, $startPos, $endPos - $startPos + 1);
        $tmp = preg_replace("/'/", '"', $tmp);
        $json = json_decode($tmp);

        $offers = $json->OFFERS;
//        $tmp = html_entity_decode($tmp);
        return $offers;
    }

}
