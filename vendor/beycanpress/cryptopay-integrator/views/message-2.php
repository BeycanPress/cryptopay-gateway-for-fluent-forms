
<div class="notice notice-error">
    <p>
        <?php echo wp_kses_post(
            sprintf(
                esc_html__('CryptoPay Gateway for %s: This plugin is an integration plugin so it cannot do anything on its own. It needs CryptoPay to work. You can buy CryptoPay by %s.', 'cryptopay'),
                esc_html($currentPlugin),
                sprintf(
                    '<a href="https://beycanpress.com/product/cryptopay-all-in-one-cryptocurrency-payments-for-wordpress/?utm_source=wp_org_addons&utm_medium=%s" target="_blank">' . esc_html__('clicking here', 'cryptopay') . '</a>',
                    esc_html($currentPlugin)
                )    
            ),
            ['a' => ['href' => [], 'target' => []]]
        ); ?>
    </p>
</div>