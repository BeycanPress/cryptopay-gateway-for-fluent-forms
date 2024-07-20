<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\Integrator;

use BeycanPress\CryptoPay\Loader;
use BeycanPress\CryptoPay\Payment;
use BeycanPress\CryptoPay\PluginHero\Hook;
use BeycanPress\CryptoPay\Helpers as ProHelpers;
use BeycanPress\CryptoPay\Pages\TransactionPage;
use BeycanPress\CryptoPay\Types\Order\OrderType;
use BeycanPress\CryptoPay\PluginHero\Http\Response;
use BeycanPress\CryptoPay\Types\Transaction\ParamsType;
// Lite
use BeycanPress\CryptoPayLite\Loader as LiteLoader;
use BeycanPress\CryptoPayLite\Payment as LitePayment;
use BeycanPress\CryptoPayLite\Helpers as LiteHelpers;
use BeycanPress\CryptoPayLite\PluginHero\Hook as LiteHook;
use BeycanPress\CryptoPayLite\Types\Order\OrderType as LiteOrderType;
use BeycanPress\CryptoPayLite\PluginHero\Http\Response as LiteResponse;
use BeycanPress\CryptoPayLite\Pages\TransactionPage as LiteTransactionPage;
use BeycanPress\CryptoPayLite\Types\Transaction\ParamsType as LiteParamsType;

class Helpers
{
    /**
     * @return bool
     */
    public static function bothExists(): bool
    {
        return static::exists() || static::liteExists();
    }

    /**
     * @return bool
     */
    public static function liteExists(): bool
    {
        return defined('CRYPTOPAY_LITE_LOADED');
    }

    /**
     * @return bool
     */
    public static function exists(): bool
    {
        return defined('CRYPTOPAY_LOADED');
    }

    /**
     * @param string $addon
     * @return void
     */
    public static function registerIntegration(string $addon): void
    {
        if (self::exists()) {
            ProHelpers::registerIntegration($addon);
        }

        if (self::liteExists()) {
            LiteHelpers::registerIntegration($addon);
        }
    }

    /**
     * @param array<mixed> ...$args
     * @return void
     */
    // phpcs:ignore
    public static function createTransactionPage(...$args): void
    {
        if (is_admin()) {
            if (self::exists()) {
                new TransactionPage(...$args);
            }

            if (self::liteExists()) {
                new LiteTransactionPage(...$args);
            }
        }
    }

    /**
     * @param string $modelClass
     * @return void
     */
    public static function registerModel(string $modelClass): void
    {
        if (self::exists() && class_exists($modelClass)) {
            $model = new $modelClass();
            Hook::addFilter('models', function (array $models) use ($model): array {
                return array_merge($models, [
                    $model->addon => $model
                ]);
            });
        }
    }

    /**
     * @param string $modelClass
     * @return void
     */
    public static function registerLiteModel(string $modelClass): void
    {
        if (self::liteExists() && class_exists($modelClass)) {
            $model = new $modelClass();
            LiteHook::addFilter('models', function (array $models) use ($model): array {
                return array_merge($models, [
                    $model->addon => $model
                ]);
            });
        }
    }

    /**
     * @param string $method
     * @param array<mixed> ...$args
     * @return mixed
     */
    // phpcs:ignore
    public static function run(string $method, ...$args): mixed
    {
        if (self::exists()) {
            return ProHelpers::$method(...$args);
        } else {
            return LiteHelpers::$method(...$args);
        }
    }

    /**
     * @param string $method
     * @param array<mixed> ...$args
     * @return mixed
     */
    // phpcs:ignore
    public static function response(string $method, ...$args): mixed
    {
        if (self::exists()) {
            return Response::$method(...$args);
        } else {
            return LiteResponse::$method(...$args);
        }
    }

    /**
     * @param string $currentPlugin
     * @param string $pluginLink
     * @param bool $download
     * @return void
     */
    public static function requirePluginMessage(string $currentPlugin, string $pluginLink, bool $download = true): void
    {
        add_action('admin_notices', function () use ($currentPlugin, $pluginLink, $download): void {
            require dirname(__DIR__) . '/views/message-1.php';
        });
    }

    /**
     * @param string $currentPlugin
     * @return void
     */
    public static function requireCryptoPayMessage(string $currentPlugin): void
    {
        add_action('admin_notices', function () use ($currentPlugin): void {
            require dirname(__DIR__) . '/views/message-2.php';
        });
    }

    /**
     * @param array<mixed> $data
     * @return string
     * @throws \Exception
     */
    public static function createSPP(array $data): string
    {
        if (!isset($data['addon'])) {
            throw new \Exception('Addon is required');
        }

        if (!isset($data['order'])) {
            throw new \Exception('Order is required');
        }

        if (!isset($data['order']['amount'])) {
            throw new \Exception('Order amount is required');
        }

        if (!isset($data['order']['currency'])) {
            throw new \Exception('Order currency is required');
        }

        if (!isset($data['type'])) {
            throw new \Exception('CryptoPay type is required');
        }

        if (!($data['type'] instanceof Type)) {
            throw new \Exception('Invalid CryptoPay type');
        }

        $token = md5(json_encode($data) . time());

        Session::set($token, $data);

        return home_url("/?cp_spp={$token}");
    }

    /**
     * @param string $url
     * @return string|null
     */
    public static function getSPPToken(string $url): ?string
    {
        /** @var array<mixed> $matches */
        preg_match('/[?&]cp_spp=([^&]+)/', $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    /**
     * @return void
     */
    public static function listenSPP(): void
    {
        $token = isset($_GET['cp_spp']) ? sanitize_text_field($_GET['cp_spp']) : null;

        if ($token && Session::has($token)) {
            extract(Session::get($token));

            if (!isset($params)) {
                $params = [];
            }

            $args = [
                $addon,
                (array) $order,
                array_merge(
                    (array) $params,
                    [
                        'token' => $token
                    ]
                )
            ];

            if (Type::PRO === $type) {
                $cryptopay = self::createProPayment(...$args);
            } else {
                $cryptopay = self::createLitePayment(...$args);
            }
            require dirname(__DIR__) . '/views/pay.php';
            exit;
        }
    }

    /**
     * @param string $addon
     * @param array<mixed> $order
     * @param array<mixed> $params
     * @return string
     */
    public static function createProPayment(string $addon, array $order, array $params = []): string
    {
        return (new Payment($addon))
        ->setOrder(
            OrderType::fromArray($order)
        )
        ->setParams(
            ParamsType::fromArray($params)
        )
        ->html(loading:true);
    }

    /**
     * @param string $addon
     * @param array<mixed> $order
     * @param array<mixed> $params
     * @return string
     */
    public static function createLitePayment(string $addon, array $order, array $params = []): string
    {
        return (new LitePayment($addon))
        ->setOrder(
            LiteOrderType::fromArray($order)
        )
        ->setParams(
            LiteParamsType::fromArray($params)
        )
        ->html(loading:true);
    }
}
