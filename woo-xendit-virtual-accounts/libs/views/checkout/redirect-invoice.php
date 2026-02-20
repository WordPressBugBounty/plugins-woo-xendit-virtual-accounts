<?php

if (!empty($invoice_url)) {
    ?>
    <p id="xendit-invoice-countdown"></p>
    <script>
        var timeLeft = <?php echo absint($delay); ?>;
        var elem = document.getElementById('xendit-invoice-countdown');

        // Load after everything is rendered
        window.addEventListener("load", function () {
            // Update the count down every 1 second
            var x = setInterval(function () {
                if (timeLeft == 0) {
                    clearTimeout(x);
                    var invoiceUrl = "<?php echo esc_url($invoice_url); ?>";
                    window.location.replace(invoiceUrl);
                    elem.innerHTML = 'Not redirected automatically? <button id="xendit-invoice-onclick">Pay Now</button>';

                    var button = document.getElementById('xendit-invoice-onclick');

                    button.onclick = function () {
                        location.href = invoiceUrl;
                    }
                } else {
                    elem.innerHTML = 'Thank you for placing the order, you will be redirected in ' + timeLeft;
                    timeLeft--;
                }
            }, 1000);
        });
    </script>

    <style>
        #xendit-invoice-countdown {
            font-size: 24px;
            text-align: center;
        }

        #xendit-invoice-onclick {
            background: #4481F1;
            border-radius: 10px;
            color: #FFFFFF;
            line-height: 28px;
            margin-left: 16px;
        }
    </style>
    <?php
}
