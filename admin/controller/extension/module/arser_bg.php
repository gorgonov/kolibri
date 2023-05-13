<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserBg extends Arser
{
    private const HOME = 'http://xn--90aafiboze2m.xn--p1ai';

    /**
     * получим список ссылок на продукты (раскрываем группы)
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(Doc $document): array
    {
        $url = [];
        $links = $document->find('a.list-product-item');
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

//        $result = $this->getUrl($link['link']);
        $url = $link['link'];
        $document = (new Doc($url, true));
        if (!$document) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Не удалось прочитать страницу');
            return;
        }

        // Общая информация о продукте
        $data = $this->getProductInfo($document);
        $data['link'] = $url;
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];

        $links = $this->getLinkProductColor($document); // содержит массив в формате
        // [[id] => [title, imageLink], ... ]

        $common_image = $data['aImgLink'] ?? [];
        $common_topic = $data['topic'] ?? '';

        foreach ($links as $item) {
            $data['topic'] = "{$common_topic} ({$item['title']})";
            $data['aImgLink'] = array_merge([$item['imageLink']], $common_image);
        }
        $this->model_extension_module_arser_product->addProduct($data);
        $this->model_extension_module_arser_link->setStatus($link['id'], 'ok');
    }

    /**
     * Возвращает список с информацией о разных цветах продукта
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getLinkProductColor(Doc $document): array
    {
        $url = [];
        $links = $document->find('#list_product_image_thumb img');
        foreach ($links as $el) {
            $id = digit($el->getAttribute('onclick'));
            $img = $document->first('#main_image_' . $id)->attr('src');
            $title = $el->getAttribute('title');
            $url[$id] = ['title' => $title, 'imageLink' => $img];
        }

        return $url;
    }

    /**
     * Возвращает информацию о продукте
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getProductInfo(Doc $document): array
    {
        $ar = [];
        $ar["topic"] = $this->getTopic($document);
        $ar["sku"] = $this->getSku($document);

        // картинки
        $ar["aImgLink"] = $this->getImg($document);

        $ar['description'] = '';

        $sTmp = $document->first('.jshop_prod_description');
        $ar['description'] .= $sTmp ? $sTmp->html() : '';

        $sTmp = $document->first('.extra_fields');
        $ar['description'] .= $sTmp ? $sTmp->html() : '';

        $sTmp = $document->first('.productweight');
        $ar['description'] .= $sTmp ? $sTmp->html() : '';

        $ar['attr'] = $this->getAttributes($document);

        $ar['description'] = $this->sizeNormal($ar);
        $ar['description'] = $this->imgRemove($ar['description']);

        $el = $document->first('#block_weight');
        if ($el) {
            $ar["weight"] = trim(digit($el->text()));
        }

        return $ar;
    }

    /**
     * @param  Doc  $doc
     * @return array
     * @throws InvalidSelectorException
     */
    private function getAttributes(Doc $doc): array
    {
        $aTmp = [];

        $el = $doc->first('span.extra_fields_name:contains("Габариты")');
        if ($el) {
            preg_match_all('!\d+!', $el->nextSibling('span')->text(), $numbers);
            @$aTmp["Ширина"] = $numbers[0][0];
            @$aTmp["Глубина"] = $numbers[0][1];
            @$aTmp["Высота"] = $numbers[0][2];
        }

        return $aTmp;
    }

    private function sizeNormal(array $ar): string
    {
        $description = $ar['description'];
        $document = (new Doc($description));
        $td_name = $document->first('span.extra_fields_name:contains("Габариты")');
        $td_value = $td_name->nextSibling('span');

        $el = $document->first('span.extra_fields_name:contains("Габариты")');
        if ($el) {

            preg_match_all('!\d+!', $el->nextSibling('span')->text(), $numbers);
            $ar["Ширина"] = $numbers[0][0] ?? 0;
            $ar["Глубина"] = $numbers[0][1] ?? 0;
            $ar["Высота"] = $numbers[0][2] ?? 0;
        }

        $td_name->setInnerHtml('Габариты (Ш*В*Г)');
        $td_value->setInnerHtml($ar["Ширина"] . '*' . $ar["Высота"] . '*' . $ar["Глубина"]);

        return $document->toElement()->innerHtml();
    }

    private function imgRemove(string $description): string
    {
        $document = (new Doc($description));
        $img = $document->first('.jshop_prod_description img');
        if ($img) {
            $img->remove();
        }

        return $document->toElement()->innerHtml();
    }

    /**
     * @param $document
     * @return string
     */
    private function getTopic($document): string
    {
        return trim($document->first('.productfull h2')->text());
    }

    /**
     * @param $document
     * @return string
     */
    private function getSku($document): string
    {
        return $document->first('#manufacturer_code')
            ? trim($document->first('#manufacturer_code')->text())
            : '';
    }

    /**
     * @param $document
     * @return array|string[]
     */
    private function getImg($document): array
    {
        return $document->first('.jshop_prod_description img')
            ? [self::HOME.$document->first('.jshop_prod_description img')->attr('src')]
            : [];
    }
}
