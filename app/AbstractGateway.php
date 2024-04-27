<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\FluentForms;

use FluentFormPro\Payments\PaymentMethods\BasePaymentMethod;

abstract class AbstractGateway extends BasePaymentMethod
{
    /**
     * @var string
     */
    // phpcs:ignore
    protected $key;

    /**
     * @var string
     */
    private string $name;

    /**
     * @param string $key
     * @param string $name
     * AbstractGateway constructor.
     */
    public function __construct(string $key, string $name)
    {
        $this->name = $name;
        parent::__construct($key);
    }

    /**
     * Initialize the gateway
     * @return void
     */
    public function init(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        add_filter('fluentform/payment_method_settings_validation_' . $this->key, [$this, 'validateSettings'], 10, 2);

        add_filter('fluentform/transaction_data_' . $this->key, [$this, 'modifyTransaction'], 10, 1);

        add_filter('fluentform/payment_method_public_name_' . $this->key, function () {
            return $this->name;
        });

        add_filter(
            'fluentform/available_payment_methods',
            [$this, 'pushPaymentMethodToForm']
        );

        (new Processor($this->key, $this->name))->init();
    }

    /**
     * Push the payment method to the form
     * @param array<string,array<string>> $methods
     * @return array<string,array<string>>
     */
    public function pushPaymentMethodToForm(array $methods): array
    {
        $methods[$this->key] = [
            'title' => $this->name,
            'enabled' => 'yes',
            'method_value' => $this->key,
            'settings' => [
                'option_label' => [
                    'type' => 'text',
                    'template' => 'inputText',
                    /* translators: %s: Payment method name */
                    'value' => sprintf(esc_html__('Pay with %s', 'fluent_forms-cryptopay'), $this->name),
                    'label' => $this->name
                ]
            ]
        ];

        return $methods;
    }

    /**
     * Modify the transaction
     * @param object $transaction
     * @return object
     */
    public function modifyTransaction(object $transaction): object
    {
        $transaction->payment_method = $this->name; // phpcs:ignore
        return $transaction;
    }

    /**
     * Get the global fields
     * @return array<mixed>
     */
    public function getGlobalFields(): array
    {
        return [
            'label' => $this->name,
            'fields' => [
                [
                    'type' => 'yes-no-checkbox',
                    'settings_key' => 'is_active',
                    'label' => esc_html__('Status', 'fluent_forms-cryptopay'),
                    /* translators: %s: Payment method name */
                    'checkbox_label' => sprintf(esc_html__('Enable %s', 'fluent_forms-cryptopay'), $this->name),
                ]
            ]
        ];
    }

    /**
     * Validate the settings
     * @param array<mixed> $errors
     * @param array<mixed> $settings
     * @return array<mixed>
     */
    public function validateSettings(array $errors, array $settings): array
    {
        return $errors;
    }

    /**
     * Get the global settings
     * @return array<mixed>
     */
    public function getGlobalSettings(): array
    {
        return wp_parse_args(get_option('fluentform_payment_settings_' . $this->key, []), [
            'is_active' => 'no'
        ]);
    }

    /**
     * Check if the gateway is enabled
     * @return bool
     */
    public function isEnabled(): bool
    {
        $settings = $this->getGlobalSettings();
        return 'yes' === $settings['is_active'];
    }
}
