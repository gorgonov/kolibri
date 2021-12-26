<?php

use DiDom\Document;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserGnt extends Controller
{
    private const HOME = 'http://pnz.gorizontmebel.ru';

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

    /**
     * @param  array  $linkGroup
     */
    private function parseGroup(array $linkGroup)
    {
        try {
            loadDidom();
            // 1 страница
            $document = new DiDom\Document($linkGroup['link'], true);
            $linkProduct = $this->getLinkProduct($document);
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

        $this->parseProduct($productLinks[0]);
        $link_count = $this->model_extension_module_arser_link->getLinkCount($siteId);

        $json = [
            'link' => $productLinks[0],
            'link_count' => $link_count['all'],
            'link_product_count' => ($link_count['ok'] ?? 0) + ($link_count['bad'] ?? 0),
            'status' => 'go',
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

        // Получаем массив продуктов со страницы
        $products = $this->getProductInfo($document);

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
//        } catch (Exception $e) {
//            // установить статус у текущей групповой ссылки 'bad'
//            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', $e->getMessage() . ' in ' . $e->getLine());
//        }
    }

    private function getLinkProduct(DiDom\Document $document): array
    {
        $url = [];
        $str = $document->first('ui-products-list')->html();
        $re = '/"url":"(\/[^"]+)"/m';
        preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
        foreach ($matches as $match) {
            $url[] = self::HOME.$match[1];
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
        $ar['topic'] = $document->first('h1')->text();

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
     * @param $sum  - строка, содержащая цифры и текст
     * @return int - возвращает целое число, состоящее из цифр $sum
     */
    private static function normalSum($sum)
    {
        $result = (int)preg_replace("/[^0-9]/", '', $sum);
        return $result;
    }

    /**
     * @param  \DiDom\Element  $element
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getImg($el): array
    {
        $res = [];
        foreach ($el->images as $item) {
            $res[$item->id] = $item->original_url;
        }

        return $res;
    }

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

    private function getTopic(DiDom\Document $doc): string
    {
        $res = '';
        $item = $doc->first('.module_description b');
        if ($item) {
            $res = $item->text();
        }

        return $res;
    }

    private function getDopImg(DiDom\Document $document)
    {
        $res = [];
        $items = $document->find('.n2-ss-slider picture img');
        foreach ($items as $item) {
            $res[] = $item->getAttribute('src');
        }

        $res = array_map(function ($x) {
            return substr($x, 0, 2) == '//' ? 'https:'.$x : $x;
        }, $res);


        return $res;
    }

   private function getAttr(?string $short_description)
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
}
