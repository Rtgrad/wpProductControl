<?php
set_time_limit(0);
ini_set('memory_limit', '512M');
setlocale(LC_ALL, 'ru_RU.UTF-8');
require_once('lib/woocommerce-api.php');
require "vendor/autoload.php";

$options = array(
    'ssl_verify' => false,
);
$consumer_key = 'ck_c31763cf4b39a0be5fda499f773811fe9b36bf87';
$consumer_secret = 'cs_7a4a3585276d68039c419ce8b464eb196f971624';
$client = new WC_API_Client('https://example.ru', $consumer_key, $consumer_secret, $options);

$urlRetail = "https://example.retailcrm.ru";
$apiKey = "N9TWNJJzUqMUhBAHWWMLzqoYwRpuksxq";

$retail = new \RetailCrm\ApiClient(
    $urlRetail,
    $apiKey,
    \RetailCrm\ApiClient::V5
);

//Получаем данные из Retail
//$productsList = $retail->request->storeProducts ();
$product = [];
$page = 1;
$product_get = [];
do {
    $product[] = $retail->request->storeProducts(['sites' => ['test-ru']], $page, 100);
    var_dump($page);

    $page++;
} while (!empty($product[$page - 2]->products));

//Получаем группы из Retail
$retail_group = [];
$gr_id = [];
$page = 1;

do {
    $retail_group[] = $retail->request->storeProductsGroups([], $page, 100);
    $page++;
} while (!empty($retail_group->productGroup));

$i = 0;
foreach ($retail_group as $gr) {

    foreach ($gr->productGroup as $item) {
        $gr_id[$i]['name'] = $item['name'];
        $gr_id[$i]['id'] = $item['id'];
        $i++;
    }
}

//Получаем группы из WordPress
$wp_groups = [];
$wp_group_item = $client->products->get_categories();

$i = 0;
foreach ($wp_group_item->product_categories as $wp_gr) {
    $wp_groups[$i]['name'] = $wp_gr->name;
    $wp_groups[$i]['id'] = $wp_gr->id;
    $i++;
}


//Формируем массив товаров которые надо добавить
foreach ($product as $item) {
    foreach ($item->products as $prod) {


        $a = [];
        if (!empty($prod['groups'])) {
            foreach ($gr_id as $gr) {
                foreach ($prod['groups'] as $agr)
                    if (!empty($agr['id']) && $gr['id'] == $agr['id']) {
                        $a[] = $gr['name'];
                    }
            }
        }
        foreach ($wp_groups as $item) {
            foreach ($a as $ait)
                if ($item['name'] == $ait) {
                    $group[] = $item['id'];
                }
        }
        $manufacturer = !empty($prod['manufacturer']) ? $prod['manufacturer'] : '';

        $product_get[] = [
            "id" => $prod['id'],
            "title" => !empty($prod['name']) ? $prod['name'] : '', //name
            "sku" => !empty($prod['article']) ? $prod['article'] : '', //article
            "price" => !empty($prod["offers"][0]['price']) ? $prod["offers"][0]['price'] : null,
            "regular_price" => !empty($prod["offers"][0]['price']) ? $prod["offers"][0]['price'] : null,
            "weight" => !empty($prod["offers"][0]["weight"]) ? $prod["offers"][0]["weight"] : null,
            "stock_quantity" => !empty($prod['quantity']) ? $prod['quantity'] : null,
            'attributes' => array(
                array(
                    'name' => 'Производитель', // parameter for custom attributes
                    'visible' => true, // default: false
                    'options' => array(
                        $manufacturer,
                    )
                )
            ),
            "categories" => $group,
        ];
        $group = [];
    }
}

//Выделяем отсутвующие товары
$check_new_catalog = json_encode($product_get);
$decode_check_new_catalog = json_decode($check_new_catalog, true);
$file = json_decode(file_get_contents('file1.json'), true);
$wp_array = [];

if (count($decode_check_new_catalog) > count($file)) {
    foreach ($decode_check_new_catalog as $new) {
        foreach ($file as $f) {
            $f_id[] = $f['id'];
        }
        if (!in_array($new['id'], $f_id)) {
            ////array for create
            $wp_create_array[] = $new;

        }
    }
}


//создаем товары которые не были найдены в файле "file1.json"
if (!empty($wp_create_array)) {
    foreach ($wp_create_array as $create) {
        var_dump('create');
        var_dump($create);
        file_put_contents('create.log', print_r([date('d.m.Y H:i:s'), $create], 1), FILE_APPEND);
        try {
            $cr = $client->products->create($create);
        } catch (Exception $e) {
            file_put_contents('create_error.log', print_r([date('d.m.Y H:i:s'), $e->getMessage()], 1), FILE_APPEND);
        }////ok
        var_dump($cr);
    }
}

