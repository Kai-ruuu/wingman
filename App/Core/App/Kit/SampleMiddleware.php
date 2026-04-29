<?php

namespace Wingman\Middlewares;

use Wingman\Core\App\Context;
use Wingman\Core\Bases\BaseMiddleware;

class SampleMiddleware extends BaseMiddleware
{
    public function run(): Context
    {
        // accessing database connection
        $db = $this->db;
        
        // add a new data to the current context
        $this->context->add('new_context_data', 'new_data');

        // get data from the current context using its key
        $contextData = $this->context->get('new_context_data');

        return $this->next();
    }
}