<?php
use Wood\Sdk\Pay\WeChat\Client as WechatClient;
use Wood\Sdk\Pay\WeChat\Config as WechatConfig;
use Wood\Sdk\Pay\WeChat\Upload as UploadClient;

if (!function_exists('root_path')) {
    function root_path(): string
    {
        return __DIR__ . '/';
    }
}

//function jsonEncode(array $data): string
//{
//    return !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';
//}

require_once __DIR__ . '/vendor/autoload.php';

$config = new WechatConfig([
    'merch_no'            => '1654562829',
    'api_v3'              => 'c4ca4238a0b923820dcc509a6f75849b',
    'cert_number'         => '5E4B1F92172B80AD787B85297EEEA82BA0130288',
    'private_key_path'    => 'test/apiclient_key.pem',
    'wxpay_public_key_id' => '123',
    'wxpay_public_key_path' => 'test/wxpay_public_key.pem',
]);

//$client = new WechatClient($config);
////dump($client);
//try {
//    $result = $client->request('POST', '/v3/pay/transactions/jsapi', [
//        'body' => [
//            'mchid'        => $config->get('merch_no'),
//            'appid'        => '123',
//            'description'  => '测试订单',
//            'out_trade_no' => '1694534567890123456',
//            'notify_url'   => 'https://www.baidu.com/notify/' . '1694534567890123456',
//            'amount'       => [
//                'total'    => (int)number_format(round(100), 0, '', ''),
//                'currency' => 'CNY'
//            ],
//            'payer'        => [
//                'openid' => 'openid',
//            ],
//        ]
//    ]);
//} catch (\Wood\Sdk\Exceptions\HttpRequestException $e) {
//    dump($e->getMessage());
//    echo $e->getSimpleInfo();
//}


//$result = $WechatClient->request('GET', '/v3/merchant-service/complaints-v2', [
//    'body'     => [
//        'begin_date' => '2023-10-01',
//        'end_date'   => '2023-10-30',
//        'limit'      => 10,
//    ],
//    'is_query' => true,
//]);

$upload_client = new UploadClient($config);
$result = $upload_client->image(
    '/v3/merchant-service/images/upload',
    file_get_contents(root_path() . 'test/test-upload.png'),
    'test-upload.png',
);
dump($result);
