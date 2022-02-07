<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserPz extends Arser
{
    private const HOME = 'https://pazitif.com';

    /**
     * @param  array  $linkGroup
     * @throws InvalidSelectorException
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link'] . '?SHOWALL_5=1';
        $document = new Doc($link, true);

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
            $this->model_extension_module_arser_link->addLinks($data);
        }
        $this->model_extension_module_arser_link->deleteLinks([$linkGroup['id']]);
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
        $links = $document->find('div.productColText a.name');
        foreach ($links as $el) {
            $url[] = self::HOME.$el->attr('href');
        }
        $url = array_unique($url);

        // получим ссылки на размер продукта
        $sizeLink = [];
        foreach ($url as $item) {
            $links = $this->getSizeLinks($item);
            $sizeLink = array_merge($sizeLink, $links);
        }
        // получим ссылки на цвет продукта
        $colorLink = [];
        foreach ($sizeLink as $item) {
            $link = $this->getColorLinks($item);
            $colorLink = array_merge($colorLink, $link);
        }
        $colorLink = array_unique($colorLink);

        return $colorLink;
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

        // Получаем массив продуктов со страницы
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
        $ar['sku'] = $this->getSku($document);
        $ar['aImgLink'] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
        $ar['attr'] = $this->getAttr($ar['description']);

        return $ar;
    }

    /**
     * @param  Doc  $document
     * @return array|string[]
     * @throws InvalidSelectorException
     */
    private function getImg(Doc $document): array
    {
        $img = [];
        $elements = $document->find('div.slideBox a');
        foreach ($elements as $element) {
            $img[] = self::HOME . $element->href;
        }

        if (empty($img)) {
            if ($el = $document->first('#pictureContainer a')) {
                $img = [self::HOME . $el->href];
            }
        }
        return $img;
    }

    /**
     * @param  Doc  $doc
     * @return string
     * @throws InvalidSelectorException
     */
    private function getDescription(Doc $doc): string
    {
        if ($el = $doc->first('div.detailPropertiesTable')) {
            // надо удалить строчки из документа
            $el->firstInDocument('span:contains("Производитель")')->closest('tr')->remove();
            $tr = $el->firstInDocument('span:contains("Для детей")');
            if ($tr) {
                $tr->closest('tr')->remove();
            }

            return $el->html();
        }

        return '';
    }

    /**
     * @param  string  $str
     * @return array
     * @throws InvalidSelectorException
     */
    private function getAttr(string $str): array
    {
        $attrList = [];
        $doc = new Doc($str);

        if ($attr = $this->getAttribute($doc, 'Высота')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Ширина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Длина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Глубина')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал корпуса')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Материал фасада')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Максимально допустимый вес')) {
            $attrList = array_merge($attrList, $attr);
        }
        if ($attr = $this->getAttribute($doc, 'Размеры под матрац')) {
            $attrList = array_merge($attrList, ['Размер спального места' => $attr['Размеры под матрац']]);
        }

        return $attrList;
    }

    /**
     * @param  Doc  $doc
     * @param $attrName
     * @return array|false
     * @throws InvalidSelectorException
     */
    private function getAttribute(Doc $doc, $attrName)
    {
        $el = $doc->first("td span:contains({$attrName})");
        if ($el) {
            return [$attrName => trim($el->closest('td')->nextSibling('td')->text())];
        }

        return false;
    }

    /**
     * собираем ссылки на разные цвета продукта
     * @param  string|null  $href
     * @return array
     * @throws InvalidSelectorException
     */
    private function getColorLinks(?string $href): array
    {
        $link = [];
        $doc = (new Doc($href, true));
        $elements = $doc->find('li.elementSkuPropertyValue[data-name="SKU_COLOR"] a');
        foreach ($elements as $element) {
            $link[] = self::HOME . $element->href;
        }

        return $link;
    }

    /**
     * @param  string|null  $href
     * @return array
     * @throws InvalidSelectorException
     */
    private function getSizeLinks(?string $href): array
    {
        $link = [];
        $doc = (new Doc($href, true));
        $elements = $doc->find('li.elementSkuPropertyValue[data-name="SIZE"] a');
        foreach ($elements as $element) {
            $link[] = self::HOME . $element->href;
        }

        return $link;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document): string
    {
        $res = $document->first('h1.changeName')->text();
        $el = $document->first('li.elementSkuPropertyValue.selected');
        if ($el) {
            $res = $document->first('h1.changeName')->text();
        }

        return $res;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getSku(Doc $document): string
    {
        return $document->first('span.changeArticle::text');
    }
}
