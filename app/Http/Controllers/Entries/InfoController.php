<?php

namespace App\Http\Controllers\Entries;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Rules\ValidPayload;
use App\Services\PayloadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InfoController extends Controller
{

    /**
     * Store a new Info entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'id'   => 'present|nullable|exists:infos,id',
            'payload' => [ 'required', 'array', new ValidPayload() ],
            'is_permanent' => 'required|boolean',
            'from'         => 'exclude_if:is_permanent,true|required_if:is_permanent,false|date',
            'until'        => 'exclude_if:is_permanent,true|required_if:is_permanent,false|date',
            'is_allday'    => 'required|accepted',
        ]);

        // Validate the nested icon separately so Laravel retains the full payload array.
        $request->validate([
            'payload.icon' => ['nullable', 'string', Rule::in([
                'mdi-information', 'mdi-alert', 'mdi-wrench',
                'mdi-broom', 'mdi-boom-gate', 'mdi-lightning-bolt',
            ])],
        ]);

        PayloadService::normalize($validated['payload']);

        if (isset($validated['id']))
        {
            // Update existing Info model
            $info = Info::findOrFail($validated['id']);
            $info->update($validated);
        }
        else
        {
            // Create a new Info record
            unset($validated['id']);
            Info::create($validated);
        }

    }

     /**
     * Remove the specified Info entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $info = Info::findOrFail($id);
        $info->delete();
    }

}
