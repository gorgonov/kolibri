<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserSg extends Arser
{
    /**
     * раскрываем группы
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     * @throws InvalidSelectorException
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        // 1 страница
        $document = new Doc($linkGroup['link'], true);

        $linkProducts = $this->getLinkProduct($document); // получим ссылки на продукты (отличаются цветом)

        // другие страницы
        $links = $document->find('.pagination__items >a');
        foreach ($links as $el) {
            $url = $el->attr('href');
            $document = new Doc($url, true);
            $linkProducts = array_merge($linkProducts, $this->getLinkProduct($document));
        }

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
    }

    /**
     * Получение ссылок на продукты (раскрываем группы)
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    protected function getLinkProduct(Doc $document): array
    {
        $url = [];
        $links = $document->find('.product-card a.product-card__wrapper');
        foreach ($links as $el) {
            $url[] = $el->attr('href');
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
        sleep(3); // борьба с частыми зависаниями

        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

        loadDidom();

        // 1 страница
        try {
            $document = (new Doc($link['link'], true));
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
                $document = (new Doc($item, true));
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
    }

    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getLinkProductColor(Doc $document): array
    {
        $url = [];
        $links = $document->find('a.characteristics__item');
        foreach ($links as $el) {
            $url[] = $el->attr('href');
        }

        return $url;
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
        $ar["sku"] = $this->getSku($document);
        $ar["aImgLink"] = $this->getImg($document);
        $ar['description'] = $this->getDescription($document);
        $ar['attr'] = $this->getAttributes($document);

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

        $el = $doc->first('td:contains("Габариты В*Ш*Г")');
        if ($el) {
            preg_match_all('!\d+!', $el->nextSibling('td')->text(), $numbers);
            @$aTmp["Высота"] = $numbers[0][0].'0';
            @$aTmp["Ширина"] = $numbers[0][1].'0';
            @$aTmp["Глубина"] = $numbers[0][2].'0';
        }

        if (!isset($aTmp["Высота"])) {
            $el = $doc->first('td:contains("Высота изделия")');
            if ($el) {
                preg_match_all('!\d+!', $el->nextSibling('td')->text(), $numbers);
                @$aTmp["Высота"] = $numbers[0][0].'0';
            }
        }

        if (!isset($aTmp["Ширина"])) {
            $el = $doc->first('td:contains("Ширина изделия")');
            if ($el) {
                preg_match_all('!\d+!', $el->nextSibling('td')->text(), $numbers);
                @$aTmp["Ширина"] = $numbers[0][0].'0';
            }
        }

        if (!isset($aTmp["Глубина"])) {
            $el = $doc->first('td:contains("Глубина изделия")');
            if ($el) {
                preg_match_all('!\d+!', $el->nextSibling('td')->text(), $numbers);
                @$aTmp["Глубина"] = $numbers[0][0].'0';
            }
        }

        $el = $doc->first('td:contains("Цвет:")');
        if ($el) {
            $aTmp["Цвет"] = trim($el->nextSibling('td')->text());
        }

        return $aTmp;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $document): string
    {
        return trim($document->first('h1.product-interface__title::text'));
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getSku(Doc $document): string
    {
        return trim($document->first('div.product-interface__options-text::text'));
    }

    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getImg(Doc $document): array
    {
        $aImgLink = [];
        $arTmp = $document->find('img.product-gallery__image');
        foreach ($arTmp as $el) {
            $aImgLink[] = str_replace("'", '%27', $el->attr('src')); // защищаемся от апострофов
        }
        return $aImgLink;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getDescription(Doc $document): string
    {
        $res = '';
        $arTmp = $document->find('.spoiler-list__item');
        if ($arTmp) {
            $res = $arTmp[0]->html().$arTmp[1]->html();
        }
        $tmp = $document->first('.download-list');
        if ($tmp) {
            $res .= $tmp->html();
        }
//        $res = urlencode($res);

        return $res;
    }
}
