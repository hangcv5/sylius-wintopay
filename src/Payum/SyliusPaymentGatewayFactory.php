<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Payum;

use Acme\SyliusExamplePlugin\Payum\Action\StatusAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

final class SyliusPaymentGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'wintopay_payment',
            'payum.factory_title' => 'Wintopay Payment',
            'payum.action.status' => new StatusAction(),
        ]);
        
        $config['payum.api'] = function (ArrayObject $config) {
            return new SyliusApi($config['merchant_id'],$config['md5key'],$config['gateway_url']);
        };
    }
}