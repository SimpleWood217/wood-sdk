<?php
declare(strict_types=1);

namespace Wood\Sdk;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * 支付宝V2接口SDK
 * 支持公私钥、证书两种签名方式
 * V2文档：https://opendocs.alipay.com/open
 *
 * @author  WooD
 * @version 1.0.0
 */
class AlipayServiceClientV2
{
    protected string $gateway              = 'https://openapi.alipay.com/gateway.do';
    protected bool   $strict;                           // 严格模式(开启后则会校验支付宝请求响应签名)
    protected string $path                 = '';        // 请求路径
    protected string $signType             = 'rsa';     // 签名类型(rsa公私钥、cert证书)
    protected string $method               = 'POST';    // 请求方法
    protected string $action               = '';        // 接口名
    protected string $appId                = '';        // 支付宝应用ID
    protected array  $bizContent           = [];        // 业务参数
    protected string $notifyUrl            = '';        // 异步通知地址
    protected string $returnUrl            = '';        // 同步通知地址
    protected bool   $isPageExecute        = true;      // 是否为页面跳转
    protected string $privateKey           = '';        // 商户私钥
    protected string $alipayPublicKey      = '';        // 支付宝公钥
    protected string $appCertPath          = '';        // 应用公钥证书路径
    protected string $alipayPublicCertPath = '';        // 支付宝公钥证书路径
    protected string $alipayRootCertPath   = '';        // 支付宝根证书路径

    function __construct($strict = true)
    {
        $this->strict = $strict;
    }

    /**
     * 执行请求
     *
     * @throws Exception|GuzzleException
     *
     * @return array|false|string|string[]|null
     */
    public function execute(): array|false|string|null
    {
        // 1. 检查必要参数
        if (empty($this->appId)) {
            throw new Exception('应用ID不能为空');
        }
        if ($this->signType === 'rsa') {
            if (empty($this->privateKey)) {
                throw new Exception('商户私钥不能为空');
            }
            if (empty($this->alipayPublicKey)) {
                throw new Exception('支付宝公钥不能为空');
            }
        } else {
            if (!file_exists($this->appCertPath)) {
                throw new Exception('应用公钥证书路径不存在');
            }
            if (!file_exists($this->alipayPublicCertPath)) {
                throw new Exception('支付宝公钥证书路径不存在');
            }
            if (!file_exists($this->alipayRootCertPath)) {
                throw new Exception('支付宝根证书路径不存在');
            }
        }

        $bizContent = $this->jsonEncode($this->bizContent);

        // 2. 构建公共请求参数
        $reqData = [
            'app_id'      => $this->appId,
            'method'      => $this->action,
            'format'      => 'json', // 默认增加 format 以防部分网关接口强校验
            'charset'     => 'utf-8',
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'biz_content' => $bizContent,
        ];

        if (!empty($this->notifyUrl)) {
            $reqData['notify_url'] = $this->notifyUrl;
        }
        if (!empty($this->returnUrl)) {
            $reqData['return_url'] = $this->returnUrl;
        }

        if ($this->signType === 'cert') {
            $reqData['app_cert_sn'] = $this->getCertSN($this->appCertPath);
            $reqData['alipay_root_cert_sn'] = $this->getRootCertSN($this->alipayRootCertPath);
        }

        // 3. 生成签名（签名算法会自动剔除空值并排序，包含 biz_content）
        $reqData['sign'] = $this->sign($reqData);

        // 4. 路由：根据 isPageExecute 决定执行模式
        if ($this->isPageExecute === true) {

            // 模式 A：页面跳转 - GET
            if (strtoupper($this->method) === 'GET') {
                return $this->gateway . '?' . http_build_query($reqData);
            }

            // 模式 B：页面跳转 - POST (Form表单提交)
            $queryParams = $reqData;
            unset($queryParams['biz_content']); // GET参数中剔除业务数据，防止URL超长
            $actionUrl = $this->gateway . '?' . http_build_query($queryParams);

            // 防护：将双引号转义为 &quot; 防止 HTML value 属性被截断
            $bizContentHtml = htmlspecialchars($bizContent, ENT_QUOTES, 'UTF-8');

            return <<<HTML
<form name="punchout_form" method="post" action="{$actionUrl}">
<input type="hidden" name="biz_content" value="$bizContentHtml">
<input type="submit" value="立即支付" style="display:none" >
</form>
<script>document.forms[0].submit();</script>
HTML;
        }

        // 模式 C：非页面跳转 - Guzzle 服务端直连
        $client = new Client();
        $res = $client->request('POST', $this->gateway . $this->path, [
            'form_params' => $reqData
        ]);

        $body = $res->getBody()->getContents();
        $body = mb_convert_encoding($body, 'UTF-8', 'GBK');

        echo $body;
        return $body;
    }

