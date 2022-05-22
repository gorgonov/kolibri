<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM . 'helper/arser.php');

class ControllerExtensionModuleArserSp extends Arser
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
        loadDidom();
        $link = $linkGroup['link'] . '?count=1000';
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

    protected function getLinkProduct(Doc $document): array
    {
        // соберем ссылки на продукты
        $url = [];
        $html = $document->find('script[type=text/template]')[2]->innerHtml();
        $html = str_replace(['\t', '\n', '\a'], '', $html);
        $html = str_replace(['\"'], '"', $html);
        $html = str_replace(['\/'], '/', $html);
        $doc = new Doc($html);

        $links = $doc->find('div.product-image>a');
        foreach ($links as $el) {
            if (substr($el->href, 0, 4) !== 'http') {
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

        $ar = $document->find('script[type=text/template]');
        $html = end($ar)->innerHtml();
        $html = str_replace(['\t', '\n', '\a'], '', $html);
        $html = str_replace(['\"'], '"', $html);
        $html = str_replace(['\/'], '/', $html);
        $html = unicode_decode($html); // конвертируем из формата \u043e в кириллицу

        $doc = new Doc($html);

        // Получаем массив - информацию о продукте
        $items = $this->getProductInfo($doc);
        if (empty($items)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $data['topic'] = $this->getTopic($document);
        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];
        $data = array_merge($data, $items);
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

        $img = $this->getImg($document);
        $description = $this->getDescription($document);
        $attr = $this->getAttr($document);

        $ar = [
            'description' => $description,
            'aImgLink' => $img,
            'attr' => $attr,
        ];

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

        if ($attr = $this->getAttribute($doc, 'Ширина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Глубина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Высота')) {
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
        return trim($document->first('h1')->text());
    }
}
