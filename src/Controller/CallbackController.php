<?php

declare(strict_types=1);

namespace  Acme\SyliusExamplePlugin\Controller;

use Payum\Bundle\PayumBundle\Controller\NotifyController;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\OrderRepository;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Model\Order;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Core\Model\OrderInterface;

class CallbackController extends NotifyController
{
    private \Sylius\Component\Resource\Factory\FactoryInterface $orderFactory;
    private OrderRepository $orderRepository;

    public function __construct()
    {
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
                $encrypt = $this->hashEncrypt($string);
                if($pay_type && $order_id){
                    if($result_code == '0000'){
                        //payment success ,redirect to payment success page
                        //update order status to success
                        $result = $db->exec('update sylius_payment set state="completed" where id='.$order_id);
                        $result1 = $db->exec('update sylius_order set payment_state="paid" where id='.$order_id);
                    }else{
                        //payment failed ,redirect to payment failed page
                        $result = $db->exec('update sylius_payment set state="failed" where id='.$order_id);
                        $result1 = $db->exec('update sylius_order set payment_state="failed" where id='.$order_id);
                    }
                    header("Location:".$redirect_url);
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
                $this->record_logs('encrypt pass');
                $db = new MysqlHelper();
                $order = $db->query('select * from sylius_payment where id='.$order_id);
                if($order){
                    $this->record_logs('order data',$order);
                    //authorized, cancelled, cart, completed, failed, new, processing, refunded
                    if($status == 'paid'){
                        //update order status to success
                        $result = $db->exec('update sylius_payment set state="completed" where id='.$order_id);
                        $result1 = $db->exec('update sylius_order set payment_state="paid" where id='.$order_id);
                    }elseif($status == 'failed'){
                        //update order status to failed
                        $result = $db->exec('update sylius_payment set state="failed" where id='.$order_id);
                        $result1 = $db->exec('update sylius_order set payment_state="failed" where id='.$order_id);
                    }elseif($status == 'cancelled'){
                        //update order status to failed
                        $result = $db->exec('update sylius_payment set state="cancelled" where id='.$order_id);
                        $result1 = $db->exec('update sylius_order set payment_state="cancelled" where id='.$order_id);
                    }
                }
                if($result){
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

    public function setApi($api): void
    {
        if (!$api instanceof SyliusApi) {
            throw new UnsupportedApiException('Not supported. Expected an instance of ' . SyliusApi::class);
        }

        $this->api = $api;
    }
}
class MysqlHelper{
    private static $instance;
    private $dbh;
    //私有化构造函数
    public function __construct(){
        $env = new \_PHPStan_ccec86fc8\OndraM\CiDetector\Env();
        $data = $env->get('DATABASE_URL');
        $result = $this->getDatabaseSetting($data);
        if($result['host'] && $result['dbName'] && $result['userName'] && $result['pass']) {
            $this->dbh = new \mysqli($result['host'],$result['userName'],$result['pass'],$result['dbName']);
        }else{
            echo '数据库配置错误';exit;
        }
    }
    //查询方法
    public function query($sql)
    {
        return $this->dbh->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    //删除，更新，添加方法
    public function exec($sql)
    {
        return $this->dbh->query($sql);
    }
    public function getDatabaseSetting($data)
    {
        $temp = explode('//',$data);
        $temp1 = explode(':',$temp[1]);
        $userName = $temp1[0];
        $temp2 = explode('@',$temp1[1]);
        $pass = $temp2[0];
        $temp3 = explode('/',$temp2[1]);
        $database = $temp3[1];
        $temp4 = explode('/',$temp2[1]);
        $host = $temp4[0];
        return [
            'host'=>$host,
            'dbms'=>$temp[0],
            'userName'=>$userName,
            'pass'=>$pass,
            'dbName'=>$database
        ];
    }
}


