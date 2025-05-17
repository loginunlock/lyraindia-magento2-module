/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'lyra_lyraindia',
                component: 'Lyra_LyraIndia/js/view/payment/method-renderer/lyra_lyraindia-method'
            }
        );

        /** Add view logic here if needed */
        
        return Component.extend({});
    }
);