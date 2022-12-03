<?php

namespace JazzMan\WpDuplicatePost;

use JazzMan\AutoloadInterface\AutoloadInterface;

class Duplicate implements AutoloadInterface {
    private static string $action = 'duplicate_post_as_draft';

    private static string $nonce = 'duplicate_nonce';

    public function load(): void {
        add_filter('post_row_actions', [self::class, 'duplicatePostLink'], 10, 2);
        add_filter('page_row_actions', [self::class, 'duplicatePostLink'], 10, 2);

        add_action(sprintf('admin_action_%s', self::$action), [self::class, 'duplicatePostAsDraft']);
    }

    /**
     * @param array<string,string> $actions
     *
     * @return array<string,string>
     */
    public static function duplicatePostLink(array $actions, \WP_Post $wpPost): array {
        if ('publish' === $wpPost->post_status && current_user_can('edit_posts')) {
            $actions['duplicate'] = sprintf(
                '<a href="%s" title="%s" rel="permalink">%s</a>',
                wp_nonce_url(
                    add_query_arg(
                        [
                            'action' => self::$action,
                            'post' => $wpPost->ID,
                        ],
                        'admin.php'
                    ),
                    basename(__FILE__),
                    self::$nonce
                ),
                esc_attr__('Duplicate this item'),
                esc_attr__('Duplicate')
            );
        }

        return $actions;
    }

    public static function duplicatePostAsDraft(): void {
        check_ajax_referer(basename(__FILE__), self::$nonce);

        $parameterBag = app_get_request_data();

        $postId = $parameterBag->getDigits('post');

        /** @var null|string $action */
        $action = $parameterBag->get('action');

        if (empty($postId) && self::$action !== $action) {
            wp_die('No post to duplicate has been supplied!');
        }

        // get the original post data
        $post = get_post((int) $postId);

        // if post data exists, create the post duplicate
        if ($post instanceof \WP_Post) {
            self::createNewDraftPost($post, (int) $postId);

            exit;
        }

        $title = 'Post creation failed!';
        wp_die(
            sprintf(
                '<span>%s</span>> could not find original post: %d',
                $title,
                $postId
            ),
            $title
        );
    }

    private static function createNewDraftPost(\WP_Post $wpPost, int $oldPostId): void {
        /**
         * if you don't want current user to be the new post author,
         * then change next couple of lines to this: $new_post_author = $post->post_author;.
         */
        $currentUser = wp_get_current_user();

        $newPostAuthor = (int) $wpPost->post_author === $currentUser->ID ? $wpPost->post_author : $currentUser->ID;

        // new post data array
        $postData = $wpPost->to_array();

        unset(
            $postData['ID'],
            $postData['post_date'],
            $postData['post_name'],
            $postData['post_date_gmt'],
            $postData['post_modified'],
            $postData['post_modified_gmt'],
            $postData['guid'],
            $postData['ancestors'],
            $postData['to_ping'],
        );

        /** @var string[] $taxonomies */
        $taxonomies = get_object_taxonomies($wpPost->post_type);

		/** @var array<string,mixed> $meta_data */
        $meta_data = get_post_custom($oldPostId);

        $tax_input = [];
        $meta_input = [];

        if (!empty($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                /** @var string[] $postTerms */
                $postTerms = wp_get_object_terms($oldPostId, $taxonomy, ['fields' => 'slugs']);

                if (!empty($postTerms)) {
                    $tax_input[$taxonomy] = $postTerms;
                }
            }
        }

        if (!empty($meta_data)) {
            foreach ($meta_data as $meta_key => $meta_value) {
                if (\in_array($meta_key, ['_edit_lock', '_edit_last'], true)) {
                    continue;
                }

                if (\is_array($meta_value)) {
                    $meta_input[$meta_key] = \count($meta_value) > 1 ? $meta_value : $meta_value[0];
                } else {
                    $meta_input[$meta_key] = $meta_value;
                }
            }
        }

        /** @var array<string,int|string|string[]> $newPostArgs */
        $newPostArgs = wp_parse_args(
            [
                'post_author' => $newPostAuthor,
                'post_status' => 'draft',
                'post_title' => sprintf('%s Copy', $wpPost->post_title),
                'tax_input' => $tax_input,
                'meta_input' => $meta_input,
            ],
            $postData
        );

        $newPostId = wp_insert_post($newPostArgs, true);

        if ($newPostId instanceof \WP_Error) {
            wp_die($newPostId->get_error_message());
        }

        $editPostLink = get_edit_post_link((int) $newPostId, 'edit');

        if (!empty($editPostLink)) {
            // finally, redirect to the edit post screen for the new draft
            wp_redirect($editPostLink);
        }
    }
}
