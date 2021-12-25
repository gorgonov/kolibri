<?php

class ControllerExtensionModuleArserSite extends Controller
{
    private $error = [];

    /**
     * Check if the table 'ar_site' exists
     * @return boolean TRUE if table exists, FALSE otherwise.
     */
    public function CheckCustomer()
    {
        $res = $this->db->query("SHOW TABLES LIKE 'ar_site'");
        return (bool)$res->num_rows;
    }

    /**
     * вывод основной страницы
     */
    public function index()
    {
        $this->load->language('extension/module/arser_site');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/arser_site');

        $this->getList();
    }

    /**
     * вывод основной страницы
     * вспомогательный блок
     */
    protected function getList()
    {
        // определение вида сортировки
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/module/arser_site',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            )
        );

        // кнопки
        $data['add'] = $this->url->link(
            'extension/module/arser_site/add',
            'user_token=' . $this->session->data['user_token'] . $url,
            true
        );
        $data['delete'] = $this->url->link(
            'extension/module/arser_site/delete',
            'user_token=' . $this->session->data['user_token'] . $url,
            true
        );

        // это что ?
        $data['modal'] = $this->url->link(
            'extension/module/arser_site/modal',
            'user_token=' . $this->session->data['user_token'] . $url,
            true
        );

        $filter_data = array(
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin')
        );

        $this->model_extension_module_arser_site->recalcProducts();

        $site_total = $this->model_extension_module_arser_site->getTotalSites();

        $results = $this->model_extension_module_arser_site->getSites($filter_data);

