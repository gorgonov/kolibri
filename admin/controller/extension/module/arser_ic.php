<?php

use Arser\Arser;
use DiDom\Document as Doc;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserIc extends Arser
{
    private const HOME = 'https://interior-center.ru';

    /**
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     * @throws InvalidSelectorException
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link']; //показать все товары

        $document = new Doc($link, true);
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
        // соберем ссылки на продукты
        $url = [];
        $links = $document->find('li.bx_item_set_hor_item a');
        foreach ($links as $el) {
            $url[] = self::HOME . trim($el->href);
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

        $document = (new Doc($link['link'], true));
        if (!$document) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Не удалось прочитать страницу');
            return;
        }

        // Получаем массив - информацию о продукте
        $items = $this->getProductInfo($document);
        if (empty($items)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }
        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];
        foreach ($items as $item) {
            $data = array_merge($data,$item);
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
        $description = $this->getDescription($document);
        $offers = $this->getOffers($document);
        foreach ($offers as $offer) {
            $attr = [];
            foreach ($offer->DISPLAY_PROPERTIES as $item) {
                $attr[$item->NAME] = str_replace('\\','/', $item->VALUE);
            }

            $img = [];
            foreach ($offer->SLIDER as $item) {
                $img[] = self::HOME . $item->SRC;
            }

            $ar[] = [
                'sku' => $offer->ID,
                'topic' => $offer->NAME,
                'description' => $description,
                'aImgLink' => $img,
                'attr' => $attr,

            ];
        }

        return $ar;
    }

    /**
     * @param  Doc  $document
     * @return mixed
     * @throws InvalidSelectorException
     */
    private function getOffers(Doc $document)
    {
        $tmp = $document->first('script:contains(JCCatalogElement)')->text();
        $startPos = mb_strpos($tmp, '{');
        $endPos = mb_strrpos($tmp, '}');
        $tmp = mb_substr($tmp, $startPos, $endPos - $startPos + 1);
        $tmp = preg_replace("/'/", '"', $tmp);
        $tmp = html_entity_decode($tmp);
        $json = json_decode($tmp);

        $offers = $json->OFFERS;
        return $offers;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getDescription(Doc $document): string
    {
        return ($el = $document->first('div.preview_text')) ? $el->html() : '';
    }
}
