<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserDg extends Arser
{
    private const HOME = 'https://xn--80ahajdf1e.xn--p1ai';

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
     * @throws InvalidSelectorException
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link']; //показать все товары

        // получим имя раздела из ссылки
        $urlArray = array_filter(explode('/', $link));
        $category = end($urlArray);

        $page = 1;

        while ($str = $this->getGroupPage($category, $page)) {
            $document = new Doc($str);
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
    }

    /**
     * Получение ссылок на продукты (раскрываем группы)
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(Doc $document): array
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
     * @throws InvalidSelectorException
     */
    private function getProductInfo(Doc $document): array
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
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getImg(Doc $document): array
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

    /**
     * @throws InvalidSelectorException
     */
    private
    function getDescription(
        Doc $doc
    ): string {
        $res = '';
//        if ($el = $doc->first('div.meta-product')) {
//            $res .= $el->html();
//        }

        if ($el = $doc->first('table.woocommerce-product-attributes.shop_attributes')) {
            // удалим лишнее
            if ($excess = $el->firstInDocument('th:contains(sku)')) {
                $excess->closest('tr')->remove();
            }

            $res .= $el->html();
        }

        return $res;
    }

    /**
     * @param  string  $str
     * @return array
     */
    private function getAttr(string $str): array
    {
        return [];
    }

    /**
     * @param  Doc  $document
     * @return string|null
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document): ?string
    {
        return $document->first('h1.product_title::text');
    }

    /**
     * @param  Doc  $document
     * @return string|null
     * @throws InvalidSelectorException
     */
    private function getPrice(Doc $document): ?string
    {
        $res = $document->first('.woocommerce-Price-amount::text');
        $price = digit($res);
        return $price;
    }

    /**
     * @param  Doc  $document
     * @return string|null
     * @throws InvalidSelectorException
     */
    private function getSku(Doc $document): ?string
    {
        return $document->first('span.sku::text');
    }

    /**
     * @param  string  $url
     * @return string
     */
    private function normalUrl(string $url): string
    {
        return str_replace('https://тддизаж.рф', self::HOME, $url);
    }
}
