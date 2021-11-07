<?php


ini_set('display_errors', 1);

ini_set('display_startup_errors', 1);

error_reporting(E_ALL);


// HTTP
define('HTTP_SERVER', 'http://kolibri.loc/admin/');
define('HTTP_CATALOG', 'http://kolibri.loc/');

// HTTPS
define('HTTPS_SERVER', 'http://kolibri.loc/admin/');
define('HTTPS_CATALOG', 'http://kolibri.loc/');

// DIR
define('DIR_APPLICATION', '/home/papaha/domains/opencart/admin/');
define('DIR_SYSTEM', '/home/papaha/domains/opencart/system/');
define('DIR_IMAGE', '/home/papaha/domains/opencart/image/');
define('DIR_STORAGE', '/home/papaha/domains/storage/');
define('DIR_CATALOG', '/home/papaha/domains/opencart/catalog/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_STORAGE . 'cache/');
define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
define('DIR_LOGS', DIR_STORAGE . 'logs/');
define('DIR_MODIFICATION', DIR_STORAGE . 'modification/');
define('DIR_SESSION', DIR_STORAGE . 'session/');
define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

// DB
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '123456');
define('DB_DATABASE', 'opencart');
define('DB_PORT', '3306');
define('DB_PREFIX', 'oc_');

// OpenCart API
define('OPENCART_SERVER', 'https://www.opencart.com/');
