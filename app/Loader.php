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
        if (class_exists('FluentFormPro\Payments\PaymentMethods\BasePaymentMethod')) {
            Helpers::registerIntegration('fluent_forms');

            // add transaction page
            Helpers::createTransactionPage(
                esc_html__('Fluent Forms Transactions', 'cryptopay-gateway-for-fluent-forms'),
                'fluent_forms',
                10,
                [
                    'orderId' => function ($tx) {
                        return Helpers::run('view', 'components/link', [
                            'url' => sprintf(admin_url('admin.php?page=fluent_forms&route=entries&form_id=%d#/entries/%d'), $tx->params->formId, $tx->orderId), // @phpcs:ignore
                            'text' => sprintf(
                                /* translators: %d: transaction id */
                                esc_html__('View entry #%d', 'cryptopay-gateway-for-fluent-forms'),
                                $tx->orderId
                            )
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
            Hook::addFilter('edit_config_data_fluent_forms', [$this, 'disableReminderEmail']);
            Hook::addFilter('payment_redirect_urls_fluent_forms', [$this, 'paymentRedirectUrls']);
        } else {
            Helpers::requirePluginMessage('Fluent Forms Pro', 'https://fluentforms.com/');
        }
    }

    /**
     * @param object $data
     * @return object
     */
    public function disableReminderEmail(object $data): object
    {
        return $data->disableReminderEmail();
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
            'title'            => sprintf(__('%s - Payment %s', 'cryptopay-gateway-for-fluent-forms'), $name, $data->getStatus() ? 'completed' : 'failed'), // @phpcs:ignore
            'description'      => sprintf(
                /* translators: %s: Payment status */
                __('Payment %s', 'cryptopay-gateway-for-fluent-forms'),
                $data->getStatus() ? 'completed' : 'failed'
            )
        ]);

        Session::set('fluent_forms_payment', [
            'token' => $data->getParams()->get('token'),
            'status' => $data->getStatus(),
            'hash' => $data->getHash(),
            'paymentNote' => sprintf(
                /* translators: %s: Payment currency symbol */
                esc_html__('Paid with %s', 'cryptopay-gateway-for-fluent-forms'),
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
