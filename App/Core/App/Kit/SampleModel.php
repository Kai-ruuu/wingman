<?php

namespace Wingman\Models;

use Wingman\Core\Bases\QueryableModel;

class SampleModel extends QueryableModel
{
    public string $table = 'samples';

    public function describe(): void
    {
        $this
            ->primaryKey()
            ->timestamps();
    }
}
