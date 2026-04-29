<?php

namespace Wingman\Controllers;

use Wingman\Core\App\Request;
use Wingman\Core\App\Response;
use Wingman\Core\App\Validator;
use Wingman\Core\Bases\BaseController;

class SampleController extends BaseController
{   
    public function show(Request $request, Response $response): void
    {
        // accessing database connection
        $db = $this->db;
        
        // acessing a route parameter value
        $userId = $request->fromParams('id');
        
        // accessing a url query value
        $userName = $request->fromQuery('name');

        // accessing a request body value
        $age = $request->fromBody('age');

        // validating inputs
        // NOTE: for fields with optional values, use Validator::string, Validator::int, Validator::float, etc.
        $vUserId   = Validator::requiredInt('id', $userId, 1);
        $vUserName = Validator::requiredString('name', $userName, 2, 50);
        $vUserAge  = Validator::requiredString('age', $age, 18, 120);


        // acessing a context value
        $contextValue = $request->fromContext('context_value_key');

        // sending a json response
        $response::ok(['message' => "You are looking for {$userName}, {$age} years old, with a user id of {$userId}."]);
    }


    public function store(Request $request, Response $response): void
    {
        $response::ok(['message' => 'User has been created.']);
    }


    public function update(Request $request, Response $response): void
    {
        $response::ok(['message' => 'User has been updated.']);
    }


    public function destroy(Request $request, Response $response): void
    {
        $response::ok(['message' => 'User has been deleted.']);
    }
}