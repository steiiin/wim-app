<?php

namespace App\Http\Controllers\Entries;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Rules\ValidPayload;
use App\Services\PayloadService;
use Illuminate\Http\Request;

class TaskController extends Controller
{

    /**
     * Store a new Task entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'id'   => 'present|nullable|exists:tasks,id',
            'payload' => [ 'required', 'array', new ValidPayload() ],
            'dueto' => 'required|date',
        ]);

        PayloadService::normalize($validated['payload']);

        if (isset($validated['id']))
        {
            // Update existing Task model
            $event = Task::findOrFail($validated['id']);
            $event->update($validated);
        }
        else
        {
            // Create a new Task record
            unset($validated['id']);
            Task::create($validated);
        }

    }

     /**
     * Remove the specified Task entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $event = Task::findOrFail($id);
        $event->delete();
    }

}
