<?php

class ControllerExtensionModuleArserProduct extends Controller
{

    /**
     * вывод основной страницы
     */
    public function index()
    {
        $this->load->language('extension/module/arser_site');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/module/arser_site');
        $this->load->model('extension/module/arser_product');

        $this->getList();
    }

    /**
     * вывод основной страницы
     * вспомогательный блок
     */
    protected function getList()
    {
        $productId = $this->request->get['id'];
        $product = $this->model_extension_module_arser_product->getProduct($productId);
        $product = $product[0];
        $data['attr'] = unserialize($product['attr']);
        $data['product_option'] = unserialize($product['product_option']);
        $data['images'] = unserialize($product['images_link']);

        unset($product['images_link']);
        unset($product['attr']);
        unset($product['product_option']);
        $data['product'] = $product;

        $this->response->setOutput($this->load->view('extension/module/arser_preview', $data));
    }
}
