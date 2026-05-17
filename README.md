# Wingman

**Wingman** is a lightweight PHP REST API framework built for developers who want full control without the overhead of a full-stack framework. It gives you the essential building blocks — routing, controllers, models, middleware, a fluent query builder, and file upload handling — in a clean, minimal package you can actually understand end to end.

---

## Why Wingman?

Most PHP frameworks do too much. Wingman does just enough.

It's designed for building focused, fast REST APIs where you know exactly what's happening under the hood — no magic, no black boxes, no bloat. Every layer is explicit and transparent, from how requests are routed to how SQL is built and executed.

---

## Features

| Feature                       | Status      |
| ----------------------------- | ----------- |
| Routing                       | ✅ Supported |
| Controllers                   | ✅ Supported |
| JSON Responses                | ✅ Supported |
| Models                        | ✅ Supported |
| Query Chaining                | ✅ Supported |
| Middlewares                   | ✅ Supported |
| Input Validation              | ✅ Supported |
| Logging                       | ✅ Supported |
| CORS Configuration            | ✅ Supported |
| Rate Limiting                 | ✅ Supported |
| File Upload Handling          | ✅ Supported |

---

## Supported Database
- MySQL

---

## Installation

### Using wing.exe (Recommended)

`wing.exe` is a standalone CLI tool built with Rust for bootstrapping and managing Wingman projects outside of PHP. It handles project scaffolding, production builds, and directory cleanup — with no PHP or Composer required to run it.

**Setup:**

1. Add the directory containing `wing.exe` to your system's **PATH** environment variable.
2. Open an **empty directory** where you want to create your project.
3. Run:

```bash
wing new
```

This copies all Wingman project essentials into the current directory. The command will refuse to run if the directory is not empty.

> **Note:** `wing new` must be run from a directory that is separate from the `wing.exe` source directory.

---

### wing.exe Commands

| Command               | Description |
| --------------------- | ----------- |
| `wing new`            | Scaffolds a new Wingman project into the current (empty) directory |
| `wing new --clean`    | Same as `wing new`, but strips all comments from PHP files for a minimal starting point |
| `wing build`          | Bundles the project into `Builds/build-<timestamp>/`, excluding files listed in `.wingignore` |
| `wing clean`          | Wipes the current directory so it can be reused as a fresh Wingman project |
| `wing stats`          | Displays the total size of the current project, excluding the `Builds/` directory |
| `wing stats --latest` | Displays the total size of the most recent build based on its timestamp |

---

### wing new --clean

Scaffolds a new project with all comments stripped from PHP source files. This is useful if you are already familiar with the framework and prefer a cleaner starting point without inline documentation.

```bash
wing new --clean
```

> **Note:** Comments are only stripped from your application's PHP files. Files inside the `vendor/` directory are always copied as-is to avoid breaking third-party dependencies.

---

### wing build

Produces a clean production snapshot of your project under:

```
Builds/
└── build-2025-04-30_14-00-00/
```

Files and directories listed in `.wingignore` are excluded from the build. Example `.wingignore`:

```
App/Seeders
Docs
App/Core/App/Kit
README.md
wing
.env
Logs/*
```

---

### wing clean

Wipes all files and folders in the current directory, resetting it so you can run `wing new` again.

You'll be prompted to confirm before anything is deleted:

```
Delete all files in the current directory? [yes]
```

Enter anything other than `n` or `no` to proceed.

> **Warning:** Do not run this command inside the Wingman framework's source directory, as it will delete the source files.

---

### wing stats

Displays the total size of the current project, excluding the `Builds/` directory and `.git/`.

```bash
wing stats
```

```
Project size : 322.00 KB
```

Use `--latest` to display the size of the most recent build instead, determined by the timestamp in the build folder name.

```bash
wing stats --latest
```

```
Latest build : build-2026-04-30_17-04-47
Size         : 90.97 KB
```

> **Note:** `wing stats --latest` requires at least one build to exist under `Builds/`. Run `wing build` first if the directory is empty.

---

### Manual Installation

```bash
git clone https://github.com/Kai-ruuu/wingman.git
cd wingman-project
composer install
```

Configure your database in the environment config, then:

```bash
php wing build-models
php wing serve
```

---

## Core Concepts

### Wing CLI (PHP)

Wingman also ships with a PHP-based command-line tool called `wing` for scaffolding and managing your application from within the project.

