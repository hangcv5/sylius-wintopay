<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Payum\Action;

use Acme\SyliusExamplePlugin\Payum\SyliusApi;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Payum\Core\Request\Capture;

final class CaptureAction implements ActionInterface, ApiAwareInterface
{
    /** @var Client */
    private $client;
    /** @var SyliusApi */
    private $api;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);
        $model = $request->getModel();
        $order = $request->getFirstModel()->getOrder();
        /** @var PaymentInterface $payment */
        $payment = $request->getFirstModel();
        /** @var SyliusPaymentInterface $payment */
        /** @var CustomerInterface $customer */
        $customer = $order->getCustomer();
        //持卡人账单信息
        $billing_first_name = empty($order->getBillingAddress()->getFirstName())?'':$order->getBillingAddress()->getFirstName();
        $billing_last_name  = empty($order->getBillingAddress()->getLastName())?'':$order->getBillingAddress()->getLastName();
        $billing_email      = empty((string) $customer->getEmail())?'':(string) $customer->getEmail();
        $billing_country    = empty($order->getBillingAddress()->getCountryCode())?'':$order->getBillingAddress()->getCountryCode();
        $billing_state      = empty($order->getBillingAddress()->getProvinceName())?'':$order->getBillingAddress()->getProvinceName();
        $billing_city       = empty($order->getBillingAddress()->getCity())?'':$order->getBillingAddress()->getCity();
        $billing_postal_code= empty($order->getBillingAddress()->getPostcode())?'':$order->getBillingAddress()->getPostcode();
        $billing_address    = empty($order->getBillingAddress()->getStreet())?'':$order->getBillingAddress()->getStreet();
        $billing_phone      = empty($order->getBillingAddress()->getPhoneNumber())?'':$order->getBillingAddress()->getPhoneNumber();

        //收货地址,没有就保持和账单信息一致
        $shipping_first_name  = empty($order->getShippingAddress()->getFirstName())?$order->getBillingAddress()->getFirstName():$order->getShippingAddress()->getFirstName();
        $shipping_last_name   = empty($order->getShippingAddress()->getLastName())?$order->getBillingAddress()->getLastName():$order->getShippingAddress()->getLastName();
        $shipping_email       = empty((string) $customer->getEmail())?'':(string) $customer->getEmail();
        $shipping_country     = empty($order->getShippingAddress()->getCountryCode())?$order->getBillingAddress()->getCountryCode():$order->getShippingAddress()->getCountryCode();
        $shipping_state       = empty($order->getShippingAddress()->getProvinceName())?$order->getBillingAddress()->getProvinceName():$order->getShippingAddress()->getProvinceName();
        $shipping_city        = empty($order->getShippingAddress()->getCity())?$order->getBillingAddress()->getCity():$order->getShippingAddress()->getCity();
        $shipping_postal_code = empty($order->getShippingAddress()->getPostcode())?$order->getBillingAddress()->getPostcode():$order->getShippingAddress()->getPostcode();
        $shipping_address     = empty($order->getShippingAddress()->getStreet())?$order->getBillingAddress()->getStreet():$order->getShippingAddress()->getStreet();
        $shipping_phone       = empty($order->getShippingAddress()->getPhoneNumber())?$order->getBillingAddress()->getPhoneNumber():$order->getShippingAddress()->getPhoneNumber();
        $currency = $payment->getCurrencyCode();
        if(empty($billing_postal_code) && empty($shipping_postal_code)){
            $billing_postal_code = $shipping_postal_code = '000000';
        }
        /*if(empty($billing_state) && empty($shipping_state)){
            $billing_state = $shipping_state = $billing_city;
        }*/
        $website = $_SERVER['HTTP_HOST'];
        $return_url = $this->api->getReturnUrl();
        $data = [
            'billing_first_name' => $billing_first_name,
            'billing_last_name'  => $billing_last_name,
            'billing_email'      => $billing_email,
            'billing_phone'      => $billing_phone,
            'billing_postal_code'=> $billing_postal_code,
            'billing_address'    => $billing_address,
            'billing_city'       => $billing_city,
            'billing_state'      => $billing_state,
            'billing_country'    => $billing_country,
            'shipping_first_name' => $shipping_first_name,
            'shipping_last_name'  => $shipping_last_name,
            'shipping_email'      => $shipping_email,
            'shipping_phone'      => $shipping_phone,
            'shipping_postal_code'=> $shipping_postal_code,
            'shipping_address'    => $shipping_address,
            'shipping_city'       => $shipping_city,
            'shipping_state'      => $shipping_state,
            'shipping_country'    => $shipping_country,

            'email'=>(string) $customer->getEmail(),
            'merchant_id'=>$this->api->getMerchantId(),
            'order_amount'=>($payment->getAmount()/100),
            'currency'=>$currency,
            'order_id'=>$order->getNumber(),
            'freight'=>$order->getShippingTotal(),
            'ip'=> $this->getIP(),
            'language'          => 'en',
            'hash'=>md5($this->api->getMerchantId().$order->getNumber().($payment->getAmount()/100).$currency.$this->api->getMd5Key().$website),
            'metadata'=>'',
            'session_id'=>'',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'version'       =>'20201001',
            'products'=>json_encode($this->getOrderItems($order,$currency)),
            'success_url'=>$return_url,
            'fail_url'=>$return_url,
            'pending_url'=>$return_url,
        ];
        try {
            $response = $this->wccpaycurlPost($this->api->getGatewayUrl(),$data,$website,$this->api->getMerchantId());
            $response_data = json_decode($response,true);
            $status_code = empty($response_data['status_code'])?'':$response_data['status_code'];
            $status = empty($response_data['status'])?'':$response_data['status'];
            $message = empty($response_data['message'])?'':$response_data['message'];
            $fail_code = empty($response_data['fail_code'])?'':$response_data['fail_code'];
            $cy_id = empty($response_data['cy_id'])?'':$response_data['cy_id'];
            $expires = empty($response_data['expires'])?'':$response_data['expires'];
            $redirect_url = empty($response_data['redirect_url'])?'':$response_data['redirect_url'];
            if($status == 'authorization' && $redirect_url){
                header("Location:".$redirect_url);
                exit;
            }else{
                $this->record_logs('something wrong');
            }
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
        } finally {
            header("Location:".$redirect_url);
            exit;
        }
    }
