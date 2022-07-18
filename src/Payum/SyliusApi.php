<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Payum;

final class SyliusApi
{
    /** @var string */
    private $merchant_id;
    private $md5key;
    private $gateway_url;

    public function __construct(string $merchant_id,string $md5key,$gateway_url)
    {
        $this->merchant_id = $merchant_id;
        $this->md5key = $md5key;
        $this->gateway_url = $gateway_url;
    }

    public function getMerchantId(): string
    {
        return $this->merchant_id;
    }
    public function getMd5Key(): string
    {
        return $this->md5key;
    }
    public function getGatewayUrl(): string
    {
        return $this->gateway_url;
    }
}
