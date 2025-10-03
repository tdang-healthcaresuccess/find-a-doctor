# PHP Warning Fix - $map_row Undefined Variable

## Issue
```
PHP Warning: Undefined variable $map_row in /wp-content/plugins/find-a-doctor/includes/class-fad-graphql.php on line 17
```

## Root Cause
The `$map_row` variable was being used in GraphQL resolver closures before it was defined:

```php
// PROBLEM: $map_row used here (line ~17)
'resolve' => function ($root, $args) use ($map_row) {
    // ...
}

// But $map_row was defined much later (line ~177)
$map_row = function(array $row) {
    // ...
};
```

## Solution
Moved the `$map_row` function definition to the beginning of the GraphQL registration action:

```php
add_action('graphql_register_types', function () {

    // Define $map_row FIRST
    $map_row = function(array $row) {
        // ... mapping logic
    };

    // THEN register GraphQL fields that use it
    register_graphql_field('RootQuery', 'physicians', [
        'resolve' => function ($root, $args) use ($map_row) {
            // Now $map_row is available
        }
    ]);
});
```

## Files Modified
- `includes/class-fad-graphql.php` - Moved `$map_row` function to line 6

## Result
- ✅ PHP warnings eliminated
- ✅ GraphQL queries function correctly
- ✅ No breaking changes to existing functionality