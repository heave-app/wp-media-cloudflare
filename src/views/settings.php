<?php defined('ABSPATH') or die('Access denied'); ?>

<?php $settings = wmcf_get_setting(); ?>

<div class="wrap">

    <h1><?php _e('Settings', 'wmcf'); ?></h1>

    <?php if (isset($_GET['status'])) : ?>
        <?php if ($_GET['status'] === 'success') : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved.', 'wmcf'); ?></p>
            </div>
        <?php elseif ($_GET['status'] === 'error') : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('Error saving settings.', 'wmcf'); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    

    <form method="post" action="options.php" novalidate>
        <input type="hidden" name="page" value="wmcf">
        <?php wp_nonce_field('update-wmcf-settings'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="account_id"><?php _e('Account ID', 'wmcf'); ?></label>
                    </th>
                    <td><input type="text" name="wmcf[account_id]" id="account_id" class="regular-text" value="<?= esc_attr($settings['account_id']) ?? '' ?>" /></td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="api_token"><?php _e('API Token', 'wmcf'); ?></label>
                    </th>
                    <td><input type="text" name="wmcf[api_token]" id="api_token" class="regular-text" value="<?= esc_attr($settings['api_token']) ?? '' ?>" /></td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="default_variant"><?php _e('Default Variant', 'wmcf'); ?></label>
                    </th>
                    <td><input type="text" name="wmcf[default_variant]" id="default_variant" class="regular-text" value="<?= esc_attr($settings['default_variant']) ?? '' ?>" /></td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save Changes', 'wmcf')); ?>
    </form>
</div>