# Gen Fillable

Generate a model's fillable columns from the database.

## Install

```shell
composer require --dev mitoop/laravel-gen-fillable
```

Requires Laravel `^6|^7|^8|^9|^10|^11|^12|^13`.

## Usage

```shell
php artisan gen:model {name}
php artisan gen:fillable {name}
```

`gen:model` creates the model with `make:model`, then writes the fillable columns into it. A table name such as `users` becomes `User`.

`gen:fillable` looks at the first letter of `{name}`:

| Name | Treated as | Result |
| --- | --- | --- |
| `User` | Model | Writes fillable into the model file |
| `users` | Table | Prints fillable. On macOS, also copies it to the clipboard |

`id`, `created_at`, `updated_at`, and `deleted_at` are left out. A model that already has fillable is left unchanged.

Laravel 13 writes a `Fillable` attribute above the class. A short list stays on one line. A longer list is split across lines. Older versions write `protected $fillable`.

```php
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'email', 'password'])]
class User extends Model
{
}
```
