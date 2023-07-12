<?php

namespace Telr\TelrPayments\Controller\Standard;

class IvpCallback extends \Telr\TelrPayments\Controller\TelrPayments {

    public function execute() {
        $this->getTelrModel()->logDebug("IVP Callback Called");
        $this->getTelrModel()->logDebug(json_encode($_POST));
        if (isset($_GET['cart_id']) && !empty($_GET['cart_id']) && !empty($_POST)) {
            // proceed to update order payment details:
            $cartIdExtract = explode("_", $_POST['tran_cartid']);
            $order_id = $cartIdExtract[0];
            
            if ($order_id == $_GET['cart_id']) {
                try {
                    
                    $order = $this->getOrderById($order_id);
                    $tranType = $_POST['tran_type'];
                    $tranStatus = $_POST['tran_authstatus'];
                    $tran_id = $_POST['tran_ref'];

                    if ($tranStatus == 'A') {
                        switch ($tranType) {
                            case '1':
                            case '4':
                            case '7':
                                $this->getTelrModel()->updateOrderStatusWithMessage($order, "complete", $tran_id, true);
                                break;

                            case '2':
                            case '6':
                            case '8':
                                $this->getTelrModel()->updateOrderStatusWithMessage($order, "canceled", $tran_id);
                                break;

                            case '3':
                                $this->getTelrModel()->updateOrderStatusWithMessage($order, "refunded", $tran_id);
                                break;

                            default:
                                // No action defined
                                break;
                        }
                    }
                } catch (Exception $e) {
                    // Error Occurred While processing request.
                     die('Error Occurred While processing request');
                }
            } else {
                 die('Cart id mismatch');
            }
            
            exit;
        }else{
            die('Invalid Cart id');
            exit;
        }
    }

}
