<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM . 'helper/arser.php');

class ControllerExtensionModuleArserOl extends Arser
{
    private const HOME = 'https://olmeko.ru';

    /**
     * Получение ссылок на продукты (раскрываем группы)
     * @param Doc $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(Doc $document): array
    {
        $url = [];
        $doc = $document->first('div.bx_catalog_list_home');
        if (!$doc) {
            return $url;
        }

        $links = $doc->find('div.bx_catalog_item_title.short_title a');

        foreach ($links as $el) {
            $url[] = self::HOME . $el->attr('href');
        }

        return $url;
    }

    /**
     * Получаем информацию о продукте
     * @param array $link
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

        // попытки прочитать "хорошую страницу, игнорируя сообщения безопасности"
        // костыль - часто возвращает «плохую» страницу
        $sTmp = $document->first('h1.card__title1');

        while (!$sTmp) {
            // НЕУДАЧА. Ждем 5 секунд.
            sleep(5);
            $document = (new Doc($link['link'], true));
            if ($document) {
                $sTmp = $document->first('h1.card__title1');
            }
        }

        $arrayData = $this->getProductInfo($document);

        foreach ($arrayData as $item) {
            $data = $item;
            $data['link'] = $link['link'];
            $data['site_id'] = $link['site_id'];
            $data['category'] = $link['category_list'];
            $data['category1c'] = $link['category1c'];

            $this->model_extension_module_arser_product->addProduct($data);
        }

        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
    }

    /**
     * @param Doc $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getProductInfoMulty(Doc $document): array
    {
        $productName = $sTmp = $document->first('h1.card__title1')->text();
        $ar = [];

        // несколько продуктов на одной странице, отличаются цветом/материалом
        $products = $document->find('div.card__list1 div.card__col1');

        foreach ($products as $product) {
            $topic = $productName . ' (' . $product->attr('data-color') . ')';
//            $sku = $product->attr('data-kodv1c');
//            echo 'pagetype: ' . $product->attr('data-pagetype');
            $id_offer = $product->attr('data-offer');

            $sTmp = "div.card-info__list1[data-offer_id='{$id_offer}']";
            $specifications = $document->first($sTmp);
            if (!empty($specifications)) {
                $attr = $this->getAttributes($specifications);
            }

            $popup = $document->first('div.card-info-item1__text1-popup');
            while (!empty($popup)) {
                $popup->remove();
                $popup = $document->first('div.card-info-item1__text1-popup');
            }
            $popup = $document->first('div.card-info-item1__text1-popup-wrap');
            while (!empty($popup)) {
                $popup->remove();
                $popup = $document->first('div.card-info-item1__text1-popup-wrap');
            }

            $description = $document->first($sTmp)->html();
            $description = $this->getDescription($id_offer);

            $sTmp = "a.card__img-big.ibg.fancybox[data-fancybox='gallery{$id_offer}']";

            $aImgLink = [];  // список картинок для карусели
            $aImg = $document->find($sTmp);

            foreach ($aImg as $el) {
                $src = $el->attr('href');
                if (substr($src, 0, 1) == '/') {
                    $src = self::HOME . $src;
                }
                if ($src <> '') {
                    $aImgLink [] = $src;
                }
            }

            $aImgLink = array_unique($aImgLink); // удалим дубли

            $ar[] = compact('topic', 'id_offer', 'description', 'aImgLink', 'attr', 'description');
        }

        return $ar;
    }

    private function getProductInfo(Doc $document): array
    {
        $productName = $document->first('h1.card__title1')->text();
//        вариант 1 (короткий)
        $element = $document->first('div.card-description1:not([class*="hidden"])');

//        вариант 2 (длинный)
//        $nodes = $document->find('div.card-description1');
//        foreach ($nodes as $node) {
//            $class = $node->getAttribute('class');
//            if (strpos($class, 'hidden') == false) {
//                $id_offer = $node->getAttribute('data-offer_id');
//                break;
//            }
//        }

        if ($element) {
            $id_offer = $element->getAttribute('data-offer_id');
        } else {
            echo 'id_offer не найден';
            die();
        }

        if (!isset($id_offer)) {
            echo 'id_offer не найден';
            die();
        }
        $sku = null;
        $element = $document->first("div.card__col1[data-offer={$id_offer}]");
        if ($element) {
            $topic = $productName . ' (' . $element->getAttribute('data-color') . ')';
            $sku = $element->getAttribute('data-kod');
        }

        $description = $this->getDescription($id_offer);
        $specifications = $document->first(".card-info__list1[data-offer_id={$id_offer}]");

//        $element = $document->first('div.card__text1');
//        if ($element) {
//            $sku = $element->text();
//            $sku = 'П' . preg_replace('/[^0-9]/', '', $sku);
//        } else {
//            $sku = null;
//        }

        $attr = [];
        if (!empty($specifications)) {
            $attr = $this->getAttributes($specifications);
        }

        $sTmp = "a.card__img-big.ibg.fancybox[data-fancybox='gallery{$id_offer}']";

        $aImgLink = [];  // список картинок для карусели
        $aImg = $document->find($sTmp);

        foreach ($aImg as $el) {
            $src = $el->attr('href');
            if (substr($src, 0, 1) == '/') {
                $src = self::HOME . $src;
            }
            if ($src <> '') {
                $aImgLink [] = $src;
            }
        }

        $aImgLink = array_unique($aImgLink); // удалим дубли

        $ar[] = compact('topic', 'id_offer', 'description', 'aImgLink', 'attr', 'sku', 'description');

        return $ar;
    }

    /**
     * @param Doc $doc
     * @param string $attrName
     * @return array|null
     * @throws InvalidSelectorException
     */
    private function getAttribute($doc, $attrName): ?array
    {
        $el = $doc->first('div.card-info-item1__text1:contains("' . $attrName . '")');
        if ($el) {
            return [
                $attrName => digit($el->nextSibling('div')->text())
            ];
        }

        return null;
    }

    /**
     * @param $doc
     * @return array
     * @throws InvalidSelectorException
     */
    private function getAttributes($doc): array
    {
        $attrs = [];

        if ($attr = $this->getAttribute($doc, 'Высота')) {
            $attrs = $attrs + $attr;
        }

        if ($attr = $this->getAttribute($doc, 'Ширина')) {
            $attrs = $attrs + $attr;
        }
        if ($attr = $this->getAttribute($doc, 'Глубина')) {
            $attrs = $attrs + $attr;
        }
//        $el = $doc->first('span:contains("Материал:")');
//        if ($el) {
//            $sTmp = trim($el->text());
//            $aTmp = explode(":", $sTmp);
//            $attrs["Материал"] = trim($aTmp[count($aTmp) - 1]);
//        }

        return $attrs;
    }

    private function getDescription($id_offer)
    {
        $html = $this->curlRequest($id_offer);

        return $this->removeLinks($html);
    }

    private function curlRequest($id_offer)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://olmeko.ru/ajax/get_offer_description.php?id=' . $id_offer,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/110.0',
                'Accept: */*',
                'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
                'Accept-Encoding: gzip, deflate, br',
                'X-Requested-With: XMLHttpRequest',
                'Connection: keep-alive',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    private function removeLinks(string $html): string
    {
        $doc = new Doc($html);
        $a = $doc->first('a');

        while (!empty($a)) {
            $text = $a->text();
            $doc->find('a')[0]->replace(new Element('p', $text));
            $a = $doc->first('a');
        }

        return $doc->html();
    }
}
