<?php
declare(strict_types=1);

namespace Wood\Sdk;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * 支付宝V3接口SDK
 * 支持公私钥、证书两种签名方式
 * V3文档：https://opendocs.alipay.com/open-v3
 *
 * @author  WooD
 * @version 1.0.0
 */
class AlipayServiceClient
{
    protected string $gateway              = 'https://openapi.alipay.com';
    protected bool   $strict;                           // 严格模式(开启后则会校验支付宝请求响应签名)
    protected string $path                 = '';        // 请求路径
    protected string $signType             = 'rsa';     // 签名类型(rsa公私钥、cert证书)
    protected string $method               = 'POST';    // 请求方法
    protected string $appId                = '';        // 支付宝应用ID
    protected bool   $upload               = false;     // 是否上传文件
    protected bool   $sandbox              = false;     // 是否沙箱环境
    protected array  $requestData          = [];        // 请求参数
    protected string $privateKey           = '';        // 商户私钥
    protected string $alipayPublicKey      = '';        // 支付宝公钥
    protected string $appCertPath          = '';        // 应用公钥证书路径
    protected string $alipayPublicCertPath = '';        // 支付宝公钥证书路径

    function __construct($strict = true)
    {
        $this->strict = $strict;
    }

    /**
     * 发起请求
     *
     * @throws Exception
     * @return array|string
     */
    public function execute(): array|string
    {
        // 校验参数
        if (!$this->appId) {
            throw new Exception('应用ID不能为空');
        }
        if (!$this->path) {
            throw new Exception('请求地址不能为空');
        }
        if ($this->signType == 'rsa') {
            if (!$this->privateKey) {
                throw new Exception("商户私钥不能为空");
            }
            if (!$this->alipayPublicKey) {
                throw new Exception("支付宝公钥不能为空");
            }
        } else {
            if (!file_exists($this->appCertPath)) {
                throw new Exception('应用公钥证书路径不存在');
            }
            if (!file_exists($this->alipayPublicCertPath)) {
                throw new Exception('支付宝公钥证书路径不存在');
            }
        }
        if ($this->method == 'GET') {
            $this->setPath($this->path . '?' . http_build_query($this->requestData));
        }
        if($this->sandbox) $this->setGateway('https://openapi-sandbox.dl.alipaydev.com');

        // 将请求数据编码成JSON格式
        $data = $this->jsonEncode($this->requestData);

        // 计算签名
        $sign = $this->sign($data);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'ALIPAY-SHA256withRSA ' . $sign['authString'] . ',sign=' . $sign['sign'],
        ];

