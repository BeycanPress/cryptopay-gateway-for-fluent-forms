<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\FluentForms;

use BeycanPress\CryptoPay\Integrator\Hook;
use BeycanPress\CryptoPay\Integrator\Helpers;
use BeycanPress\CryptoPay\Integrator\Session;

class Loader
{
    /**
     * Loader constructor.
     */
    public function __construct()
    {
        Helpers::registerIntegration('fluent_forms');

        // add transaction page
        Helpers::createTransactionPage(
            esc_html__('Fluent Forms Transactions', 'fluent_forms-cryptopay'),
            'fluent_forms',
            10,
            [
                'orderId' => function ($tx) {
                    return Helpers::run('view', 'components/link', [
                        'url' => sprintf(admin_url('admin.php?page=fluent_forms&route=entries&form_id=%d#/entries/%d'), $tx->params->formId, $tx->orderId), // @phpcs:ignore
                        /* translators: %d: transaction id */
                        'text' => sprintf(esc_html__('View entry #%d', 'gf-cryptopay'), $tx->orderId)
                    ]);
                }
            ]
        );

        if (Helpers::exists()) {
            (new ProGateway())->init();
        }

        if (Helpers::liteExists()) {
            (new LiteGateway())->init();
        }

        add_action('init', [Helpers::class, 'listenSPP']);
        Hook::addFilter('payment_finished', [$this, 'paymentFinished']);
        Hook::addFilter('payment_redirect_urls_fluent_forms', [$this, 'paymentRedirectUrls']);
    }

    /**
     * Payment finished
     * @param object $data
     * @return void
     */
    public function paymentFinished(object $data): void
    {
        $name = $data->getParams()->get('name');
        $formId = $data->getParams()->get('formId');
        $submissionId = $data->getParams()->get('submissionId');

        do_action('fluentform/log_data', [
            'parent_source_id' => $formId,
            'source_type'      => 'submission_item',
            'source_id'        => $submissionId,
            'component'        => 'Payment',
            'status'           => 'info',
            /* translators: %s: Payment status */
            'title'            => sprintf(__('%s - Payment %s', 'fluent_forms-cryptopay'), $name, $data->getStatus() ? 'completed' : 'failed'), // @phpcs:ignore
            'description'      => sprintf(
                __('Payment %s', 'fluent_forms-cryptopay'),
                $data->getStatus() ? 'completed' : 'failed'
            )
        ]);

        Session::set('fluent_forms_payment', [
            'token' => $data->getParams()->get('token'),
            'status' => $data->getStatus(),
            'hash' => $data->getHash(),
            'paymentNote' => sprintf(
                /* translators: %s: Payment currency symbol */
                esc_html__('Paid with %s', 'fluent_forms-cryptopay'),
                $data->getOrder()->getPaymentCurrency()->getSymbol()
            )
        ]);
    }

    /**
     * Payment redirect urls
     * @param object $data
     * @return array<string>
     */
    public function paymentRedirectUrls(object $data): array
    {
        $token = $data->getParams()->get('token');
        $returnUrl = $data->getParams()->get('returnUrl');

        return [
            'success' => $returnUrl . '&token=' . $token,
            'failed' => $returnUrl . '&token=' . $token,
        ];
    }
}