```bash
php wing make-model --name=Post
php wing make-controller --name=Post
php wing make-router --name=Post
php wing make-middleware --name=Auth
php wing make-seeder --name=Post
php wing build --all
php wing build --model=Post
php wing build --model=Post --schema
php wing demolish --all
php wing demolish --model=Post
php wing seed --all
php wing seed --seeder=Post
php wing serve
php wing serve --host=0.0.0.0 --port=9000
```

### Routing

Routes are defined inside a router's `describe()` method. Each router extends `BaseRouter` and maps HTTP methods and path patterns to controller methods.

```php
class PostRouter extends BaseRouter
{
    public function describe(): void
    {
        $this->setPrefix('/api/posts'); // optional prefix

        $this->get('/', PostController::class, 'index');
        $this->get('/{id}', PostController::class, 'show');
        $this->post('/', PostController::class, 'store');
        $this->put('/{id}', PostController::class, 'update');
        $this->delete('/{id}', PostController::class, 'destroy');
    }
}
```

#### Middlewares

Attach an ordered middleware chain to any route using `withMiddlewares()`. Middlewares run before the controller method is invoked.

```php
$this->get('/', PostController::class, 'index')
    ->withMiddlewares([
        AuthMiddleware::class,
        LogMiddleware::class,
    ]);
```

#### Rate Limiting

Limit the number of requests a client can make to a route within a given time window. Rate limiting is based on the client's IP address and uses file-based storage — no additional dependencies required.

Apply a limit to a single route using `withLimitation(maxRequests, perSeconds)`:

```php
$this->post('/login', AuthController::class, 'login')
    ->withLimitation(5, 60); // 5 requests per minute
```

Apply a limit to all routes defined in the router using `withLimitationToAll(maxRequests, perSeconds)`:

```php
class SampleRouter extends BaseRouter
{
    public function describe(): void
    {
        $this->setPrefix('/api/sample');

        $this->get('/', SampleController::class, 'show')
            ->withMiddlewares([
                SampleMiddleware::class,
            ]);

        $this->get('/{id}', SampleController::class, 'showById')
            // Apply a limit of 60 requests per minute to this specific route
            ->withLimitation(60, 60);

        /**
         * Apply a limit of 60 requests per minute to all routes defined above
         *
         * NOTE: Do not use this if you have already applied rate limiting to any
         * specific routes above, as it will overwrite those individual limits.
         */
        $this->withLimitationToAll(60, 60);
    }
}
```

When a client exceeds the limit, Wingman responds with `429 Too Many Requests` and a human-readable message indicating when they can retry.

### Models & Schema Builder

Define your database schema directly in your model using a fluent, chainable API — no raw SQL required.

```php
class PostModel extends QueryableModel
{
    protected string $table = 'posts';

    public function describe(): void
    {
        $this->primaryKey()
             ->integer('user_id')->required()
             ->characters('title', 255)->required()
             ->text('body')->optional()
             ->boolean('active', true)
             ->foreignKey('user_id', 'users.id', 'cascade')
             ->timestamps();
    }
}
```

### Seeders

Seeders populate your database tables with initial or test data. Define your records inside `describe()` using the `setup()` method, referencing the target model and the data to insert.

```php
class PostSeeder extends BaseSeeder
{
    public function describe(): void
    {
        $this->setup(PostModel::class, [
            [
                'title'  => 'Hello World',
                'body'   => 'My first post.',
                'active' => true,
            ],
            [
                'title'  => 'Second Post',
                'body'   => 'Another entry.',
                'active' => true,
            ],
        ]);
    }
}
```

Run a specific seeder or all registered seeders at once:

```bash
php wing seed --seeder=Post
php wing seed --all
```

### Query Builder

A safe, fluent query builder with full support for filtering, joining, grouping, ordering, and pagination. All values are parameterized — SQL injection safe by default.

📄 [View the Full Query Builder & QueryableModel Documentation](./Docs/wingman-query-docs.pdf)

```php
$posts = $post
    ->select('posts.title', 'users.username')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.active', '=', 1)
    ->whereIn('category', ['tech', 'design'])
    ->orderByDesc('posts.created_at')
    ->paginateResult(page: 1, perPage: 10);
```

### Controllers

Controllers handle request logic cleanly, with access to a shared database connection via the base controller.

