<div class="notice notice-error">
    <p>
    <?php echo wp_kses_post(
        str_replace(
            '{name}',
            esc_html($currentPlugin),
            sprintf(
                esc_html__('{name} - CryptoPay Gateway: This plugin requires {name} to work. You can %s {name} by %s.', 'arm-cryptopay'),
                $download ? esc_html__('download', 'cryptopay') : esc_html__('buy', 'cryptopay'),
                sprintf(
                    '<a href="%s" target="_blank">' . esc_html__('clicking here', 'cryptopay') . '</a>',
                    $pluginLink
                )
            )
        ),
        ['a' => ['href' => [], 'target' => []]]
    ); ?>
    </p>
</div>