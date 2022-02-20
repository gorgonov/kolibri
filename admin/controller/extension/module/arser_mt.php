<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM . 'helper/arser.php');

class ControllerExtensionModuleArserMt extends Arser
{
    private const HOME = 'https://www.metta.ru';

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
        die(); // групп быть не должно

        // соберем ссылки на продукты
        $url = [];
        $links = $document->find('div.proposal-item__info.proposal-item__info_catalog a');
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

        // Получаем массив - информацию о продукте
        $parts = parse_url($url);
        parse_str($parts['query'], $query);
        $sku = $query['offer'];
        $items = $this->getProductInfo($document, $sku);
        if (empty($items)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $data['sku'] = $sku;
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
    private function getProductInfo(Doc $document, $id): array
    {
        $ar = [];

        $offer = $this->getCurrentOffer($document, $id);
        $key = array_key_first($offer);
        if (!$offer) {
            return [];
        }

        $img = [];
        foreach ($offer[$key]->SLIDER as $item) {
            $img[] = self::HOME . html_entity_decode($item->SRC);
        }

        $sizeImg = $document->first('#dimensions img');
        if ($sizeImg) {
            $img[] = self::HOME . $sizeImg->attr('src');
        }

        $treeArray = (array)$offer[$key]->TREE;
//        $propName = array_key_first($treeArray);
//        $id = $treeArray[$propName];
        $id = reset($treeArray);

        $topic = $offer[$key]->NAME
            . ' '
            . $this->getColor($document, $id);

        $description = $this->getDescription($document);

        $attr = $this->getAttr($document);

        $ar = [
            'description' => $description,
            'topic' => $topic,
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
        $res = $doc->first('#desc')->innerHtml() . $doc->first('#props')->innerHtml();
        $res = preg_replace('/\s?<img[^>]*?>.*?>\s?/si', ' ', $res);

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

        if ($attr = $this->getAttribute($doc, 'Максимальная нагрузка')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал спинки')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал сиденья')) {
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
        $el = $doc->first("#props table td:contains({$attrName})");
        if ($el) {
            $res = $el->nextSibling('td')->text();
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

    private function getColor(Doc $document, $id)
    {
        $res = '';
        $tmp = $document->first("input[value={$id}]");
        if ($tmp) {
            $res = $tmp->nextSibling('label')->getAttribute('data-tooltip');
        }

        return $res;
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
}
