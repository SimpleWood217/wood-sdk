<?php

namespace Wood\Sdk\Abstracts;

use Wood\Sdk\Exceptions\InvalidConfigException;

class BaseConfig
{
    protected array $essential_config = [];

    /**
     * 检查配置是否完整
     *
     * @param array $config 配置数组
     *
     * @throws InvalidConfigException
     * @return void
     */
    public function check(array $config): void
    {
        foreach ($this->essential_config as $key) {
            if (!isset($config[$key])) {
                throw new InvalidConfigException("缺少必要配置项: $key");
            }
        }
    }

    /**
     * 获取配置项
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->$key;
    }

    /**
     * 获取所有配置项
     *
     * @return array
     */
    public function getAll(): array
    {
        return get_object_vars($this);
    }
}