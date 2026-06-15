<?php
if (!defined('ABSPATH')) {
    exit;
}


final class IntegrationNotification {
    public string $_id;
    public string $trigger;
    public ?PaymentSession $session;
    public string $status;
    public ?string $webhook_id;
    public string $app_mode;
    public string $integration_name;
    public string $business_id;
    public string $signature;
    public int $signature_version;
    /** @var array<int, array>|null */
    public ?array $attempts;
    public ?PaymentRequestInfo $payment_request;
    public ?PaymentToken $payment_token;
    public ?Refund $refund;
    public ?DateTimeImmutable $createdAt;
    public ?DateTimeImmutable $updatedAt;
    public int $woocommerce_order_id;
    public function __construct(array $data) {
        // required: no silent fallback
        $this->_id              = (string) $data['_id'];
        $this->trigger          = (string) $data['trigger'];
        $this->status           = (string) $data['status'];
        $this->app_mode         = (string) $data['app_mode'];
        $this->integration_name = (string) $data['integration_name'];
        $this->business_id      = (string) $data['business_id'];
        $this->signature        = (string) $data['signature'];
        $this->signature_version= (int) $data['signature_version'];

        // optional: allow null
        $this->webhook_id   = isset($data['webhook_id']) ? (string) $data['webhook_id'] : null;

        if (isset($data['session']) && is_array($data['session'])) {
            $this->session = isset($data['session']) && is_array($data['session'])
                ? new PaymentSession($data['session'])
                : null;
        }

        if (isset($data['payment_request']) && is_array($data['payment_request'])) {
            $this->payment_request = isset($data['payment_request']) && is_array($data['payment_request'])
                ? new PaymentRequestInfo($data['payment_request'])
                : null;
        }

        if (isset($data['payment_token']) && is_array($data['payment_token'])) {
            $this->payment_token = isset($data['payment_token']) && is_array($data['payment_token'])
                ? new PaymentToken($data['payment_token'])
                : null;
        }

        if (isset($data['refund']) && is_array($data['refund'])) {
            $this->refund = isset($data['refund']) && is_array($data['refund'])
                ? new Refund($data['refund'])
                : null;
        }

        if (isset($data['attempts']) && is_array($data['attempts'])) {
            $this->attempts = isset($data['attempts']) && is_array($data['attempts'])
                ? $data['attempts']
                : null;
        }

        $this->createdAt = isset($data['createdAt'])
            ? new DateTimeImmutable($data['createdAt'])
            : null;

        $this->updatedAt = isset($data['updatedAt'])
            ? new DateTimeImmutable($data['updatedAt'])
            : null;

        $this->woocommerce_order_id = isset($data['woocommerce_order_id'])
            ? (int) $data['woocommerce_order_id']
            : 0;
    }
}

final class PaymentToken {
    public string $payment_token_id;
    public string $status;

    public function __construct(array $data) {
        $this->payment_token_id = (string)$data['payment_token_id'];
        $this->status = (string)$data['status'];
    }
}

final class Refund {
    public string $refund_id;
    public string $status;
    public ?string $amount;
    public ?string $currency;

    public function __construct(array $data) {
        $this->refund_id = (string) $data['refund_id'];
        $this->status    = (string) $data['status'];
        $this->amount    = (string) $data['amount'];
        $this->currency  = (string) $data['currency'];
    }
}

final class PaymentRequestInfo {
    public string $payment_request_id;
    public ?string $payment_id;
    public string $status;
    public function __construct(array $data) {
        $this->payment_request_id = $data['payment_request_id'];
        $this->status = $data['status'];
        $this->payment_id = isset($data['payment_id']) ? (string)$data['payment_id'] : null;
    }
}

final class PaymentSession {
    public string $business_id;
    public string $payment_session_id;
    public ?string $payment_request_id;
    public ?string $payment_id;
    public string $status;
    public string $payment_link_url;
    public ?DateTimeImmutable $expires_at;
    public string $app_mode;
    public ?string $currency;
    public ?string $amount;
    public ?string $mode;
    public ?string $session_type;
    public ?string $allow_save_payment_method;
    public ?string $capture_method;
    public string $checkout_entity_id;
    public string $integration_name;
    public ?DateTimeImmutable $createdAt;
    public ?DateTimeImmutable $updatedAt;

    public function __construct(array $data) {
        $this->business_id = (string)$data['business_id'];
        $this->payment_session_id = (string)$data['payment_session_id'];
        $this->payment_request_id = isset($data['payment_request_id']) ? (string)$data['payment_request_id'] : null;
        $this->payment_id = isset($data['payment_id']) ? (string)$data['payment_id'] : null;
        $this->status = (string)$data['status'];
        $this->payment_link_url = (string)$data['payment_link_url'];
        $this->expires_at = isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null;
        $this->app_mode = (string)$data['app_mode'];
        $this->currency = isset($data['currency']) ? (string)$data['currency'] : null;
        $this->amount = isset($data['amount']) ? (string)$data['amount'] : null;
        $this->mode =  isset($data['mode']) ? (string)$data['mode'] : null;
        $this->session_type =  isset($data['session_type']) ? (string)$data['session_type'] : null;
        $this->allow_save_payment_method = isset($data['allow_save_payment_method']) ? (string)$data['allow_save_payment_method'] : null;
        $this->capture_method = isset($data['capture_method']) ? (string)$data['capture_method'] : null;
        $this->checkout_entity_id = (string)$data['checkout_entity_id'];
        $this->integration_name = (string)$data['integration_name'];
        $this->createdAt = isset($data['createdAt'])
            ? new DateTimeImmutable($data['createdAt'])
            : null;
        $this->updatedAt = isset($data['updatedAt'])
            ? new DateTimeImmutable($data['updatedAt'])
            : null;
    }
}