<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR12.Files.FileHeader
// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable Generic.Files.LineLength

/**
 * Plugin Name: CryptoPay Gateway for Fluent Forms Pro
 * Version:     1.0.1
 * Plugin URI:  https://beycanpress.com/cryptopay/
 * Description: Adds Cryptocurrency payment gateway (CryptoPay) for Fluent Forms.
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: cryptopay-gateway-for-fluent-forms
 * Tags: Bitcoin, Ethereum, Crypto, Payment, Fluent Forms
 * Requires at least: 5.0
 * Tested up to: 6.7.1
 * Requires PHP: 8.1
 */

// Autoload
require_once __DIR__ . '/vendor/autoload.php';

define('FLUENT_FORMS_CRYPTOPAY_FILE', __FILE__);
define('FLUENT_FORMS_CRYPTOPAY_VERSION', '1.0.1');
define('FLUENT_FORMS_CRYPTOPAY_KEY', basename(__DIR__));
define('FLUENT_FORMS_CRYPTOPAY_URL', plugin_dir_url(__FILE__));
define('FLUENT_FORMS_CRYPTOPAY_DIR', plugin_dir_path(__FILE__));
define('FLUENT_FORMS_CRYPTOPAY_SLUG', plugin_basename(__FILE__));

use BeycanPress\CryptoPay\Integrator\Helpers;

/**
 * Register CryptoPay Fluent Form models
 * @return void
 */
function registerCryptoPayFluentFormModels(): void
{
    Helpers::registerModel(BeycanPress\CryptoPay\FluentForms\Models\TransactionsPro::class);
    Helpers::registerLiteModel(BeycanPress\CryptoPay\FluentForms\Models\TransactionsLite::class);
}

registerCryptoPayFluentFormModels();

add_action('plugins_loaded', function (): void {
    if (!defined('FLUENTFORM')) {
        Helpers::requirePluginMessage('Fluent Forms', admin_url('plugin-install.php?s=Fluent%2520forms&tab=search&type=term'));
    } elseif (Helpers::bothExists()) {
        registerCryptoPayFluentFormModels();
        new BeycanPress\CryptoPay\FluentForms\Loader();
    } else {
        Helpers::requireCryptoPayMessage('Fluent Forms');
    }
});
