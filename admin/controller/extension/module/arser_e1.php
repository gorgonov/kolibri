<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM . 'helper/arser.php');

class ControllerExtensionModuleArserE1 extends Arser
{
    private const HOME = 'https://sitparad.com';

    /**
     * раскрываем группы
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     * @throws InvalidSelectorException
     */
    protected function parseGroup(array $linkGroup)
    {
        return; // здесь нет групп
    }

    protected function getLinkProduct(Doc $document): array
    {
        // соберем ссылки на продукты
        $url = [];
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
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getProductInfo(Doc $document): array
    {
        $ar = [];

        $ar['attr'] = $this->getAttr($document);
        $ar['topic'] = $this->getTopic($document);
        $ar['sku'] = $this->getSku($document);
        $ar['aImgLink'] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);

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

        if ($slide = $document->find('div.inner img[href]')) {
            foreach ($slide as $item) {
                $res[] = $item->href;
            }
        }

        if ($slide = $document->find('div.wvg-single-gallery-image-container img')) {
            foreach ($slide as $item) {
                $res[] = $item->src;
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
        $res = $doc->first('#tab-description')->innerHtml();
        $res .= $doc->first('#tab-additional_information')->innerHtml();

        $res = str_replace("\t", '', $res);
        // удалить ссылки
        $res = preg_replace('/\s?<a[^>]*?>(.*?)<\/a>\s?/', '\\1', $res);
        // удалить h2
        $res = preg_replace('/\s?<h2[^>]*?>.*?<\/h2>\s?/si', ' ', $res);
        // удалить все атрибуты
//        $res = preg_replace("/(<[a-z]).*?(>)/i", '\\1\\2', $res);
        // удалить class
        $res = preg_replace('/\s?class=["][^"]*"\s?/i', ' ', $res);

        return $res;
    }

    /**
     * @param  string  $str
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttr(Doc $doc)
    {
        $attrList = [];
        $ar = $doc->find('li.active[title]');
        foreach ($ar as $element) {
            $name = explode(',', $element->title)[0];
            $value = explode(' ', $element->title)[2];
            $attrList[$name] = $value;
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
        $el = $doc->first("table th:contains({$attrName})");
        if ($el) {
            $res = $el->nextSibling('td')->text();
            $res = digit($res);
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
        $tmp = $document->first('script:contains(jsOffers)::text')->text();
        if (empty($tmp)) {
            return [];
        }
        $startPos = mb_strpos($tmp, "{'CONFIG'");
        $endPos = mb_strrpos($tmp, '}}');
        $tmp = mb_substr($tmp, $startPos, $endPos - $startPos + 2);
        $tmp = preg_replace("/'/", '"', $tmp);
        $tmp = str_replace("\t", '', $tmp);
        $tmp = html_entity_decode($tmp);
        $json = json_decode($tmp);

        $offers = $json->OFFERS;
        return $offers;
    }

    /**
     * @param  Doc  $document
     * @param $id
     * @return array|null
     * @throws InvalidSelectorException
     */
    private function getCurrentOffer(Doc $document, $id)
    {
        $offers = $this->getOffers($document);
        if ($offers) {
            foreach ($offers as $key => $offer) {
                if ($offer->ID == $id) {
                    return [$key => $offer];
                }
            }
        }

        return null;
    }

    private function getTopic(Doc $document)
    {
        $s = trim($document->first('#pagetitle')->text());
        return $s;
    }
}
