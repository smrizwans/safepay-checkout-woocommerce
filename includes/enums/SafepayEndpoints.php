<?php

enum SafepayEndpoints: string
{
    case PRODUCTION_URL       = 'https://getsafepay.com';
    case SANDBOX_BASE_URL     = 'https://sandbox.api.getsafepay.com';
    case DEVELOPMENT_BASE_URL = 'https://dev.api.getsafepay.com';
    case CHECKOUT_ROUTE       = '/checkout/pay';

    // Trailing slash removed from base URLs only — endpoints own their own slash.
    case PRODUCTION_BASE_URL  = 'https://api.getsafepay.com';

    case TOKEN_ENDPOINT       = '/client/passport/v1/token';

    // Trailing slash restored on TRANSACTION_ENDPOINT — Safepay's API router
    // requires it. Without it, requests fall through to the Next.js web frontend
    // which returns HTTP 405 Method Not Allowed.
    case TRANSACTION_ENDPOINT = '/order/payments/v3/';

    // META_DATA_ENDPOINT must differ from TRANSACTION_ENDPOINT (PHP 8.3 enum rule).
    // The metadata call appends /{token}/metadata at runtime in SafePayApiHandler,
    // so this base path intentionally has no trailing slash.
    case META_DATA_ENDPOINT   = '/order/payments/v3';
}
