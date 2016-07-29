<?php

namespace Codedge\Updater\Controller;

use App\Http\Controllers\Controller;

/**
 * Class SelfUpdateController.
 */
class SelfUpdateController extends Controller
{
    public function index()
    {
        return view('self-update');
    }
}
