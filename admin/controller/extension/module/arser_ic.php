<?php

use Arser\Arser;

require_once(DIR_SYSTEM.'helper/arser.php');

class ControllerExtensionModuleArserIc extends Arser
{
    private const HOME = 'https://interior-center.ru';

    public function openGroup()
    {
        parent::openGroup();
    }

    /**
     * добавим линки на продукты и удалим группу
     * @param  array  $linkGroup
     */
    protected function parseGroup(array $linkGroup)
    {
        loadDidom();
        $link = $linkGroup['link']; //показать все товары

        $document = new DiDom\Document($link, true);
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

        return;
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
        $links = $document->find('li.bx_item_set_hor_item a');
        foreach ($links as $el) {
            $url[] = self::HOME . trim($el->href);
        }

        $url = array_unique($url);

        return $url;
    }

    /**
     * Парсим следующий товар (arser_link.status='new'), добавляем его в arser_product
     * @throws Exception
     */
    public function parseNextProduct()
    {
        parent::parseNextProduct();
    }

    /**
     * Получаем информацию о продукте
     * @param  array  $link
     */
    protected function parseProduct(array $link)
    {
        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

        loadDidom();

//        $result = $this->getUrl($link['link']);
//
//        if ($result == 404) {
//            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Страница не существует');
//            return;
//        }

//        if ($result>200) {
//            die('httpError: ' . $result);
//        }


//        $document = (new DiDom\Document($result));
        $document = (new DiDom\Document($link['link'], true));
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
     * @param  Document  $document
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getProductInfo(DiDom\Document $document): array
    {
        $ar = [];
        $description = ($el = $document->first('div.preview_text')) ? $el->html() : '';
        $offers = $this->getOffers($document);
        foreach ($offers as $offer) {
            $attr = [];
            foreach ($offer->DISPLAY_PROPERTIES as $item) {
                $attr[$item->NAME] = $item->VALUE;
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
     * удаляет комментарии из html-разметки
     * @param $html
     * @return array|string|string[]|null
     */
    private function removeHtmlComments($html)
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
     * @param $document
     * @return mixed
     */
    private function getOffers($document)
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
}
