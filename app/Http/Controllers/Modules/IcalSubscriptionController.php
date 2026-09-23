<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ModuleIcalSubscriptionService;
use App\Services\PayloadService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IcalSubscriptionController extends Controller
{
  public function store(Request $request, ModuleIcalSubscriptionService $service)
  {
    $existing = SettingService::getModuleIcalSubscriptions();
    $data = $request->validate([
      'calendars' => 'present|array|list',
      'calendars.*' => 'array:id,name,url,icon',
      'calendars.*.id' => ['nullable', 'uuid', 'distinct', Rule::in(array_column($existing, 'id'))],
      'calendars.*.name' => 'required|string|max:100',
      'calendars.*.url' => ['required', 'string', 'max:2048', 'url:http,https,webcal'],
      'calendars.*.icon' => ['sometimes', Rule::in(ModuleIcalSubscriptionService::ICONS)],
    ]);
    $calendars = array_map(function ($calendar) {
      $calendar['id'] = $calendar['id'] ?? (string) Str::uuid();
      $calendar['icon'] = $calendar['icon'] ?? ModuleIcalSubscriptionService::DEFAULT_ICON;
      $calendar['url'] = preg_replace('/^webcal:/i', 'https:', $calendar['url']);
      return $calendar;
    }, $data['calendars']);
    $service->checkLinks($calendars);

    SettingService::updateModuleIcalSubscriptions(function ($previous) use ($calendars) {
      $old = collect($previous)->keyBy('id');
      $ids = array_column($calendars, 'id');
      foreach ($previous as $calendar) {
        if (!in_array($calendar['id'], $ids, true)) {
          Event::where('autotag', ModuleIcalSubscriptionService::tag($calendar['id']))->delete();
        }
      }
      foreach ($calendars as &$calendar) {
        $prior = $old->get($calendar['id']);
        foreach (['last_attempt', 'last_success', 'last_error'] as $field) {
          $calendar[$field] = $prior && $prior['url'] === $calendar['url'] ? ($prior[$field] ?? null) : null;
        }
        foreach (Event::where('autotag', ModuleIcalSubscriptionService::tag($calendar['id']))->get() as $event) {
          $payload = $event->payload;
          $payload['vehicle'] = $calendar['name'];
          $payload['icon'] = $calendar['icon'];
          PayloadService::normalize($payload);
          $event->update(['payload' => $payload]);
        }
      }
      return $calendars;
    });

    return redirect('/admin');
  }
}
