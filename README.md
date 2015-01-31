# WordPress Post-Thumbnails

PostThumbnails Class for Wordpress. Enables multiple post thumbnails for post type.

## Requirements

* PHP >= 5.4
* WordPress >= 3.5

## Quick Start

First, in your theme's `functions.php` (or you can create a shim plugin that does this on the `wp_loaded` action) register a thumbnail. To do this, create a new `PostThumbnails` instance and pass in an array of arguments:

```php
if (class_exists('PostThumbnails')) {
    new PostThumbnails(
        [
            'label' => 'Secondary Image',
            'id' => 'secondary-image',
            'post_type' => ['post']
        ]
    );
}
```

The template tag `PostThumbnails::the_post_thumbnail` is similar to WordPress' `the_post_thumbnail` but it displays your custom thumbnail in a post loop:

```php
<?php if (class_exists('PostThumbnails')) :
    PostThumbnails::the_post_thumbnail('secondary-image');
endif; ?>
```

For more details please [check out the wiki](https://github.com/starise/PostThumbnails/wiki)
