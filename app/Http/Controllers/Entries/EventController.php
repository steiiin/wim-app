<?php

namespace App\Http\Controllers\Entries;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Rules\ValidPayload;
use App\Services\PayloadService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{

    /**
     * Store a new Event entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'id'   => 'present|nullable|exists:events,id',
            'payload' => [ 'required', 'array', new ValidPayload() ],
            'start' => 'required|date',
            'until' => 'present|nullable|date',
            'is_allday'    => 'required|boolean',
        ]);

        PayloadService::normalize($validated['payload']);

        if (isset($validated['id']))
        {
            // Update existing Event model
            $event = Event::findOrFail($validated['id']);
            $event->update($validated);
        }
        else
        {
            // Create a new Event record
            unset($validated['id']);
            Event::create($validated);
        }

    }

     /**
     * Remove the specified Event entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();
    }

}
