<?php

namespace Wingman\Core\Bases;

use mysqli;
use Wingman\Core\App\Context;

class BaseMiddleware
{
    protected ?mysqli $db;
    protected Context $context;
    protected array $middlewares;

    public function setup(array $middlewares, Context $context, ?mysqli $db = null): void
    {
        $this->middlewares = $middlewares;
        $this->context = $context;
        $this->db = $db;
    }
    
    public function run(): Context
    {
        return $this->next();
    }

    protected function next(): Context
    {
        $nextMiddleware = $this->middlewares[0] ?? null;

        if (!$nextMiddleware)
            return $this->context;
            
        array_shift($this->middlewares);
        $middleware = new $nextMiddleware;
        $middleware->setup($this->middlewares, $this->context, $this->db);
        return $middleware->run();
    }
}