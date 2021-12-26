<?php

use DiDom\Document;

require_once(DIR_SYSTEM . 'helper/arser.php');

class ControllerExtensionModuleArserMb extends Controller
{
    private const HOME = 'https://mobi-mebel.ru';

    public function openGroup()
    {
        $json = [
            'link_group_count' => 0,
            'link_product_count' => 0,
            'status' => 'finish',
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
            'link' => $productLinks[0],
            'link_count' => $link_count['all'],
            'link_product_count' => ($link_count['ok'] ?? 0) + ($link_count['bad'] ?? 0),
            'status' => 'go',
        ];
        echo json_encode($json);
        return;
    }

    private function getUrl($link)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $link,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Cookie: f261601ce57a4ee01f9efde6727e03e5=srlhf2krg7d51dejkdmkkhcsd1'
            ),
        ));

        $response = curl_exec($curl);
        /* Check for 404 (file not found). */
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode == 404) {
            $response = 404;
        }
        curl_close($curl);
        return $response;
    }

    /**
     * Получаем информацию о продукте
     * @param array $link
     */
    private function parseProduct(array $link)
    {
        $this->load->model('extension/module/arser_link');
        $this->load->model('extension/module/arser_product');

        loadDidom();

        $result = $this->getUrl($link['link']);

        if ($result == 404) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Страница не существует');
            return;
        }

        $document = (new DiDom\Document($result));
        if (!$document) {
            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', 'Не удалось прочитать страницу');
            return;
        }

        // Получаем массив продуктов со страницы
        $products = $this->getProductInfo($document);

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
//        } catch (Exception $e) {
//            // установить статус у текущей групповой ссылки 'bad'
//            $this->model_extension_module_arser_link->setStatus($link['id'], 'bad', $e->getMessage() . ' in ' . $e->getLine());
//        }
    }

    private function getLinkProductColor(DiDom\Document $document): array
    {
        $url = [];
        $links = $document->find('#list_product_image_thumb img');
        foreach ($links as $el) {
            $id = self::normalSum($el->getAttribute('onclick'));
            $img = $document->first('#main_image_' . $id)->attr('src');
            $title = $el->getAttribute('title');
            $url[$id] = ['title' => $title, 'imageLink' => $img];
        }

        return $url;
    }

    private function getLinkProduct(DiDom\Document $document): array
    {
        $url = [];
        $links = $document->find('a.list-product-item');
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
     * @param string $str
     * @return string
     */
    private function trimScript(string $str): string
    {
        return preg_split("/<script>/", $str)[0];
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

    /**
     * @param \DiDom\Element $element
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getImg(DiDom\Document $doc): array
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

    private function getTopic(DiDom\Document $doc): string
    {
        $res = '';
        $item = $doc->first('.module_description b');
        if ($item) {
            $res = $item->text();
        }

        return $res;
    }

    private function getDopImg(DiDom\Document $document)
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

    private function getSize(DiDom\Document $doc)
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

    private function getDescription(DiDom\Document $doc, array $size): string
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

    private function getCommonDescription(DiDom\Document $document)
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

    private function getSection(Didom\Document $document, string $sectionName): string
    {
        $res = [];
        $el = $document->first("h1:contains('{$sectionName}')");

        if ($el) {
            $res[] = "{$sectionName} ";
            if ($el1 = $el->closest('div.panel-grid')->nextSibling()) {
//                $res[] = $this->getContent($el1);
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

    private function getContent(Didom\Element $el): string
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
     * @param Document $document
     * @return string
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    private function getVideo(Document $document)
    {
        $el = $document->first('#existing-iframe-example');
        if ($el) {
            return '<iframe frameborder="0" src="' . $el->attr("src") . '" width="640" height="360" class="note-video-clip"></iframe>';
        } else {
            return '';
        }
    }
}
