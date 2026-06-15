<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Result object returned by notification handlers.
 *
 * Encapsulates the HTTP status code and response body so the caller
 * (the webhook dispatcher) can translate them into header()/echo/exit
 * while tests can inspect the values directly.
 *
 * @since 7.0.0
 */
final class WC_Xendit_Notification_Result
{
    /** @var int */
    public $http_status;

    /** @var string */
    public $body;

    public function __construct(int $http_status, string $body)
    {
        $this->http_status = $http_status;
        $this->body        = $body;
    }
}