        $data['sites'] = [];
        foreach ($results as $result) {
            $data['sites'][] = array(
                'id' => $result['id'],
                'name' => $result['name'],
                'link' => $result['link'],
                'modulname' => $result['modulname'],
                'provider' => $result['provider'],
                'min_id' => $result['min_id'],
                'stock' => $result['stock'],
                'prefix' => $result['prefix'],
                'status' => (!$result['message'] == '') ? $result['message'] : $result['status'],
                'productcount' => $result['productcount'],
                'edit' => $this->url->link(
                    'extension/module/arser_site/edit',
                    'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url,
                    true
                ),
                /*
                'export' => $this->url->link(
                    'extension/module/arser_site/export',
                    'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url,
                    true
                ),
                'get' => $this->url->link(
                    'extension/module/arser_site/setGetstatus',
                    'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url,
                    true
                ),
                //                'import' => $this->url->link('extension/module/arser_site/import', 'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url, true),
                'getimage' => $this->url->link(
                    'extension/module/arser_site/getimage',
                    'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url,
                    true
                ),
                */
                'delete' => $this->url->link(
                    'extension/module/arser_site/delete',
                    'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url,
                    true
                )
            );
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];

            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->request->post['selected'])) {
            $data['selected'] = (array)$this->request->post['selected'];
        } else {
            $data['selected'] = [];
        }

        $url = '';

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['sort_name'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url,
            true
        );
        $data['sort_link'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . '&sort=link' . $url,
            true
        );
        $data['sort_modulname'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . '&sort=modulname' . $url,
            true
        );
        $data['sort_minid'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . '&sort=minid' . $url,
            true
        );
        $data['sort_maxid'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . '&sort=maxid' . $url,
            true
        );
        $data['sort_prefix'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . '&sort=prefix' . $url,
            true
        );
        $data['sort_status'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url,
            true
        );

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        $pagination = new Pagination();
        $pagination->total = $site_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . $url . '&page={page}',
            true
        );

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($site_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
            ((($page - 1) * $this->config->get(
                        'config_limit_admin'
                    )) > ($site_total - $this->config->get(
                        'config_limit_admin'
                    ))) ? $site_total : ((($page - 1) * $this->config->get(
                        'config_limit_admin'
                    )) + $this->config->get('config_limit_admin')),
            $site_total,
            ceil($site_total / $this->config->get('config_limit_admin'))
        );

        $data['sort'] = $sort;
        $data['order'] = $order;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/arser_site', $data));
    }

    /**
     * Настройка
     * todo Может быть удалить?
     *
     */
    public function setting()
    {
        $this->load->language('extension/module/arser_site');

        $this->document->setTitle($this->language->get('heading_setting'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/arser_site');

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            // Вызываем метод "модели" для сохранения настроек
            $this->model_extension_module_arser_site->SaveSettings();

            // Выходим из настроек с выводом сообщения
            $this->session->data['success'] = 'Настройки сохранены';
            $this->response->redirect(
                $this->url->link(
                    'extension/module/arser_site',
                    'user_token=' . $this->session->data['user_token'] . '&type=module',
                    true
                )
            );
        }

        // Загружаем настройки через метод "модели"
        $setting = $this->model_extension_module_arser_site->LoadSettings();

        $data = [];
        $data['text_form'] = $this->language->get('heading_setting');
        $data['arser_status'] = $setting['arser_status'] ?? 1;
        $data['arser_import_path'] = $setting['arser_import_path'] ?? '';

        // Загружаем "хлебные крошки"
        $url = '';
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/module/arser_site',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            )
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_setting'),
            'href' => $this->url->link(
                'extension/module/arser_site/setting',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            )
        );

        // Кнопки действий
        $data['action'] = $this->url->link(
            'extension/module/arser_site/setting',
            'user_token=' . $this->session->data['user_token'] . $url,
            true
        );
        $data['cancel'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . $url,
            true
        );

        // Загрузка шаблонов для шапки, колонки слева и футера
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        // Выводим в браузер шаблон
        $this->response->setOutput($this->load->view('extension/module/site_setting', $data));
    }


    /**
     * Добавление сайта для парсинга
     */
    public function add()
    {
        $this->load->language('extension/module/arser_site');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('extension/module/arser_site');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            /** ModelExtensionModuleArserSite $this->model_extension_module_arser_site */
            $siteId = $this->model_extension_module_arser_site->addSite($this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $url = '';

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

            $this->response->redirect(
                $this->url->link(
                    'extension/module/arser_site',
                    'user_token=' . $this->session->data['user_token'] . $url,
                    true
                )
            );
        }

        $this->getForm();
    }

    /**
     * изменение
     */
    public function edit()
    {
        $this->load->language('extension/module/arser_site');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('extension/module/arser_site');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_module_arser_site->editSite($this->request->get['id'], $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $url = '';

            if (isset($this->request->get['sort'])) {
                $url .= ' & sort = ' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= ' & order = ' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= ' & page = ' . $this->request->get['page'];
            }

            $this->response->redirect(
                $this->url->link(
                    'extension/module/arser_site',
                    'user_token=' . $this->session->data['user_token'] . $url,
                    true
                )
            );
        }

        $this->getForm();
    }

    /**
     * валидация полей формы перед сохранением
     * @return bool
     */
    protected function validateForm()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/arser_site')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 30)) {
            $this->error['name'] = $this->language->get('error_name');
        }

        if ((utf8_strlen($this->request->post['prefix']) < 1) || (utf8_strlen($this->request->post['prefix']) > 5)) {
            $this->error['prefix'] = $this->language->get('error_prefix');
        }

        if ((utf8_strlen($this->request->post['modulname']) < 2)) {
            $this->error['modulname'] = $this->language->get('error_empty');
        }

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        return !$this->error;
    }

    /**
     * форма для добавления/изменения
     */
    protected function getForm()
    {
        $data['text_form'] = !isset($this->request->get['id'])
            ? $this->language->get('text_add')
            : $this->language->get('text_edit');

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : [];
        $data['error_link'] = isset($this->error['link']) ? $this->error['link'] : [];
        $data['error_modulname'] = isset($this->error['modulname']) ? $this->error['modulname'] : [];
        $data['error_minid'] = isset($this->error['minid']) ? $this->error['minid'] : [];
        $data['error_prefix'] = isset($this->error['prefix']) ? $this->error['prefix'] : [];
        $data['error_status'] = isset($this->error['status']) ? $this->error['status'] : '';
        $data['error_group_name'] = isset($this->error['group_name']) ? $this->error['group_name'] : '';

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= ' & sort = ' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= ' & order = ' . $this->request->get['order'];
        }

        if (isset($this->request->get['page'])) {
            $url .= ' & page = ' . $this->request->get['page'];
        }

        // крошки
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/module/arser_site',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            )
        ];

        // формирование данных для кнопок
        if (!isset($this->request->get['id'])) {
            $data['action'] = $this->url->link(
                'extension/module/arser_site/add',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            );
        } else {
            $data['action'] = $this->url->link(
                'extension/module/arser_site/edit',
                'user_token=' . $this->session->data['user_token'] . '&id=' . $this->request->get['id'] . $url,
                true
            );
        }

        $data['cancel'] = $this->url->link(
            'extension/module/arser_site',
            'user_token=' . $this->session->data['user_token'] . $url,
            true
        );

        // получаем данные для вывода
        if (isset($this->request->get['id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
            $site_info = $this->model_extension_module_arser_site->getSite($this->request->get['id']);
        }

        $data['user_token'] = $this->session->data['user_token'];

        $this->load->model('localisation / language');
        $data['languages'] = $this->model_localisation_language->getLanguages();


        // заполним значения полей для формы
        $fields = [
            'name',
            'modulname',
            'jan',
            'model',
            'manufacturer',
            'provider',
            'stock',
            'maker',
            'execution_period',
            'prefix',
            'min_id',
            'group_name',
            'status',
        ];

        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } elseif (!empty($site_info)) {
                $data[$field] = $site_info[$field];
            } else {
                $data[$field] = '';
            }
        }

        $data['pages'] = $this->getPages(0);

        $data['header'] = $this->load->controller('common / header');
        $data['column_left'] = $this->load->controller('common / column_left');
        $data['footer'] = $this->load->controller('common / footer');

        $this->response->setOutput($this->load->view('extension / module / site_form', $data));
    }

    public function import_setting()
    {
        $this->load->language('extension/module/arser_site');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/arser_import_setting');
        $this->load->model('extension/module/arser_site');

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $this->model_extension_module_arser_import_setting->editSetting($this->request->get['id'],
                $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link(
                    'extension/module/arser_site/import_setting',
                    'user_token=' . $this->session->data['user_token'] . '&id=' . $this->request->get['id'],
                    true
                )
            );
        }

        $this->getFormImportSetting();
    }


    private function getFormImportSetting()
    {
//        $data['text_form'] = !isset($this->request->get['id'])
//            ? $this->language->get('text_add')
//            : $this->language->get('text_edit');
        $this->document->setTitle($this->language->get('heading_title'));

        $siteId = $this->request->get['id'];
        $site = $this->model_extension_module_arser_site->getSite($siteId);
        $data['dn_name'] = $site['name'];

        // todo поправить сообщения об ошибках и поля
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : [];
        $data['error_barcode'] = isset($this->error['barcode']) ? $this->error['barcode'] : [];
        $data['error_sku'] = isset($this->error['sku']) ? $this->error['sku'] : [];
        $data['error_weight'] = isset($this->error['weight']) ? $this->error['weight'] : [];
        $data['error_volume'] = isset($this->error['volume']) ? $this->error['volume'] : [];
        $data['error_quantity'] = isset($this->error['quantity']) ? $this->error['quantity'] : [];
        $data['error_price'] = isset($this->error['price']) ? $this->error['price'] : '';
        $data['error_articul'] = isset($this->error['articul']) ? $this->error['articul'] : '';

        $url = '';

        // крошки
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/module/arser_site',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            )
        ];

        // формирование данных для кнопок
        if (!isset($this->request->get['id'])) {
            $data['action'] = $this->url->link(
                'extension/module/arser_site/import_setting',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            );
        } else {
            $data['action'] = $this->url->link(
                'extension/module/arser_site/import_setting',
                'user_token=' . $this->session->data['user_token'] . '&id=' . $this->request->get['id'] . $url,
                true
            );
        }

        // получаем данные для вывода
        if (isset($this->request->get['id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
            $import_column = $this->model_extension_module_arser_import_setting->getSetting($this->request->get['id']);
        }

        $data['user_token'] = $this->session->data['user_token'];

        $this->load->model('localisation / language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        // заполним значения полей для формы
        $fields = [
            'name',
            'barcode',
            'sku',
            'weight',
            'volume',
            'quantity',
            'price',
            'header_rows',
            'number_packages',
        ];

        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } elseif (!empty($import_column)) {
                $data[$field] = $import_column[$field];
            } else {
                $data[$field] = '';
            }
        }
        $data['id_type_selected'] = $import_column['id_type'] ?? 1;
        $data['id_types'] = [
            ['id' => 1, 'name' => 'По sku'],
            ['id' => 2, 'name' => 'По наименованию'],
            ['id' => 3, 'name' => 'По части наименования'],
        ];

        $data['pages'] = $this->getPages(3);

        $data['header'] = $this->load->controller('common / header');
        $data['column_left'] = $this->load->controller('common / column_left');
        $data['footer'] = $this->load->controller('common / footer');

        $this->response->setOutput($this->load->view('extension / module / arser_import_setting', $data));
    }

    // получаем данные для вывода закладки Продукты
    protected function getFormProduct()
    {
        $this->document->setTitle($this->language->get('heading_title'));

        $siteId = $this->request->get['id'];
        $site = $this->model_extension_module_arser_site->getSite($siteId);
        $data['name'] = $site['name'];

        $linkFilter = $this->request->get['status'] ?? 'all';
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        // определение вида сортировки
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'sku';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        // формирование ссылок с учетом сортировки, страницы
        $url = '';
        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }
        $url .= '&page=' . $page . '&status=' . $linkFilter . '&id=' . $siteId;

        $data['sort_id'] = $this->url->link(
            'extension/module/arser_site/product',
            'user_token=' . $this->session->data['user_token'] . '&sort=id' . $url,
            true
        );

        $data['sort_sku'] = $this->url->link(
            'extension/module/arser_site/product',
            'user_token=' . $this->session->data['user_token'] . '&sort=sku' . $url,
            true
        );

        $data['sort_link'] = $this->url->link(
            'extension/module/arser_site/product',
            'user_token=' . $this->session->data['user_token'] . '&sort=link' . $url,
            true
        );

        $data['sort_name'] = $this->url->link(
            'extension/module/arser_site/product',
            'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url,
            true
        );

        $data['sort_status'] = $this->url->link(
            'extension/module/arser_site/product',
            'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url,
            true
        );

        $data['ajax_link'] = $this->url->link(
            'extension/module/arser_' . $site['modulname'] . '/parseNextProduct&user_token=' . $this->session->data['user_token'] . '&site_id=' . $siteId,
            '',
            true
        );

        $data['search_text'] = $this->request->post['search_text'] ?? '';

        // формируем данные для фильтрации и построении радиокнопок справа
        $productCount = $this->model_extension_module_arser_product->getProductCount($siteId, $data['search_text']);
        $data['href'] = $this->url->link(
            'extension/module/arser_site/product',
            'user_token=' . $this->session->data['user_token'] . '&sort=' . $sort . '&id=' . $siteId . '&order=' . $order . '&page=' . $page . '&status=',
            true
        );
        $data['linkFilters'] = [
            'all' => [
                'name' => 'Все',
                'count' => $productCount['all'] ?? 0,
                'checked' => $linkFilter == 'all' ? 'checked' : ''
            ],
            'ok' => [
                'name' => 'Обработанные',
                'count' => $productCount['ok'] ?? 0,
                'checked' => $linkFilter == 'ok' ? 'checked' : ''
            ],
            'new' => [
                'name' => 'НЕ обработанные',
                'count' => $productCount['new'] ?? 0,
                'checked' => $linkFilter == 'new' ? 'checked' : ''
            ],
            'bad' => [
                'name' => 'С ошибками',
                'count' => $productCount['bad'] ?? 0,
                'checked' => $linkFilter == 'bad' ? 'checked' : ''
            ],
            'del' => [
                'name' => 'Удаленные',
                'count' => $productCount['del'] ?? 0,
                'checked' => $linkFilter == 'del' ? 'checked' : ''
            ],
        ];

        // получаем данные для вывода продуктов
        $filter_data = array(
            'filter' => $linkFilter,
            'search' => $data['search_text'],
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin')
        );
        $products = $this->model_extension_module_arser_product->getProducts($siteId, $filter_data);
        $classStatus = [
            'new' => 'alert-secondary',
            'ok' => 'alert-success',
            'del' => 'alert-warning',
            'bad' => 'alert-danger',
            'price' => 'alert-primary',
        ];
        $data['hrefs'] = [];
        foreach ($products as $product) {
            $data['products'][] = [
                'id' => $product['id'],
                'link' => $product['link'],
                'sku' => $product['sku'],
                'name' => $product['name'],
                'status' => $product['status'],
                'category' => $product['category'],
                'category1c' => $product['category1c'],
                'message' => $product['message'],
                'class' => $classStatus[$product['status']] ?? '',
            ];
        }

        // todo пока не используется во вьюшке
        if (isset($this->request->post['selected'])) {
            $data['selected'] = (array)$this->request->post['selected'];
        } else {
            $data['selected'] = array();
        }

        // крошки
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/module/arser_site',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            )
        ];

        // формирование данных для кнопок
        $data['link_preview'] = $this->url->link(
            'extension/module/arser_product',
            'user_token=' . $this->session->data['user_token'],
            true
        );

        $data['user_token'] = $this->session->data['user_token'];

        $this->load->model('localisation / language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $pagination = new Pagination();
        $pagination->total = $productCount[$linkFilter];
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link(
            'extension/module/arser_site/product',
            'user_token=' . $this->session->data['user_token'] . '&sort=' . $sort . '&id=' . $siteId . '&order=' . $order . '&status=' . $linkFilter . '&page={page}',
            true
        );
        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($pagination->total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
            ((($page - 1) * $this->config->get(
                        'config_limit_admin'
                    )) > ($pagination->total - $this->config->get(
                        'config_limit_admin'
                    ))) ? $pagination->total : ((($page - 1) * $this->config->get(
                        'config_limit_admin'
                    )) + $this->config->get('config_limit_admin')),
            $pagination->total,
            ceil($pagination->total / $this->config->get('config_limit_admin'))
        );

        $data['sort'] = $sort;
        $data['order'] = $order;

        $data['pages'] = $this->getPages(2);
        $data['header'] = $this->load->controller('common / header');
        $data['column_left'] = $this->load->controller('common / column_left');
        $data['footer'] = $this->load->controller('common / footer');

//        $this->getListForProduct$this->request->get['id']);
        $this->response->setOutput($this->load->view('extension / module / arser_product', $data));
    }

    // получаем данные для вывода закладки Сбор ссылок
    protected function getFormGrab()
    {
        $this->document->setTitle($this->language->get('heading_title'));

        $siteId = $this->request->get['id'];
        $site = $this->model_extension_module_arser_site->getSite($siteId);
        $data['name'] = $site['name'];

        $linkFilter = $this->request->get['status'] ?? 'all';
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        // определение вида сортировки
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'id';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        // формирование ссылок с учетом сортировки, страницы
        $url = '';
        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }
        $url .= '&page=' . $page . '&status=' . $linkFilter . '&id=' . $siteId;

        $data['sort_id'] = $this->url->link(
            'extension/module/arser_site/grab',
            'user_token=' . $this->session->data['user_token'] . '&sort=id' . $url,
            true
        );

        $data['sort_category_list'] = $this->url->link(
            'extension/module/arser_site/grab',
            'user_token=' . $this->session->data['user_token'] . '&sort=category_list' . $url,
            true
        );

        $data['sort_link'] = $this->url->link(
            'extension/module/arser_site/grab',
            'user_token=' . $this->session->data['user_token'] . '&sort=link' . $url,
            true
        );

        $data['sort_is_group'] = $this->url->link(
            'extension/module/arser_site/grab',
            'user_token=' . $this->session->data['user_token'] . '&sort=is_group' . $url,
            true
        );

        $data['sort_category1c'] = $this->url->link(
            'extension/module/arser_site/grab',
            'user_token=' . $this->session->data['user_token'] . '&sort=category1c' . $url,
            true
        );

        $data['ajax_link'] = $this->url->link(
            'extension/module/arser_' . $site['modulname'] . '/openGroup&user_token=' . $this->session->data['user_token'] . '&site_id=' . $siteId,
            '',
            true
        );

        $data['search_text'] = $this->request->post['search_text'] ?? '';

        // формируем данные для фильтрации и построении радиокнопок справа
        $linkCount = $this->model_extension_module_arser_link->getLinkCount($siteId, $data['search_text']);
        $data['href'] = $this->url->link(
            'extension/module/arser_site/grab',
            'user_token=' . $this->session->data['user_token'] . '&sort=' . $sort . '&id=' . $siteId . '&order=' . $order . '&page=' . $page . '&status=',
            true
        );

        $data['linkFilters'] = [
            'all' => [
                'name' => 'Все',
                'count' => $linkCount['all'] ?? 0,
                'checked' => $linkFilter == 'all' ? 'checked' : '',
            ],
            'ok' => [
                'name' => 'Обработанные',
                'count' => $linkCount['ok'] ?? 0,
                'checked' => $linkFilter == 'ok' ? 'checked' : '',
            ],
            'new' => [
                'name' => 'НЕ обработанные',
                'count' => $linkCount['new'] ?? 0,
                'checked' => $linkFilter == 'new' ? 'checked' : '',
            ],
            'bad' => [
                'name' => 'С ошибками',
                'count' => $linkCount['bad'] ?? 0,
                'checked' => $linkFilter == 'bad' ? 'checked' : '',
            ],
        ];

        // получаем данные для вывода ссылок
        $filter_data = array(
            'filter' => $linkFilter,
            'search' => $data['search_text'],
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin')
        );
        $links = $this->model_extension_module_arser_link->getLink($siteId, $filter_data);

        $classStatus = [
            'new' => 'alert-secondary',
            'ok' => 'alert-success',
            'bad' => 'alert-danger',
        ];
        $data['links'] = [];
        foreach ($links as $link) {
            $data['links'][] = [
                'id' => $link['id'],
                'category_list' => $link['category_list'],
                'link' => $link['link'],
                'category1c' => $link['category1c'],
                'is_group' => $link['is_group'],
                'status' => $link['status'],
                'message' => $link['message'],
                'class' => $classStatus[$link['status']] ?? '',
            ];
        }

        // todo пока не используется во вьюшке
        if (isset($this->request->post['selected'])) {
            $data['selected'] = (array)$this->request->post['selected'];
        } else {
            $data['selected'] = array();
        }

        // крошки
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/module/arser_site',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            )
        ];

        $data['user_token'] = $this->session->data['user_token'];

        $this->load->model('localisation / language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $pagination = new Pagination();
        $pagination->total = $linkCount[$linkFilter] ?? 0;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link(
            'extension/module/arser_site/grab',
            'user_token=' . $this->session->data['user_token'] . '&sort=' . $sort . '&id=' . $siteId . '&order=' . $order . '&status=' . $linkFilter . '&page={page}',
            true
        );
        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($pagination->total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
            ((($page - 1) * $this->config->get(
                        'config_limit_admin'
                    )) > ($pagination->total - $this->config->get(
                        'config_limit_admin'
                    ))) ? $pagination->total : ((($page - 1) * $this->config->get(
                        'config_limit_admin'
                    )) + $this->config->get('config_limit_admin')),
            $pagination->total,
            ceil($pagination->total / $this->config->get('config_limit_admin'))
        );

        $data['sort'] = $sort;
        $data['order'] = $order;

        $data['pages'] = $this->getPages(1);
        $data['header'] = $this->load->controller('common / header');
        $data['column_left'] = $this->load->controller('common / column_left');
        $data['footer'] = $this->load->controller('common / footer');

        $this->response->setOutput($this->load->view('extension / module / arser_grab', $data));
    }

    /**
     * вывод подготовки списка страниц для парсинга
     */
