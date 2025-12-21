# Copilot Instructions for Quick Project

## Overview
This project is a backend application built with Laravel, a PHP framework. It includes models, controllers, services, and other components to manage a variety of features such as user management, product handling, transactions, and more. The codebase follows Laravel conventions but also introduces custom patterns and structures.

## Architecture
- **Models**: Located in `app/Models`, these represent the database tables. Examples include `Product`, `Transaction`, and `Wallet`.
- **Controllers**: Found in `app/Http/Controllers`, these handle HTTP requests and responses.
- **Services**: The `app/Services` directory contains business logic. For example, `WalletService` manages wallet-related operations.
- **Helpers**: Utility functions are in `app/Helpers`, such as `WalletHelper`.
- **Observers**: Located in `app/Observers`, these handle model events, e.g., `DriverObserver`.
- **Routes**: Defined in `routes/api.php`, `routes/web.php`, and `routes/console.php`.
- **Migrations and Seeders**: Database migrations are in `database/migrations`, and seeders are in `database/seeders`.

## Developer Workflows
### Running the Application
1. Start the development server:
   ```bash
   php artisan serve
   ```

### Running Tests
1. Execute all tests:
   ```bash
   php artisan test
   ```
2. Run specific tests:
   ```bash
   php artisan test --filter=TestName
   ```

### Database Migrations
1. Run migrations:
   ```bash
   php artisan migrate
   ```
2. Rollback migrations:
   ```bash
   php artisan migrate:rollback
   ```

### Seeding the Database
1. Seed the database:
   ```bash
   php artisan db:seed
   ```

## Project-Specific Conventions
- **Service Layer**: Business logic is encapsulated in services (e.g., `WalletService`). Avoid placing business logic in controllers.
- **Helper Functions**: Shared utility functions are in `app/Helpers`.
- **Observers**: Use observers to handle model-specific events.
- **Validation**: Request validation is handled in `app/Http/Requests`.

## External Dependencies
- **Laravel Sanctum**: Used for API authentication.
- **Guzzle**: For making HTTP requests.
- **Monolog**: For logging.

## Examples
### Adding a New Model
1. Create the model:
   ```bash
   php artisan make:model ModelName -m
   ```
2. Define the schema in the generated migration file.
3. Add relationships and methods in the model file.

### Creating a New Service
1. Add a new file in `app/Services`.
2. Implement the required business logic.
3. Inject the service into controllers as needed.

---

For further details, refer to the Laravel documentation or explore the codebase.

## Dynamic Product Attributes — Current Design (important)

- Key tables:
   - `attributes` (migration: database/migrations/2025_11_22_202301_create_attributes_table.php) — has `softDeletes`, `slug`, `is_active`.
   - `attribute_values` (migration: database/migrations/2025_11_22_204229_create_attribute_values_table.php) — `attribute_id`, `value`, `slug`, `softDeletes`, unique on `['attribute_id','value']`.
   - `product_variants` (migration: database/migrations/2025_12_16_235551_create_product_variants_table.php) — variant rows have `softDeletes`, `sku`, `price`, `stock_quantity`.
   - `product_variant_attributes` (migration: database/migrations/2025_12_16_235559_create_product_variant_attributes_table.php) — links a `product_variant_id` to `attribute_id` and `attribute_value_id`, with a unique index on `['product_variant_id','attribute_id']`.

- Models and locations:
   - `App\Models\Attribute\Attribute`
   - `App\Models\Attribute\AttributeValue`
   - `App\Models\Product`, `App\Models\ProductVariant`, `App\Models\ProductVariantAttribute` (models are present under `app/Models`).

- Deletion semantics observed:
   - `attributes` and `attribute_values` use Eloquent `softDeletes` (controller deletes call `->delete()` which soft-deletes).
   - Foreign keys in `product_variant_attributes` use `restrictOnDelete` for `attribute_id` and `attribute_value_id` and `cascadeOnDelete` for `product_variant_id`.
   - This combination prevents hard-deleting attributes/values while allowing soft-deletes — good for not breaking historical links.

## Immediate code-level recommendations (to keep Orders/Cart safe and extensible)

- Always snapshot variant data on order/cart: when an order or cart item is implemented, store immutable fields on the order item (e.g. `product_variant_id`, `sku`, `price_at_order`, and a JSON `attributes_snapshot` containing attribute name/value pairs). That ensures future edits or soft-deletes will not mutate past orders.

- Upsert correctness for attribute values: the code currently upserts attribute values via the relation. Use the composite unique keys when upserting. Example safe approach inside `AttributeController@update`:

   - include `attribute_id` in each value payload (or use raw query scoped to attribute) and call upsert with `['attribute_id','value']` as the uniqueBy keys to avoid collisions across different attributes.

- Keep FK behavior intentional:
   - `restrictOnDelete` + `softDeletes` is fine: soft deleting an attribute/value will not remove the referenced row so variants keep referencing the soft-deleted value.
   - Do not change `restrictOnDelete` to `cascadeOnDelete` for `attribute_id` or `attribute_value_id` — that would risk silently deleting variant links and breaking carts/orders.

- Enforce and document index/slug use:
   - `attributes.slug` and `attribute_values.slug` exist — use them in public APIs and search indexing.
   - Add indexes where heavy lookup will happen (e.g., `attribute_values(attribute_id, value)`) — migrations already add a unique index; ensure query usage leverages it.

- Relationship and model hygiene:
   - Add `fillable` and `casts` to `ProductVariant` and `ProductVariantAttribute` so services can safely mass-assign and serialize variants.
   - Implement helper methods on `ProductVariant` to return attributes in both ID form and human-readable form (e.g., `getAttributesMap()` that loads `attributeValues.attribute`).

## Where to look in the repo (quick links)

- Attribute controller: app/Http/Controllers/AttributeController.php (create/update/destroy patterns).
- Migrations: database/migrations/2025_11_22_202301_create_attributes_table.php and 2025_11_22_204229_create_attribute_values_table.php and product-variant migrations noted above.
- Models: app/Models/Attribute/Attribute.php, app/Models/Attribute/AttributeValue.php, app/Models/Product.php, app/Models/ProductVariant.php

## Small code examples (what AI agents should do)

- When generating update code for attribute values, include `attribute_id` in the upsert payload and use `['attribute_id','value']` as unique keys.

- When adding orders/cart features, persist a JSON snapshot of the variant attributes on order items; don't rely on joins back to `attribute_values` for historical data.

---

If you want, I can now:
- add `fillable`/`casts` to `ProductVariant` and `ProductVariantAttribute` and a `getAttributesMap()` helper on `ProductVariant`, and
- add a short unit test that demonstrates snapshotting attributes into a fake order item record.
Tell me which you'd like next.