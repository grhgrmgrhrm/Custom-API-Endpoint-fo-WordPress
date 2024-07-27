# Custom API Endpoint Plugin

A WordPress plugin that extends the WP REST API to include custom endpoints for fetching posts from specific categories using slugs. The plugin also integrates with Advanced Custom Fields (ACF) to include custom fields data in the API response.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Advanced Custom Fields (ACF) plugin (optional for ACF integration)

## Installation

1. **Download the Plugin:**
    - Download the plugin ZIP file.

2. **Upload the Plugin:**
    - Go to the WordPress admin panel.
    - Navigate to "Plugins" -> "Add New".
    - Click on "Upload Plugin" and choose the downloaded ZIP file.
    - Click "Install Now".

3. **Activate the Plugin:**
    - After the plugin is installed, click "Activate".

4. **Configure the Plugin:**
    - Go to "Settings" -> "Custom API Endpoint".
    - Enter category slugs separated by commas and save the settings.

## Usage

The plugin creates REST API endpoints for each specified category slug. These endpoints can be accessed using the following URL structure:

### API Response Structure

Each endpoint returns posts from the specified category along with the following data:


### API Response Structure

Each endpoint returns posts from the specified category along with the following data:

```json
[
    {
        "ID": 104,
        "slug": "post-slug",
        "title": "Post Title",
        "content": "Post Content",
        "date": "2024-07-27 12:34:56",
        "categories": [
            {
                "category_name": "Category Name",
                "category_slug": "category-slug"
            }
        ],
        "tags": [
            "Tag1",
            "Tag2",
            "Tag3"
        ],
        "featured_image": "https://your-site.com/wp-content/uploads/2024/06/image.jpg",
        "acf": {
            "field_1": "value1",
            "field_2": "value2"
        }
    }
]
```


* ID: The post ID.
* slug: The post slug.
* title: The post title.
* content: The post content.
* date: The post publication date.
* categories: An array of categories the post belongs to, each containing:
 category_name: The name of the category.
 category_slug: The slug of the category.
* tags: An array of tags associated with the post.
* featured_image: The URL of the post's featured image.
* acf: An object containing all ACF fields associated with the post.

### Advanced Custom Fields (ACF) Integration

If the ACF plugin is installed and activated, the plugin will automatically include all ACF fields associated with each post in the acf object of the API response.
Automatic Data Sync

The plugin automatically sends data to the external endpoint whenever a post is created or updated. The data sent includes all posts from the specified categories with the same structure as the API response.
Notes

    Ensure you have the Advanced Custom Fields (ACF) plugin installed and activated to use ACF fields in the API response.
    The acf field in the API response will contain all ACF fields associated with the post.
    The category_image field from ACF will not be included in the category data to avoid duplication.

### License

This plugin is licensed under the GPLv2 (or later) license.