//    protected function getListForProduct($siteId)
//    {
//        if (isset($this->request->get['sort'])) {
//            $sort = $this->request->get['sort'];
//        } else {
//            $sort = 'id';
//        }
//
//        if (isset($this->request->get['order'])) {
//            $order = $this->request->get['order'];
//        } else {
//            $order = 'ASC';
//        }
//
//        if (isset($this->request->get['page'])) {
//            $page = $this->request->get['page'];
//        } else {
//            $page = 1;
//        }
//
//        $url = '';
//
//        if (isset($this->request->get['sort'])) {
//            $url .= '&sort=' . $this->request->get['sort'];
//        }
//
//        if (isset($this->request->get['order'])) {
//            $url .= '&order=' . $this->request->get['order'];
//        }
//
//        if (isset($this->request->get['page'])) {
//            $url .= '&page=' . $this->request->get['page'];
//        }
//
//        $filter_data = array(
//            'sort' => $sort,
//            'order' => $order,
//            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
//            'limit' => $this->config->get('config_limit_admin')
//        );
//
//        $products = $this->model_extension_module_arser_product->getProducts($siteId);
//
//        $product_total = $this->model_extension_module_arser_product->getProductCount($siteId);
//
//        $data['products'] = [];
//        foreach ($products as $result) {
//            $data['products'][] = array(
//                'id' => $result['id'],
//                'name' => $result['name'],
//                'sku' => $result['sku'],
//            );
//        }
//
//        if (isset($this->error['warning'])) {
//            $data['error_warning'] = $this->error['warning'];
//        } else {
//            $data['error_warning'] = '';
//        }
//
//        if (isset($this->session->data['success'])) {
//            $data['success'] = $this->session->data['success'];
//
//            unset($this->session->data['success']);
//        } else {
//            $data['success'] = '';
//        }
//
//        if (isset($this->request->post['selected'])) {
//            $data['selected'] = (array)$this->request->post['selected'];
//        } else {
//            $data['selected'] = [];
//        }
//
//        $url = '';
//
//        if ($order == 'ASC') {
//            $url .= '&order=DESC';
//        } else {
//            $url .= '&order=ASC';
//        }
//
//        if (isset($this->request->get['page'])) {
//            $url .= '&page=' . $this->request->get['page'];
//        }
//
//        $data['sort_name'] = $this->url->link(
//            'extension/module/arser_site',
//            'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url,
//            true
//        );
//        $data['sort_sku'] = $this->url->link(
//            'extension/module/arser_site',
//            'user_token=' . $this->session->data['user_token'] . '&sort=sku' . $url,
//            true
//        );
//        $data['sort_modulname'] = $this->url->link(
//            'extension/module/arser_site',
//            'user_token=' . $this->session->data['user_token'] . '&sort=id' . $url,
//            true
//        );
//
//        $url = '';
//
//        if (isset($this->request->get['sort'])) {
//            $url .= '&sort=' . $this->request->get['sort'];
//        }
//
//        if (isset($this->request->get['order'])) {
//            $url .= '&order=' . $this->request->get['order'];
//        }
//
//        $total = $product_total['all'];
//        $pagination = new Pagination();
//        $pagination->total = $total;
//        $pagination->page = $page;
//        $pagination->limit = $this->config->get('config_limit_admin');
//        $pagination->url = $this->url->link(
//            'extension/module/arser_grab',
//            'user_token=' . $this->session->data['user_token'] . $url . '&page={page}',
//            true
//        );
//
//        $data['pagination'] = $pagination->render();
//
//        $data['results'] = sprintf(
//            $this->language->get('text_pagination'),
//            ($total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
//            ((($page - 1) * $this->config->get(
//                        'config_limit_admin'
//                    )) > ($total - $this->config->get(
//                        'config_limit_admin'
//                    ))) ? $total : ((($page - 1) * $this->config->get(
//                        'config_limit_admin'
//                    )) + $this->config->get('config_limit_admin')),
//            $total,
//            ceil($total / $this->config->get('config_limit_admin'))
//        );
//
//        $data['sort'] = $sort;
//        $data['order'] = $order;
//
//        $data['header'] = $this->load->controller('common/header');
//        $data['column_left'] = $this->load->controller('common/column_left');
//        $data['footer'] = $this->load->controller('common/footer');
//        $this->response->setOutput($this->load->view('extension/module/arser_grab', $data));
//    }

    // закладка Продукты
    public function product()
    {
        $this->load->language('extension/module/arser_site');
        $this->load->model('extension/module/arser_site');
        $this->load->model('extension/module/arser_product');

        $siteId = $this->request->get['id'];
        $post = $this->request->post;

        // обработка кнопки Удаляем отмеченные
        if (isset($post['del_mark_link']) && isset($post['selected'])) {
            $productIds = $this->request->post['selected'];
            $this->model_extension_module_arser_product->deleteProducts($productIds);
        }

        // обработка кнопки Удаляем все
        if (isset($post['del_finish_link'])) {
            $this->model_extension_module_arser_product->deleteProductsBySite($siteId);
        }

        $this->getFormProduct();
    }

    // закладка Сбор ссылок
    public function grab()
    {
        $this->load->language('extension/module/arser_site');
        $this->load->model('extension/module/arser_site');
        $this->load->model('extension/module/arser_link');

        $siteId = $this->request->get['id'];
        $post = $this->request->post;

        // обработка кнопки Удаляем отмеченные
        if (isset($post['del_mark_link']) && isset($post['selected'])) {
            $linkIds = $this->request->post['selected'];
            $this->model_extension_module_arser_link->deleteLinks($linkIds);
        }

        // обработка кнопки Удаляем все
        if (isset($post['del_finish_link'])) {
            $this->model_extension_module_arser_link->deleteLinksBySite($siteId);
        }

        // загрузим список ссылок, если передан файл
        if ((isset($this->request->files['import'])) && (is_uploaded_file($this->request->files['import']['tmp_name']))) {
            $file = $this->request->files['import']['tmp_name'];
            if ($this->model_extension_module_arser_link->upload($file, $siteId) == true) {
                $this->session->data['success'] = $this->language->get('text_success');
            } else {
                $this->session->data['warning'] = $this->language->get('error_upload');
                $href = $this->url->link('tool/log', 'user_token=' . $this->session->data['user_token'], $this->ssl);
                $this->session->data['warning'] .= "<br />\n" . str_replace('%1', $href,
                        $this->language->get('text_log_details_3_x'));
            }
        }

        $this->getFormGrab();
    }

    /**
     * установить признак Status='get' для CRON'a (старт парсинга) по этому сайту
     */
