# gatherpress_group_dormant


Fires when a group is detected as dormant.

## Auto-generated Example

```php
add_action(
   'gatherpress_group_dormant',
    function(
        int $blog_id,
        int $days
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$blog_id` The group blog ID.
- *`int`* `$days` Days since last event.

## Files

- [includes/core/classes/class-dormancy-detector.php:267](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-dormancy-detector.php#L267)
```php
do_action( 'gatherpress_group_dormant', $gatherpress_group->get_blog_id(), $gatherpress_days )
```



[← All Hooks](Hooks.md)
