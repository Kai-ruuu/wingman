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
| CORS Configuration            | 🔧 Coming soon |
| Date Input Validation         | 🔧 Coming soon |
| Built-in Auth Handling (JWT)  | 🔧 Coming soon |
| Rate Limiting                 | 🔧 Coming soon |
| Logging                       | 🔧 Coming soon |

---

## Supported Databases
### SQL:
- MySQL
### NoSQL:
- None

## Core Concepts

### Wing CLI
Wingman ships with a command-line tool called `wing` for scaffolding and managing your application.

```bash
php wing make-model --name=Post
php wing make-controller --name=Post
php wing make-router --name=Post
php wing make-middleware --name=Auth
php wing build-models
php wing demolish-models
php wing serve
```

### Models & Schema Builder
Define your database schema directly in your model using a fluent, chainable API — no raw SQL required.

```php
class PostModel extends QueryableModel
{
    protected string $table = 'posts';

    public function schema(): void
    {
        $this->primaryKey()
             ->integer('user_id')->required()
             ->characters('title', 255)->required()
             ->text('body')->optional()
             ->boolean('active', true)
             ->foreignKey('user_id', 'users.id', 'cascade');
    }
}
```

### Query Builder
A safe, fluent query builder with full support for filtering, joining, grouping, ordering, and pagination. All values are parameterized — SQL injection safe by default.

📄 [View the Full Query Builder & QueryableModel Documentation](./Docs/query-builder-and-queryable-model.pdf)

Either placement works — the first keeps it contextually close to the code example, the second makes it more discoverable at a glance. Your call on where it fits best.

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

## Installation

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

## Requirements

- PHP 8.1+
- MySQL / MariaDB
- Composer

---

## License

MIT