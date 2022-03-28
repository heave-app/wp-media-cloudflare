<?php

namespace Heave\WpMediaCloudflare;

class Main
{
    public $cloudflare;

    public function __construct()
    {
        $this->cloudflare = new Cloudflare();

        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);

        add_action('save_post', [$this, 'save']);

        if (is_admin()) {
            add_action('wp_ajax_cloudflare_action', [$this, 'cloudflare_action']);
        }

        add_filter('wp_get_attachment_image_src', [$this, 'filter_attachment_image_src'], 10, 4);
        add_filter('wp_get_attachment_url', [$this, 'filter_attachment_url'], 10, 2);
        add_filter('wp_prepare_attachment_for_js', [$this, 'filter_prepare_attachment_for_js'], 10, 3);

        remove_filter( 'the_content', 'wp_filter_content_tags' );
        remove_filter( 'the_excerpt', 'wp_filter_content_tags' );
        remove_filter( 'widget_text_content', 'wp_filter_content_tags' );
        remove_filter( 'widget_block_content', 'wp_filter_content_tags' );

        add_filter( 'the_content', [$this, 'wp_filter_content_tags'], 10, 2);
        add_filter( 'the_excerpt', [$this, 'wp_filter_content_tags'], 10, 2);
        add_filter( 'widget_text_content', [$this, 'wp_filter_content_tags'], 10, 2);
        add_filter( 'widget_block_content', [$this, 'wp_filter_content_tags'], 10, 2);
        
        // Handle ajax upload and edit
        add_action('save_post_attachment', [$this, 'save_post_attachment'], 10, 3);

        // Add buttons to media modal
        
        
        // Handle delete
        add_action('delete_attachment', [$this, 'delete_attachment'], 10, 2);

        // Handle edit
        // add_action('edit_attachment', [$this, 'edit_attachment'], 10, 2);

