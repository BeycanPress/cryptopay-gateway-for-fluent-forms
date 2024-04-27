<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\FluentForms;

use BeycanPress\CryptoPay\Integrator\Type;
use BeycanPress\CryptoPay\Integrator\Helpers;
use BeycanPress\CryptoPay\Integrator\Session;
use BeycanPress\CryptoPay\Helpers as ProHelpers;
use BeycanPress\CryptoPayLite\Helpers as LiteHelpers;
use FluentFormPro\Payments\PaymentHelper;
use FluentFormPro\Payments\PaymentMethods\BaseProcessor;

final class Processor extends BaseProcessor
{
    /**
     * @var string
     */
    // phpcs:ignore
    protected $name;

    /**
     * @var string
     */
    // phpcs:ignore
    protected $method;

    /**
     * @var string
     */
    // phpcs:ignore
    protected $form = null;

    /**
     * @var string
     */
    // phpcs:ignore
    protected $submission = null;

    /**
     * @var string
     */
    // phpcs:ignore
    protected $submissionId = null;

    /**
     * @param string $method
     * @param string $name
     */
    public function __construct(string $method, string $name)
    {
        $this->name = $name;
        $this->method = $method;
    }

    /**
     * Initialize the processor
     * @return void
     */
    public function init(): void
    {
        add_action('fluentform/process_payment_' . $this->method, [$this, 'handlePaymentAction'], 10, 6);
        add_action('fluentform/payment_frameless_' . $this->method, [$this, 'handleSessionRedirectBack']);
    }

    /**
     * @return string
     */
    public function getPaymentMode(): string
    {
        $testnetStatus = 'cryptopay' === $this->method
        ? ProHelpers::getTestnetStatus()
        : LiteHelpers::getTestnetStatus();

        return $testnetStatus ? 'test' : 'live';
    }

    /**
     * @param string $submissionId
     * @param array<mixed> $submissionData
     * @param object $form
     * @param mixed $methodSettings
     * @param bool $hasSubscription
     * @param int $totalPayable
     * @return void
     */
    // phpcs:ignore
    public function handlePaymentAction(
        $submissionId,
        $submissionData,
        $form,
        $methodSettings,
        $hasSubscription,
        $totalPayable
    ): void {
        $this->form = $form;
        $this->setSubmissionId($submissionId);
        $submission = $this->getSubmission();

        $transactionId = $this->insertTransaction([
            'transaction_type' => 'onetime',
            'status' => 'pending', //paid or pending
            'payment_total' => $this->getAmountTotal(),
            'payment_mode' => $this->getPaymentMode(),
            'currency' => PaymentHelper::getFormCurrency($form->id),
        ]);

        $transaction = $this->getTransaction($transactionId);

        $this->handleRedirect($transaction, $submission, $form, $submissionData);
    }

    /**
     * @param object $transaction
     * @param object $submission
     * @param object $form
     * @param mixed $submissionData
     * @return void
     */
    // phpcs:ignore
    public function handleRedirect($transaction, $submission, $form, $submissionData): void
    {
        $returnUrl = add_query_arg([
            'payment_method'     => $this->method,
            'fluentform_payment' => $submission->id,
            'transaction_hash'   => $transaction->transaction_hash, // phpcs:ignore
        ], home_url('/'));

        do_action('fluentform/log_data', [
            'parent_source_id' => $submission->form_id, // phpcs:ignore
            'source_type'      => 'submission_item',
            'source_id'        => $submission->id,
            'component'        => 'Payment',
            'status'           => 'info',
            /* translators: %s: Payment method name */
            'title'            => sprintf(__('%s - Payment Link Created', 'fluent_forms-cryptopay'), $this->name),
            'description'      => __('Payment link created and user redirected', 'fluent_forms-cryptopay')
        ]);

        $paymentPageLink = Helpers::createSPP([
            'addon' => 'fluent_forms',
            'addonName' => 'Fluent Forms',
            'order' => [
                'id' => $submission->id,
                'currency' => $transaction->currency,
                'amount' => number_format((float) $transaction->payment_total / 100, 2, '.', ''), // phpcs:ignore
            ],
            'params' => [
                'name' => $this->name,
                'formId' => $form->id,
                'returnUrl' => $returnUrl,
                'submissionId' => $submission->id,
            ],
            'type' => 'cryptopay' === $this->method ? Type::PRO : Type::LITE,
        ]);

        // wp_send_json_success([
        //     'message' => 'Example error'
        // ], 423);

        wp_send_json_success([
            'nextAction'   => 'payment',
            'actionName'   => 'normalRedirect',
            'redirect_url' => $paymentPageLink,
            'message'      => __('Redirecting to payment page...', 'fluent_forms-cryptopay'),
            'result'       => [
                'insert_id' => $submission->id
            ]
        ], 200);
    }