//    public function setGetstatus()
//    {
//        if (isset($this->request->get['id'])) {
//            $site_id = $this->request->get['id'];
//        } else {
//            $this->error['warning'] = 'Не указан id сайта для парсинга.';
//            return !$this->error;
//        }
//
//        $this->load->model('extension/module/arser_site');
//        // Вызываем метод "модели" для сохранения настроек
//        $this->model_extension_module_arser_site->setStatusSite($site_id, 'get');
//
//        //        $json = array(
//        //            'status' => 'ok',
//        //            'message' => 'Признак get установлен',
//        //        );
//        //
//        //        $this->response->addHeader('Content-Type: application/json');
//        //        $this->response->setOutput(json_encode($json));
//        //
//        //        return;
//        $this->response->redirect(
//            $this->url->link(
//                'extension/module/arser_site',
//                'user_token=' . $this->session->data['user_token'] . '&type=module',
//                true
//            )
//        );
//        //        $this->getList();
//    }

    public function import()
    {
        $this->load->language('extension/module/arser_site');
        $this->document->setTitle($this->language->get('heading_import'));
        $this->load->model('extension/module/arser_site');
        $this->load->model('extension/module/arser_product');

        $siteId = $this->request->get['id'];
        $site = $this->model_extension_module_arser_site->getSite($siteId);

        $post = $this->request->post;

        // загрузим список ссылок, если передан файл
        if ((isset($this->request->files['import'])) && (is_uploaded_file($this->request->files['import']['tmp_name']))) {
            $file = $this->request->files['import']['tmp_name'];
            $this->load->model('extension/module/arser_import_setting');
            $importSetting = $this->model_extension_module_arser_import_setting->getSetting($siteId);
            $importSetting['min_id'] = $site['min_id'];

            if ($this->model_extension_module_arser_product->upload($file, $importSetting) == true) {
                $this->session->data['success'] = $this->language->get('text_success');
//                $this->response->redirect($this->url->link('extension/export_import', 'user_token=' . $this->session->data['user_token'], $this->ssl));
            } else {
                $this->session->data['warning'] = $this->language->get('error_upload');
                $href = $this->url->link('tool/log', 'user_token=' . $this->session->data['user_token'], $this->ssl);
                $this->session->data['warning'] .= "<br />\n" . str_replace('%1', $href,
                        $this->language->get('text_log_details_3_x'));
//                $this->response->redirect($this->url->link('extension/export_import', 'user_token=' . $this->session->data['user_token'], $this->ssl));
            }
        }

        // обработка кнопки Очистить отмеченные
        if (isset($post['clear_mark']) && isset($post['selected'])) {
            $productIds = $this->request->post['selected'];
            $this->model_extension_module_arser_product->clearProducts($productIds);
        }

        // обработка кнопки Очистить все
        if (isset($post['clear_all'])) {
            $this->model_extension_module_arser_product->clearProductsBySite($siteId);
        }

        // обработка кнопки Экспорт 1С
        if (isset($post['export_1c'])) {
            $this->export1c();
            return;
        }

        // обработка кнопки Экспорт import-export
        if (isset($post['export_old'])) {
            $this->export();
            return;
        }

        // обработка кнопки Экспорт import-export
        if (isset($post['getimage'])) {
//            <a href="http://kolibri.loc/admin/index.php?route=extension/module/arser_site/getimage&amp;user_token=4uB10g538QJLJUPjY55RpxT5MlMa3WiU&amp;id=1&amp;order=DESC&amp;page=1" data-toggle="tooltip" title="" class="btnbtn-primary clickmodalimg" data-original-title="Скачать картинки" mb-checked="1" data-tip=""><i class="fa fa-file-image-o"></i></a>
            $this->getimage();
            return;
        }

        $this->getFormImport();
    }

    protected function getFormImport()
    {
        $this->document->setTitle($this->language->get('heading_title'));

        $siteId = $this->request->get['id'];
        $site = $this->model_extension_module_arser_site->getSite($siteId);
        $data['name'] = $site['name'];
        $data['modulname'] = $site['modulname'];

        $linkFilter = $this->request->get['status'] ?? 'all';
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        // определение вида сортировки
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'sku';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        // формирование ссылок с учетом сортировки, страницы
        $url = '';
        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }
        $url .= '&page=' . $page . '&status=' . $linkFilter . '&id=' . $siteId;

        $data['sort_id'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=id' . $url,
            true
        );

        $data['sort_sku'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=sku' . $url,
            true
        );

        $data['sort_barcode'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=barcode' . $url,
            true
        );

        $data['sort_weight'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=weight' . $url,
            true
        );

        $data['sort_volume'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=volume' . $url,
            true
        );

        $data['sort_name'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url,
            true
        );

        $data['sort_quantity'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=quantity' . $url,
            true
        );

        $data['sort_price'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=price' . $url,
            true
        );

        $data['sort_number_packages'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=number_packages' . $url,
            true
        );

        $data['ajax_link'] = $this->url->link(
            'extension/module/arser_site/getimage&user_token=' . $this->session->data['user_token'] . '&id=' . $siteId,
            '',
            true
        );

        $data['search_text'] = $this->request->post['search_text'] ?? '';

        // формируем данные для фильтрации и построении радиокнопок справа
        $productCount = $this->model_extension_module_arser_product->getProductCount($siteId, $data['search_text']);
        $data['href'] = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=' . $sort . '&id=' . $siteId . '&order=' . $order . '&page=' . $page . '&status=',
            true
        );

        $data['linkFilters'] = [
            'all' => [
                'name' => 'Все',
                'count' => $productCount['all'] ?? 0,
                'checked' => $linkFilter == 'all' ? 'checked' : '',
            ],
            'ok' => [
                'name' => 'Обработанные',
                'count' => $productCount['ok'] ?? 0,
                'checked' => $linkFilter == 'ok' ? 'checked' : '',
            ],
            'new' => [
                'name' => 'НЕ обработанные',
                'count' => $productCount['new'] ?? 0,
                'checked' => $linkFilter == 'new' ? 'checked' : '',
            ],
            'bad' => [
                'name' => 'С ошибками',
                'count' => $productCount['bad'] ?? 0,
                'checked' => $linkFilter == 'bad' ? 'checked' : '',
            ],
        ];

        // получаем данные для вывода продуктов
        $filter_data = array(
            'filter' => $linkFilter,
            'search' => $data['search_text'],
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin')
        );
        $products = $this->model_extension_module_arser_product->getProducts($siteId, $filter_data);

        $classStatus = [
            'new' => 'alert-secondary',
            'ok' => 'alert-success',
            'del' => 'alert-warning',
            'bad' => 'alert-danger',
            'price' => 'alert-primary',
        ];
        $data['products'] = [];
        foreach ($products as $product) {
//            $phpInfo = unserialize($product['product_info']);
            $data['products'][] = [
                'id' => $product['id'],
                'link' => $product['link'],
                'sku' => $product['sku'],
                'name' => $product['name'],
                'barcode' => $product['barcode'],
                'weight' => $product['weight'],
                'volume' => $product['volume'],
                'quantity' => $product['quantity'],
                'price' => $product['price'],
                'number_packages' => $product['number_packages'],

                'status' => $product['status'],
                'category' => $product['category'],
                'category1c' => $product['category1c'],
                'message' => $product['message'],
                'class' => $classStatus[$product['status']] ?? '',
            ];
        }

        // todo пока не используется во вьюшке
        if (isset($this->request->post['selected'])) {
            $data['selected'] = (array)$this->request->post['selected'];
        } else {
            $data['selected'] = array();
        }

        // крошки
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/module/arser_site',
                'user_token=' . $this->session->data['user_token'] . $url,
                true
            )
        ];

        // формирование данных для кнопок
        $data['link_preview'] = $this->url->link(
            'extension/module/arser_import',
            'user_token=' . $this->session->data['user_token'],
            true
        );

        $data['user_token'] = $this->session->data['user_token'];

        $this->load->model('localisation / language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $pagination = new Pagination();
        $pagination->total = $productCount[$linkFilter] ?? $productCount['all'];
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link(
            'extension/module/arser_site/import',
            'user_token=' . $this->session->data['user_token'] . '&sort=' . $sort . '&id=' . $siteId . '&order=' . $order . '&status=' . $linkFilter . '&page={page}',
            true
        );
        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($pagination->total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
            ((($page - 1) * $this->config->get(
                        'config_limit_admin'
                    )) > ($pagination->total - $this->config->get(
                        'config_limit_admin'
                    ))) ? $pagination->total : ((($page - 1) * $this->config->get(
                        'config_limit_admin'
                    )) + $this->config->get('config_limit_admin')),
            $pagination->total,
            ceil($pagination->total / $this->config->get('config_limit_admin'))
        );

        $data['sort'] = $sort;
        $data['order'] = $order;


        $data['pages'] = $this->getPages(4);
        $data['header'] = $this->load->controller('common / header');
        $data['column_left'] = $this->load->controller('common / column_left');
        $data['footer'] = $this->load->controller('common / footer');

        $this->response->setOutput($this->load->view('extension / module / arser_import', $data));
    }


    public function delete()
    {
        $this->load->language('extension/module/arser_site');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/arser_site');

        if (isset($this->request->post['selected']) && $this->validateDelete()) {
            foreach ($this->request->post['selected'] as $id) {
                $this->model_extension_module_arser_site->deleteSite($id);
            }

            $this->session->data['success'] = $this->language->get('text_success');

            $url = '';

            if (isset($this->request->get['sort'])) {
                $url .= ' & sort = ' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= ' & order = ' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= ' & page = ' . $this->request->get['page'];
            }

            $this->response->redirect(
                $this->url->link(
                    'extension/module/arser_site',
                    'user_token=' . $this->session->data['user_token'] . $url,
                    true
                )
            );
        }

        $this->getList();
    }

    protected function validateDelete()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/arser_site')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * todo нерабочий метод?
     * @throws Exception
     */
    public function repair()
    {
        $this->load->language('extension/module/arser_site');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/arser_site');

        if ($this->validateRepair()) {
            $this->model_extension_module_arser_site->repairCategories();

            $this->session->data['success'] = $this->language->get('text_success');

            $url = '';

            if (isset($this->request->get['sort'])) {
                $url .= ' & sort = ' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= ' & order = ' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= ' & page = ' . $this->request->get['page'];
            }

            $this->response->redirect(
                $this->url->link(
                    'extension/module/arser_site',
                    'user_token=' . $this->session->data['user_token'] . $url,
                    true
                )
            );
        }

        $this->getList();
    }

    protected function validateRepair()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/arser_site')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * todo нерабочий метод ?
     * @throws Exception
     */
    public function autocomplete()
    {
        $json = [];

        if (isset($this->request->get['filter_name'])) {
            $this->load->model('catalog / category');

            $filter_data = array(
                'filter_name' => $this->request->get['filter_name'],
                'sort' => 'name',
                'order' => 'ASC',
                'start' => 0,
                'limit' => 5
            );

            $results = $this->model_extension_module_arser_site->getCategories($filter_data);

            foreach ($results as $result) {
                $json[] = array(
                    'category_id' => $result['category_id'],
                    'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF - 8'))
                );
            }
        }

        $sort_order = [];

        foreach ($json as $key => $value) {
            $sort_order[$key] = $value['name'];
        }

        array_multisort($sort_order, SORT_ASC, $json);

        $this->response->addHeader('Content - Type: application / json');
        $this->response->setOutput(json_encode($json));
    }

    public function export1c()
    {
        if (isset($this->request->get['id'])) {
            $site_id = $this->request->get['id'];
        } else {
            $this->error['warning'] = 'Не указан id сайта для экспорта.';
            return !$this->error;
        }

        try {
            // we use the PHPExcel package from https://github.com/PHPOffice/PHPExcel
            $cwd = getcwd();
            $dir = version_compare(VERSION, '3.0', '>=') ? 'library/export_import' : 'PHPExcel';
            chdir(DIR_SYSTEM . $dir);
            require_once('Classes/PHPExcel.php');
            chdir($cwd);
        } catch (Exception $e) {
            $errstr = $e->getMessage();
            $errline = $e->getLine();
            $errfile = $e->getFile();
            $errno = $e->getCode();

            // этот блок надо убрать?
            $this->session->data['export_import_error'] = array(
                'errstr' => $errstr,
                'errno' => $errno,
                'errfile' => $errfile,
                'errline' => $errline
            );
            if ($this->config->get('config_error_log')) {
                $this->log->write(
                    'PHP ' . get_class($e) . ':  ' . $errstr . ' in ' . $errfile . ' on line ' . $errline
                );
            }
            $this->error['warning'] = 'Предупреждаю!!! Ошибка при создании класса PHPExcel';
            $data['error_warning'] = 'Какая-то ошибка при создании класса PHPExcel';
            $this->response->redirect(
                $this->url->link(
                    'extension/module/arser_site',
                    'user_token=' . $this->session->data['user_token'],
                    true
                )
            );
            return false;
        }

        $this->load->model('extension/module/arser_site');
        $site = $this->model_extension_module_arser_site->getSite($site_id);
        $xlsName = $site['modulname'] . '_1c.xlsx'; // этот файл вернем пользователю
        $prefix = $site['prefix'];
        $product_id = $site['min_id'];


        ob_end_clean();
        //--- create php excel object ---
        $objPHPExcel = new PHPExcel();
        ini_set('memory_limit', '3500M');
        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array('memoryCacheSize' => '800MB');
        //set php excel settings
        PHPExcel_Settings::setCacheStorageMethod(
            $cacheMethod, $cacheSettings
        );

        $objPHPExcel->getProperties()->setTitle("export " . $site['modulname'])->setDescription("none");
        $objPHPExcel->setActiveSheetIndex(0);

        $worksheet = $objPHPExcel->getActiveSheet();
        $worksheet->fromArray(
            [
                'Артикул',
                'Штрихкод',
                'Номенклатура (наименование)',
                'Группа (наименование)',
                'Ед. изм.',
                'Категория номенклатуры',
                'Цена',
                'Поставщик (ИНН или наименование)',
                'Склад (наименование)',
                'Изготовитель (наименование)',
                'Описание',
                'Срок исполнения заказа',
                'Вес брутто, кг',
                'Объем, м3',
                'Количество упаковок',
                'Гарантийный срок',
                'Длина',
                'Ширина',
                'Высота',
                'Глубина',
                'Материал корпуса',
                'Материал фасада',
                'Цвет',
            ],
            null,
            'A1'
        );

        $this->load->model('extension/module/arser_product');
        $products = $this->model_extension_module_arser_product->getProducts($site_id);

        $cur_row = 2;
        foreach ($products as $product) {
            $attr = unserialize($product['attr']);
            if (empty($product['sku'])) {
                $product['sku'] = $product_id;
            }
            $product_id++;

            $col = 0;
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row,
                $prefix . '-' . $product['sku']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $product['barcode']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $product['name']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $site['group_name']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, 'шт');
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $product['category1c']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $product['price']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $site['provider']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $site['stock']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $site['maker']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $product['description']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $site['execution_period']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $product['weight']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $product['volume']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $product['number_packages']);
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $site['jan']);

            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $attr['Длина'] ?? '');
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $attr['Ширина'] ?? '');
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $attr['Высота'] ?? '');
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $attr['Глубина'] ?? '');
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row,
                $attr['Материал корпуса'] ?? '');
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row,
                $attr['Материал фасада'] ?? '');
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col++, $cur_row, $attr['Цвет'] ?? '');

            $cur_row++;
        }

        //redirect to cleint browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $xlsName);
        header('Cache-Control: max-age=0');

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
    }

    public function export()
    {
        if (isset($this->request->get['id'])) {
            $site_id = $this->request->get['id'];
        } else {
            $this->error['warning'] = 'Не указан id сайта для экспорта.';
            return !$this->error;
        }

        try {
            // we use the PHPExcel package from https://github.com/PHPOffice/PHPExcel
            $cwd = getcwd();
            $dir = version_compare(VERSION, '3.0', '>=') ? 'library/export_import' : 'PHPExcel';
            chdir(DIR_SYSTEM . $dir);
            require_once('Classes/PHPExcel.php');
            chdir($cwd);
        } catch (Exception $e) {
            $errstr = $e->getMessage();
            $errline = $e->getLine();
            $errfile = $e->getFile();
            $errno = $e->getCode();

            // этот блок надо убрать?
            $this->session->data['export_import_error'] = array(
                'errstr' => $errstr,
                'errno' => $errno,
                'errfile' => $errfile,
                'errline' => $errline
            );
            if ($this->config->get('config_error_log')) {
                $this->log->write(
                    'PHP ' . get_class($e) . ':  ' . $errstr . ' in ' . $errfile . ' on line ' . $errline
                );
            }
            $this->error['warning'] = 'Предупреждаю!!! Ошибка при создании класса PHPExcel';
            $data['error_warning'] = 'Какая-то ошибка при создании класса PHPExcel';
            $this->response->redirect(
                $this->url->link(
                    'extension/module/arser_site',
                    'user_token=' . $this->session->data['user_token'],
                    true
                )
            );
            return false;
        }

        $this->load->model('extension/module/arser_site');
        $site = $this->model_extension_module_arser_site->getSite($site_id);
        $xlsName = $site['modulname'] . '.xlsx'; // этот файл вернем пользователю
        $prefix = $site['prefix'];
//        $product_id = $site['min_id']; //  если не опираемся на id в таблице

        ob_end_clean();
        //--- create php excel object ---
        $objPHPExcel = new PHPExcel();
        ini_set('memory_limit', '3500M');
        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array('memoryCacheSize' => '800MB');
        //set php excel settings
        PHPExcel_Settings::setCacheStorageMethod(
            $cacheMethod, $cacheSettings
        );

        // создание листов
        $this->createSheets($objPHPExcel);
        // или загрузка из шаблона

//        $templateName = DIR_APPLICATION . '../XLSX/template.xls';
//        $objPHPExcel = PHPExcel_IOFactory::load($templateName);

        // инициализация счетчиков строк на листах
        $i = array(
            'Products' => 2,
            'AdditionalImages' => 2,
            'Specials' => 2,
            'ProductOptions' => 2,
            'ProductOptionValues' => 2,
            'ProductAttributes' => 2
        );
        // Заполнение листов
        $this->load->model('extension/module/arser_product');
        $rows = $this->model_extension_module_arser_product->getProducts($site_id);
        $aImg = []; // список всех картинок (URL)
        $path = 'catalog/' . $site['modulname'] . '/';

        foreach ($rows as $row) {
            $product_id = $row['id'];
            if (empty($row['sku'])) {
                $row['sku'] = $product_id;
            }
            $attr = unserialize($row['attr']);
            $product_option = unserialize($row['product_option']);
            $imgs = unserialize($row['images_link']);

            $img = $imgs[0];

            if (($imgname = array_search($img, $aImg)) === false) { // не нашел, добавим ссылку на картинку
                $imgname = $path . $this->genImgName($img, $product_id, 0);
                $aImg[$imgname] = $img;
            }

            try {
                //Указывая номера ячеек, заполняем страницу данными
                $objPHPExcel->getSheetByName('Products')
                    ->setCellValue('A' . $i['Products'], $product_id)
                    ->setCellValue('B' . $i['Products'], $row['name'])
                    ->setCellValue('C' . $i['Products'], $row['category'])
                    ->setCellValue('D' . $i['Products'], $prefix . '-' . $row['sku'])
                    ->setCellValue('E' . $i['Products'], $site['model'])
                    ->setCellValue('G' . $i['Products'], $site['jan'])
                    ->setCellValue('K' . $i['Products'], $row['quantity'])
                    ->setCellValue('L' . $i['Products'], $prefix . '-' . $row['sku'])
                    ->setCellValue('M' . $i['Products'], $site['manufacturer'])
                    ->setCellValue('N' . $i['Products'], $imgname)
                    ->setCellValue('O' . $i['Products'], 'yes')
                    ->setCellValue('P' . $i['Products'], $row['price'])
                    ->setCellValue('Q' . $i['Products'], 0)
                    ->setCellValue('U' . $i['Products'], $row['weight'])
                    ->setCellValue('AA' . $i['Products'], 'true')
                    ->setCellValue('AB' . $i['Products'], 0)
                    ->setCellValue('AC' . $i['Products'], $row['description'])
                    //                ->setCellValue('AD' . $i['Products'], $pInfo['title'] ?? $row['name'])
                    ->setCellValue('AG' . $i['Products'], 6)
                    ->setCellValue('AH' . $i['Products'], 0)
                    ->setCellValue('AL' . $i['Products'], 1)
                    ->setCellValue('AM' . $i['Products'], 'true')
                    ->setCellValue('AN' . $i['Products'], 1)
                    ->setCellValue('AO' . $i['Products'], $row['link']);
            } catch (Exception $e) {
                echo 'Заполняем Products' . PHP_EOL;
                $this->echoError($e);

                echo '$i=';
                var_dump($i);
                echo PHP_EOL;
                echo '$row=';
                var_dump($row);
                echo PHP_EOL;
                die();
            }
            $i['Products']++;

            try {
                foreach ($imgs as $key => $img) {
                    if (($imgname = array_search($img, $aImg)) === false) { // не нашел, добавим ссылку на картинку
                        $imgname = $path . $this->genImgName($img, $product_id, $key);
                        $aImg[$imgname] = $img;
                    } else {
                    }

                    if ($key > 0) {
                        $objPHPExcel->getSheetByName('AdditionalImages')
                            ->setCellValue('A' . $i['AdditionalImages'], $product_id)
                            ->setCellValue(
                                'B' . $i['AdditionalImages'],
                                $imgname
                            ) // $this->genImgName($img, $site['modulname'], $row['sku'], $key))
                            ->setCellValue('C' . $i['AdditionalImages'], 0);
                        $i['AdditionalImages']++;
                    }
                }
            } catch (Exception $e) {
                echo 'AdditionalImages' . PHP_EOL;
                $this->echoError($e);
                echo '$i=';
                var_dump($i);
                echo PHP_EOL;
                echo '$pInfo=';
                var_dump($pInfo);
                echo PHP_EOL;
                die();
            }

            try {
                if (isset($product_option) && !empty($product_option)) {
                    $objPHPExcel->getSheetByName('ProductOptions')
                        ->setCellValue('A' . $i['ProductOptions'], $product_id)
                        ->setCellValue('B' . $i['ProductOptions'], 'Доп. опции')
                        ->setCellValue('D' . $i['ProductOptions'], 'false');
                    $i['ProductOptions']++;


                    foreach ($product_option as $key => $item) {
                        $objPHPExcel->getSheetByName('ProductOptionValues')
                            ->setCellValue('A' . $i['ProductOptionValues'], $product_id)
                            ->setCellValue('B' . $i['ProductOptionValues'], 'Доп. опции')
                            ->setCellValue('C' . $i['ProductOptionValues'], $item['name'])
                            ->setCellValue('D' . $i['ProductOptionValues'], 999)
                            ->setCellValue('E' . $i['ProductOptionValues'], 'false')
                            ->setCellValue('F' . $i['ProductOptionValues'], $item['price'])
                            ->setCellValue('G' . $i['ProductOptionValues'], '+')
                            ->setCellValue('H' . $i['ProductOptionValues'], 0)
                            ->setCellValue('I' . $i['ProductOptionValues'], '+')
                            ->setCellValue('J' . $i['ProductOptionValues'], 0)
                            ->setCellValue('K' . $i['ProductOptionValues'], '+');
                        $i['ProductOptionValues']++;
                    }
                }
            } catch (Exception $e) {
                echo 'ProductOptions' . PHP_EOL;
                $this->echoError($e);
                echo '$i=';
                var_dump($i);
                echo PHP_EOL;
                echo '$pInfo=';
                var_dump($pInfo);
                echo PHP_EOL;
                die();
            }

            try {
                if (isset($pInfo['special'])) {
                    $objPHPExcel->getSheetByName('Specials')
                        ->setCellValue('A' . $i['Specials'], $product_id)
                        ->setCellValue('B' . $i['Specials'], 'Default')
                        ->setCellValue('C' . $i['Specials'], 0)
                        ->setCellValue('D' . $i['Specials'], $pInfo['special']);
                    $i['Specials']++;
                }
            } catch (Exception $e) {
                echo 'Specials' . PHP_EOL;
                $this->echoError($e);
                echo '$i=';
                var_dump($i);
                echo PHP_EOL;
                echo '$pInfo=';
                var_dump($pInfo);
                echo PHP_EOL;
                die();
            }

            try {
                if (isset($attr)) {
                    foreach ($attr as $key => $item) {
                        $objPHPExcel->getSheetByName('ProductAttributes')
                            ->setCellValue('A' . $i['ProductAttributes'], $product_id)
                            ->setCellValue('B' . $i['ProductAttributes'], 'Фильтры')
                            ->setCellValue('C' . $i['ProductAttributes'], $key)
                            ->setCellValue('D' . $i['ProductAttributes'], $item);
                        $i['ProductAttributes']++;
                    }
                }
            } catch (Exception $e) {
                echo 'ProductAttributes' . PHP_EOL;
                $this->echoError($e);
                echo '$i=';
                var_dump($i);
                echo PHP_EOL;
                echo '$attr=';
                var_dump($attr);
                echo PHP_EOL;
                die();
            }
//            $product_id++; // если не опираемся на id в таблице
        }

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $xlsName . '"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');

        $this->load->model('extension/module/arser_site');

