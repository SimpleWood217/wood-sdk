<?php
require_once __DIR__ . '/vendor/autoload.php';

$client = \Wood\Sdk\AlipayServiceClient::newInstance([
    'appid' => '',
    'private_key' => '',
    'public_key' => '',
    'alipay_public_key' => '',
]);
dump($client);
