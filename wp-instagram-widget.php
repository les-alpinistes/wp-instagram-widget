<?php
/*
Plugin Name: WP Instagram Widget
Plugin URI: https://github.com/scottsweb/wp-instagram-widget
Description: A WordPress widget for showing your latest Instagram photos.
Version: 2.0.4
Author: Scott Evans
Author URI: https://scott.ee
Text Domain: wp-instagram-widget
Domain Path: /assets/languages/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: scottsweb/wp-instagram-widget

Copyright © 2013 Scott Evans

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

function wpiw_init()
{

    // define some constants.
    define('WP_INSTAGRAM_WIDGET_JS_URL', plugins_url('/assets/js', __FILE__));
    define('WP_INSTAGRAM_WIDGET_CSS_URL', plugins_url('/assets/css', __FILE__));
    define('WP_INSTAGRAM_WIDGET_IMAGES_URL', plugins_url('/assets/images', __FILE__));
    define('WP_INSTAGRAM_WIDGET_PATH', dirname(__FILE__));
    define('WP_INSTAGRAM_WIDGET_BASE', plugin_basename(__FILE__));
    define('WP_INSTAGRAM_WIDGET_FILE', __FILE__);

    // load language files.
    load_plugin_textdomain('wp-instagram-widget', false, dirname(WP_INSTAGRAM_WIDGET_BASE) . '/assets/languages/');
}

add_action('init', 'wpiw_init');

function wpiw_widget()
{
    register_widget('null_instagram_widget');
}

add_action('widgets_init', 'wpiw_widget');

Class null_instagram_widget extends WP_Widget
{

    function __construct()
    {
        parent::__construct(
            'null-instagram-feed',
            __('Instagram', 'wp-instagram-widget'),
            array(
                'classname' => 'null-instagram-feed',
                'description' => esc_html__('Displays your latest Instagram photos', 'wp-instagram-widget'),
                'customize_selective_refresh' => true,
            )
        );
    }

    function widget($args, $instance)
    {

        $title = empty($instance['title']) ? '' : apply_filters('widget_title', $instance['title']);
        $username = empty($instance['username']) ? '' : $instance['username'];
        $limit = empty($instance['number']) ? 9 : $instance['number'];
        $size = empty($instance['size']) ? 'large' : $instance['size'];
        $target = empty($instance['target']) ? '_self' : $instance['target'];
        $link = empty($instance['link']) ? '' : $instance['link'];

        echo $args['before_widget'];

        if (!empty($title)) {
            echo $args['before_title'] . wp_kses_post($title) . $args['after_title'];
        };

        do_action('wpiw_before_widget', $instance);

        if ('' !== $username) {

            $media_array = $this->scrape_instagram($username);

            if (is_wp_error($media_array)) {

                echo wp_kses_post($media_array->get_error_message());

            } else {

                // filter for images only?
                if ($images_only = apply_filters('wpiw_images_only', false)) {
                    $media_array = array_filter($media_array, array($this, 'images_only'));
                }

                // slice list down to required limit.
                $media_array = array_slice(apply_filters('wpiw_media_array', $media_array), 0, $limit);

                // filters for custom classes.
                $ulclass = apply_filters('wpiw_list_class', 'instagram-pics instagram-size-' . $size);
                $liclass = apply_filters('wpiw_item_class', '');
                $aclass = apply_filters('wpiw_a_class', '');
                $imgclass = apply_filters('wpiw_img_class', '');
                $template_part = apply_filters('wpiw_template_part', 'parts/wp-instagram-widget.php');

                ?>
                <ul class="<?php echo esc_attr($ulclass); ?>"><?php
                foreach ($media_array as $item) {
                    // copy the else line into a new file (parts/wp-instagram-widget.php) within your theme and customise accordingly.
                    if (locate_template($template_part) !== '') {
                        include locate_template($template_part);
                    } else {
                        $rel = ($target === '_blank') ? 'noopener' : '';
                        echo '<li class="' . esc_attr($liclass) . '"><a href="' . esc_url($item['link']) . '" target="' . esc_attr($target) . '" rel="' . esc_attr($rel) . '"  class="' . esc_attr($aclass) . '"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVQIW2MMDQ39z8DAwMAIYwAAKgMD/9AXrvgAAAAASUVORK5CYII=" data-lazy-src="' . esc_url($item[$size]) . '"  srcset="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVQIW2MMDQ39z8DAwMAIYwAAKgMD/9AXrvgAAAAASUVORK5CYII=" data-lazy-srcset="' . esc_url($item[$size]) . '" alt="' . esc_attr($item['description']) . '" title="' . esc_attr($item['description']) . '"  class="jetpack-lazy-image' . esc_attr($imgclass) . '"/></a></li>';
                    }
                }
                ?></ul><?php
            }
        }

        $linkclass = apply_filters('wpiw_link_class', 'clear');
        $linkaclass = apply_filters('wpiw_linka_class', '');

        switch (substr($username, 0, 1)) {
            case '#':
                $url = '//www.instagram.com/explore/tags/' . str_replace('#', '', $username);
                break;

            default:
                $url = '//www.instagram.com/' . str_replace('@', '', $username);
                break;
        }

        if ('' !== $link) {
            $relme = ($target === '_blank') ? 'noopener' : 'me';
            ?><p class="<?php echo esc_attr($linkclass); ?>"><a href="<?php echo trailingslashit(esc_url($url)); ?>"
                                                                rel="<?php echo esc_attr($relme); ?>"
                                                                target="<?php echo esc_attr($target); ?>"
                                                                class="<?php echo esc_attr($linkaclass); ?>"><?php echo wp_kses_post($link); ?></a>
            </p><?php
        }

        do_action('wpiw_after_widget', $instance);

        echo $args['after_widget'];
    }

    function scrape_instagram($username)
    {
        global $wp_version;

        $proxies = array(
            'https://boomproxy.com/browse.php?u=',
            'https://us.hidester.com/proxy.php?u=',
            'https://proxy-us1.toolur.com/browse.php?u=',
            'https://proxy-fr1.toolur.com/browse.php?u=',
        );

        $username = trim(strtolower($username));

        switch (substr($username, 0, 1)) {
            case '#':
                $url = 'https://www.instagram.com/explore/tags/' . str_replace('#', '', $username) . '?__a=1';
                $transient_prefix = 'h';
                break;

            default:
                $url = 'https://www.instagram.com/' . str_replace('@', '', $username) . '?__a=1';
                $transient_prefix = 'u';
                break;
        }

        if ($proxy = apply_filters('wpiw_proxy', false)) {
            $url = $proxies[array_rand($proxies)] . urlencode($url);
        }

        if (false === ($instagram = get_transient('wpiw-01-' . $transient_prefix . '-' . sanitize_title_with_dashes($username)))) {

            $remote = wp_remote_get($url, array(
                'user-agent' => 'Instagram/' . $wp_version . '; ' . home_url(),
            ));

            if (is_wp_error($remote)) {
                return new WP_Error('site_down', esc_html__('Unable to communicate with Instagram.', 'wp-instagram-widget'));
            }

            if (200 !== wp_remote_retrieve_response_code($remote)) {
                return new WP_Error('invalid_response', esc_html__('Instagram did not return a 200.', 'wp-instagram-widget'));
            }

            $insta_array = json_decode($remote['body'], true);

            if (!$insta_array) {
                return new WP_Error('bad_json', esc_html__('Instagram has returned invalid data.', 'wp-instagram-widget'));
            }

            if (isset($insta_array['graphql']['user']['edge_owner_to_timeline_media']['edges'])) {
                $images = $insta_array['graphql']['user']['edge_owner_to_timeline_media']['edges'];
            } elseif (isset($insta_array['graphql']['hashtag']['edge_hashtag_to_media']['edges'])) {
                $images = $insta_array['graphql']['hashtag']['edge_hashtag_to_media']['edges'];
            } else {
                return new WP_Error('bad_json_2', esc_html__('Instagram has returned invalid data.', 'wp-instagram-widget'));
            }

            if (!is_array($images)) {
                return new WP_Error('bad_array', esc_html__('Instagram has returned invalid data.', 'wp-instagram-widget'));
            }

            $instagram = array();

            foreach ($images as $image) {
                if (true === $image['node']['is_video']) {
                    $type = 'video';
                } else {
                    $type = 'image';
                }

                $caption = __('Instagram Image', 'wp-instagram-widget');
                if (!empty($image['node']['edge_media_to_caption']['edges'][0]['node']['text'])) {
                    $caption = wp_kses($image['node']['edge_media_to_caption']['edges'][0]['node']['text'], array());
                }

                $instagram[] = array(
                    'description' => $caption,
                    'link' => trailingslashit('//www.instagram.com/p/' . $image['node']['shortcode']),
                    'time' => $image['node']['taken_at_timestamp'],
                    'comments' => $image['node']['edge_media_to_comment']['count'],
                    'likes' => $image['node']['edge_liked_by']['count'],
                    'thumbnail' => preg_replace('/^https?\:/i', '', $image['node']['thumbnail_resources'][0]['src']),
                    'small' => preg_replace('/^https?\:/i', '', $image['node']['thumbnail_resources'][2]['src']),
                    'large' => preg_replace('/^https?\:/i', '', $image['node']['thumbnail_resources'][4]['src']),
                    'original' => preg_replace('/^https?\:/i', '', $image['node']['display_url']),
                    'type' => $type,
                );
            } // End foreach().

            // do not set an empty transient - should help catch private or empty accounts. Set a shorter transient in other cases to stop hammering Instagram
            if (!empty($instagram)) {
                $instagram = base64_encode(serialize($instagram));
                set_transient('wpiw-01-' . $transient_prefix . '-' . sanitize_title_with_dashes($username), $instagram, apply_filters('null_instagram_cache_time', HOUR_IN_SECONDS * 3));
            } else {
                $instagram = base64_encode(serialize(array()));
                set_transient('wpiw-01-' . $transient_prefix . '-' . sanitize_title_with_dashes($username), $instagram, apply_filters('null_instagram_cache_time', MINUTE_IN_SECONDS * 10));
            }
        }

        if (!empty($instagram)) {

            return unserialize(base64_decode($instagram));

        } else {

            return new WP_Error('no_images', esc_html__('Instagram did not return any images.', 'wp-instagram-widget'));

        }
    }

    function form($instance)
    {
        $instance = wp_parse_args((array)$instance, array(
            'title' => __('Instagram', 'wp-instagram-widget'),
            'username' => '',
            'size' => 'large',
            'link' => __('Follow Me!', 'wp-instagram-widget'),
            'number' => 9,
            'target' => '_self',
        ));
        $title = $instance['title'];
        $username = $instance['username'];
        $number = absint($instance['number']);
        $size = $instance['size'];
        $target = $instance['target'];
        $link = $instance['link'];
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title', 'wp-instagram-widget'); ?>
                : <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                         name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                         value="<?php echo esc_attr($title); ?>"/></label></p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('username')); ?>"><?php esc_html_e('@username or #tag', 'wp-instagram-widget'); ?>
                : <input class="widefat" id="<?php echo esc_attr($this->get_field_id('username')); ?>"
                         name="<?php echo esc_attr($this->get_field_name('username')); ?>" type="text"
                         value="<?php echo esc_attr($username); ?>"/></label></p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('number')); ?>"><?php esc_html_e('Number of photos', 'wp-instagram-widget'); ?>
                : <input class="widefat" id="<?php echo esc_attr($this->get_field_id('number')); ?>"
                         name="<?php echo esc_attr($this->get_field_name('number')); ?>" type="text"
                         value="<?php echo esc_attr($number); ?>"/></label></p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('size')); ?>"><?php esc_html_e('Photo size', 'wp-instagram-widget'); ?>
                :</label>
            <select id="<?php echo esc_attr($this->get_field_id('size')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('size')); ?>" class="widefat">
                <option value="thumbnail" <?php selected('thumbnail', $size); ?>><?php esc_html_e('Thumbnail', 'wp-instagram-widget'); ?></option>
                <option value="small" <?php selected('small', $size); ?>><?php esc_html_e('Small', 'wp-instagram-widget'); ?></option>
                <option value="large" <?php selected('large', $size); ?>><?php esc_html_e('Large', 'wp-instagram-widget'); ?></option>
                <option value="original" <?php selected('original', $size); ?>><?php esc_html_e('Original', 'wp-instagram-widget'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('target')); ?>"><?php esc_html_e('Open links in', 'wp-instagram-widget'); ?>
                :</label>
            <select id="<?php echo esc_attr($this->get_field_id('target')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('target')); ?>" class="widefat">
                <option value="_self" <?php selected('_self', $target); ?>><?php esc_html_e('Current window (_self)', 'wp-instagram-widget'); ?></option>
                <option value="_blank" <?php selected('_blank', $target); ?>><?php esc_html_e('New window (_blank)', 'wp-instagram-widget'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('link')); ?>"><?php esc_html_e('Link text', 'wp-instagram-widget'); ?>
                : <input class="widefat" id="<?php echo esc_attr($this->get_field_id('link')); ?>"
                         name="<?php echo esc_attr($this->get_field_name('link')); ?>" type="text"
                         value="<?php echo esc_attr($link); ?>"/></label></p>
        <?php

    }

    function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['username'] = trim(strip_tags($new_instance['username']));
        $instance['number'] = !absint($new_instance['number']) ? 9 : $new_instance['number'];
        $instance['size'] = (('thumbnail' === $new_instance['size'] || 'large' === $new_instance['size'] || 'small' === $new_instance['size'] || 'original' === $new_instance['size']) ? $new_instance['size'] : 'large');
        $instance['target'] = (('_self' === $new_instance['target'] || '_blank' === $new_instance['target']) ? $new_instance['target'] : '_self');
        $instance['link'] = strip_tags($new_instance['link']);
        return $instance;
    }

    function images_only($media_item)
    {

        if ('image' === $media_item['type']) {
            return true;
        }

        return false;
    }
}