//        $this->model_extension_module_arser_site->setStatusSite($site_id, 'ok');
        //        $this->response->redirect($this->url->link('extension/module/arser_site', 'user_token=' . $this->session->data['user_token'], true));

    }

    protected function genImgName($filename, $product_id, $i)
    {
        $tmp = explode(".", $filename);
        $result = $product_id;
        $result .= "_";
        $result .= $i;
        $result .= ".";
        $result .= end($tmp);
        return $result;
    }

    // TODO: не используется?
    public function getimagestatus()
    {
        if (isset($this->request->get['id'])) {
            $site_id = $this->request->get['id'];
        } else {
            echo "Не указан id сайта для экспорта.";
            $this->error['warning'] = 'Не указан id сайта для экспорта.';
            return !$this->error;
        }

        $this->load->model('extension/module/arser_site');
        $site_info = $this->model_extension_module_arser_site->getSite($this->request->get['id']);
        $result = $site_info["message"];

        echo $result;
        //Get the progress
        //        session_start();
        //
        //        print("Processed " . $_SESSION['progress'] . " of " . $_SESSION['goal'] . " rows.");

    }

    /**
     * load image from site to path
     *
     * @return bool
     */
    public function getimage()
    {
        if (isset($this->request->get['id'])) {
            $site_id = $this->request->get['id'];
        } else {
            $this->error['warning'] = 'Не указан id сайта для экспорта.';
            return !$this->error;
        }

        set_time_limit(0);

        $this->load->model('extension/module/arser_site');
        $site = $this->model_extension_module_arser_site->getSite($this->request->get['id']);
//        $product_id = $site['min_id']; // если не опираемся на id продукта в таблице
        $this->model_extension_module_arser_site->setMessageSite($site_id, $site['name'] . ' Загрузка картинок');

        $this->load->model('extension/module/arser_product');
        $rows = $this->model_extension_module_arser_product->getProducts($site_id);
        $i = 0;
        $all = count($rows);

        $log = new Log('getimage.log');
        $log->write("Продуктов:" . $all);

        $aImg = [];
        $path = DIR_APPLICATION . '../image/catalog/' . $site['modulname'];
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        if (!is_dir($path)) {
            'Каталог ' . $path . ' не создан!';
            die();
        }

        $log->write('СТАРТ выгрузки картинок в ' . $path);

        foreach ($rows as $row) {

            $imgs = unserialize($row['images_link']);
            $product_id = $row['id'];
//            if (empty($row['sku'])) {
            $row['sku'] = $product_id;
//            }
//            $product_id++;// если не опираемся на id продукта в таблице

            $log->write($row['sku'] . ' ' . print_r($imgs, true));
            foreach ($imgs as $key => $img) {
                if (($imgname = array_search($img, $aImg)) === false) { // не нашел, добавим ссылку на картинку
                    $imgname = $this->genImgName($img, $row['sku'], $key);
                    $aImg[$imgname] = $img;
                } else {
                }

                $url = $img;
                $path_img = $path . '/' . $imgname;

                if (file_exists($path_img)) {
                    $log->write($row['sku'] . ' ' . $path_img . ' существует, пропускаем.');
                } else {
                    $img = @file_get_contents($url);
                    if (!$img) {
                        $img = @file_get_contents($this->myUrlEncode($url));
                    }
                    if (!$img) {
//                        try {
//                            $cyrUrl = $this->сyrillicUrl($url);
//                            $img = @file_get_contents($this->сyrillicUrl($cyrUrl));
//                        } catch (Exception $e) {
//                            $log->write('Выброшено исключение: ' .  $e->getMessage());
//                        }
                    }
                    if ($img) {
                        file_put_contents($path_img, $img);
                        $log->write($row['sku'] . ' ' . $path_img . ' Сохранен ' . $path);
                    } else {
                        $log->write($row['sku'] . ' ' . $path_img . " Ошибка загрузки файла {$url}!\n");
                    }
                }
            }
            $i++;

            $this->model_extension_module_arser_site->setMessageSite(
                $site_id,
                $site['name'] . " Загрузка картинок ($i/$all)"
            );
        }
        $this->model_extension_module_arser_site->setMessageSite($site_id, 'Выгружено ' . $all . ' картинок.');
        return json_encode('Выгружено ' . $all . ' картинок.');
    }

    protected function myUrlEncode($string)
    {
        $entities = array(
            '%21',
            '%2A',
            '%27',
            '%28',
            '%29',
            '%3B',
            '%3A',
            '%40',
            '%26',
            '%3D',
            '%2B',
            '%24',
            '%2C',
            '%2F',
            '%3F',
            '%25',
            '%23',
            '%5B',
            '%5D'
        );
        $replacements = array(
            '!',
            '*',
            "'",
            "(",
            ")",
            ";",
            ":",
            "@",
            "&",
            "=",
            "+",
            "$",
            ",",
            "/",
            "?",
            "%",
            "#",
            "[",
            "]"
        );
        return str_replace($entities, $replacements, urlencode($string));
    }

    /**
     * todo не используется, в имени символы кириллицы и латиницы
     * @param $url
     * @return array|string|string[]
     */
    protected function сyrillicUrl($url)
    {
        if (preg_match('#^([\w\d]+://)([^/]+)(.*)$#iu', $url, $m)) {
            $url = $m[1] . idn_to_ascii($m[2], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) . $m[3];
        }
        $url = urldecode($url);
        $url = rawurlencode($url);
        $url = str_replace(array('%3A', '%2F'), array(':', '/'), $url);
        return $url;
    }

    protected function echoError(Exception $e)
    {
        echo $e->getMessage() . PHP_EOL;
        echo $e->getCode() . PHP_EOL;
        echo $e->getLine() . PHP_EOL;
    }

    private function getPages(int $active_tab)
    {
        $menu = [
            [
                'href' => 'edit',
                'title' => '<i class="fa fa-pencil"></i> Настройки сайта'
            ],
            [
                'href' => 'grab',
                'title' => '<i class="fa fa-outdent" aria-hidden="true"></i> Сбор ссылок'
            ],
            [
                'href' => 'product',
                'title' => '<i class="fa fa-list" aria-hidden="true"></i> Продукты'
            ],
            [
                'href' => 'import_setting',
                'title' => '<i class="fa fa-list" aria-hidden="true"></i> Настройка импорта'
            ],
            [
                'href' => 'import',
                'title' => '<i class="fa fa-list" aria-hidden="true"></i> Импорт/Экспорт'
            ],
        ];

        foreach ($menu as $key => $item) {
            $menu[$key]['href'] = $this->url->link(
                'extension/module/arser_site/' . $menu[$key]['href'],
                'user_token=' . $this->session->data['user_token'] . '&id=' . ($this->request->get['id'] ?? ''),
                true
            );
            $menu[$key]['active'] = ($key == $active_tab) ? 'active' : '';
        }

        return $menu;
    }

    private function createSheets(PHPExcel $objPHPExcel)
    {
        $sheetsName = [
            [
                'name' => 'Products',
                'headers' =>
                    [
                        'product_id',
                        'name(ru-ru)',
                        'categories',
                        'sku',
                        'upc',
                        'ean',
                        'jan',
                        'isbn',
                        'mpn',
                        'location',
                        'quantity',
                        'model',
                        'manufacturer',
                        'image_name',
                        'shipping',
                        'price',
                        'points',
                        'date_added',
                        'date_modified',
                        'date_available',
                        'weight',
                        'weight_unit',
                        'length',
                        'width',
                        'height',
                        'length_unit',
                        'status',
                        'tax_class_id',
                        'description(ru-ru)',
                        'meta_title(ru-ru)',
                        'meta_description(ru-ru)',
                        'meta_keywords(ru-ru)',
                        'stock_status_id',
                        'store_ids',
                        'layout',
                        'related_ids',
                        'tags(ru-ru)',
                        'sort_order',
                        'subtract',
                        'minimum',
                        'link',
                    ],
            ],
            [
                'name' => 'AdditionalImages',
                'headers' => [
                    'product_id',
                    'image',
                    'sort_order',
                ],
            ],
            [
                'name' => 'Specials',
                'headers' => [
                    'product_id',
                    'customer_group',
                    'priority',
                    'price',
                    'date_start',
                    'date_end',
                ],
            ],
            [
                'name' => 'Discounts',
                'headers' => [
                    'product_id',
                    'customer_group',
                    'quantity',
                    'priority',
                    'price',
                    'date_start',
                    'date_end',
                ],
            ],
            [
                'name' => 'Rewards',
                'headers' => [
                    'product_id',
                    'customer_group',
                    'points',
                ],
            ],
            [
                'name' => 'ProductOptions',
                'headers' => [
                    'product_id',
                    'option',
                    'default_option_value',
                    'required',
                ],
            ],
            [
                'name' => 'ProductOptionValues',
                'headers' => [
                    'product_id',
                    'option',
                    'option_value',
                    'quantity',
                    'subtract',
                    'price',
                    'price_prefix',
                    'points',
                    'points_prefix',
                    'weight',
                    'weight_prefix',
                ],
            ],
            [
                'name' => 'ProductAttributes',
                'headers' => [
                    'product_id',
                    'attribute_group',
                    'attribute',
                    'text(ru-ru)',
                ],
            ],
            [
                'name' => 'ProductFilters',
                'headers' => [
                    'product_id',
                    'filter_group',
                    'filter',
                ],
            ],
            [
                'name' => 'ProductSEOKeywords',
                'headers' => [
                    'product_id',
                    'store_id',
                    'keyword(ru-ru)',
                ],
            ],
        ];

        foreach ($sheetsName as $key => $item) {
            if ($key > 0) {
                $objPHPExcel->createSheet();
            }
            $wSheet = $objPHPExcel->setActiveSheetIndex($key)->setTitle($item['name']);
            $wSheet->fromArray($item['headers'], null, 'A1');
        }
    }
}
