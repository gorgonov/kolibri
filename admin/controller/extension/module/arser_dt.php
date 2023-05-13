<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserDt extends Arser
{
    private const HOME = 'https://www.utromebel.ru';

    /**
     * Получение ссылок на продукты (раскрываем группы)
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(Doc $document): array
    {
        $url = [];
        $links = $document->find('div.product_preview a');
        foreach ($links as $el) {
            $url[] = self::HOME.$el->attr('href');
        }

        $url = array_unique($url);

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
        $ar["topic"] = $this->getTopic($document);

        $ar['aImgLink'] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
        $ar['attr'] = $this->getAttr($ar['description']);
        $ar['product_option'] = $this->getOptions($document);

        return $ar;
    }

    private function getOptions(Doc $doc): array
    {
        $aTmp = [
            ['name' => 'Цвет корпуса', 'value' => 'анкор белый',],
            ['name' => 'Цвет корпуса', 'value' => 'анкор светлый'],
            ['name' => 'Цвет корпуса', 'value' => 'анкор темный'],
            ['name' => 'Цвет корпуса', 'value' => 'белый'],
            ['name' => 'Цвет корпуса', 'value' => 'белый глянец'],
            ['name' => 'Цвет корпуса', 'value' => 'венге'],
            ['name' => 'Цвет корпуса', 'value' => 'дуб бунратти'],
            ['name' => 'Цвет корпуса', 'value' => 'дуб выбеленный'],
            ['name' => 'Цвет корпуса', 'value' => 'дуб сонома'],
            ['name' => 'Цвет корпуса', 'value' => 'дуб французский'],
            ['name' => 'Цвет корпуса', 'value' => 'крем'],
            ['name' => 'Цвет корпуса', 'value' => 'ольха'],
            ['name' => 'Цвет корпуса', 'value' => 'орех итальянский'],
            ['name' => 'Цвет корпуса', 'value' => 'орех миланский'],
            ['name' => 'Цвет корпуса', 'value' => 'орех таволато'],
            ['name' => 'Цвет корпуса', 'value' => 'слива валлис'],
            ['name' => 'Цвет корпуса', 'value' => 'сосна астрид'],
            ['name' => 'Цвет корпуса', 'value' => 'сосна винтенберг'],
            ['name' => 'Цвет корпуса', 'value' => 'черный'],
            ['name' => 'Цвет корпуса', 'value' => 'шимо светлый'],
            ['name' => 'Цвет корпуса', 'value' => 'шимо темный'],
            ['name' => 'Цвет фасада', 'value' => 'аква'],
            ['name' => 'Цвет фасада', 'value' => 'анкор белый'],
            ['name' => 'Цвет фасада', 'value' => 'анкор светлый'],
            ['name' => 'Цвет фасада', 'value' => 'анкор темный'],
            ['name' => 'Цвет фасада', 'value' => 'белый'],
            ['name' => 'Цвет фасада', 'value' => 'белый глянец'],
            ['name' => 'Цвет фасада', 'value' => 'бетон пайн экзотик'],
            ['name' => 'Цвет фасада', 'value' => 'венге'],
            ['name' => 'Цвет фасада', 'value' => 'дуб аризона'],
            ['name' => 'Цвет фасада', 'value' => 'дуб бунратти'],
            ['name' => 'Цвет фасада', 'value' => 'дуб вотан'],
            ['name' => 'Цвет фасада', 'value' => 'дуб выбеленный'],
            ['name' => 'Цвет фасада', 'value' => 'дуб интра'],
            ['name' => 'Цвет фасада', 'value' => 'дуб сонома'],
            ['name' => 'Цвет фасада', 'value' => 'дуб французский'],
            ['name' => 'Цвет фасада', 'value' => 'желтый'],
            ['name' => 'Цвет фасада', 'value' => 'индиана эбони'],
            ['name' => 'Цвет фасада', 'value' => 'ирис'],
            ['name' => 'Цвет фасада', 'value' => 'камень темный'],
            ['name' => 'Цвет фасада', 'value' => 'красный'],
            ['name' => 'Цвет фасада', 'value' => 'крем'],
            ['name' => 'Цвет фасада', 'value' => 'лайм'],
            ['name' => 'Цвет фасада', 'value' => 'лимонный'],
            ['name' => 'Цвет фасада', 'value' => 'олива шоколад'],
            ['name' => 'Цвет фасада', 'value' => 'ольха'],
            ['name' => 'Цвет фасада', 'value' => 'оранж'],
            ['name' => 'Цвет фасада', 'value' => 'орех итальянский'],
            ['name' => 'Цвет фасада', 'value' => 'орех миланский'],
            ['name' => 'Цвет фасада', 'value' => 'орех таволато'],
            ['name' => 'Цвет фасада', 'value' => 'орех таволато'],
            ['name' => 'Цвет фасада', 'value' => 'пинк'],
            ['name' => 'Цвет фасада', 'value' => 'розовый кварц'],
            ['name' => 'Цвет фасада', 'value' => 'салатовый'],
            ['name' => 'Цвет фасада', 'value' => 'серенити'],
            ['name' => 'Цвет фасада', 'value' => 'синий'],
            ['name' => 'Цвет фасада', 'value' => 'салатовый'],
            ['name' => 'Цвет фасада', 'value' => 'слива валлис'],
            ['name' => 'Цвет фасада', 'value' => 'сосна астрид'],
            ['name' => 'Цвет фасада', 'value' => 'сосна винтенберг'],
            ['name' => 'Цвет фасада', 'value' => 'черный'],
            ['name' => 'Цвет фасада', 'value' => 'шимо светлый'],
            ['name' => 'Цвет фасада', 'value' => 'шимо темный'],
        ];

        return $aTmp;
    }

    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getImg(Doc $document): array
    {
        $res = [];
        $elementList = $document->find('.gallery-preview_list a');
        foreach ($elementList as $element) {
            $res[] = $element->attr('href');
        }

        return $res;
    }

    /**
     * @param  Doc  $doc
     * @return string
     * @throws InvalidSelectorException
     */
    private function getDescription(Doc $doc): string
    {
        if ($el = $doc->first('table.product-properties')) {
            $doc = $el->toDocument();
            $doc->first('tr')->remove(); // удалим первую строку из таблицы
            $el = $doc->first('td a');
            if ($el) {
                $el->parent()->setInnerHtml(trim($el->text()));
            }
            $str = removeHtmlComments2($doc->html()); // удалим html-комментарий
            return $str;
        } else {
            return '';
        }
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
        $el = $doc->first("td:contains({$attrName})");
        if ($el) {
            return [$attrName => trim($el->nextSibling('td')->text())];
        }

        $el = $doc->first("td a:contains({$attrName})");
        if ($el) {
            return [$attrName => trim($el->parent()->nextSibling('td')->text())];
        }

        return false;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document): string
    {
        return trim($document->first('h1.product-title::text'));
    }
}
