<?php
/**
 * Created by NextPay.ir
 * author: Nextpay Company
 * ID: @nextpay
 * Date: 2018/10/27
 * Time: 5:05 PM
 * Website: NextPay.ir
 * Email: info@nextpay.ir
 * @copyright 2018
 */
namespace Payments;
use Input;
use NextPay\NextpayPayment;


class Nextpay extends AbstractGateway
{

    /**
     * Nextpay Api key
     * @var ApiKey
     */
    private $api_key;

    /**
     * Init.
     */
    public function __construct()
    {
        $this->api_key = $this->apiController();
    }

    private function apiController(){
        if ($this->api_key == ""){
            $integrations = \Controller::model("GeneralData", "integrations");
            $this->api_key = $integrations->get("data.nextpay.api_key");
        }
        return $this->api_key;
    }
    /**
     * Place Order
     *
     * Generate payment page url here and return it
     * @return string URL of the payment page
     */
    public function placeOrder($params = [])
    {

        $Order = $this->getOrder();

        if (!$Order) {
            throw new \Exception('Set order before calling AbstractGateway::placeOrder()');
        }

        if ($Order->get("status") != "payment_processing") {
            throw new \Exception('Order status must be payment_processing to place it');
        }

        $currency = $Order->get("currency");
        $amount = $Order->get("total");
        $callback_uri = APPURL."/checkout/".$Order->get("id").".".sha1($Order->get("id").NP_SALT);
        $order_id = $Order->get("id");

        if ($currency == "RIAL"){
            $amount /= 10;
        }

        $params = array(
            "api_key"=>$this->api_key,
            "amount"=>$amount,
            "order_id"=>$order_id,
            "callback_uri"=>$callback_uri
        );

        $payment = new NextpayPayment($params);
        $res = $payment->token();

        $request_http = "https://api.nextpay.org/gateway/payment";
        $ret = Array("url"=> "xxxx", "result"=> -1000, "msg"=> "Error Gateway");
        if(intval($res->code) == -1){
            $trans_id = $res->trans_id;
            if(isset($trans_id))
            {

                    $ret = Array("url"=> $request_http."/".$trans_id, "result"=> intval($res->code), "msg" => "Success");
                    return $ret;
            }
            else

                return $ret;
        }
        else
        {
            $ret['url'] = APPURL."/checkout/error";
            return $ret;
        }

    }


    /**
     * Payment callback
     * @return boolean [description]
     */
    public function callback($params = [])
    {

        $Order = $this->getOrder();
        if (!$Order) {
            throw new \Exception('Set order before calling AbstractGateway::placeOrder()');
        }
        $order_id = $Order->get("id");
        $trans_id = Input::post("trans_id");
        $amount = $Order->get("total");
        $params = array(
                "api_key"=>$this->api_key,
                "order_id"=>$order_id,
                "amount"=>$amount,
                "trans_id"=>$trans_id);

        $payment = new NextpayPayment();
        $res = $payment->verify_request($params);

        if ($res == 0) {
            $Order->finishProcessing();

            // Updating order...
            $Order->set("status","paid")
                ->set("payment_id", $trans_id)
                ->set("paid", $amount)
                ->update();
            try {
                // Send notification emails to admins
                \Email::sendNotification("new-payment", ["order" => $Order]);
            } catch (\Exception $e) {
                // Failed to send notification email to admins
                // Do nothing here, it's not critical error
            }
            return true;
        }
    }
}

