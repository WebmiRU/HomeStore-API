<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class CodeController extends Controller
{
    public function index()
    {
        return Uuid::uuid7()->toString();
    }


}
