<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!empty($payment_link_url)) {
    ?>
    <p id="xendit-payment-countdown"></p>
    <script>
        var timeLeft = <?php echo absint($delay); ?>;
        var elem = document.getElementById('xendit-payment-countdown');

        // Load after everything is rendered
        window.addEventListener("load", function () {
            // Update the count down every 1 second
            var x = setInterval(function () {
                if (timeLeft == 0) {
                    clearTimeout(x);
                    var paymentLinkUrl = "<?php echo esc_url($payment_link_url); ?>"
                    window.location.replace(paymentLinkUrl);
                    elem.innerHTML = 'Not redirected automatically? <button id="xendit-payment-onclick">Pay Now</button>';

                    var button = document.getElementById('xendit-payment-onclick');

                    button.onclick = function () {
                        location.href = paymentLinkUrl;
                    }
                } else {
                    elem.innerHTML = 'Thank you for placing the order, you will be redirected in ' + timeLeft;
                    timeLeft--;
                }
            }, 1000);
        });
    </script>

    <style>
        #xendit-payment-countdown {
            font-size: 24px;
            text-align: center;
        }

        #xendit-payment-onclick {
            background: #4481F1;
            border-radius: 10px;
            color: #FFFFFF;
            line-height: 28px;
            margin-left: 16px;
        }
    </style>
    <?php
}