    /**
     * @param array<string,mixed> $data
     * @return void
     */
    // phpcs:ignore
    public function handleSessionRedirectBack($data): void
    {
        $submissionId = intval($data['fluentform_payment']);
        $this->setSubmissionId($submissionId);

        $token = sanitize_text_field($data['token'] ?? '');

        if (!Session::has($token) || !Session::has('fluent_forms_payment')) {
            $this->showPaymentView([
                'type' => 'failed',
                'is_new' => false,
                'title' => __('Payment Argument Problems!', 'fluent_forms-cryptopay'),
                'error' => __('Payment token not found!', 'fluent_forms-cryptopay')
            ]);
            return;
        }

        $paymentData = Session::get('fluent_forms_payment');

        $isSuccess = boolval($paymentData['status']);
        $transactionHash = sanitize_text_field($data['transaction_hash']);
        $transaction = $this->getTransaction($transactionHash, 'transaction_hash');

        $status = $isSuccess ? 'paid' : 'failed';

        $updateData = [
            'charge_id'    => $paymentData['hash'],
            'payment_note' => $paymentData['paymentNote'],
        ];

        if ($isSuccess) {
            $returnData = $this->handleSuccess($transaction, $status, $updateData);
        } else {
            $returnData = $this->handleFailed($transaction, $status, $updateData);
        }

        $returnData['type'] = ($isSuccess) ? 'success' : 'failed';

        if (!$isSuccess) {
            $returnData['error'] = esc_html__('Unfortunately, the payment could not be verified, but your form has been submitted anyway. Please contact us if you think the process is faulty!', 'fluent_forms-cryptopay'); // phpcs:ignore
        }

        if (!isset($returnData['is_new'])) {
            $returnData['is_new'] = false;
        }

        Session::remove($token);

        $this->showPaymentView($returnData);
    }

    /**
     * @param object $transaction
     * @param string $status
     * @param array<string,mixed> $updateData
     * @return mixed
     */
    public function handleSuccess(object $transaction, string $status, array $updateData): mixed
    {
        // Check if actions are fired
        if ('yes' === $this->getMetaData('is_form_action_fired')) {
            return $this->completePaymentSubmission(false);
        }

        $this->updateTransaction($transaction->id, $updateData);
        $this->changeTransactionStatus($transaction->id, $status);
        $this->changeSubmissionPaymentStatus($status);
        $this->recalculatePaidTotal();

        $this->setMetaData('is_form_action_fired', 'yes');

        return $this->getReturnData();
    }

    /**
     * @param object $transaction
     * @param string $status
     * @param array<string,mixed> $updateData
     * @return mixed
     */
    public function handleFailed(object $transaction, string $status, array $updateData): mixed
    {
        $this->updateTransaction($transaction->id, $updateData);
        $this->changeTransactionStatus($transaction->id, $status);
        $this->changeSubmissionPaymentStatus($status);

        return $this->getReturnData();
    }
}
