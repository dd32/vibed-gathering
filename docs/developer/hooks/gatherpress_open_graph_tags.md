# gatherpress_open_graph_tags


Filters the Open Graph tags before output.

## Auto-generated Example

```php
add_filter(
   'gatherpress_open_graph_tags',
    function(
        array $gatherpress_tags,
        int $gatherpress_post_id
    ) {
        // Your code here.
        return $gatherpress_tags;
    },
    10,
    2
);
```

## Parameters

- *`array`* `$gatherpress_tags` The OG/Twitter tags.
- *`int`* `$gatherpress_post_id` The event post ID.

## Files

- [includes/core/classes/class-seo.php:133](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-seo.php#L133)
```php
apply_filters( 'gatherpress_open_graph_tags', $gatherpress_tags, $gatherpress_post_id )
```



[← All Hooks](Hooks.md)
