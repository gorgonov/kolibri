<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserOl extends Arser
{
    private const HOME = 'https://olmeko.ru';

    /**
     * Получение ссылок на продукты (раскрываем группы)
     * @param  Doc  $document
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
     * @param  array  $link
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
        $sTmp = $document->first('h1.bx-title');
        while (!$sTmp) {
            // НЕУДАЧА. Ждем 5 секунд.
            sleep(5);
            $document = (new Doc($link['link'], true));
            if ($document) {
                $sTmp = $document->first('h1.bx-title');
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
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getProductInfo(Doc $document): array
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
            if (!empty($specifications)) {
                $attr = $this->getAttributes($specifications);
            }

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
     * @param $doc
     * @return array
     */
    private function getAttributes($doc): array
    {
        $attrs = [];

        $el = $doc->first('span:contains("Высота")');
        if ($el) {
            $attrs['Высота'] = digit($el->text());
        }

        $el = $doc->first('span:contains("Ширина")');
        if ($el) {
            $attrs['Ширина'] = digit($el->text());
        }

        $el = $doc->first('span:contains("Глубина")');
        if ($el) {
            $attrs['Глубина'] = digit($el->text());
        }

        $el = $doc->first('span:contains("Материал:")');
        if ($el) {
            $sTmp = trim($el->text());
            $aTmp = explode(":", $sTmp);
            $attrs["Материал"] = trim($aTmp[count($aTmp) - 1]);
        }

        return $attrs;
    }
}
