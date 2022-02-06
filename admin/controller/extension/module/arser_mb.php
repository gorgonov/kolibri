<?php

use Arser\Arser;
use DiDom\Document as Doc;
use Didom\Element;
use DiDom\Exceptions\InvalidSelectorException;

require_once(DIR_SYSTEM . 'helper/arser.php');

class ControllerExtensionModuleArserMb extends Arser
{
    private const HOME = 'https://mobi-mebel.ru';

    /**
     * с группами не работаем
     */
    public function openGroup()
    {
        $json = [
            'link_group_count' => 0,
            'link_product_count' => 0,
            'status' => 'finish',
        ];
        echo json_encode($json);
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

        // Получаем массив продуктов со страницы
        $products = $this->getProductInfo($document);
        if (empty($products)) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad');
            return;
        }

        $data['link'] = $link['link'];
        $data['site_id'] = $link['site_id'];
        $data['category'] = $link['category_list'];
        $data['category1c'] = $link['category1c'];

        foreach ($products['productList'] as $product) {
            if (!empty($product['topic'])) {
                $data['topic'] = $product['topic'];
                $data['attr'] = $product['size'];
                $data['description'] = $product['description'] . $products['commonDescription'];
                $data['aImgLink'] = array_merge($product['img'], $products['dopImg']);
                $this->model_extension_module_arser_product->addProduct($data);
            }
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
        $product = [];
        $arProduct = $document->find('.module_element');
        foreach ($arProduct as $item) {
            $doc = $item->toDocument();
            $topic = $this->getTopic($doc);
            if (!empty($topic)) {
                $size = $this->getSize($doc);
                $product[] = [
                    'topic' => $topic,
                    'size' => $size,
                    'img' => $this->getImg($doc),
                    'description' => $this->getDescription($doc, $size),
                ];
            }
        }
        $ar = [
            'productList' => $product,
            'dopImg' => $this->getDopImg($document),
            'commonDescription' => $this->getCommonDescription($document),
        ];

        return $ar;
    }

    /**
     * @param  Doc  $doc
     * @return array
     * @throws InvalidSelectorException
     */
    private function getImg(Doc $doc): array
    {
        $res = [];
        $imgList = $doc->find('img');
        foreach ($imgList as $item) {
            /** @var DiDom\Element $item */
            $res[] = $item->getAttribute('src');
        }

        // сортировка по авфавиту, но содержащие «shem» ссылки сзади
        uasort($res, function ($a, $b) {
            $pos = strripos($a, 'shem');
            if ($pos !== false) {
                return 1;
            } else {
                return ($a < $b) ? -1 : 1;
            }
        });

        $res = array_map(function($x){
            return substr($x, 0, 2) == '//' ? 'https:' . $x : $x;
        }, $res);

        return $res;
    }

    /**
     * @param  Doc  $doc
     * @return string
     * @throws InvalidSelectorException
     */
    private function getTopic(Doc $doc): string
    {
        $res = '';
        $item = $doc->first('.module_description b');
        if ($item) {
            $res = $item->text();
        }

        return $res;
    }

    /**
     * @param  Doc  $document
     * @return array
     * @throws InvalidSelectorException
     */
    private function getDopImg(Doc $document): array
    {
        $res = [];
        $items = $document->find('.n2-ss-slider picture img');
        foreach ($items as $item) {
            $res[] = $item->getAttribute('src');
        }

        $res = array_map(function($x){
            return substr($x, 0, 2) == '//' ? 'https:' . $x : $x;
        }, $res);


        return $res;
    }

    /**
     * @param  Doc  $doc
     * @return array
     * @throws InvalidSelectorException
     */
    private function getSize(Doc $doc): array
    {
        $aTmp = [];

        $item = $doc->first('.module_description b');
        if ($item) {
            preg_match_all('!\d+!', $item->nextSibling()->text(), $numbers);
            @$aTmp["Ширина"] = $numbers[0][0];
            @$aTmp["Глубина"] = $numbers[0][1];
            @$aTmp["Высота"] = $numbers[0][2];
        }

        return $aTmp;
    }

    /**
     * @param  Doc  $doc
     * @param  array  $size
     * @return string
     * @throws InvalidSelectorException
     */
    private function getDescription(Doc $doc, array $size): string
    {
        $res = getSizeString($size);
        $item = $doc->first('.module_description b');
        if ($item) {
            $el = $item->nextSibling()->text();
            $aTmp = explode(" мм", $el);
            $res .= '<br>' . trim($aTmp[1]) ?? '';
        }

        return $res;
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getCommonDescription(Doc $document): string
    {
        $res = [];

        $res[] = $this->getSection($document, "Цветовое решение:");
        $res[] = $this->getSection($document, "Цветовое решение 1");
        $res[] = $this->getSection($document, "Цветовое решение 2");
        $res[] = $this->getSection($document, "Фурнитура");
        $res[] = $this->getSection($document, "Особенности изделия");
        $res[] = $this->getVideo($document);
        $res[] = $document->first('.so-widget-sow-editor-base')->html();

        return implode('<br>', $res);
    }

    /**
     * @param  Doc  $document
     * @param  string  $sectionName
     * @return string
     * @throws InvalidSelectorException
     */
    private function getSection(Didom\Document $document, string $sectionName): string
    {
        $res = [];
        $el = $document->first("h1:contains('{$sectionName}')");

        if ($el) {
            $res[] = "{$sectionName} ";
            if ($el1 = $el->closest('div.panel-grid')->nextSibling()) {
                $items = $el1->toDocument()->find('div.so-panel');
                foreach ($items as $item) {
                    $res[] = $this->getContent($item);
                }
            }
        }

        $res = array_filter($res, function($element) {
            return !empty($element);
        });

        return implode('<br>', $res);
    }

    /**
     * @param  Element  $el
     * @return string
     * @throws InvalidSelectorException
     */
    private function getContent(Element $el): string
    {
        $aTmp = [];
        $doc = $el->toDocument();
        $sub1 = $doc->first('h1');
        $sub2 = $doc->first('h3');
        $sub3 = $doc->first('p');
        $sub4 = $doc->first('table');
        if ($sub1) {
            $aTmp[] = trim($sub1->text());
        }
        if ($sub2) {
            $aTmp[] = trim($sub2->text());
        }
        if ($sub3) {
            $aTmp[] = trim($sub3->text());
        }
        if ($sub4) {
            $aTmp[] = trim($sub4->text());
        }

        return implode(' ', $aTmp);
    }

    /**
     * @param  Doc  $document
     * @return string
     * @throws InvalidSelectorException
     */
    private function getVideo(Doc $document): string
    {
        $el = $document->first('#existing-iframe-example');
        if ($el) {
            return '<iframe frameborder="0" src="' . $el->attr("src") . '" width="640" height="360" class="note-video-clip"></iframe>';
        } else {
            return '';
        }
    }
}
