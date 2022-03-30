# WordPress Cloudflare Images Integration (WIP)

This plugin lets you upload and offload image to [Cloudflare Images](https://www.cloudflare.com/products/cloudflare-images/) which serve, optimize, resize at scale, low cost.

The plugin is work in progress and it's actively development. Use at your own risk. More detailed documentation will available on April.

## Installation
@todo: Image for each step
0. Download this plugin, extract and put it in `wp-content/plugins` folder like other plugins.
1. Hit activate from wp-admin plugins screen.
2. Go to Settings page and copy related api keys from Cloudflare.
3. Create variants on Cloudflare based on variants of your website.
4. Save Settings and enjoy.


## Todo
- [x] Offload single image manually.
- [x] Offload automatically when upload.
- [x] Limit file size to 10MB.
- [ ] Add nonce to AJAX requests.
- [ ] Upload to CF from preview dialog.
- [ ] Delete local image after upload.
- [x] Variants setup instruction on settings page.
- [ ] Work with images only.
- [ ] Sync delete and update.
- [ ] WordPress.org repo.
- [ ] Product page and documentation.
- [ ] TravisCI badge
- [ ] Batch upload images. Currently, CF doesn't have that feature, we have to upload one-by-one.