<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserBm extends Arser
{
    private const HOME = 'https://belmarco.ru';

    /**
     * @param array $linkGroup
     */
    protected function parseGroup(array $linkGroup)
    {
        try {
            loadDidom();
            // 1 страница
            $document = new Doc($linkGroup['link'], true);
            $linkProduct = $this->getLinkProduct($document);

            // другие страницы
            $links = $document->find('a.page.larger');
            foreach ($links as $el) {
                $url = $el->attr('href');
                $document = new Doc($url, true);
                $linkProduct = array_merge($linkProduct, $this->getLinkProduct($document));
            }
        } catch (Exception $e) {
//            echo $e->getMessage();
            // установить статус у текущей групповой ссылки 'bad'
            $this->model_extension_module_arser_link->setStatus($linkGroup['id'], 'bad', $e->getMessage());
            return;
        }

        // добавим линки на продукты и удалим группу
        $data = [];
        foreach ($linkProduct as $item) {
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
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(Doc $document): array
    {
        $url = [];
        $links = $document->find('a.woocommerce-LoopProduct-link.woocommerce-loop-product__link');
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
        loadDidom();
        $document = (new Doc($link['link'], true));
        if (!$document) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Не удалось прочитать страницу');
            return;
        }

        $data = $this->getProductInfo($document);
        if (empty($data)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }

        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];

        $this->load->model('extension/module/arser_product');
        $this->model_extension_module_arser_product->addProduct($data);
        $this->load->model('extension/module/arser_link');
        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
    }

    /**
     * @param $product_id
     * @param $tab
     * @return bool|string
     */
    private function getTab($product_id, $tab)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://belmarco.ru/wp-admin/admin-ajax.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'action=wctll_ajax&tab_id=%23' . $tab . '&product_id=' . $product_id . '&cpage=11',
            CURLOPT_HTTPHEADER => array(
                'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:94.0) Gecko/20100101 Firefox/94.0',
                'Accept: */*',
                'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
                'Referer: https://belmarco.ru/shop/krovati/krovati-mashiny/bondmobil-belyj/',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With: XMLHttpRequest',
                'Origin: https://belmarco.ru',
                'Connection: keep-alive',
                'Cookie: wc_geo_city=%D0%9A%D1%80%D0%B0%D1%81%D0%BD%D0%BE%D0%B4%D0%B0%D1%80; r7k12_ci=365902925531325.1637406395000; r7k12_si=128981357; r7k12_source=%D0%94%D1%80%D1%83%D0%B3%D0%BE%D0%B9; wt_geo_data=%7B%22country%22%3Anull%2C%22district%22%3Anull%2C%22region%22%3Anull%2C%22city%22%3Anull%2C%22lat%22%3Anull%2C%22lng%22%3Anull%7D; wt_geo_data=%7B%22country%22%3Anull%2C%22district%22%3Anull%2C%22region%22%3Anull%2C%22city%22%3Anull%2C%22lat%22%3Anull%2C%22lng%22%3Anull%7D',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'Cache-Control: max-age=0',
                'TE: trailers'
            ),
        ));

        $response = curl_exec($curl);

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
        $tmp = trim($document->first('h1.product_title')->text()); // Заголовок товара c артикулом
        $arTmp = preg_split("/[()]+/", $tmp);
        $ar["topic"] = $arTmp[0]; // Заголовок товара
        $ar["sku"] = (int)$arTmp[1]; // Артикул
        $ar["aImgLink"] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
        $ar['attr'] = $this->getAttributes($document); // todo

        // опции
        $product_id = $this->getProductId($document);
        $tmp = $this->getTab($product_id, 'tab-accessories');
        $ar['product_option'] = $this->getOptions($tmp);

        return $ar;
    }

    /**
     * @param string $str
     * @return string
     */
    private function trimScript(string $str): string
    {
        return preg_split("/<script>/", $str)[0];
    }

    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getAttributes(Doc $document): array
    {
        $product_id = $this->getProductId($document);
        $tmp = $this->getTab($product_id, 'tab-characteristics');

        if (!$tmp) {
            return [];
        }

        $doc = (new Doc($tmp));
        $aTmp = [];

        $el = $doc->first('div:contains("Размер")');

        if ($el) {
            $str = $el->nextSibling('div')->text();
            $aTmp = getSize($str);
        }

        $el = $doc->first('div:contains("Размер спального места:")');
        if (isset($el)) {
            preg_match_all('!\d+!', trim($el->nextSibling('div')->text()), $numbers);
            $aTmp["Размер спального места"] = $numbers[0][1] . '0*' . $numbers[0][0] . '0';
        } else {
            $el1 = $doc->first('div:contains("Длина спального места")');
            $el2 = $doc->first('div:contains("Ширина спального места")');
            if ($el1 && $el2) {
                $aTmp["Размер спального места"] =
                    trim($el1->nextSibling('div')->text()) . '0*' . trim($el2->nextSibling('div')->text()) . '0';
            }
        }

        $el = $doc->first('div:contains("Цвет:")');
        if ($el) {
            $aTmp["Цвет"] = trim($el->nextSibling('div')->text());
        }

        $el = $doc->first('div:contains("Материал:")');
        if ($el) {
            $aTmp["Материал корпуса"] = trim($el->nextSibling('div')->text());
        }

        return $aTmp;
    }

    private function getOptions(string $tmp): array
    {
        if (empty($tmp)) {
            return [];
        }

        $doc = (new Doc($tmp));
        $aTmp = [];

        $aDop = $doc->find('li.type-product');
        foreach ($aDop as $dop) {
            $el = $dop->toDocument();
            $name = $el->first('div.h2.woocommerce-loop-product__title')->text();
            $price = digit($el->first('bdi')->text());

            $aTmp[] = ['name' => $name, 'value' => $price];
        }

        return $aTmp;
    }

    /**
     * @param $document
     * @return array
     */
    private function getImg($document): array
    {
        $res = [];
        $arTmp = $document->find('#owl-products-images img');
        foreach ($arTmp as $el) {
            $res[] = $el->attr('data-large_image'); // оч. большие картинки
//            $res[] = $el->attr('data-medium');
        }
        return $res;
    }

    /**
     * @param $document
     * @return string
     */
    private function getDescription($document): string
    {
        $description = '';
        $product_id = $this->getProductId($document);

        if ($tmp = $this->getTab($product_id, 'tab-characteristics')) {
            $description .= $this->trimScript($tmp);
        }
        if ($tmp = $this->getTab($product_id, 'tab-description')) {
            $description .= $this->trimScript($tmp);
        }
        if ($tmp = $document->first('a.video')) {
            $description .= '<iframe frameborder="0" src="'.$tmp->attr('href').'" width="640" height="360" class="note-video-clip"></iframe>';
        }

        return $description;
    }

    /**
     * @param $document
     * @return mixed
     */
    private function getProductId($document)
    {
        return $document->first('#wcull-content')->attr('data-product_id');
    }
}
