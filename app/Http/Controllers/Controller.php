<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Routes using this are behind the auth middleware, so a missing user is a configuration error.
     */
    protected function authenticatedUser(Request $request): User
    {
        return $request->user() ?? throw new AuthenticationException;
    }
}
