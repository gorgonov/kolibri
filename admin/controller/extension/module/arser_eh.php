<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException as InvalidSelectorExceptionAlias;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserEh extends Arser
{
    private const HOME = 'https://esandwich.ru';

    /**
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     * @throws InvalidSelectorExceptionAlias
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link'].'?SHOWALL_1=1'; //показать все товары

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
     * @throws InvalidSelectorExceptionAlias
     */
    protected function getLinkProduct(Doc $document): array
    {
        $url = [];
        $links = $document->find('a.one_img_wrapper');
        foreach ($links as $el) {
            $url[] = self::HOME . $el->href;
        }

        $url = array_unique($url);

        return $url;
    }

    /**
     * Получаем информацию о продукте
     * @param  array  $link
     * @throws InvalidSelectorExceptionAlias
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
     * @throws InvalidSelectorExceptionAlias
     */
    private function getProductInfo(Doc $document): array
    {
        $ar = [];
        $ar['topic'] = $this->getTopic($document);
        $ar['sku'] = $this->getSku($document);
        $ar['description'] = $this->getDescription($document);
        $ar['aImgLink'] = $this->getImg($document);
        $ar['attr'] = $this->getAttr($ar['description']);

        return $ar;
    }

    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorExceptionAlias
     */
    private function getImg(Doc $document): array
    {
        $res = [];

        $aImg = $document->find('div.preview_con a'); // список картинок для карусели
        foreach ($aImg as $el) {
            $href = $this->getImageSrc($el->attr('href'));
            if ($href <> '') {
                $res[] = $href;
            }
        }

        return $res;
    }

    /**
     * @param $str
     * @return string
     */
    private function getImageSrc($str): string
    {
        $str = str_replace("bmp.jpg", "bmp", $str); // предобработка при двух расширениях

        $re = '/p\d+_[\d\w\.]+/';
        $re2 = '/(\/upload\/).+(iblock\/[\d\w]*\/).+_([\d\w]+\.bmp)/';
        $re1 = '/(\/upload\/).+(iblock\/[\d\w]*\/).+_([\d\w]+\.jpg)/';

        if (preg_match($re, $str, $matches)) {
            $ret = 'https://esandwich.ru/upload/iblock/' . $matches[0];
        } elseif (preg_match($re2, $str, $matches)) {
            $ret = 'https://esandwich.ru' . $matches[1] . $matches[2] . $matches[3];
        } elseif (preg_match($re1, $str, $matches)) {
            $ret = 'https://esandwich.ru' . $matches[1] . $matches[2] . $matches[3];
        } else {
            $ret = '';
        }

        return htmlspecialchars($ret);
    }

    /**
     * @param  Doc  $doc
     * @return string
     * @throws InvalidSelectorExceptionAlias
     */
    private function getDescription(Doc $doc): string
    {
        $res = '';
        if ($el = $doc->first('div[itemprop=description]')) {
            $res .= $el->html();
        }

        if ($el = $doc->first('table#product_teh')) {
            $res .= $el->html();
        }

        // удалим комментарии из html
        $res = removeHtmlComments($res);
        // удалить ссылки из описания
        $res = removeHtmlLink($res);
        // удалим плохие символы из текста
        $res = str_replace("'", '', $res);

        return $res;
    }

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
     * @param $attrName
     * @param  false  $is_digit
     * @return array|false
     * @throws InvalidSelectorExceptionAlias
     */
    private function getAttribute(Doc $doc, $attrName, bool $is_digit = false)
    {
        $el = $doc->first("td:contains({$attrName})");
        if ($el) {
            $res = $el->nextSibling('td')->text();
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
     * @throws InvalidSelectorExceptionAlias
     */
    private function getTopic(Doc $document): string
    {
        return $document->first('h1::text');
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorExceptionAlias
     */
    private function getSku(Doc $document): string
    {
        return $document->first('span.articule__value::text');
    }
}
