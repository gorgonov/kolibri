<?php

use DiDom\Document;

class ControllerExtensionModuleArserOl extends Controller
{
    private const HOME = 'https://olmeko.ru';

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
        // хотим показать все продукты, 500 хватит?
        $document = new DiDom\Document($linkGroup['link'] . "/?cnt=500", true);

        $linkProducts = $this->getLinkProduct($document); // получим ссылки на продукты (отличаются цветом)

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
        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

        $this->loadDidom();
        $document = (new DiDom\Document($link['link'], true));
        if (!$document) {
            return;
        }

        // попытки прочитать "хорошую страницу, игнорируя сообщения безопасности"
        $sTmp = $document->first('h1.bx-title');
        while (!$sTmp) {
            // НЕУДАЧА. Ждем 5 секунд.
            sleep(5);
            $document = (new DiDom\Document($link['link'], true));
            if ($document) {
                $sTmp = $document->first('h1.bx-title');
            }
        }

        $arrayData = $this->getProductInfo($document, $link['link']);

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
     * @param Document $document
     * @param string $link
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getProductInfo(DiDom\Document $document, string $link): array
    {
        $ar = [];

        // несколько продуктов на одной странице, отличаются цветом/материалом
        $products = $document->find('div.detailed_color_wr input');

        foreach ($products as $product) {
            $topic = $product->attr('data-colorname') . ' (' . $product->attr('data-tsvet') . ')';
            $sku = $product->attr('data-kodv1c');
//            echo 'pagetype: ' . $product->attr('data-pagetype');
            $id_offer = $product->attr('data-id_offer');

            $sTmp = "div.bx_item_description[data-id_offer='{$id_offer}']";
            $specifications = $document->first($sTmp);
            $attr = $this->getAttributes($specifications);

            $description = $product->attr('data-description') . $specifications->innerHtml();

            $sTmp = "div.detailed_l[data-id_offer='{$id_offer}'] .detailed_big-slider img";
            $aImgLink = [];  // список картинок для карусели
            $aImg = $document->find($sTmp);

            foreach ($aImg as $el) {
                $src = $el->attr('src');
                if (substr($src,0,1) == '/') {
                    $src =  self::HOME . $src;
                }
                if ($src <> '') {
                    $aImgLink [] = $src;
                }
            }

            $aImgLink = array_unique($aImgLink); // удалим дубли

            $ar[] = compact('topic', 'sku', 'id_offer', 'description', 'aImgLink', 'attr', 'description');
        }

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
    private function getAttributes($doc): array
    {
        $attrs = [];

        $el = $doc->first('span:contains("Высота")');
        if ($el) {
            $attrs['Высота'] = self::normalSum($el->text());
        }

        $el = $doc->first('span:contains("Ширина")');
        if ($el) {
            $attrs['Ширина'] = self::normalSum($el->text());
        }

        $el = $doc->first('span:contains("Глубина")');
        if ($el) {
            $attrs['Глубина'] = self::normalSum($el->text());
        }

        $el = $doc->first('span:contains("Материал:")');
        if ($el) {
            $sTmp = trim($el->text());
            $aTmp = explode(":", $sTmp);
            $attrs["Материал"] = trim($aTmp[count($aTmp) - 1]);
        }


        return $attrs;
    }

    /**
     * @param $sum - строка, содержащая цифры и текст
     * @return int - возвращает целое число, состоящее из цифр $sum
     */
    private static function normalSum($sum)
    {
        $result = (int)preg_replace("/[^0-9]/", '', $sum);
        return $result;
    }

}
