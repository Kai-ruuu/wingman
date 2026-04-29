<?php

namespace Wingman\Core\Bases;

use mysqli;

class BaseController
{
    protected ?mysqli $db = null;
    
    public function __construct(?mysqli $db = null)
    {
        $this->db = $db;
    }
}