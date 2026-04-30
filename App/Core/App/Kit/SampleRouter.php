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

        $this->get('/{id}', SampleController::class, 'showById')
            // Apply a limit of 60 requests per minute to this specific route
            ->withLimit(60, 60);

        /**
         * Apply a limit of 60 requests per minute to all routes defined above
         * 
         * NOTE: Do not use this if you have already applied rate limiting to any
         * specific routes above, as it will overwrite those individual limits.
         */
        $this->withLimitToAll(60, 60);
    }
}