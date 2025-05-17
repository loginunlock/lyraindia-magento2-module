<?php

/**
 * LyraIndia Magento2 Module using \Magento\Payment\Model\Method\AbstractMethod
 * Copyright (C) 2025 Lyra.com
 * 
 * This file is part of Lyra/LyraIndia.
 * 
 * Lyra/LyraIndia is free software => you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http =>//www.gnu.org/licenses/>.
 */

namespace Lyra\LyraIndia\Controller\Payment;

use Magento\Sales\Model\Order;

class Recreate extends AbstractLyraIndiaStandard {

    public function execute() {
        
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation("Payment failed or cancelled")->save();
            
        }
        
        $this->checkoutSession->restoreQuote();
        $this->_redirect('checkout', ['_fragment' => 'payment']);
    }

}
