<?php

use DiDom\Document;

class ControllerExtensionModuleArserBm extends Controller
{
    private $error = array();

    public function openGroup()
    {
        $siteId = $this->request->get['site_id'];
        $this->load->model('extension/module/arser_link');
        $linksGroup = $this->model_extension_module_arser_link->getGroupLink($siteId, 1);

        if (count($linksGroup) == 0) { // все группы раскрыты
            $link_product_count = count($this->model_extension_module_arser_link->getGroupLink($siteId, 0));
            $json = [
                'link_group_count' => 0,
                'link_product_count' => $link_product_count,
                'status' => 'finish',
            ];
            echo json_encode($json);
            return;
        }

        $this->parseGroup($linksGroup[0]);
        $link_group_count = count($this->model_extension_module_arser_link->getGroupLink($siteId, 1));
        $link_product_count = count($this->model_extension_module_arser_link->getGroupLink($siteId, 0));

        $json = [
            'link_group_count' => $link_group_count,
            'link_product_count' => $link_product_count,
            'status' => 'go',
        ];
        echo json_encode($json);
        return;
    }

    public function parseNextProduct()
    {
        $siteId = $this->request->get['site_id'];
        $this->load->model('extension/module/arser_link');
        $productLinks = $this->model_extension_module_arser_link->getNextLink($siteId);

        if (count($productLinks) == 0) { // все товары парсены
            $json = [
                'link_count' => count($productLinks),
                'link_product_count' => count($productLinks),
                'status' => 'finish',
            ];
            echo json_encode($json);
            return;
        }

        $this->parseProduct($productLinks[0]);
        $link_count = $this->model_extension_module_arser_link->getLinkCount($siteId);

        $json = [
            'link_parse' => $productLinks[0],
            'link_count' => $link_count['all'],
            'link_product_count' => ($link_count['ok'] ?? 0) + ($link_count['bad'] ?? 0),
            'status' => 'go',
        ];
        echo json_encode($json);
        return;
    }

    /**
     * @param array $linkGroup
     */
    private function parseGroup(array $linkGroup)
    {
        try {
            $this->loadDidom();
            // 1 страница
            $document = new DiDom\Document($linkGroup['link'], true);
            $linkProduct = $this->getLinkProduct($document);

            // другие страницы
            $links = $document->find('a.page.larger');
            foreach ($links as $el) {
                $url = $el->attr('href');
                $document = new DiDom\Document($url, true);
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

        return;
    }

    private function parseProduct(array $link)
    {
        try {
//            error_reporting(0);
            $this->loadDidom();
            $document = @(new DiDom\Document($link['link'], true));
            if (!$document) {
                $this->model_extension_module_arser_link->setStatus($link['id'], 'bad',
                    'Не удалось прочитать страницу');
                return;
            }

            $data = $this->getProductInfo($document);
            $data['link'] = $link['link'];
            $data['site_id'] = $link['site_id'];
            $data['category'] = $link['category_list'];
            $data['category1c'] = $link['category1c'];

            $this->load->model('extension/module/arser_product');
            $this->model_extension_module_arser_product->addProduct($data);
            $this->load->model('extension/module/arser_link');
            $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
        } catch (Exception $e) {
            // установить статус у текущей групповой ссылки 'bad'
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', $e->getMessage());
            return;
        }

        return;
    }

    private function loadDidom()
    {
        $cwd = getcwd();
        chdir(DIR_SYSTEM . 'library/DiDom');
        require_once('ClassAttribute.php');
        require_once('Document.php');
        require_once('Node.php');
        require_once('Element.php');
        require_once('Encoder.php');
        require_once('Errors.php');
        require_once('Query.php');
        require_once('StyleAttribute.php');
        require_once('Exceptions/InvalidSelectorException.php');
        chdir($cwd);
    }

    /**
     * @param \DiDom\Document $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getLinkProduct(DiDom\Document $document): array
    {
        $url = [];
        $links = $document->find('a.woocommerce-LoopProduct-link.woocommerce-loop-product__link');
        foreach ($links as $el) {
            $url[] = $el->attr('href');
        }

        return $url;
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
     * @param Document $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getProductInfo(DiDom\Document $document): array
    {
        $ar = [];
        $tmp = trim($document->first('h1.product_title')->text()); // Заголовок товара c артикулом
        $arTmp = preg_split("/[()]+/", $tmp);
        $ar["topic"] = $arTmp[0]; // Заголовок товара
        $ar["sku"] = (int)$arTmp[1]; // Артикул
        // картинки
        $aImgLink = [];
        $arTmp = $document->find('#owl-products-images img');
        foreach ($arTmp as $el) {
            $aImgLink[] = $el->attr('data-large_image'); // оч. большие картинки
//            $aImgLink[] = $el->attr('data-medium');
        }
        $ar["aImgLink"] = $aImgLink;

        $product_id = $document->first('#wcull-content')->attr('data-product_id');
        $ar['description'] = '';
        $tmp = $this->getTab($product_id, 'tab-characteristics');
        if ($tmp) {
            $ar['attr'] = $this->getAttributes($tmp);
            $ar['description'] .= $this->trimScript($tmp);
        }

        $tmp = $this->getTab($product_id, 'tab-description');
        if ($tmp) {
            $ar['description'] .= $this->trimScript($tmp);
        }

        $tmp = $document->first('a.video');
        if ($tmp) {
            $ar['description'] .= '<iframe frameborder="0" src="' . $tmp->attr('href') . '" width="640" height="360" class="note-video-clip"></iframe>';
        }

        // опции
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
     * @param string $tmp
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getAttributes(string $tmp): array
    {
        $doc = (new DiDom\Document($tmp));
        $aTmp = [];

        $el = $doc->first('div:contains("Размер")');

        if ($el) {
            $str = $el->nextSibling('div')->text();
            $aTmp = $this->getSize($str);
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

        $doc = (new DiDom\Document($tmp));
        $aTmp = [];

        $aDop = $doc->find('li.type-product');
        foreach ($aDop as $dop) {
            $el = $dop->toDocument();
            $name = $el->first('div.h2.woocommerce-loop-product__title')->text();
            $price = $this->normalSum($el->first('bdi')->text());

            $aTmp[] = ['name' => $name, 'value' => $price];
        }

        return $aTmp;
    }

    /**
     * @param $sum - строка, содержащая цифры и текст
     * @return int - возвращает целое число, состоящее из цифр $sum
     */
    private function normalSum($sum)
    {
        $result = (int)preg_replace("/[^0-9]/", '', $sum);
        return $result;
    }

    private function getSize(string $str)
    {
        $aTmp = [];
        $size = [
            'Д' => 'Длина',
            'Ш' => 'Ширина',
            'Г' => 'Глубина',
            'В' => 'Высота',
        ];
        preg_match_all('!\d+!', $str, $numbers);
        preg_match_all('![ДШВГ]!u', $str, $sizeName);

        foreach ($sizeName[0] as $key => $item) {
            $aTmp[$size[$item]] = $numbers[0][$key] . '0';
        }

        return $aTmp;
    }
}
