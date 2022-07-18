<?php

declare(strict_types=1);

namespace  Acme\SyliusExamplePlugin\Controller;

use Acme\SyliusExamplePlugin\Payum\SyliusApi;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Payum\Bundle\PayumBundle\Controller\NotifyController;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;

class CallbackController extends NotifyController implements ActionInterface, ApiAwareInterface
{
    private $orderFactory;
    private $orderRepository;
    private $paymentRepository;
    private $entityManager;
    private $localeContext;
    private $paymentMethodRepository;

    public function __construct(OrderRepositoryInterface $orderRepository, PaymentRepositoryInterface $paymentRepository,  EntityManagerInterface $entityManager, LocaleContextInterface $localeContext,PaymentMethodRepositoryInterface $paymentMethodRepository){
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->entityManager = $entityManager;
        $this->localeContext = $localeContext;
    }
    public function callback()
    {
        if($_SERVER['REQUEST_METHOD']==='GET'){
            $get_data = $_GET;//3d payment return
            if(!empty($get_data['pay_type']) && $get_data['result_code']){
                $id         = isset($get_data['id'])?$get_data['id']:'';
                $order_id   = isset($get_data['order_id'])?$get_data['order_id']:'';
                $pay_type   = isset($get_data['pay_type'])?$get_data['pay_type']:'';
                $result_code  = isset($get_data['result_code'])?$get_data['result_code']:'';
                $card_no   = isset($get_data['card_no'])?$get_data['card_no']:'';
                $card_orgn   = isset($get_data['card_orgn'])?$get_data['card_orgn']:'';
                $sign_verify   = isset($get_data['sign_verify'])?$get_data['sign_verify']:'';
                $result_msg   = isset($get_data['result_msg'])?$get_data['result_msg']:'';
                $amount   = isset($get_data['amount'])?$get_data['amount']:'';
                $currency = empty($get_data['currency'])?'':$get_data['currency'];
                $metadata   = isset($get_data['metadata'])?$get_data['metadata']:'';

                if($pay_type && $order_id){
                    if($result_code == '0000'){
                        $order = $this->orderRepository->find($order_id);

                        $order->setPaymentState('paid');
                        $payment = $order->getLastPayment();
                        $payment->setState(SyliusPaymentInterface::STATE_COMPLETED);
                        $this->entityManager->persist($order);
                        $this->entityManager->flush();
                    }else{
                        $order = $this->orderRepository->find($order_id);
                        $payment = $order->getLastPayment();
                        $payment->setState(SyliusPaymentInterface::STATE_FAILED);
                        $this->entityManager->persist($order);
                        $this->entityManager->flush();
                    }
                    header("Location:https://".$_SERVER['HTTP_HOST'].'/en_US/order/thank-you');
                    exit;
                }else{
                    exit('[sign_verify-error]');
                }
            }
        }elseif($_SERVER['REQUEST_METHOD']==='POST'){
            $this->record_logs('callback post request');
            $result = file_get_contents('php://input',true);
            $this->record_logs('callback post data',$result);
            $data = json_decode($result,true);
            $id         = empty($data['id'])?'':$data['id']; 			//流水号
            $order_id   = empty($data['order_id'])?'':$data['order_id'];//订单号
            $status     = empty($data['status'])?'':$data['status']; 	//支付状态
            $currency   = empty($data['currency'])?'':$data['currency']; 	//币种
            $amount_value= empty($data['amount_value'])?'':$data['amount_value']; 	//金额，单位为 分
            $metadata   = empty($data['metadata'])?'':$data['metadata'];
            $fail_code  = empty($data['fail_code'])?'':$data['fail_code'];
            $fail_message= empty($data['fail_message'])?'':$data['fail_message'];
            $request_id = empty($data['request_id'])?'':$data['request_id'];
            $sign_verify= empty($data['sign_verify'])?'':$data['sign_verify']; //加密
            //$str = $id.$status.$amount_value.$this->api->getMd5Key().$this->api->getMerchantId().$request_id;
            if($order_id && $status){
                if($order){
                    $this->record_logs('order data',$order);
                    //authorized, cancelled, cart, completed, failed, new, processing, refunded
                    if($status == 'paid'){
                        $order = $this->orderRepository->find($order_id);
                        $order->setPaymentState('paid');
                        $payment = $order->getLastPayment();
                        $payment->setState(SyliusPaymentInterface::STATE_COMPLETED);
                        $this->entityManager->persist($order);
                        $this->entityManager->flush();
                    }elseif($status == 'failed'){
                        $order = $this->orderRepository->find($order_id);
                        $payment = $order->getLastPayment();
                        $payment->setState(SyliusPaymentInterface::STATE_FAILED);
                        $this->entityManager->persist($order);
                        $this->entityManager->flush();
                    }elseif($status == 'cancelled'){
                        $order = $this->orderRepository->find($order_id);
                        $payment = $order->getLastPayment();
                        $payment->setState(SyliusPaymentInterface::STATE_CANCELLED);
                        $this->entityManager->persist($order);
                        $this->entityManager->flush();
                    }
                }
                if($result && $result1){
                    exit('[success]');
                }else{
                    exit('[update_failed]');
                }
            }
        }
        exit('[request-error]');
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

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof SyliusPaymentInterface;
    }

    public function execute($request)
    {

    }
    public function setApi($api): void
    {
        echo 'setapi in';
        if (!$api instanceof SyliusApi) {
            echo 'throw exception';
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . SyliusApi::class);
        }
        echo 'set api';
        $this->api = $api;
    }
}

