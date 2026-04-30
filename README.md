# Wingman

**Wingman** is a lightweight PHP REST API framework built for developers who want full control without the overhead of a full-stack framework. It gives you the essential building blocks — routing, controllers, models, middleware, and a fluent query builder — in a clean, minimal package you can actually understand end to end.

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
| Input Validation              | ✅ Supported (Partially) |
| Logging                       | ✅ Supported |
| CORS Configuration            | 🔧 Coming soon |
| Rate Limiting                 | 🔧 Coming soon |
| Date Input Validation         | 🔧 Coming soon |
| Built-in Uploads Handling     | 🔧 Coming soon |
| Built-in Auth Handling (JWT)  | 🔧 Coming soon |

---

## Supported Databases

### SQL:
- MySQL

### NoSQL:
- None

---

## Installation

### Using wing.exe (Recommended)

`wing.exe` is a standalone CLI tool for bootstrapping and managing Wingman projects outside of PHP. It handles project scaffolding, production builds, and directory cleanup.

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

| Command       | Description |
| ------------- | ----------- |
| `wing new`    | Scaffolds a new Wingman project into the current (empty) directory |
| `wing build`  | Bundles the project into `Builds/build-<timestamp>/`, excluding files listed in `.wingignore` |
| `wing clean`  | Wipes the current directory so it can be reused as a fresh Wingman project |

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

Wipes all files and folders in the current directory (except `wing.exe` itself), resetting it so you can run `wing new` again.

You'll be prompted to confirm before anything is deleted:

```
Delete all files in the current directory? [yes]
```

Enter anything other than `n` or `no` to proceed.

---

### Manual Installation

```bash
git clone https://github.com/your-username/wingman-project.git
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

📄 [View the Full Query Builder & QueryableModel Documentation](./Docs/query-builder-and-queryable-model.pdf)

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
            Response::bad['message' => 'Invalid authentication token.'];

        // pass authenticated user downstream via context
        $this->context->add('auth_user', $user);

        return $this->next();
    }
}
```

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