        // Bulk upload all images to cloudflare
        add_action('wp_enqueue_media', [$this, 'enqueue_media']);
    }

    public function enqueue_media()
    {
        add_action('admin_print_footer_scripts', [$this, 'media_modal_buttons']);
    }

    public function media_modal_buttons()
    {
        ?>
        <script type="text/html" id="tmpl-media-modal-buttons">
            <h2>Cloudflare</h2>
            <label>Cloudflare Actions</label>
        </script>
        <?php
    }

    public function delete_attachment($post_id, $post)
    {
        $image_id = get_post_meta($post_id, 'cloudflare_image_id', true);

        if (!$image_id) {
            return;
        }

        $this->cloudflare->delete($image_id);
    }

    public function save_post_attachment($post_id, $post, $update)
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || wp_is_post_autodraft($post_id)) {
            return;
        }

        // if ($update) {
        //     $image_id = get_post_meta($post_id, 'cloudflare_image_id', true);

        //     if (!$image_id) {
        //         return;
        //     }
        // }

        $this->cloudflare->upload($post);
    }

    public function wp_calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id = 0 ) 
    {
        $cloudflare_url = get_post_meta($attachment_id, 'cloudflare_url', true);
        $cloudflare_base_url = substr($cloudflare_url, 0, strrpos($cloudflare_url, '/') + 1);

        /**
         * Let plugins pre-filter the image meta to be able to fix inconsistencies in the stored data.
         *
         * @since 4.5.0
         *
         * @param array  $image_meta    The image meta data as returned by 'wp_get_attachment_metadata()'.
         * @param int[]  $size_array    {
         *     An array of requested width and height values.
         *
         *     @type int $0 The width in pixels.
         *     @type int $1 The height in pixels.
         * }
         * @param string $image_src     The 'src' of the image.
         * @param int    $attachment_id The image attachment ID or 0 if not supplied.
         */
        $image_meta = apply_filters( 'wp_calculate_image_srcset_meta', $image_meta, $size_array, $image_src, $attachment_id );
            
        if ( empty( $image_meta['sizes'] ) || ! isset( $image_meta['file'] ) || strlen( $image_meta['file'] ) < 4 ) {
            return false;
        }
        
        $image_sizes = $image_meta['sizes'];
        
        // Get the width and height of the image.
        $image_width  = (int) $size_array[0];
        $image_height = (int) $size_array[1];
    
        // Bail early if error/no width.
        if ( $image_width < 1 ) {
            return false;
        }
    
        $image_basename = wp_basename( $image_meta['file'] );
        
        /*
         * WordPress flattens animated GIFs into one frame when generating intermediate sizes.
         * To avoid hiding animation in user content, if src is a full size GIF, a srcset attribute is not generated.
         * If src is an intermediate size GIF, the full size is excluded from srcset to keep a flattened GIF from becoming animated.
         */
        if ( ! isset( $image_sizes['thumbnail']['mime-type'] ) || 'image/gif' !== $image_sizes['thumbnail']['mime-type'] ) {
            $image_sizes[] = array(
                'width'  => $image_meta['width'],
                'height' => $image_meta['height'],
                'file'   => $image_basename,
            );
        } elseif ( strpos( $image_src, $image_meta['file'] ) ) {
            return false;
        }
    
        // Retrieve the uploads sub-directory from the full size image.
        $dirname = _wp_get_attachment_relative_path( $image_meta['file'] );
    
        if ( $dirname ) {
            $dirname = trailingslashit( $dirname );
        }
    
        $upload_dir    = wp_get_upload_dir();
        $image_baseurl = trailingslashit( $upload_dir['baseurl'] ) . $dirname;
    
        /*
         * If currently on HTTPS, prefer HTTPS URLs when we know they're supported by the domain
         * (which is to say, when they share the domain name of the current request).
         */
        if ( is_ssl() && 'https' !== substr( $image_baseurl, 0, 5 ) && parse_url( $image_baseurl, PHP_URL_HOST ) === $_SERVER['HTTP_HOST'] ) {
            $image_baseurl = set_url_scheme( $image_baseurl, 'https' );
        }
 
        /*
         * Images that have been edited in WordPress after being uploaded will
         * contain a unique hash. Look for that hash and use it later to filter
         * out images that are leftovers from previous versions.
         */
        $image_edited = preg_match( '/-e[0-9]{13}/', wp_basename( $image_src ), $image_edit_hash );
    
        /**
         * Filters the maximum image width to be included in a 'srcset' attribute.
         *
         * @since 4.4.0
         *
         * @param int   $max_width  The maximum image width to be included in the 'srcset'. Default '2048'.
         * @param int[] $size_array {
         *     An array of requested width and height values.
         *
         *     @type int $0 The width in pixels.
         *     @type int $1 The height in pixels.
         * }
         */
        $max_srcset_image_width = apply_filters( 'max_srcset_image_width', 2048, $size_array );

        // Array to hold URL candidates.
        $sources = array();
    
        /**
         * To make sure the ID matches our image src, we will check to see if any sizes in our attachment
         * meta match our $image_src. If no matches are found we don't return a srcset to avoid serving
         * an incorrect image. See #35045.
         */
        $src_matched = true;	
    
        /*
         * Loop through available images. Only use images that are resized
         * versions of the same edit.
         */
        foreach ( $image_sizes as $image ) {
            $is_src = false;
    
            // Check if image meta isn't corrupted.
            if ( ! is_array( $image ) ) {
                continue;
            }
    
            // If the file name is part of the `src`, we've confirmed a match.
            if ( ! $src_matched && false !== strpos( $image_src, $dirname . $image['file'] ) ) {
                $src_matched = true;
                $is_src      = true;
            }
    
            // Filter out images that are from previous edits.
            if ( $image_edited && ! strpos( $image['file'], $image_edit_hash[0] ) ) {
                continue;
            }
    
            /*
             * Filters out images that are wider than '$max_srcset_image_width' unless
             * that file is in the 'src' attribute.
             */
            if ( $max_srcset_image_width && $image['width'] > $max_srcset_image_width && ! $is_src ) {
                continue;
            }
    
            // If the image dimensions are within 1px of the expected size, use it.
            if ( wp_image_matches_ratio( $image_width, $image_height, $image['width'], $image['height'] ) ) {
                // Add the URL, descriptor, and value to the sources array to be returned.
                $source = array(
                    'url'        => $cloudflare_base_url . $image['file'],
                    'descriptor' => 'w',
                    'value'      => $image['width'],
                );
    
                // The 'src' image has to be the first in the 'srcset', because of a bug in iOS8. See #35030.
                if ( $is_src ) {
                    $sources = array( $image['width'] => $source ) + $sources;
                } else {
                    $sources[ $image['width'] ] = $source;
                }
            }
        }
    
    
        /**
         * Filters an image's 'srcset' sources.
         *
         * @since 4.4.0
         *
         * @param array  $sources {
         *     One or more arrays of source data to include in the 'srcset'.
         *
         *     @type array $width {
         *         @type string $url        The URL of an image source.
         *         @type string $descriptor The descriptor type used in the image candidate string,
         *                                  either 'w' or 'x'.
         *         @type int    $value      The source width if paired with a 'w' descriptor, or a
         *                                  pixel density value if paired with an 'x' descriptor.
         *     }
         * }
         * @param array $size_array     {
         *     An array of requested width and height values.
         *
         *     @type int $0 The width in pixels.
         *     @type int $1 The height in pixels.
         * }
         * @param string $image_src     The 'src' of the image.
         * @param array  $image_meta    The image meta data as returned by 'wp_get_attachment_metadata()'.
         * @param int    $attachment_id Image attachment ID or 0.
         */
        $sources = apply_filters( 'wp_calculate_image_srcset', $sources, $size_array, $image_src, $image_meta, $attachment_id );
        
        // Only return a 'srcset' value if there is more than one source.
        if ( ! $src_matched || ! is_array( $sources ) || count( $sources ) < 2 ) {
            return false;
        }
    
        $srcset = '';
    
        foreach ( $sources as $source ) {
            $srcset .= str_replace( ' ', '%20', $source['url'] ) . ' ' . $source['value'] . $source['descriptor'] . ', ';
        }
    
        return rtrim( $srcset, ', ' );
    }

    public function wp_image_add_srcset_and_sizes( $image, $image_meta, $attachment_id ) {
        // Ensure the image meta exists.
        if ( empty( $image_meta['sizes'] ) ) {
            return $image;
        }
    
        $image_src         = preg_match( '/src="([^"]+)"/', $image, $match_src ) ? $match_src[1] : '';
        list( $image_src ) = explode( '?', $image_src );
        
        // Return early if we couldn't get the image source.
        if ( ! $image_src ) {
            return $image;
        }
    
        // Bail early if an image has been inserted and later edited.
        if ( preg_match( '/-e[0-9]{13}/', $image_meta['file'], $img_edit_hash ) &&
            strpos( wp_basename( $image_src ), $img_edit_hash[0] ) === false ) {
    
            return $image;
        }
    
        $width  = preg_match( '/ width="([0-9]+)"/', $image, $match_width ) ? (int) $match_width[1] : 0;
        $height = preg_match( '/ height="([0-9]+)"/', $image, $match_height ) ? (int) $match_height[1] : 0;
    
        if ( $width && $height ) {
            $size_array = array( $width, $height );
        } else {
            $size_array = wp_image_src_get_dimensions( $image_src, $image_meta, $attachment_id );
            if ( ! $size_array ) {
                return $image;
            }
        }
    
        $srcset = $this->wp_calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id );
            
        if ( $srcset ) {
            // Check if there is already a 'sizes' attribute.
            $sizes = strpos( $image, ' sizes=' );
    
            if ( ! $sizes ) {
                $sizes = wp_calculate_image_sizes( $size_array, $image_src, $image_meta, $attachment_id );
            }
        }
    
        if ( $srcset && $sizes ) {
            // Format the 'srcset' and 'sizes' string and escape attributes.
            $attr = sprintf( ' srcset="%s"', esc_attr( $srcset ) );
    
            if ( is_string( $sizes ) ) {
                $attr .= sprintf( ' sizes="%s"', esc_attr( $sizes ) );
            }
    
            // Add the srcset and sizes attributes to the image markup.
            return preg_replace( '/<img ([^>]+?)[\/ ]*>/', '<img $1' . $attr . ' />', $image );
        }
    
        return $image;
    }

    public function wp_img_tag_add_srcset_and_sizes_attr( $image, $context, $attachment_id ) {
        /**
         * Filters whether to add the `srcset` and `sizes` HTML attributes to the img tag. Default `true`.
         *
         * Returning anything else than `true` will not add the attributes.
         *
         * @since 5.5.0
         *
         * @param bool   $value         The filtered value, defaults to `true`.
         * @param string $image         The HTML `img` tag where the attribute should be added.
         * @param string $context       Additional context about how the function was called or where the img tag is.
         * @param int    $attachment_id The image attachment ID.
         */
        $add = apply_filters( 'wp_img_tag_add_srcset_and_sizes_attr', true, $image, $context, $attachment_id );
    
        if ( true === $add ) {
            $image_meta = wp_get_attachment_metadata( $attachment_id );
            return $this->wp_image_add_srcset_and_sizes( $image, $image_meta, $attachment_id );
        }
    
        return $image;
    }

    
    function wp_filter_content_tags( $content, $context = null ) {
        if ( null === $context ) {
            $context = current_filter();
        }
    
        $add_img_loading_attr    = wp_lazy_loading_enabled( 'img', $context );

        $add_iframe_loading_attr = wp_lazy_loading_enabled( 'iframe', $context );
    
        if ( ! preg_match_all( '/<(img|iframe)\s[^>]+>/', $content, $matches, PREG_SET_ORDER ) ) {
            return $content;
        }
    
        // List of the unique `img` tags found in $content.
        $images = array();
    
        // List of the unique `iframe` tags found in $content.
        $iframes = array();
    
        foreach ( $matches as $match ) {
            list( $tag, $tag_name ) = $match;
    
            switch ( $tag_name ) {
                case 'img':
                    if ( preg_match( '/wp-image-([0-9]+)/i', $tag, $class_id ) ) {
                        $attachment_id = absint( $class_id[1] );
    
                        if ( $attachment_id ) {
                            // If exactly the same image tag is used more than once, overwrite it.
                            // All identical tags will be replaced later with 'str_replace()'.
                            $images[ $tag ] = $attachment_id;
                            break;
                        }
                    }
                    $images[ $tag ] = 0;
                    break;
                case 'iframe':
                    $iframes[ $tag ] = 0;
                    break;
            }
        }
    
        // Reduce the array to unique attachment IDs.
        $attachment_ids = array_unique( array_filter( array_values( $images ) ) );
    
        if ( count( $attachment_ids ) > 1 ) {
            /*
             * Warm the object cache with post and meta information for all found
             * images to avoid making individual database calls.
             */
            _prime_post_caches( $attachment_ids, false, true );
        }
    
        // Iterate through the matches in order of occurrence as it is relevant for whether or not to lazy-load.
        foreach ( $matches as $match ) {
            // Filter an image match.
            if ( isset( $images[ $match[0] ] ) ) {
                $filtered_image = $match[0];
                $attachment_id  = $images[ $match[0] ];
    
                // Add 'width' and 'height' attributes if applicable.
                if ( $attachment_id > 0 && false === strpos( $filtered_image, ' width=' ) && false === strpos( $filtered_image, ' height=' ) ) {
                    $filtered_image = wp_img_tag_add_width_and_height_attr( $filtered_image, $context, $attachment_id );
                }
    
                // Add 'srcset' and 'sizes' attributes if applicable.
                if ( $attachment_id > 0 && false === strpos( $filtered_image, ' srcset=' ) ) {
                    $filtered_image = $this->wp_img_tag_add_srcset_and_sizes_attr( $filtered_image, $context, $attachment_id );
                }
    
                // Add 'loading' attribute if applicable.
                if ( $add_img_loading_attr && false === strpos( $filtered_image, ' loading=' ) ) {
                    $filtered_image = wp_img_tag_add_loading_attr( $filtered_image, $context );
                }
    
                if ( $filtered_image !== $match[0] ) {
                    $content = str_replace( $match[0], $filtered_image, $content );
                }
            }
    
            // Filter an iframe match.
            if ( isset( $iframes[ $match[0] ] ) ) {
                $filtered_iframe = $match[0];
    
                // Add 'loading' attribute if applicable.
                if ( $add_iframe_loading_attr && false === strpos( $filtered_iframe, ' loading=' ) ) {
                    $filtered_iframe = wp_iframe_tag_add_loading_attr( $filtered_iframe, $context );
                }
    
                if ( $filtered_iframe !== $match[0] ) {
                    $content = str_replace( $match[0], $filtered_iframe, $content );
                }
            }
        }
    
        return $content;
    }

    public function get_attachment_url($url, $attachment_id)
    {
        $cloudflare_url = get_post_meta($attachment_id, 'cloudflare_url', true);

        return $cloudflare_url ?? $url;
    }


    public function filter_prepare_attachment_for_js($response, $attachment, $meta)
    {
        // $response['cloudflare_url'] = $this->get_cloudflare_url($attachment->ID);

        // dd($response);

        return $response;
    }


    public function filter_attachment_image_src($image, $attachment_id, $size, $icon)
    {
        $cloudflare_url = get_post_meta($attachment_id, 'cloudflare_url', true);
  
        if ($cloudflare_url) {
            $image[0] = $cloudflare_url;
        }
        
        return $image;
    }

    public function filter_attachment_url($url, $attachment_id)
    {
        if (!get_post_meta($attachment_id, 'cloudflare_url', true)) {
            return $url;
        }

        return get_post_meta($attachment_id, 'cloudflare_url', true);
    }

    public function cloudflare_action()
    {
        $action = trim($_POST['name']);

        if (!in_array($action, ['push', 'push_and_remove_local'])) {
            wp_send_json_error();
        }

        $mediaId = intval($_POST['value']);
        $media = get_post($mediaId);

        // Response error when post isn't attachment
        if (!$media || $media->post_type !== 'attachment' || !wp_attachment_is_image($mediaId)) {
            wp_send_json_error([
                'message' => 'Invalid media type',
            ]);
        }

        if ($action === 'push') {
            // Check if media is already uploaded to cloudflare
            $uploaded = $this->cloudflare->upload($media);

            wp_send_json_success(['uploaded' => $uploaded]);
        }
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'wp-media-cloudflare-meta-box',
            __('Cloudflare Integration', 'wp-media-cloudflare'),
            [$this, 'render_meta_box'],
            'attachment',
            'side',
            'low'
        );
    }

    public function render_meta_box($post)
    {
        $actions = [
            'push' => __('Push to Cloudflare', 'wmcf'),
            'push_and_remove_local' => __('Push to Cloudflare and remove local copy', 'wmcf'),
            'pull' => __('Pull from Cloudflare', 'wmcf'),
            'remove_local' => __('Remove local copy', 'wmcf'),
            'remove_cloudflare' => __('Remove from Cloudflare', 'wmcf'),
            'remove_cloudflare_and_local' => __('Remove from Cloudflare and local copy', 'wmcf'),
        ];
?>
        <div>
            <?php foreach ($actions as $action => $label) : ?>
                <button type="button" class="button" name="<?php echo $action; ?>" value="<?php echo $post->ID; ?>"><?php echo $label; ?></button>
            <?php endforeach; ?>
        </div>
<?php
    }

    public function save($post_id)
    {
        dd($post_id);
    }
}
