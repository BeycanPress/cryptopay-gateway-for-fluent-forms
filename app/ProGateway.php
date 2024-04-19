<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\FluentForms;

final class ProGateway extends AbstractGateway
{
    /**
     * ProGateway constructor.
     */
    public function __construct()
    {
        parent::__construct('cryptopay', 'CryptoPay');
    }
}
