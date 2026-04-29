<?php

namespace Wingman\Routers;

use Wingman\Controllers\SampleController;
use Wingman\Core\Bases\BaseRouter;
use Wingman\Middlewares\SampleMiddleware;

class SampleRouter extends BaseRouter
{
    public function describe(): void
    {
        $this->setPrefix('/api/sample'); // defaults to an empty prefix '' if omitted
        
        $this->get('/', SampleController::class, 'show')
            ->withMiddlewares([
                SampleMiddleware::class
            ]);

        $this->get('/{id}', SampleController::class, 'showById');
    }
}