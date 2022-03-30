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

                <tr>
                    <th scope="row">
                        <label for="auto_upload"><?php _e('Auto upload to Cloudflare?', 'wmcf'); ?></label>
                    </th>
                    <td>
                        <input type="hidden" name="wmcf[auto_upload]" value="0">
                        <input type="checkbox" name="wmcf[auto_upload]" id="auto_upload" value="1" <?= isset($settings['auto_upload']) ? 'checked' : '' ?> />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="variants"><?php _e('Copy these variants to Cloudflare', 'wmcf'); ?></label> 
                        <a href="https://heave.app/docs/wp-media-cloudflare#variants">docs</a>
                    </th>
                    <td>
                        <dl>
                            <?php
                            $variants = wp_get_registered_image_subsizes();

                            foreach ($variants as $name => $size) :
                            ?>
                                <dt><?= $name ?></dt>
                                <dd>
                                    <code><?= $size['width'] ?>x<?= $size['height'] ?></code>
                                </dd>
                            <?php
                            endforeach;
                            ?>
                        </dl>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save Changes', 'wmcf')); ?>
    </form>
</div>