```php
class PostController extends BaseController
{
    public function show(Request $request, Response $response): void
    {
        $id   = Validator::requiredInt('id', $request->fromParams('id'), 1);
        $post = (new PostModel($this->db))->find($id);

        if (!$post)
            $response::notFound(['message' => 'Post not found.']);

        $response::ok($post);
    }
}
```

### Middleware

Middleware intercepts requests before they reach your controller. Each middleware has access to a shared `Context` object — a data bag that carries state across the middleware chain and into the controller.

```php
class AuthMiddleware extends BaseMiddleware
{
    public function run(): Context
    {
        $token = // extract token from request

        if (!$token)
            Response::bad(['message' => 'Invalid authentication token.']);

        // pass authenticated user downstream via context
        $this->context->add('auth_user', $user);

        return $this->next();
    }
}
```

### File Upload Handling

Wingman provides a two-phase staging and commit workflow for handling file uploads safely. Files are first moved to a temporary directory during `stage()`, then relocated to their final destination during `commit()`. If anything goes wrong at any point, `rollback()` cleans up any staged temp files automatically.

There are three classes involved:

- **`Upload`** — handles a single file from a single-file input
- **`MultiUpload`** — handles multiple files from a multi-file input (`name="field[]"`)
- **`UploadHandler`** — orchestrates one or more `Upload` and `MultiUpload` instances together

All three implement the `Uploadable` interface, so `UploadHandler` can manage them uniformly.

---

#### Single File — `Upload`

Use `Upload` when your form has a standard single-file input (e.g. `name="avatar"`).

```php
$handler = UploadHandler::build()
    ->add(
        Upload::build()
            ->asRequired()
            ->withLabel('Profile Photo')
            ->withFieldName('avatar')
            ->withAllowedTypes(['image/png', 'image/jpeg'])
            ->withMaxSizeMbOf(2.0)
            ->withDestination('avatars')
            ->withPrefix('avatar')
    );
```

| Method | Description |
| ------ | ----------- |
| `asRequired()` | Marks the upload as mandatory; fails if no file is provided |
| `withLabel(string)` | Sets the human-readable label used in error messages |
| `withFieldName(string)` | Sets the `$_FILES` key to read from |
| `withPrefix(string)` | Prepends a string to the generated unique filename (e.g. `avatar_<uniqid>.png`) |
| `withAllowedTypes(array)` | List of permitted MIME types; files outside this list are rejected |
| `withMaxSizeMbOf(float)` | Maximum allowed file size in megabytes (default: `5.0`) |
| `withDestination(string)` | Subdirectory under `Uploads/` where the file is committed |

MIME type detection uses PHP's `finfo` extension on the actual file content — not the client-supplied filename or content-type header. This makes type validation reliable and spoof-resistant.

---

#### Multiple Files — `MultiUpload`

Use `MultiUpload` when your form has a multi-file input with an array field name (e.g. `name="images[]" multiple`).

PHP structures `$_FILES` for multi-file inputs as parallel arrays keyed by property — `MultiUpload` handles that transposition internally, so you work with it the same way as `Upload`.

```php
$handler = UploadHandler::build()
    ->add(
        MultiUpload::build()
            ->asRequired()
            ->withLabel('Recipe Images')
            ->withFieldName('images')
            ->withAllowedTypes(['image/png', 'image/jpeg', 'image/webp'])
            ->withMaxSizeMbOf(2.0)
            ->withMaxFileOf(3)
            ->withDestination('recipes/images')
            ->withPrefix('recipe')
    );
```

| Method | Description |
| ------ | ----------- |
| `asRequired()` | Marks the upload as mandatory; fails if no files are provided |
| `withLabel(string)` | Sets the human-readable label used in error messages |
| `withFieldName(string)` | Sets the `$_FILES` key to read from (without brackets, e.g. `'images'` for `images[]`) |
| `withPrefix(string)` | Prepends a string to each generated unique filename (e.g. `recipe_<uniqid>.jpg`) |
| `withAllowedTypes(array)` | List of permitted MIME types applied to every file |
| `withMaxSizeMbOf(float)` | Maximum allowed file size in megabytes, enforced per file (default: `5.0`) |
| `withMaxFileOf(int)` | Maximum number of files allowed in the upload (default: `3`) |
| `withDestination(string)` | Subdirectory under `Uploads/` where the files are committed |

---

#### `UploadHandler` — Orchestrating Uploads

