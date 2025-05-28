<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientErrorController extends Controller
{

    public function store(Request $request)
    {

      try
      {

        $data = $request->validate([
            'message'   => 'nullable|string',
            'stack'     => 'nullable|string',
            'info'      => 'nullable|string',
            'component' => 'nullable|string',
            'url'       => 'nullable|url',
            'userAgent' => 'nullable|string',
            'timestamp' => 'nullable|date',
        ]);
        Log::error('FRONTEND-ERROR', $data);

      } catch (Exception $ex) { Log::error($ex); }

      return response()->noContent();

    }
}