    /**
     * 生成签名
     *
     * @throws Exception
     */
    private function sign($bizContent): string
    {
        $privateKey = $this->loadPrivateKey();
        if (!openssl_pkey_get_private($privateKey)) throw new Exception('商户私钥加载失败');
        if ($this->signType === 'cert') {
            $bizContent['app_cert_sn'] = $this->getCertSN($this->appCertPath);
            $bizContent['alipay_root_cert_sn'] = $this->getRootCertSN($this->alipayRootCertPath);
        }
        $signStr = '';
        ksort($bizContent);
        foreach ($bizContent as $key => $value) {
            if ($key !== 'sign') {
                $signStr .= $key . '=' . $value . '&';
            }
        }
        $signStr = rtrim($signStr, '&');
        openssl_sign($signStr, $sign, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
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
        $sslData = openssl_x509_parse($certContent);
        if (!$sslData) {
            throw new Exception("证书解析失败");
        }
        $issuerParts = [];
        $issuer = array_merge([], $sslData['issuer']);
        foreach (array_reverse($issuer) as $key => $value) {
            $issuerParts[] = $key . '=' . $value;
        }
        $issuerString = implode(',', $issuerParts);
        $rawSN = $issuerString . $sslData['serialNumber'];
        return md5($rawSN);
    }


    /**
     * 获取支付宝根证书序列号（SN）
     *
     * @param string $certPath 根证书路径
     *
     * @return string|null
     */
    private function getRootCertSN(string $certPath): ?string
    {
        $cert = file_get_contents($certPath);
        if (!$cert) return null;
        $array = explode("-----END CERTIFICATE-----", $cert);
        $SN = null;
        for ($i = 0; $i < count($array) - 1; $i++) {
            $ssl = openssl_x509_parse($array[$i] . "-----END CERTIFICATE-----");
            if (!$ssl) continue;
            // === 内联 hex2dec（保持官方逻辑）===
            if (str_starts_with($ssl['serialNumber'], '0x')) {
                $hex = $ssl['serialNumberHex'];
                $dec = "0";
                $len = strlen($hex);
                for ($j = 1; $j <= $len; $j++) {
                    $dec = bcadd(
                        $dec,
                        bcmul(strval(hexdec($hex[$j - 1])), bcpow('16', strval($len - $j)))
                    );
                }
                $ssl['serialNumber'] = $dec;
            }
            // 仅处理 RSA 证书（官方逻辑）
            if ($ssl['signatureTypeLN'] == "sha1WithRSAEncryption" ||
                $ssl['signatureTypeLN'] == "sha256WithRSAEncryption") {
                // === 内联 array2string（保持官方逻辑）===
                $issuer = array_reverse($ssl['issuer']);
                $issuerString = [];
                foreach ($issuer as $key => $value) {
                    $issuerString[] = $key . '=' . $value;
                }
                $issuerString = implode(',', $issuerString);
                $snPart = md5($issuerString . $ssl['serialNumber']);
                if ($SN === null) {
                    $SN = $snPart;
                } else {
                    $SN .= "_" . $snPart;
                }
            }
        }
        return $SN;
    }


    /**
     * 加载商户私钥
     *
     * @return string 格式化后的私钥
     */
    private function loadPrivateKey(): string
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
    private function loadAlipayPublicKey(): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
               . wordwrap($this->alipayPublicKey, 64, "\n", true)
               . "\n-----END PUBLIC KEY-----";
    }

