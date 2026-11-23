<?php

namespace App\Models;

use App\Entities\User;
use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

/** Shield's user model, returning App\Entities\User (adds the module check to can()). */
class UserModel extends ShieldUserModel
{
    protected $returnType = User::class;
}
