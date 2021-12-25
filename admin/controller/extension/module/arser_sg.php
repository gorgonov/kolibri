<?php

use DiDom\Document;

class ControllerExtensionModuleArserSg extends Controller
{
    /**
     * Парсим группы товаров (получаем ссылки на конкретные товары)
     *
     * @throws Exception
     */
    public function openGroup()
    {
        ini_set('memory_limit', '3500M');

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
//        try {
        $this->loadDidom();
        // 1 страница
        $document = new DiDom\Document($linkGroup['link'], true);

        $linkProducts = $this->getLinkProduct($document); // получим ссылки на продукты (отличаются цветом)

        // другие страницы
        $links = $document->find('.pagination__items >a');
        foreach ($links as $el) {
            $url = $el->attr('href');
            $document = new DiDom\Document($url, true);
            $linkProducts = array_merge($linkProducts, $this->getLinkProduct($document));
        }
//        } catch (Exception $e) {
////            echo $e->getMessage();
//            // установить статус у текущей групповой ссылки 'bad'
//            $this->model_extension_module_arser_link->setStatus($linkGroup['id'], 'bad', $e->getMessage() . ' in ' . $e->getLine());
//            return;
//        }

        // добавим линки на продукты и удалим группу
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
        }
        $this->model_extension_module_arser_link->addLinks($data);
        $this->model_extension_module_arser_link->deleteLinks([$linkGroup['id']]);

        return;
    }


    /**
     * Получаем информацию о продукте
     * @param array $link
     */
    private function parseProduct(array $link)
    {
        sleep(3); // борьба с частыми зависаниями

        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

//        try {
//            error_reporting(0);
        $this->loadDidom();
        // 1 страница
        try {
            $document = (new DiDom\Document($link['link'], true));
        } catch (Exception $e) {
            sleep(3);
            return;
        }
        if (!$document) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Не удалось прочитать страницу');
            return;
        }

        $links = $this->getLinkProductColor($document);

        foreach ($links as $item) {
            try {
                $document = (new DiDom\Document($item, true));
            } catch (Exception $e) {
                sleep(3);
                return;
            }

            $data = $this->getProductInfo($document);
            $data['link'] = $item;
            $data['site_id'] = $link['site_id'];
            $data['category'] = $link['category_list'];
            $data['category1c'] = $link['category1c'];
            $this->model_extension_module_arser_product->addProduct($data);
        }
        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
//        } catch (Exception $e) {
//            // установить статус у текущей групповой ссылки 'bad'
//            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', $e->getMessage() . ' in ' . $e->getLine());
//        }
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

    private function getLinkProductColor(DiDom\Document $document): array
    {
        $url = [];
        $links = $document->find('a.characteristics__item');
        foreach ($links as $el) {
            $url[] = $el->attr('href');
        }

        return $url;
    }

    private function getLinkProduct(DiDom\Document $document): array
    {
        $url = [];
        $links = $document->find('.product-card a.product-card__wrapper');
        foreach ($links as $el) {
            $url[] = $el->attr('href');
        }

        return $url;
    }

    /**
     * @param Document $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getProductInfo(DiDom\Document $document): array
    {
        $ar = [];
        $ar["topic"] = trim($document->first('h1.product-interface__title')->text());
        $ar["sku"] = trim($document->first('div.product-interface__options-text')->text());

        // картинки
        $aImgLink = [];
        $arTmp = $document->find('img.product-gallery__image');
        foreach ($arTmp as $el) {
            $aImgLink[] = str_replace("'", '%27', $el->attr('src')); // защищаемся от апострофов
        }
        $ar["aImgLink"] = $aImgLink;

        $ar['description'] = '';
        $arTmp = $document->find('.spoiler-list__item');
        if ($arTmp) {
            $ar['description'] = $arTmp[0]->html() . $arTmp[1]->html();
        }

        $tmp = $document->first('.download-list');
        if ($tmp) {
            $ar['description'] .= $tmp->html();
        }
        $ar['description'] = urlencode($ar['description']);

        $ar['attr'] = $this->getAttributes($document);

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
     * @param Document $doc
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getAttributes(DiDom\Document $doc): array
    {
        $aTmp = [];

        $el = $doc->first('td:contains("Габариты В*Ш*Г")');
        if ($el) {
            preg_match_all('!\d+!', $el->nextSibling('td')->text(), $numbers);
            @$aTmp["Высота"] = $numbers[0][0] . '0';
            @$aTmp["Ширина"] = $numbers[0][1] . '0';
            @$aTmp["Глубина"] = $numbers[0][2] . '0';
        }

        if (!isset($aTmp["Высота"])) {
            $el = $doc->first('td:contains("Высота изделия")');
            if ($el) {
                preg_match_all('!\d+!', $el->nextSibling('td')->text(), $numbers);
                @$aTmp["Высота"] = $numbers[0][0] . '0';
            }
        }

        if (!isset($aTmp["Ширина"])) {
            $el = $doc->first('td:contains("Ширина изделия")');
            if ($el) {
                preg_match_all('!\d+!', $el->nextSibling('td')->text(), $numbers);
                @$aTmp["Ширина"] = $numbers[0][0] . '0';
            }
        }

        if (!isset($aTmp["Глубина"])) {
            $el = $doc->first('td:contains("Глубина изделия")');
            if ($el) {
                preg_match_all('!\d+!', $el->nextSibling('td')->text(), $numbers);
                @$aTmp["Глубина"] = $numbers[0][0] . '0';
            }
        }

        $el = $doc->first('td:contains("Цвет:")');
        if ($el) {
            $aTmp["Цвет"] = trim($el->nextSibling('td')->text());
        }

        return $aTmp;
    }
}
