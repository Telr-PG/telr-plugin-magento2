<?php

namespace Telr\TelrPayments\Controller\Standard;

class Process extends \Telr\TelrPayments\Controller\TelrPayments {

    public function execute() {
        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }
}