        // 发送请求
        try {
            $client = new Client();
            $response = $client->request($this->method, $this->gateway . $this->path, [
                'headers' => $headers,
                'body' => $data,
            ]);
            $headers = $response->getHeaders();
            $body = $response->getBody()->getContents();
            if ($this->strict) {
                // 严格模式下校验签名
                $signContent = $headers['alipay-timestamp'][0] . "\n"
                    . $headers['alipay-nonce'][0] . "\n"
                    . $body . "\n";
                $result = $this->verify($signContent, $headers['alipay-signature'][0]);
                if (!$result) {
                    throw new Exception('严格模式已开启，响应签名校验失败');
                }
            }
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        } catch (GuzzleException $e) {
            $body = $e->getMessage();
        }
        return json_validate($body) ? json_decode($body, true) : $body;
    }

    /**
     * 上传图片
     *
     * @param string $file   图片二进制数据
     * @param string $suffix 图片后缀名
     *
     * @throws Exception
     * @return array
     */
    public function uploadImage(string $file, string $suffix): array
    {
        $signData = $this->jsonEncode([
            'image_type' => $suffix,
        ]);
        $requestData = [
            [
                'name' => 'data',
                'contents' => $signData,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ],
            [
                'name' => 'image_content',
                'contents' => $file,
                'headers' => [
                    'Content-Type' => 'image/' . $suffix,
                ],
            ],
        ];
        $sign = $this->sign($signData);
        $headers = [
            'Authorization' => 'ALIPAY-SHA256withRSA ' . $sign['authString'] . ',sign=' . $sign['sign']
        ];
        $client = new Client();
        try {
            $response = $client->request($this->method, $this->gateway . $this->path, [
                'headers' => $headers,
                'multipart' => $requestData,
            ]);
            $body = $response->getBody()->getContents();
            echo $body;
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        } catch (GuzzleException $e) {
            $body = $e->getMessage();
        }
        return json_validate($body) ? json_decode($body, true) : $body;
    }

    /**
     * 计算签名
     *
     * @param string $data 需要签名的数据
     *
     * @throws Exception
     * @return array Base64编码后的签名
     */
    public function sign(string $data): array
    {
        /*
         * 构造认证串 authString 认证字符串
         *
         * 构造规则
         * app_id=${应用APPID},
         * app_cert_sn=${应用公钥证书序列号}, 证书模式下必填
         * nonce=${随机数},
         * timestamp=${当前时间戳}
         */
        $authString = 'app_id=' . $this->appId
            . ',timestamp=' . time()
            . ',nonce=' . uniqid();

        if ($this->signType == 'cert') {
            $appCertSn = $this->getCertSN($this->appCertPath);
            $authString .= ',app_cert_sn=' . $appCertSn;
        }

        /*
         * 构造待签名内容
         *
         * 构造规则
         * ${authString}\n
         * ${httpMethod}\n
         * ${httpRequestUrl}\n
         * ${httpRequestBody}\n
         * ${appAuthToken}\n
         */
        $signContent = $authString . "\n"
            . $this->method . "\n"
            . $this->path . "\n";

        if ($this->method == 'POST') {
            $signContent .= $data . "\n";
        } else {
            /*
             * 文档上给的是GET请求，body为空
             * 但是测试发现GET请求时，支付宝网关返回的待验签字符串存在body参数
             * query和body参数都需要参与签名
             * 与文档悖论
             *
             * 签名规则文档地址 https://opendocs.alipay.com/open-v3/054q58?pathHash=474929ac
             * 测试发现问题的对接文档 https://opendocs.alipay.com/open-v3/77e2b925_alipay.fund.account.query
             */
            $signContent .= $data . "\n";
            // $signContent .= "\n";
        }

        // 生成签名
        openssl_sign($signContent, $sign, $this->loadPrivateKey(), OPENSSL_ALGO_SHA256);
        return [
            'sign' => base64_encode($sign),
            'authString' => $authString
        ];
    }

    /**
     * 验证签名
     *
     * @param string|array $data 需要验证的数据
     * @param string       $sign 签名
     *
     * @throws Exception
     * @return bool 验证结果
     */
    public function verify(string|array $data, string $sign): bool
    {
        if ($this->signType == 'cert') {
            $publicRes = $this->loadAlipayPublicCert();
        } else {
            $publicRes = $this->loadAlipayPublicKey();
        }
        $res = openssl_get_publickey($publicRes);
        if (!$res) {
            throw new Exception('公钥格式错误');
        }

        if (is_array($data)) {
            $signStr = '';
            ksort($data);
            foreach ($data as $key => $value) {
                if ($key !== 'sign' && $key !== 'sign_type') {
                    $signStr .= $key . '=' . $value . '&';
                }
            }
            $data = rtrim($signStr, '&');
        }

        return openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * 加载商户私钥
     *
     * @return string 格式化后的私钥
     */
    public function loadPrivateKey(): string
    {
        return "-----BEGIN PRIVATE KEY-----\n"
            . wordwrap($this->privateKey, 64, "\n", true)
            . "\n-----END PRIVATE KEY-----";
    }

    /**
     * 加载支付宝公钥
     *
     * @return string 格式化后的公钥
     */
    public function loadAlipayPublicKey(): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . wordwrap($this->alipayPublicKey, 64, "\n", true)
            . "\n-----END PUBLIC KEY-----";
    }

    /**
     * 加载支付宝公钥证书
     *
     * @return string 证书内容
     */
    public function loadAlipayPublicCert(): string
    {
        return file_get_contents($this->alipayPublicCertPath);
    }

    /**
     * 获取应用证书序列号（SN）
     *
     * @param string $certPath 证书文件路径
     *
     * @throws Exception 如果证书解析失败
     * @return string 证书SN（MD5哈希值）
     */
    private function getCertSN(string $certPath): string
    {
        $certContent = file_get_contents($certPath);

        // 解析证书
        $sslData = openssl_x509_parse($certContent);
        if (!$sslData) {
            throw new Exception("证书解析失败");
        }

        // 处理颁发者信息（issuer）
        $issuerParts = [];
        $issuer = array_merge([], $sslData['issuer']);
        foreach (array_reverse($issuer) as $key => $value) {
            $issuerParts[] = $key . '=' . $value;
        }
        $issuerString = implode(',', $issuerParts);

        // 计算SN（MD5哈希）
        $rawSN = $issuerString . $sslData['serialNumber'];
        return md5($rawSN);
    }

    /**
     * 设置商户私钥
     *
     * @param string $privateKey 私钥内容
     */
    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    /**
     * 设置支付宝公钥
     *
     * @param string $alipayPublicKey 公钥内容
     */
    public function setAlipayPublicKey(string $alipayPublicKey): void
    {
        $this->alipayPublicKey = $alipayPublicKey;
    }

    /**
     * 设置支付宝公钥证书路径
     *
     * @param string $alipayPublicCertPath 证书路径
     */
    public function setAlipayPublicCertPath(string $alipayPublicCertPath): void
    {
        $this->alipayPublicCertPath = $alipayPublicCertPath;
    }

    /**
     * 设置网关URL
     *
     * @param string $url 网关URL
     */
    public function setGateway(string $url): void
    {
        $this->gateway = $url;
    }

    /**
     * 设置请求路径
     *
     * @param string $path 请求路径
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * 设置是否为上传文件
     *
     * @param bool $upload
     *
     * @return void
     */
    public function setUpload(bool $upload): void
    {
        $this->upload = $upload;
    }

    /**
     * 设置应用ID
     *
     * @param string $appId 应用ID
     */
    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    /**
     * 设置请求数据
     *
     * @param array $data 请求数据
     */
    public function setRequest(array $data): void
    {
        $this->requestData = $data;
    }

    /**
     * 设置签名类型
     *
     * @param string $signType 签名类型
     */
    public function setSignType(string $signType): void
    {
        $this->signType = $signType;
    }

    /**
     * 设置应用证书路径
     *
     * @param string $appCertPath 证书路径
     */
    public function setAppCertPath(string $appCertPath): void
    {
        $this->appCertPath = $appCertPath;
    }

    /**
     * 设置请求方法
     *
     * @param string $method
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * 设置是否为沙箱环境
     *
     * @param bool $sandbox
     */
    public function setSandbox(bool $sandbox): void
    {
        $this->sandbox = $sandbox;
    }

    public function jsonEncode(array $data): string
    {
        return empty($data) ? '' : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 创建请求实例
     *
     * @param array $param 插件配置参数
     *
     * @return self
     */
    public static function newInstance(array $param): self
    {
        $instance = new self();
        $instance->setAppId($param['appid']);
        $instance->setPrivateKey($param['private_key']); // 应用私钥

        $sign_type = $param['sign_type'] ?? 'rsa';
        $instance->setSignType($sign_type);
        if ($sign_type == 'rsa') {
            $instance->setAlipayPublicKey($param['alipay_public_key']);
        } else {
            $instance->setAppCertPath(root_path() . $param['alipay_app_cert']); // 应用公钥证书
            $instance->setAlipayPublicCertPath(root_path() . $param['alipay_public_cert']); // 支付宝公钥证书
        }
        return $instance;
    }
}
