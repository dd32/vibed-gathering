# gatherpress_email_headers


Filters the email headers before sending.

## Auto-generated Example

```php
add_filter(
   'gatherpress_email_headers',
    function(
        GatherPress\string[] $headers,
        string $to,
        string $subject
    ) {
        // Your code here.
        return $headers;
    },
    10,
    3
);
```

## Parameters

- *`GatherPress\string[]`* `$headers` The email headers.
- *`string`* `$to` The recipient email.
- *`string`* `$subject` The email subject.

## Files

- [includes/core/classes/class-email.php:665](https://github.com/GatherPress/gatherpress/blob/develop/includes/core/classes/class-email.php#L665)
```php
apply_filters( 'gatherpress_email_headers', $headers, $to, $subject )
```



[← All Hooks](Hooks.md)
