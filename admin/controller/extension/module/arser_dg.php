<?php

use DiDom\Document;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserDg extends Controller
{
    private const HOME = 'https://xn--80ahajdf1e.xn--p1ai';

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

    private function getGroupPage($category, $page)
    {
        $curl = curl_init();
        $postFields = [
            'action' => 'product_action',
            'context' => 'frontend',
            'pagination' => $page,
            'dataQuery' => '{"product_cat":"'.$category.'"}',
            'dataTax' => '{"relation":"AND","0":{"taxonomy":"product_visibility","field":"term_taxonomy_id","terms":[7],"operator":"NOT IN"}}',
        ];

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://xn--80ahajdf1e.xn--p1ai/wp-admin/admin-ajax.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_HTTPHEADER => array(
                'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:95.0) Gecko/20100101 Firefox/95.0',
                'Accept: */*',
                'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
                'Accept-Encoding: gzip, deflate, br',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: https://xn--80ahajdf1e.xn--p1ai',
                'Alt-Used: xn--80ahajdf1e.xn--p1ai',
                'Connection: keep-alive',
                'Referer: https://xn--80ahajdf1e.xn--p1ai/product-category/mojki-kamennye/?all',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'TE: trailers'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }

    /**
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     */
    private function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link']; //показать все товары

        // получим имя раздела из ссылки
        $urlArray = array_filter(explode('/', $link));
        $category = end($urlArray);

        $page = 1;

        while ($str = $this->getGroupPage($category, $page)) {
            $document = new DiDom\Document($str);
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
                $this->model_extension_module_arser_link->addLinks($data);
            }
            $this->model_extension_module_arser_link->deleteLinks([$linkGroup['id']]);
            $page++;
        }

        return;
    }

    /**
     * Парсим следующий товар (arser_link.status='new'), добавляем его в arser_product
     * @throws Exception
     */
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

        $status = 'go';
        $link = $productLinks[0];
        try {
            $this->parseProduct($link);
        } catch (Exception $exception) {
            $status =
                'siteId='.$siteId
                .'; link='.print_r($link, true)
                .'; error='.$exception->getMessage();
        }
        $link_count = $this->model_extension_module_arser_link->getLinkCount($siteId);

        $json = [
            'link' => $productLinks[0],
            'link_count' => $link_count['all'],
            'link_product_count' => ($link_count['ok'] ?? 0) + ($link_count['bad'] ?? 0),
            'status' => $status,
        ];
        echo json_encode($json);
        return;
    }

    private function getUrl($link)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $link,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Cookie: f261601ce57a4ee01f9efde6727e03e5=srlhf2krg7d51dejkdmkkhcsd1'
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
     * Получаем информацию о продукте
     * @param  array  $link
     */
    private function parseProduct(array $link)
    {
        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

        loadDidom();

        $result = $this->getUrl($link['link']);

        if ($result == 404) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Страница не существует');
            return;
        }

        $document = (new DiDom\Document($result));
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
     * Получение ссылок на продукты (раскрываем группы)
     * @param  Document  $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getLinkProduct(DiDom\Document $document): array
    {
        // соберем ссылки на продукты
        $url = [];
        $links = $document->find('ul.products a.woocommerce-LoopProduct-link');
        foreach ($links as $el) {
            $url[] = $this->normalUrl($el->href);
        }

        return $url;
    }


    /**
     * @param  Document  $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getProductInfo(DiDom\Document $document): array
    {
        $ar = [];
        $ar['topic'] = $this->getTopic($document);
        $ar['price'] = $this->getPrice($document);
        $ar['sku'] = $this->getSku($document);
        $ar['description'] = $this->getDescription($document);
        $ar['aImgLink'] = $this->getImg($document);
        $ar['attr'] = $this->getAttr($ar['description']);

        return $ar;
    }

    /**
     * @param  \DiDom\Element  $element
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getImg(DiDom\Document $document): array
    {
        $res = [];

        if ($slide = $document->find('.product img.product-thumbnails')) {
            foreach ($slide as $item) {
                $res[] = $this->normalUrl($item->src);
            }
        } elseif ($slide = $document->find('.woocommerce-product-gallery__image a')) {
            foreach ($slide as $item) {
                $res[] = $this->normalUrl($item->href);
            }
        } elseif ($slide = $document->find('.woocommerce-product-gallery__image--placeholder img')) {
            foreach ($slide as $item) {
                $res[] = $this->normalUrl($item->src);
            }
        }


//        if ($el = $document->first('.jshop_img_description')) {
//            $dopImg = $el->find('p img');
//            foreach ($dopImg as $item) {
//                $res[] = self::HOME.$item->src;
//            }
//        }

        return $res;
    }

    private
    function getDescription(
        DiDom\Document $doc
    ): string {
        $res = '';
//        if ($el = $doc->first('div.meta-product')) {
//            $res .= $el->html();
//        }

        if ($el = $doc->first('table.woocommerce-product-attributes.shop_attributes')) {
            // удалим лишнее
            if ($excess = $el->firstInDocument('th:contains(sku)')) {
                $excess->closest('tr')->remove();
            };

            $res .= $el->html();
        }

        return $res;
    }

    private function getAttr(string $str)
    {
        return [];
    }

    /**
     * @param  Document  $doc
     * @param $attrName
     * @param  false  $is_digit
     * @return array|false
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private
    function getAttribute(
        DiDom\Document $doc,
        $attrName,
        $is_digit = false
    ) {
        $el = $doc->first("#tab1 li:contains({$attrName})");
        if ($el) {
            $res = str_replace($attrName, '', $el->text());
            if ($is_digit) {
                $res = preg_replace('/[^0-9]/', '', $res);
            }
            return [$attrName => $res];
        }

        return false;
    }

    /**
     * собираем ссылки на разные цвета продукта
     * @param  string|null  $href
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getDopLinks(?string $href): array
    {
        $link = [];
        $doc = (new DiDom\Document($href, true));
        $elements = $doc->find('div.kind-image a');
        foreach ($elements as $element) {
            $link[] = self::HOME.$element->href;
        }

        return $link;
    }

    private function getTopic(Document $document)
    {
        return $document->first('h1.product_title::text');
    }

    private function getPrice(Document $document)
    {
        $res = $document->first('.woocommerce-Price-amount::text');
        $price = preg_replace('/[^0-9]/', '', $res);
        return $price;
    }

    private function getSku(Document $document)
    {
        return $document->first('span.sku::text');
    }

    /**
     * удаляет комментарии из html-разметки
     * @param $html
     * @return array|string|string[]|null
     */
    private function removeHtmlComments($html)
    {
        $res = $html;
        $startPos = mb_strpos($html, '<!--');
        $endPos = mb_strpos($html, '-->');
        while ($startPos !== false && $endPos !== false) {
            $res = mb_substr($res, 0, $startPos - 1).mb_substr($html, $endPos + 3);
            $startPos = mb_strpos($res, '<!--');
            $endPos = mb_strpos($res, '-->');
        }

//        $res = preg_replace('/<!--(.*?)-->/', '', $html);
        return $res;
    }

    private function normalUrl(string $url)
    {
        return str_replace('https://тддизаж.рф', self::HOME, $url);
    }
}
