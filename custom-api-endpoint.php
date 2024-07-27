<?php
/*
Plugin Name: Custom API Endpoint
Description: A plugin to add custom endpoints to WP REST API for fetching posts from a specific category.
Version: 2.1.1
Author: grhgrmgrhrm
Author URI: https://github.com/grhgrmgrhrm/Custom-API-Endpoint-for-WordPress
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.0
Requires PHP: 7.2
*/

// Регистрация страницы настроек
add_action('admin_menu', 'custom_api_endpoint_add_admin_menu');
add_action('admin_init', 'custom_api_endpoint_settings_init');

function custom_api_endpoint_add_admin_menu() {
    add_options_page('Custom API Endpoint', 'Custom API Endpoint', 'manage_options', 'custom_api_endpoint', 'custom_api_endpoint_options_page');
}

function custom_api_endpoint_settings_init() {
    register_setting('customApiEndpoint', 'custom_api_endpoint_settings');

    add_settings_section(
        'custom_api_endpoint_section',
        __('Custom API Endpoint Settings', 'custom-api-endpoint'),
        'custom_api_endpoint_settings_section_callback',
        'customApiEndpoint'
    );

    add_settings_field(
        'custom_api_endpoint_category_slugs',
        __('Category Slugs', 'custom-api-endpoint'),
        'custom_api_endpoint_category_slugs_render',
        'customApiEndpoint',
        'custom_api_endpoint_section'
    );
}

function custom_api_endpoint_category_slugs_render() {
    $options = get_option('custom_api_endpoint_settings');
    ?>
    <input type='text' name='custom_api_endpoint_settings[custom_api_endpoint_category_slugs]' value='<?php echo isset($options['custom_api_endpoint_category_slugs']) ? esc_attr($options['custom_api_endpoint_category_slugs']) : ''; ?>'>
    <p><?php _e('Enter category slugs separated by commas. The API endpoints will be created at: <code>https://your-site.com/wp-json/custom-api/v1/{slug}</code>', 'custom-api-endpoint'); ?></p>
    <?php
}

function custom_api_endpoint_settings_section_callback() {
    echo __('Enter the slugs of the categories. The API endpoints will be created at: <code>https://your-site.com/wp-json/custom-api/v1/{slug}</code>', 'custom-api-endpoint');
}

function custom_api_endpoint_options_page() {
    ?>
    <form action='options.php' method='post'>
        <h2>Custom API Endpoint</h2>
        <?php
        settings_fields('customApiEndpoint');
        do_settings_sections('customApiEndpoint');
        submit_button();
        ?>
    </form>
    <?php
}

// Регистрация REST API эндпоинтов
add_action('rest_api_init', function () {
    $options = get_option('custom_api_endpoint_settings');
    $category_slugs = isset($options['custom_api_endpoint_category_slugs']) ? explode(',', $options['custom_api_endpoint_category_slugs']) : [];

    foreach ($category_slugs as $slug) {
        $slug = trim($slug);
        if ($slug) {
            register_rest_route('custom-api/v1', '/' . $slug, array(
                'methods' => 'GET',
                'callback' => function ($data) use ($slug) {
                    return custom_api_endpoint_callback($data, $slug);
                },
                'args' => array(
                    'per_page' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param) && $param > 0;
                        },
                        'default' => 10
                    ),
                    'page' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param) && $param > 0;
                        },
                        'default' => 1
                    ),
                    'search' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_string($param);
                        },
                    ),
                    'orderby' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return in_array($param, array('date', 'title', 'modified', 'ID'));
                        },
                        'default' => 'date'
                    ),
                    'order' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return in_array(strtoupper($param), array('ASC', 'DESC'));
                        },
                        'default' => 'DESC'
                    )
                ),
            ));
        }
    }
});

function custom_api_endpoint_callback($data, $slug) {
    // Получение параметров запроса
    $per_page = $data['per_page'];
    $page = $data['page'];
    $search = isset($data['search']) ? $data['search'] : '';
    $orderby = $data['orderby'];
    $order = strtoupper($data['order']);

    // Получение постов из заданной категории
    $args = array(
        'category_name' => $slug,
        'posts_per_page' => $per_page,
        'paged' => $page,
        's' => $search,
        'orderby' => $orderby,
        'order' => $order,
    );

    $posts = get_posts($args);

    // Форматирование данных постов
    $post_data = array();
    foreach ($posts as $post) {
        // Получение метаданных поста
        $categories = get_the_category($post->ID);
        $formatted_categories = array();

        foreach ($categories as $category) {
            $formatted_categories[] = array(
                'category_name' => $category->name,
                'category_slug' => $category->slug,
            );
        }

        $tags = get_the_tags($post->ID);
        $tag_names = $tags ? wp_list_pluck($tags, 'name') : array();

        // Получение всех полей ACF
        $acf_fields = get_fields($post->ID);
        $acf_data = $acf_fields ? $acf_fields : array();

        $post_data[] = array(
            'ID' => $post->ID,
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'date' => $post->post_date,
            'categories' => $formatted_categories,
            'tags' => $tag_names,
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'full'), // Получение изображения записи
            'acf' => $acf_data // Включение полей ACF
        );
    }

    return new WP_REST_Response($post_data, 200);
}

// Отправка данных на внешний эндпоинт при создании или обновлении поста
add_action('save_post', 'custom_api_endpoint_send_data_on_save', 10, 3);

function custom_api_endpoint_send_data_on_save($post_id, $post, $update) {
    // Проверка, что это не автосохранение
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Не отправляем данные для определенных типов постов
    if ($post->post_type !== 'post') return;

    // Получите настройки
    $options = get_option('custom_api_endpoint_settings');
    $category_slugs = isset($options['custom_api_endpoint_category_slugs']) ? explode(',', $options['custom_api_endpoint_category_slugs']) : [];

    foreach ($category_slugs as $slug) {
        $slug = trim($slug);
        if (!$slug) continue;

        // Получите все посты из указанной категории
        $args = array(
            'category_name' => $slug,
            'posts_per_page' => -1,
        );

        $posts = get_posts($args);
        $post_data = array();

        foreach ($posts as $post) {
            // Получение метаданных поста
            $categories = get_the_category($post->ID);
            $formatted_categories = array();

            foreach ($categories as $category) {
                $formatted_categories[] = array(
                    'category_name' => $category->name,
                    'category_slug' => $category->slug,
                );
            }

            $tags = get_the_tags($post->ID);
            $tag_names = $tags ? wp_list_pluck($tags, 'name') : array();

            // Получение всех полей ACF
            $acf_fields = get_fields($post->ID);
            $acf_data = $acf_fields ? $acf_fields : array();

            $post_data[] = array(
                'ID' => $post->ID,
                'slug' => $post->post_name,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'date' => $post->post_date,
                'categories' => $formatted_categories,
                'tags' => $tag_names,
                'featured_image' => get_the_post_thumbnail_url($post->ID, 'full'), // Получение изображения записи
                'acf' => $acf_data // Включение полей ACF
            );
        }

        // URL вашего эндпоинта
        $endpoint_url = 'https://your-site.com/wp-json/custom-api/v1/' . $slug;

        // Отправка данных через POST запрос
        $response = wp_remote_post($endpoint_url, array(
            'method'    => 'POST',
            'body'      => json_encode($post_data),
            'headers'   => array(
                'Content-Type' => 'application/json',
            ),
        ));
    }
}

?>
