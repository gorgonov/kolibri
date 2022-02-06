<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserGnt extends Arser
{
    private const HOME = 'http://pnz.gorizontmebel.ru';

    /**
     * Получение ссылок на продукты (раскрываем группы)
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(DiDom\Document $document): array
    {
        $url = [];
        $str = $document->first('ui-products-list')->html();
        $re = '/"url":"(\/[^"]+)"/m';
        preg_match_all($re, $str, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $url[] = self::HOME.$match[1];
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

        // Получаем массив продуктов со страницы
        $products = $this->getProductInfo($document);
        if (empty($products)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];
        $topic = $products['topic'];
        $data['description'] = $products['description'];
        $data['attr'] = $products['attr'];
        foreach ($products['variant'] as $key => $product) {
            $data['sku'] = $key;
            $data['topic'] = $topic
                .(empty($product['title']) ? '' : '('.$product['title'].')');
            $data['aImgLink'] = $product['img'];
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

        $el = json_decode($document->first('ui-product')->attr(':product-json'));
        $ar['variant'] = $this->getVariant($el);
        $ar['description'] = $el->short_description;

        $ar['attr'] = $this->getAttr($el->short_description);
        $el = $document->first('ui-product-share');
        $ar['description'] .= $el->attr('description');
        $ar['description'] = str_replace("'", "\'", $ar['description']);

        return $ar;
    }

    /**
     * @param $el
     * @return array
     */
    private function getImg($el): array
    {
        $res = [];
        foreach ($el->images as $item) {
            $res[$item->id] = $item->original_url;
        }

        return $res;
    }

    /**
     * @param $el
     * @return array
     */
    private function getVariant($el): array
    {
        $imgList = $this->getImg($el);

        $res = [];
        foreach ($el->variants as $item) {
            $img = [];
            foreach ($item->image_ids as $image_id) {
                if (isset($imgList[$image_id])) {
                    $img[] = $imgList[$image_id];
                    unset($imgList[$image_id]);
                }
            }
            $res[$item->sku] = [
                'title' => $item->title,
                'img' => $img,
                'weight' => $item->weight,
            ];
        }

        foreach ($res as $key => $variant) {
            $res[$key]['img'] = array_merge($variant['img'], $imgList);
        }

        return $res;
    }

    /**
     * @param  string|null  $short_description
     * @return array
     */
   private function getAttr(?string $short_description): array
   {
        $aTmp = [];
        if (!empty($short_description)) {
            $str = strip_tags($short_description);
            preg_match_all('!\d+!', $str, $numbers);
            @$aTmp["Ширина"] = $numbers[0][0];
            @$aTmp["Глубина"] = $numbers[0][1];
            @$aTmp["Высота"] = $numbers[0][2];
        }

        return $aTmp;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document): string
    {
        return $document->first('h1')->text();
    }
}
