<?php

namespace Wood\Sdk\Contracts;

/**
 * 签名器接口
 *
 * 定义了数据签名和验证的标准方法，用于实现各种签名算法（如RSA、HMAC等）。
 * 实现此接口的类可以对数据进行数字签名生成，以及验证签名数据的真实性。
 *
 * 典型使用场景:
 * - 微信支付 V3 API 的 RSA 签名验证
 * - 支付宝 API 的 RSA/SM2 签名验证
 * - 第三方 API 的 HMAC 签名计算
 *
 * @author  WooD
 * @package Wood\Sdk\Contracts
 */
interface SignerInterface
{
    /**
     * 初始化签名器
     *
     * @param string $key 签名密钥（私钥/公钥/HMAC密钥等）
     */
    public function __construct(string $key);

    /**
     * 对数据进行签名
     *
     * @param string $data  待签名的数据内容
     * @param array  $info  签名所需的附加信息（如请求方法、路径、时间戳等）
     *
     * @return string 编码后的签名字符串（通常为 Base64 格式）
     */
    public function sign(string $data, array $info = []): string;

    /**
     * 验证签名数据的真实性
     *
     * @param string $data  原始数据内容
     * @param string $sign  待验证的签名字符串
     *
     * @return bool 验证是否通过
     */
    public function verify(string $data, string $sign): bool;
}
