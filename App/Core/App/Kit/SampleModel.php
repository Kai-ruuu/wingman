<?php

namespace Wingman\Models;

use Wingman\Core\Bases\QueryableModel;

class SampleModel extends QueryableModel
{
    public string $table = 'users';
    
    public function describe(): void
    {
        $this
            ->primaryKey()
            ->characters('username', 50)->unique()->required()
            ->characters('email')->unique()->required()
            ->characters('password_hash')->unique()->required()
            ->timestamps();
    }
}