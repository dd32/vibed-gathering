# gatherpress_group_member_banned


Fires after a user is banned from a group.

## Auto-generated Example

```php
add_action(
   'gatherpress_group_member_banned',
    function(
        int $blog_id,
        int $user_id
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`int`* `$blog_id` The blog ID of the group.
- *`int`* `$user_id` The user ID.

## Files

- [includes/core/classes/class-group.php:462](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-group.php#L462)
```php
do_action( 'gatherpress_group_member_banned', $this->blog_id, $user_id )
```



[← All Hooks](Hooks.md)
