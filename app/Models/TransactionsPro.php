<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\FluentForms\Models;

use BeycanPress\CryptoPay\Models\AbstractTransaction;

class TransactionsPro extends AbstractTransaction
{
    public string $addon = 'fluent_forms';

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct('fluent_forms_transaction');
    }
}
