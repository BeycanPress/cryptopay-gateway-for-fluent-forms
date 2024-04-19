<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\FluentForms;

final class LiteGateway extends AbstractGateway
{
    /**
     * LiteGateway constructor.
     */
    public function __construct()
    {
        parent::__construct('cryptopay_lite', 'CryptoPay Lite');
    }
}
