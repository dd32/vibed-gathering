# gatherpress_event_structured_data


Filters the event structured data before output.

## Auto-generated Example

```php
add_filter(
   'gatherpress_event_structured_data',
    function(
        array $schema,
        int $post_id
    ) {
        // Your code here.
        return $schema;
    },
    10,
    2
);
```

## Parameters

- *`array`* `$schema` The structured data array.
- *`int`* `$post_id` The event post ID.

## Files

- [includes/core/classes/class-seo.php:254](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-seo.php#L254)
```php
apply_filters( 'gatherpress_event_structured_data', $schema, $post_id )
```



[← All Hooks](Hooks.md)