`UploadHandler` manages one or more `Upload` and `MultiUpload` instances together, running stage, commit, and rollback across all of them in the order they were added.

| Method | Description |
| ------ | ----------- |
| `add(Uploadable)` | Registers an `Upload` or `MultiUpload` instance to be handled |
| `stage()` | Validates and moves all uploads to the temp directory |
| `commit()` | Moves all staged uploads to their final destinations |
| `rollback()` | Deletes all staged temp files; safe to call at any point |
| `hasError()` | Returns `true` if any upload encountered an error |
| `getError()` | Returns the first error message captured, or `null` |
| `deleteIfExists(string $destination, string $filename)` | Deletes an existing file from a destination — useful for removing old files on update |
| `getFileNameByFieldName(string $fieldName, ?int $index)` | Returns the generated filename for a given field; pass an index for `MultiUpload` fields |
| `getFileNamesByFieldName(string $fieldName)` | Returns all generated filenames for a `MultiUpload` field as an array |

---

#### Retrieving Filenames

How you retrieve filenames depends on whether the field is a single or multi-file upload:

```php
// Single file (Upload) — no index needed
$avatarName = $handler->getFileNameByFieldName('avatar');

// Multiple files (MultiUpload) — retrieve by zero-based index
$firstImage  = $handler->getFileNameByFieldName('images', 0);
$secondImage = $handler->getFileNameByFieldName('images', 1);

// Multiple files (MultiUpload) — retrieve all at once
$imageNames = $handler->getFileNamesByFieldName('images');
// ['recipe_abc123.jpg', 'recipe_def456.jpg', 'recipe_ghi789.jpg']
```

---

#### Typical Controller Usage

The recommended pattern is: **stage → check → do DB work → commit → check**. This ensures files are only permanently stored after all dependent operations succeed.

**Creating a record with a single file upload:**

```php
public function store(Request $request, Response $response): void
{
    $title = Validator::requiredString('Title', $request->fromBody('title'), 3, 255);

    $handler = UploadHandler::build()
        ->add(
            Upload::build()
                ->asRequired()
                ->withLabel('Cover Image')
                ->withFieldName('cover')
                ->withAllowedTypes(['image/png', 'image/jpeg'])
                ->withMaxSizeMbOf(2.0)
                ->withDestination('posts/covers')
                ->withPrefix('cover')
        );

    $handler->stage();
    if ($handler->hasError())
        $response::badRequest(['message' => $handler->getError()]);

    $this->db->begin_transaction();

    try
    {
        $postModel = new PostModel($this->db);
        $postModel->insert([
            'title' => $title,
            'cover' => $handler->getFileNameByFieldName('cover'),
        ]);

        $handler->commit();
        if ($handler->hasError())
            $response::internalServerError(['message' => $handler->getError()]);

        $this->db->commit();
        $response::created(['message' => 'Post created successfully.']);
    }
    catch (Exception $e)
    {
        Logger::error('Post Store - ' . $e->getMessage());
        $this->db->rollback();
        $handler->rollback();
        $response::internalServerError(['message' => 'Unable to create post.']);
    }
}
```

**Updating a record and replacing an existing file:**

When a new file is uploaded on update, use `deleteIfExists()` to remove the old file after a successful commit. This should run after `$this->db->commit()` inside the try block — it's non-critical cleanup that should only happen on success.

```php
public function update(Request $request, Response $response): void
{
    $title = Validator::string('Title', $request->fromBody('title'), 3, 255);

    $handler = UploadHandler::build()
        ->add(
            Upload::build()
                ->withLabel('Cover Image')
                ->withFieldName('cover')
                ->withAllowedTypes(['image/png', 'image/jpeg'])
                ->withMaxSizeMbOf(2.0)
                ->withDestination('posts/covers')
                ->withPrefix('cover')
        );

    $handler->stage();
    if ($handler->hasError())
        $response::badRequest(['message' => $handler->getError()]);

    $post = (new PostModel($this->db))->find($request->fromParams('id'));
    if (!$post)
        $response::notFound(['message' => 'Post not found.']);

    $this->db->begin_transaction();

    try
    {
        (new PostModel($this->db))
            ->where('id', '=', $post['id'])
            ->update(array_filter([
                'title' => $title,
                'cover' => $handler->getFileNameByFieldName('cover'),
            ], fn($value) => $value !== null));

        $handler->commit();
        if ($handler->hasError())
            $response::internalServerError(['message' => $handler->getError()]);

        $this->db->commit();

        // Delete old cover only after everything succeeded
        if ($handler->getFileNameByFieldName('cover') && $post['cover'])
            $handler->deleteIfExists('posts/covers', $post['cover']);

        $response::ok(['message' => 'Post updated successfully.']);
    }
    catch (Exception $e)
    {
        Logger::error('Post Update - ' . $e->getMessage());
        $this->db->rollback();
        $handler->rollback();
        $response::internalServerError(['message' => 'Unable to update post.']);
    }
}
```

