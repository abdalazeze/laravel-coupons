<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Tests\Models;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