    /**
     * 加载应用公钥证书
     *
     * @return string
     */
    private function loadAppCert(): string
    {
        return file_get_contents($this->appCertPath);
    }

    /**
     * 加载支付宝公钥证书
     *
     * @return string 证书内容
     */
    private function loadAlipayPublicCert(): string
    {
        return file_get_contents($this->alipayPublicCertPath);
    }

    /**
     * 加载支付宝根证书
     *
     * @return string
     */
    private function loadAlipayRootCert(): string
    {
        return file_get_contents($this->alipayRootCertPath);
    }

    private function jsonEncode(array $data): string
    {
        return empty($data) ? '' : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 设置应用ID
     *
     * @param string $appId
     *
     * @return void
     */
    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    /**
     * 设置是否为页面跳转执行
     *
     * @param bool $isPageExecute
     *
     * @return void
     */
    public function setIsPageExecute(bool $isPageExecute): void
    {
        $this->isPageExecute = $isPageExecute;
    }

    /**
     * 设置网关
     *
     * @param string $gateway
     *
     * @return void
     */
    public function setGateway(string $gateway): void
    {
        $this->gateway = $gateway;
    }

    /**
     * 设置请求路径
     *
     * @param string $path
     *
     * @return void
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * 设置签名类型
     *
     * @param string $signType
     *
     * @return void
     */
    public function setSignType(string $signType): void
    {
        $this->signType = $signType;
    }

    /**
     * 设置请求方法
     *
     * @param string $method
     *
     * @return void
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * 设置接口名
     *
     * @param string $action
     *
     * @return void
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /**
     * 设置业务参数
     *
     * @param array $bizContent
     *
     * @return void
     */
    public function setBizContent(array $bizContent): void
    {
        $this->bizContent = $bizContent;
    }

    /**
     * 设置商户私钥
     *
     * @param string $privateKey
     *
     * @return void
     */
    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    /**
     * 设置支付宝公钥
     *
     * @param string $alipayPublicKey
     *
     * @return void
     */
    public function setAlipayPublicKey(string $alipayPublicKey): void
    {
        $this->alipayPublicKey = $alipayPublicKey;
    }

    /**
     * 设置应用公钥证书路径
     *
     * @param string $appCertPath
     *
     * @return void
     */
    public function setAppCertPath(string $appCertPath): void
    {
        $this->appCertPath = $appCertPath;
    }

    /**
     * 设置支付宝公钥证书路径
     *
     * @param string $alipayPublicCertPath
     *
     * @return void
     */
    public function setAlipayPublicCertPath(string $alipayPublicCertPath): void
    {
        $this->alipayPublicCertPath = $alipayPublicCertPath;
    }

    /**
     * 设置支付宝根证书路径
     *
     * @param string $alipayRootCertPath
     *
     * @return void
     */
    public function setAlipayRootCertPath(string $alipayRootCertPath): void
    {
        $this->alipayRootCertPath = $alipayRootCertPath;
    }

    /**
     * 设置异步通知地址
     *
     * @param string $notifyUrl
     *
     * @return void
     */
    public function setNotifyUrl(string $notifyUrl): void
    {
        $this->notifyUrl = $notifyUrl;
    }

    /**
     * 设置同步通知地址
     *
     * @param string $returnUrl
     *
     * @return void
     */
    public function setReturnUrl(string $returnUrl): void
    {
        $this->returnUrl = $returnUrl;
    }

    /**
     * 创建请求实例
     *
     * @param $param
     *
     * @return self
     */
    public static function newInstance($param): self
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
            $instance->setAlipayRootCertPath(root_path() . $param['alipay_root_cert']); // 支付宝根证书
        }
        return $instance;
    }
}