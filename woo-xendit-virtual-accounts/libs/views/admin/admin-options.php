<?php if ($this->is_connected) : ?>
<table class="form-table">
    <?php $this->show_merchant_info(); ?>

    <?php
    // Remove secret key settings if merchant connected via OAuth
    if (empty($this->get_option('secret_key')) && empty($this->get_option('secret_key_dev'))) {
        unset($this->form_fields['dummy_secret_key']);
        unset($this->form_fields['dummy_secret_key_dev']);
    }
    ?>

    <?php $this->generate_settings_html(); ?>
</table>
<?php endif ?>

<style>
    .xendit-ttl-wrapper {
        width: 400px;
        position: relative;
    }

    .xendit-ttl,
    .xendit-ext-id {
        width: 320px !important;
    }

    .xendit-form-suffix {
        width: 70px;
        position: absolute;
        bottom: 6px;
        right: 0;
    }
</style>

<script>
    jQuery(document).ready(function ($) {
        // always hide oauth fields
        $(".xendit-oauth").parents("tr").hide();

        <?php if (!$this->is_connected) : ?>
            $(".api-keys-container").hide();
        <?php endif ?>

        // Disconect action
        let disconect_button = $('#woocommerce_<?php echo esc_html($this->id) ?>_disconnect_button');
        disconect_button.on('click', function (e) {
            e.preventDefault();
            new swal({
                title: "Are you sure you want to disconnect Xendit payment?",
                text: "Transactions can no longer be made, and all settings will be lost.",
                icon: "warning",
                dangerMode: true,
                buttons: ["Cancel", "Disconnect"],
            })
                .then((willDelete) => {
                    if (willDelete) {
                        disconect_button.text('Loading, please wait a moment...').attr('disabled', true);

                        fetch("<?php esc_url(home_url()); ?>/wp-json/xendit-wc/v1/disconnect", {
                            method: "DELETE",
                            headers: {
                                "Content-Type": "application/json",
                                "X-WP-Nonce": '<?php echo esc_html(wp_create_nonce('wp_rest'))?>'
                            }
                        })
                            .then((response) => response.json())
                            .then(json => {
                                switch (json.message) {
                                    case 'success':
                                        location.reload();
                                        break;
                                    case 'Sorry, you are not allowed to do that.':
                                        new swal({
                                            type: 'error',
                                            title: 'Failed',
                                            text: 'Only Administrators and Shop Managers can disconnect'
                                        }).then(
                                            function () {
                                                location.reload();
                                            }
                                        )
                                        break;
                                    default:
                                        new swal({
                                            type: 'error',
                                            title: 'Failed',
                                            text: json.message
                                        }).then(
                                            function () {
                                                location.reload();
                                            }
                                        )
                                        break;
                                }
                            })
                            .catch(error => {
                                new swal({
                                    type: 'error',
                                    title: 'Failed',
                                    text: 'Oops, something wrong happened! Please try again.'
                                }).then(
                                    function () {
                                        location.reload();
                                    }
                                )
                            });
                    }
                });
        });

        // Change send data value
        let send_data_button = $('#woocommerce_<?php echo esc_html($this->id) ?>_send_site_data_button');
        send_data_button.val('<?php echo esc_html(__('Send site data to Xendit', 'woo-xendit-virtual-accounts')); ?>');

        send_data_button.on('click', function (e) {
            <?php
            try {
                $site_data = WC_Xendit_Site_Data::retrieve();
                $this->xenditClass->createPluginInfo($site_data);
                ?>
                    new swal({
                        type: 'success',
                        title: '<?php echo esc_html(__('Success', 'woo-xendit-virtual-accounts')); ?>',
                        text: '<?php echo esc_html(__('Thank you! We have successfully collected all the basic information that we need to assist you with any issues you may have. All data will remain private & confidential', 'woo-xendit-virtual-accounts')); ?>'
                    }).then(
                        function () {
                            location.reload();
                        }
                    )
                <?php
            } catch (\Throwable $th) {
                ?>
                    new swal({
                        type: 'error',
                        title: '<?php echo esc_html(__('Failed', 'woo-xendit-virtual-accounts')); ?>',
                        text: '<?php echo esc_html(__('Oops, something wrong happened! Please try again', 'woo-xendit-virtual-accounts')); ?>'
                    }).then(
                        function () {
                            location.reload();
                        }
                    )
                <?php
            }
            ?>
        });

        let xendit_connect_button = $('#woocommerce_xendit_connect_button');
        xendit_connect_button.on('click', function (e) {
            e.preventDefault();
            window.open("<?php echo esc_url_raw(sanitize_url($this->oauth_link)); ?>", '_blank').focus();

            new swal({
                title: "<?php echo esc_html__('Loading', 'woo-xendit-virtual-accounts'); ?> ...",
                text: "<?php echo esc_html__('Please finish your integration on Xendit', 'woo-xendit-virtual-accounts'); ?>",
                buttons: ["Cancel", false],
                closeOnClickOutside: false,
            }).then(
                function () {
                    location.reload();
                }
            );

            // Check OAuth status every 5 seconds
            let checkOauthStatusInterval = setInterval(() => {
                fetch("<?php echo esc_url(home_url()); ?>/wp-json/xendit-wc/v1/oauth_status", {
                    method: "GET",
                    headers: {
                        "Content-Type": "application/json",
                        "X-WP-Nonce": '<?php echo esc_html(wp_create_nonce('wp_rest'))?>'
                    }
                })
                    .then((response) => response.json())
                    .then(json => {
                        if (json.is_connected) {
                            location.reload();
                        }
                        if (!json.is_connected && json.error_code) {
                            clearInterval(checkOauthStatusInterval);
                            new swal({
                                type: 'error',
                                icon: "warning",
                                dangerMode: true,
                                title: json.error_code,
                                text: "<?php esc_html__('Integration has been declined. Please try again', 'woo-xendit-virtual-accounts'); ?>",
                                buttons: [false, true],
                                closeOnClickOutside: false,
                            });
                        }
                    });
            }, 5000);
        });

        <?php if ($this->developmentmode == 'yes') { ?>
        $('.xendit_dev').parents('tr').show();
        $('.xendit_live').parents('tr').hide();
        <?php } else { ?>
        $('.xendit_dev').parents('tr').hide();
        $('.xendit_live').parents('tr').show();
        <?php } ?>

        <?php if ($this->for_user_id) { ?>
        $("#woocommerce_<?php echo esc_html($this->id) ?>_enable_xenplatform").prop('checked', true);
        $('.xendit-xenplatform').parents('tr').show();
        <?php } else { ?>
        $("#woocommerce_<?php echo esc_html($this->id) ?>_enable_xenplatform").prop('checked', false);
        $('.xendit-xenplatform').parents('tr').hide();
        <?php } ?>

        $(".xendit-ttl").wrap("<div class='xendit-ttl-wrapper'></div>");
        $("<span class='xendit-form-suffix'>Seconds</span>").insertAfter(".xendit-ttl");

        $(".xendit-ext-id").wrap("<div class='input-text regular-input xendit-ttl-wrapper'></div>");
        $("<span class='xendit-form-suffix'>-order_id</span>").insertAfter(".xendit-ext-id");

        $("#ext-id-example").text(
            "<?php echo esc_html($this->external_id_format) ?>-4245");

        $("#woocommerce_<?php echo esc_html($this->id) ?>_external_id_format").change(
            function () {
                $("#ext-id-example").text($(this).val() + "-4245");
            });

        var isSubmitCheckDone = false;

        $('button[name="save"]').on('click', function (e) {
            if (isSubmitCheckDone) {
                isSubmitCheckDone = false;
                return;
            }

            e.preventDefault();

            //empty "on behalf of" if enable xenplatform is unchecked
            if (!$("#woocommerce_<?php echo esc_html($this->id) ?>_enable_xenplatform").is(":checked")) {
                $("#woocommerce_<?php echo esc_html($this->id) ?>_on_behalf_of").val('');
            }

            if ($("#woocommerce_<?php echo esc_html($this->id) ?>_external_id_format").length > 0) {
                var externalIdValue = $("#woocommerce_<?php echo esc_html($this->id); ?>_external_id_format").val();
                if (externalIdValue.length === 0) {
                    return new swal({
                        type: 'error',
                        title: 'Invalid External ID Format',
                        text: 'External ID cannot be empty, please input one or change it to woo-xendit-virtual-accounts'
                    }).then(function () {
                        e.preventDefault();
                    });
                }

                if (/[^a-z0-9-]/gmi.test(externalIdValue)) {
                    return new swal({
                        type: 'error',
                        title: 'Unsupported Character',
                        text: 'The only supported characters in external ID are alphanumeric (a - z, 0 - 9) and dash (-)'
                    }).then(function () {
                        e.preventDefault();
                    });
                }

                if (externalIdValue.length <= 5 || externalIdValue.length > 54) {
                    return new swal({
                        type: 'error',
                        title: 'External ID length is outside range',
                        text: 'External ID must be between 6 to 54 characters'
                    }).then(function () {
                        e.preventDefault();
                    });
                }
            }

            isSubmitCheckDone = true;
            $("button[name='save']").trigger('click');
        });

        $("#woocommerce_<?php echo esc_html($this->id) ?>_enable_xenplatform").on('change',
            function () {
                if (this.checked) {
                    $(".xendit-xenplatform").parents("tr").show();
                } else {
                    $(".xendit-xenplatform").parents("tr").hide();
                }
            }
        );

        $("#woocommerce_<?php echo esc_html($this->id) ?>_developmentmode").on('change',
            function () {
                if (this.checked) {
                    $(".xendit_dev").parents("tr").show();
                    $(".xendit_live").parents("tr").hide();
                } else {
                    $(".xendit_dev").parents("tr").hide();
                    $(".xendit_live").parents("tr").show();
                }
            }
        );

        // Overwrite default value
        $("#woocommerce_<?php echo esc_html($this->id) ?>_dummy_api_key").val("<?php echo esc_html($this->generateStarChar(strlen($this->get_option('api_key')))); ?>");
        $("#woocommerce_<?php echo esc_html($this->id) ?>_dummy_secret_key").val("<?php echo esc_html($this->generateStarChar(strlen($this->get_option('secret_key')))); ?>");
        $("#woocommerce_<?php echo esc_html($this->id) ?>_dummy_api_key_dev").val("<?php echo esc_html($this->generateStarChar(strlen($this->get_option('api_key_dev')))); ?>");
        $("#woocommerce_<?php echo esc_html($this->id) ?>_dummy_secret_key_dev").val("<?php echo esc_html($this->generateStarChar(strlen($this->get_option('secret_key_dev')))); ?>");
    });
</script>
