<?php

set_time_limit ( 0 );
ini_set ( 'memory_limit' , '512M' );
setlocale ( LC_ALL , 'ru_RU.UTF-8' );
require_once ( 'lib/woocommerce-api.php' );
require "vendor/autoload.php";

$urlRetail = "https://example.retailcrm.ru";
$apiKey = "N9TWNJJzUqMUhBAHWWMLzqoYwRpuksxq";

$options = array (
    'ssl_verify' => false ,
);
$consumer_key = 'ck_c31763cf4b39a0be5fda499f773811fe9b36bf87';
$consumer_secret = 'cs_7a4a3585276d68039c419ce8b464eb196f971624';
$client = new WC_API_Client( 'https://example.ru' , $consumer_key , $consumer_secret , $options );


$retail = new \RetailCrm\ApiClient(
    $urlRetail ,
    $apiKey ,
    \RetailCrm\ApiClient::V5
);

//Получаем группы из Retail
$retail_group = [];
$gr_id = [];
$page = 1;

do {
    $retail_group[] = $retail->request->storeProductsGroups ( [] , $page , 100 );
    $page ++;
} while ( !empty( $retail_group->productGroup ) );

$i = 0;
foreach ( $retail_group as $gr ) {

    foreach ( $gr->productGroup as $item ) {
        $gr_id[ $i ][ 'name' ] = $item[ 'name' ];
        $gr_id[ $i ][ 'id' ] = $item[ 'id' ];
        $i ++;
    }
}

//Получаем группы из WordPress
$wp_groups = [];
$wp_group_item = $client->products->get_categories ();
$i = 0;
foreach ( $wp_group_item->product_categories as $wp_gr ) {
    $wp_groups[ $i ][ 'name' ] = $wp_gr->name;
    $wp_groups[ $i ][ 'id' ] = $wp_gr->id;
    $i ++;
}

//Получаем товары из Retail
$product = [];
$page = 1;
$product_get = [];
do {
    $product[] = $retail->request->storeProducts ( [ 'sites' => [ 'test-ru' ] ] , $page , 100 );
    var_dump ( $page );
//    exit();
//    file_put_contents ( 'testfile.json' ,  $retail->request->storeProducts ( [] , $page , 100 ) ,FILE_APPEND );

    $page ++;
} while ( !empty( $product[ $page - 2 ]->products ) );

//Формируем массив товаров с их "Группой" для добавления в файл "file1.json"
foreach ( $product as $item ) {
    foreach ( $item->products as $prod ) {

        $a = [];
        if ( !empty( $prod[ 'groups' ] ) ) {
            foreach ( $gr_id as $gr ) {
                foreach ( $prod[ 'groups' ] as $agr )
                    if ( !empty( $agr[ 'id' ] ) && $gr[ 'id' ] == $agr[ 'id' ] ) {
                        $a[] = $gr[ 'name' ];
                    }
            }
        }


        foreach ( $a as $ait ) {
            foreach ( $wp_groups as $item ) {
                if ( $item[ 'name' ] == $ait ) {
                    $group[] = $item[ 'id' ];
                }
            }
        }

        $manufacturer = !empty( $prod[ 'manufacturer' ] ) ? $prod[ 'manufacturer' ] : '';

        $product_get_1[] = [
            "id" => $prod[ 'id' ] ,
            "title" => !empty( $prod[ 'name' ] ) ? $prod[ 'name' ] : '' , //name
            "sku" => !empty( $prod[ 'article' ] ) ? $prod[ 'article' ] : '' , //article
            "price" => !empty( $prod[ "offers" ][ 0 ][ 'price' ] ) ? $prod[ "offers" ][ 0 ][ 'price' ] : null ,
            "regular_price" => !empty( $prod[ "offers" ][ 0 ][ 'price' ] ) ? $prod[ "offers" ][ 0 ][ 'price' ] : null ,
            "quantity" => !empty( $prod[ "quantity" ] ) ? $prod[ "quantity" ] : 0 ,
            "weight" => !empty( $prod[ "offers" ][ 0 ][ "weight" ] ) ? $prod[ "offers" ][ 0 ][ "weight" ] : null ,
            "stock_quantity" => !empty( $prod[ 'quantity' ] ) ? $prod[ 'quantity' ] : null ,
            "manufacturer" =>  $manufacturer  ,
            "categories" => !empty( $group ) ? $group : [] ,

        ];

        $group = [];
    }

}
//Добавляем сформированные НОВЫЕ данные в файл "file1.json"
file_put_contents ( 'file1.json' , json_encode ( $product_get_1 ) );