//curl封装
    public function wccpaycurlPost($url, $data,$website,$merchant_id)
    {
        $headers = array(
            'MerNo:'.$merchant_id,
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL ,$url);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_REFERER,$website);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,20);
        curl_setopt($ch, CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
        $data = curl_exec($ch);
        if($data === false){
            echo 'Curl error: ' . curl_error($ch);
        }
        curl_close($ch);
        return $data;
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof SyliusPaymentInterface;
    }

    public function setApi($api): void
    {
        if (!$api instanceof SyliusApi) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . SyliusApi::class);
        }

        $this->api = $api;
    }
    private function getOrderItems(OrderInterface $order,$currency): array
    {
        $itemsData = [];

        if ($items = $order->getItems()) {
            /** @var OrderItemInterface $item */
            foreach ($items as $key => $item) {
                $itemsData[$key] = [
                    'name' => $item->getProductName(),
                    'amount' => $item->getUnitPrice()/100,
                    'quantity' => $item->getQuantity(),
                    'sku'=>'',
                    'currency'=>$currency,
                ];
            }
        }

        return $itemsData;
    }
    /**
     * @param $data
     * @param string $file_name
     * 记录日志方法
     */
    public function record_logs($message = '',$data = '',$file_name='logs.txt')
    {
        $date = date('Y-m-d H:i:s',time());
        if(is_array($data)){
            file_put_contents($file_name,$date.' :: '.$message.' -- '.var_export($data,true).PHP_EOL,FILE_APPEND);
        }elseif(is_string($data)){
            file_put_contents($file_name,$date.' :: '.$message.' -- '.$data.PHP_EOL,FILE_APPEND);
        }else{
            file_put_contents($file_name,$date.' :: '.$message.' -- '.'unknow type'.PHP_EOL,FILE_APPEND);
        }
    }
    //获取用户ip
    function getIP(){
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $online_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        elseif(isset($_SERVER['HTTP_CLIENT_IP'])){
            $online_ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif(isset($_SERVER['HTTP_X_REAL_IP'])){
            $online_ip = $_SERVER['HTTP_X_REAL_IP'];
        }else{
            $online_ip = $_SERVER['REMOTE_ADDR'];
        }
        $ips = explode(",",$online_ip);
        return $ips[0];
    }
}