**Creating a record with multiple file uploads:**

```php
public function store(Request $request, Response $response): void
{
    $title = Validator::requiredString('Title', $request->fromBody('title'), 3, 255);

    $handler = UploadHandler::build()
        ->add(
            MultiUpload::build()
                ->asRequired()
                ->withLabel('Recipe Images')
                ->withFieldName('images')
                ->withAllowedTypes(['image/png', 'image/jpeg', 'image/webp'])
                ->withMaxSizeMbOf(2.0)
                ->withMaxFileOf(3)
                ->withDestination('recipes/images')
                ->withPrefix('recipe')
        );

    $handler->stage();
    if ($handler->hasError())
        $response::badRequest(['message' => $handler->getError()]);

    $this->db->begin_transaction();

    try
    {
        $recipeModel = new RecipeModel($this->db);
        $recipeImageModel = new RecipeImageModel($this->db);

        $recipeId = $recipeModel->insert(['title' => $title]);

        // Insert one row per uploaded image
        $imageNames = $handler->getFileNamesByFieldName('images');
        foreach ($imageNames as $imageName)
        {
            $recipeImageModel->insert([
                'recipe_id' => $recipeId,
                'filename'  => $imageName,
            ]);
        }

        $handler->commit();
        if ($handler->hasError())
            $response::internalServerError(['message' => $handler->getError()]);

        $this->db->commit();
        $response::created(['message' => 'Recipe created successfully.']);
    }
    catch (Exception $e)
    {
        Logger::error('Recipe Store - ' . $e->getMessage());
        $this->db->rollback();
        $handler->rollback();
        $response::internalServerError(['message' => 'Unable to create recipe.']);
    }
}
```

**Mixing single and multiple file uploads in one handler:**

`UploadHandler` can manage both `Upload` and `MultiUpload` instances together. If any upload fails at either phase, the handler rolls back everything automatically.

```php
$handler = UploadHandler::build()
    ->add(
        Upload::build()
            ->asRequired()
            ->withLabel('Thumbnail')
            ->withFieldName('thumbnail')
            ->withAllowedTypes(['image/png', 'image/jpeg'])
            ->withMaxSizeMbOf(1.0)
            ->withDestination('recipes/thumbnails')
            ->withPrefix('thumb')
    )
    ->add(
        MultiUpload::build()
            ->withLabel('Step Images')
            ->withFieldName('step_images')
            ->withAllowedTypes(['image/png', 'image/jpeg', 'image/webp'])
            ->withMaxSizeMbOf(2.0)
            ->withMaxFileOf(5)
            ->withDestination('recipes/steps')
            ->withPrefix('step')
    );

$handler->stage();
if ($handler->hasError())
    $response::badRequest(['message' => $handler->getError()]);

// Retrieve filenames
$thumbnailName = $handler->getFileNameByFieldName('thumbnail');
$stepImages    = $handler->getFileNamesByFieldName('step_images');
```

---

#### Upload Directory Structure

Uploads are stored under the `Uploads/` directory at the project root. Temporary files are held in `Uploads/Temp/` during staging and moved to their final subdirectory on commit.

```
Uploads/
├── Temp/                      # Staging area; files here are transient
├── avatars/                   # e.g. withDestination('avatars')
└── recipes/
    ├── images/                # e.g. withDestination('recipes/images')
    ├── thumbnails/
    └── steps/
```

Destination directories are created automatically if they do not exist.

---

### JSON Responses

Consistent, expressive response helpers out of the box.

```php
$response::ok($data);
$response::created($data);
$response::notFound(['message' => 'Not found.']);
$response::conflict(['message' => 'Already exists.']);
$response::internalServerError(['message' => 'Something went wrong.']);
```

---

## Requirements

- PHP 8.1+
- MySQL / MariaDB
- Composer

---

## License

MIT