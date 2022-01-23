<?php

use DiDom\Document;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserEh extends Controller
{
    private const HOME = 'https://esandwich.ru';

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
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     */
    private function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link'].'?SHOWALL_1=1'; //показать все товары

        $document = new DiDom\Document($link, true);
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
        $links = $document->find('a.one_img_wrapper');
        foreach ($links as $el) {
            $url[] = self::HOME . $el->href;
        }

        $url = array_unique($url);

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
    protected function getImageSrc($str)
    {
        $str = str_replace("bmp.jpg", "bmp", $str); // предобработка при двух расширениях

        $ret = '';

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


    private function getDescription(DiDom\Document $doc): string
    {
        $res = '';
        if ($el = $doc->first('div[itemprop=description]')) {
            $res .= $el->html();
        }

        if ($el = $doc->first('table#product_teh')) {
            $res .= $el->html();
        }

        // удалим комментарии из html
        $res = $this->removeHtmlComments($res);
        // удалить ссылки из описания
        $res = $this->removeHtmlLink($res);
        // удалим плохие символы из текста
        $res = str_replace("'", '', $res);

        return $res;
    }

    private function getAttr(string $str)
    {
        $attrList = [];
        $doc = new DiDom\Document($str);

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
     * @param  Document  $doc
     * @param $attrName
     * @param  false  $is_digit
     * @return array|false
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getAttribute(DiDom\Document $doc, $attrName, $is_digit = false)
    {
        $el = $doc->first("td:contains({$attrName})");
        if ($el) {
            $res = $el->nextSibling('td')->text();
            return [$attrName => $res];
        }

        return false;
    }

    private function getTopic(Document $document)
    {
        return $document->first('h1::text');
    }

    private function getSku(Document $document)
    {
        return $document->first('span.articule__value::text');
    }

    /**
     * удаляет комментарии из html-разметки
     * @param $html
     * @return array|string|string[]|null
     */
    private function removeHtmlComments2($html)
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

    /**
     * удаляет комментарии из html-разметки
     * @param $html
     * @return array|string|string[]|null
     */
    private function removeHtmlComments($html)
    {
        return preg_replace('/<!--(.*?)-->/', '', $html);
    }

    /**
     * удаляет комментарии из html-разметки
     * @param $html
     * @return array|string|string[]|null
     */
    private function removeHtmlLink($html)
    {
        // удаляем открывающие тэги
        $html = preg_replace('/\<a.*?\>/s', '', $html);

        // удаляем закрывающие тэги
        $html = preg_replace('/<\/a\>/s', '', $html);

        return $html;
    }
}
