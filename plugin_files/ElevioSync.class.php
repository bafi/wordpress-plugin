<?php

class ElevioSync
{
    public function __construct()
    {
        require_once dirname(__FILE__).'/models/category.php';
        require_once dirname(__FILE__).'/models/post.php';
        require_once dirname(__FILE__).'/models/tag.php';
    }

    public function run($query)
    {
        if ($query === 'categories') {
            return $this->syncCategories();
        } elseif ($query === 'posts') {
            return $this->syncTopics();
        }
    }

    public function syncCategories($args = [])
    {
        $args['taxonomy'] = Elevio::get_instance()->get_category_taxonomy();

        $wp_categories = get_categories($args);
        $categories = [];
        foreach ($wp_categories as $wp_category) {
            if ($wp_category->term_id == 1 && $wp_category->slug == 'uncategorized' && $args['taxonomy'] == 'category') {
                continue;
            }
            $category = new Elevio_Sync_Category($wp_category);
            $categories[] = $category;
        }

        return $categories;
    }

    public function syncTopics($query = false, $wp_posts = false)
    {
        global $post, $wp_query;

        // We first force the post type to be our custom type...
        $_GET['post_type'] = Elevio::get_instance()->get_post_taxonomy();

        // Then, if a specific category is being requested, we manipulate the
        // parameter into a taxonomy request. 'cat' only works with normal
        // categories, not custom taxonomies.
        $tax_query = [];
        if (isset($_GET['cat'])) {
            $tax_query = [
                [
                    'taxonomy' => Elevio::get_instance()->get_category_taxonomy(),
                    'field'    => 'term_id',
                    'terms'    => $_GET['cat'],
                ],
            ];

            // We get rid of the 'cat' parameter too.
            unset($_GET['cat']);
        }

        // Allow the running of some extra filters on the retrieved topics
        $tax_query = apply_filters('elevio_posts_tax_query', $tax_query);
        $_GET['tax_query'] = $tax_query;

        query_posts(http_build_query($_GET));

        $output = [];
        while (have_posts()) {
            the_post();
            if ($wp_posts) {
                $new_post = $post;
            } else {
                $new_post = new Elevio_Sync_Post($post);
            }
            $output[] = $new_post;
        }

        return $output;
    }

    protected function set_posts_query($query = false)
    {
        global $json_api, $wp_query;

        if (! $query) {
            $query = [];
        }

        $query = array_merge($query, $wp_query->query);

        if ($json_api->query->page) {
            $query['paged'] = $json_api->query->page;
        }

        if ($json_api->query->count) {
            $query['posts_per_page'] = $json_api->query->count;
        }

        if ($json_api->query->post_type) {
            $query['post_type'] = $json_api->query->post_type;
        }

        $query = apply_filters('json_api_query_args', $query);
        if (! empty($query)) {
            query_posts($query);
            do_action('json_api_query', $wp_query);
        }
    }
}
