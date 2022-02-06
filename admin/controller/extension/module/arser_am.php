<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserAm extends Arser
{
    private const HOME = 'https://mobi-mebel.ru';

    /**
     * получим список ссылок на продукты (раскрываем группы)
     *
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(Doc $document): array
    {
        $url = [];
        $links = $document->find('a.jbimage-link');
        foreach ($links as $el) {
            $url[] = $el->attr('href');
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

        $document = (new Doc($link['link'], true));
        if (!$document) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Не удалось прочитать страницу');
            return;
        }

        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];
        // Получаем массив продуктов со страницы
        $product = $this->getProductInfo($document);
        if (empty($product)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $dopImg = $this->getDopImg($document);
        $data['description'] = $product['description'];
        $data['attr'] = $product['attr'];
        foreach ($product['aImgLink'] as $item) {
            $data['aImgLink'] = [$item['image']];
            if ($dopImg) {
                $data['aImgLink'] = array_merge($data['aImgLink'], $dopImg);
            }
            $data['topic'] = $product['topic'];
            if ($item['color'] !== 'none') {
                $data['topic'] .= ' ('.$item['color'].')';
            }
            $this->model_extension_module_arser_product->addProduct($data);
        }
        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
    }

    private function getUrlProduct($data)
    {
        $item_id = $data['item_id'];
        $element_id = $data['element_id'];

        $rand = rand(100, 999);
        $template_id = $data['template_id'];
        $identifier = $data['identifier'];
        $color = $data['color'];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://altaimebel22.ru/?option=com_zoo&controller=default&task=callelement&element={$element_id}&method=ajaxChangeVariant&item_id={$item_id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "rand={$rand}
            &option=com_zoo
            &tmpl=component
            &format=raw
            &args[template][{$template_id}]=full
            &args[values][{$identifier}][value]={$color}",

            CURLOPT_HTTPHEADER => array(
                'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:94.0) Gecko/20100101 Firefox/94.0',
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'cache-control: no-cache',
                'X-Requested-With: XMLHttpRequest',
                'Origin: https://altaimebel22.ru',
                'Connection: keep-alive',
                'Referer: https://altaimebel22.ru/shop/item/krovatkseniya-1600-2000',
                'Cookie: 1418c2a6a4505526d894d3dbcf888205=29328c0c2c7f0f81a2883c72c2a36e91; MCS_CITY_NAME=%D0%90%D0%BB%D1%82%D0%B0%D0%B9%D1%81%D0%BA%D0%B8%D0%B9+%D0%BA%D1%80%D0%B0%D0%B9; MCS_CITY_NAME=%D0%90%D0%BB%D1%82%D0%B0%D0%B9%D1%81%D0%BA%D0%B8%D0%B9+%D0%BA%D1%80%D0%B0%D0%B9',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'TE: trailers'
            ),
        ));

        $response = curl_exec($curl);
        /* Check for 404 (file not found). */
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode == 404) {
            $response = 404;
        }
        curl_close($curl);
        return $response;
    }


    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getProductInfo(Doc $document): array
    {
        $ar = [];
        $el = $document->first('h1.item-title');
        if (!$el) {
            return [];
        }
        $ar["topic"] = $this->getTopic($document);

        $ar['aImgLink'] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
        $ar['attr'] = $this->getAttr($ar['description']);
        if (!isset($ar['attr']['Размер спального места'])) { // если не нашли в описании, то ищем в названии
            $re = '/(\d+)[^\d]+(\d+)/';
            $pos = strpos($ar["topic"], '(');
            if ($pos !== false) {
                $str = substr($ar["topic"], $pos);

                if (preg_match_all($re, $str, $matches, PREG_SET_ORDER)) {
                    $ar['attr']['Размер спального места'] = $matches[0][1].'*'.$matches[0][2];
                }
            }
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
        $colorList = [];

        $variants = $this->getVariants($document);
        $item_id = $variants[0]['item_id'];
        $element_id = $variants[0]['element_id'];

        $el = $document->first('.jbprice-color');
        if (empty($el)) {
            $colorList[] = [
                'color' => 'none',
                'image' => $document->first('div.image a')->attr('href')
            ];
            return $colorList;
        }
//        $identifier = $el->attr('data-identifier');
        $elementList = $el->toDocument()->find('.jbzoo-colors input');
        foreach ($elementList as $el) {
            $color = $el->attr('value');
            $name = $el->attr('name');
            $aAttr = preg_split("/[\[\]]+/", $name);
            $template_id = $aAttr[0];
            $identifier = $aAttr[1];

            $res = $this->getUrlProduct(compact('item_id', 'element_id', 'identifier', 'color', 'template_id'));
            $json = json_decode($res);

            $colorList[] = [
                'color' => $color,
                'image' => $json->image->jselementfulllist2->popup
            ];
        }

        return $colorList;
    }

    /**
     * Возвращает наименование товара
     * @param  Doc  $doc
     * @return string
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $doc): string
    {
        return trim($doc->first('h1.item-title::text'));
    }

    /**
     * Возвращает ссылки на дополнительные картинки
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getDopImg(Doc $document): array
    {
        $res = [];
        $items = $document->find('div.middle-line div.image a');
        if (count($items) > 1) {
            foreach ($items as $key => $item) {
                if ($key>0) {
                    $res[] = $item->getAttribute('href');
                }
            }
        }

        return $res;
    }

    /**
     * Возвращает описание продукта
     * @param  Doc  $doc
     * @return string
     * @throws InvalidSelectorException
     */
    private function getDescription(Doc $doc): string
    {
        if ($el = $doc->first('.properties')) {
            $str = $el->html();
            $str = str_replace("'", '', $str);
            return $str;
        } else {
            return '';
        }

    }

    /**
     * Возвращает список атрибутов
     * @param  string  $str
     * @return array
     */
    private function getAttr(string $str):array
    {
        $attrList = [];
        $str = strip_tags($str);
        $str = htmlentities($str);
        $str = str_replace("&nbsp;", ' ', $str);

        if ($attr = $this->getAttribute($str, 'высота')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($str, 'ширина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($str, 'длина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($str, 'глубина')) {
            $attrList = array_merge($attrList, $attr);
        }
        $pos = mb_strpos($str, 'Размер спального места');
        if ($pos !== false) {
            $str1 = mb_substr($str, $pos);
            $attr1 = $this->getAttribute($str1, 'ширина');
            $attr2 = $this->getAttribute($str1, 'длина');
            $attr = [
                'Размер спального места' =>
                    $attr1['ширина'].'*'.$attr2['длина']
            ];
            $attrList = array_merge($attrList, $attr);
        }

        return $attrList;
    }

    /**
     * Возвращает атрибут
     * @param $str
     * @param $attrName
     * @return int[]|null
     */
    private function getAttribute($str, $attrName): ?array
    {
        $pos = mb_strpos($str, $attrName);
        if ($pos === false) {
            return null;
        }

        $str1 = mb_substr($str, $pos, 30);
        return [$attrName => (int)(explode(' ', $str1)[1])];
    }

    /**
     * Возвращает список вариантов продукта
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getVariants(Doc $document): array
    {
        $aTmp = [];
        $re = '/({[^}]+})}/m';
        $a = $document->find('div.price-block script');
        foreach ($a as $item) {
            $str = strip_tags($item);
            preg_match_all($re, $str, $matches, PREG_SET_ORDER);
            if (isset($matches[1][1])) {
                $obj = json_decode($matches[1][1]);
                if (!is_null($obj)) {
                    $aTmp[] = [
                        'item_id' => $obj->item_id,
                        'element_id' => $obj->element_id,
                    ];
                }
            } elseif (isset($matches[0][1])) {
                $obj = json_decode($matches[0][1]);
                $aTmp[] = [
                    'item_id' => $obj->item_id,
                    'element_id' => $obj->element_id,
                ];
            }
        }

        return $aTmp;
    }
}
