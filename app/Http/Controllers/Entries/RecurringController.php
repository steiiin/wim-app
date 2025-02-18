<?php

namespace App\Http\Controllers\Entries;

use App\Http\Controllers\Controller;
use App\Models\Recurring;
use App\Rules\ValidPayload;
use App\Services\PayloadService;
use Illuminate\Http\Request;

class RecurringController extends Controller
{

    /**
     * Store a new Recurring entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'id'   => 'present|nullable|exists:recurrings,id',
            'payload' => [ 'required', 'array', new ValidPayload() ],
            'from' => 'required|date',
            'dueto' => 'required|date',
            'recurrence_type' => 'required|in:daily,weekly,monthly-day,monthly-weekday',
            'weekday' => 'present|nullable|integer|between:0,6',
            'nth_day' => 'present|nullable|integer|between:0,31',
        ]);

        PayloadService::normalize($validated['payload']);

        if (isset($validated['id']))
        {
            // Update existing Recurring model
            $event = Recurring::findOrFail($validated['id']);
            $event->update($validated);
        }
        else
        {
            // Create a new Recurring record
            unset($validated['id']);
            Recurring::create($validated);
        }

    }

     /**
     * Remove the specified Recurring entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $event = Recurring::findOrFail($id);
        $event->delete();
    }

}
