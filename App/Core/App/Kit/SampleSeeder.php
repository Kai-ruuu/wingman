<?php

namespace Wingman\Seeders;

use Wingman\Core\Bases\BaseSeeder;
use Wingman\Models\SampleModel;

class SampleSeeder extends BaseSeeder
{
    public function describe(): void
    {
        $this->setup(SampleModel::class, [
            /**
             * [
             *      'username' => Env::get('ADMIN_NAME'),
             *      'email'    => Env::get('ADMIN_EMAIL'),
             *      'password' => Env::get('ADMIN_PASS'),
             * ]
             */
        ]);
    }
}