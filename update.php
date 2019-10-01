<?php
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);
ini_set('memory_limit', '512M');
setlocale(LC_ALL, 'ru_RU.UTF-8');
require_once('lib/woocommerce-api.php');
require "vendor/autoload.php";

use Automattic\WooCommerce\Client;

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

//Получаем Товары из Retail
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

//Записываем группы из Retail для последующего сравнения
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

//Формируем массив товаров из Retail
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


        foreach ($a as $ait) {
            foreach ($wp_groups as $item) {
                if ($item['name'] == $ait) {
                    $group[] = $item['id'];
                }
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
            "manufacturer" => $manufacturer,
//            'attributes' =>[],
            "categories" => !empty($group) ? $group : [],
        ];
        $group = [];


    }
}

//Делаем сравнение полей товаров и групп товаров из Retail и старых товаров которые заисаны в "file1.json"
$check_new_catalog = json_encode($product_get);
$decode_check_new_catalog = json_decode($check_new_catalog, true);
$file = json_decode(file_get_contents('file1.json'), true);
$wp_array = [];

foreach ($decode_check_new_catalog as $new) {
    foreach ($file as $f) {
        $f_id[] = $f['id'];
        if ($new['id'] == $f['id']) {

            $update_item_category = array_map('unserialize', array_diff(array_map('serialize', $new), array_map('serialize', $f)));

            unset($new['categories']);
            unset($f['categories']);

            $update_item = array_diff($new, $f);
            $update_item = array_merge($update_item, $update_item_category);


            if (!empty($update_item)) {
                $update[] = $update_item;
                $wp_update_array[] = array_merge($f, $update_item);

            }
            break;
        }
    }
}

//Обновляем товары которые были изменены
if (!empty($wp_update_array)) {
    $j = 1;
    foreach ($wp_update_array as $update) {

        $sku = $update['sku'];
        $urlapi = "https://healthymama.ru/wc-api/v2/products?consumer_key=$consumer_key&consumer_secret=$consumer_secret&filter[sku]=$sku";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlapi);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $response = curl_exec($ch);
        curl_close($ch);

        $id = !empty(!empty(json_decode($response, true)["products"][0]["id"])) ? json_decode($response, true)["products"][0]["id"] : null;

//        var_dump ($update);exit();
//        $update['attributes'][]['option'][]=$update['manufacturer'];
        $update['attributes'] = [
            [
                'id' => 11,
                'position' => 0,
                'visible' => true,
                'variation' => false,
                'options' => [
                    $update['manufacturer']
                ]
            ]
        ];

        unset($update['manufacturer']);
        if (!empty($id)) {
            var_dump('1');
            var_dump('-update-' . $j);
            sleep(1);
            var_dump($update);
            file_put_contents('update.log', print_r([date('d.m.Y H:i:s'), $update], 1), FILE_APPEND);
            try {
                $up = $client->products->update($id, $update);
            } catch (Exception $e) {
                file_put_contents('create_error.log', print_r([date('d.m.Y H:i:s'), $e->getMessage()], 1), FILE_APPEND);
            } ////ok
            var_dump($up);
        }

        $j++;
    }
}
