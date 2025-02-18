<?php

namespace App\Services;

class PayloadService
{

  /**
   * Normalize Payload data.
   *
   * @param array $payload
   * @return array
   */
  public static function normalize(array &$payload)
  {

    $normalized = [];

    $vehicle = $payload['vehicle'] ?? null;
    $title = $payload['title'];

    $ab = isset($payload['vehicle']) ? "$vehicle-$title" : "XXX-$title";
    $normalized = [
      'ab' => $ab,
      'title' => $title,
    ];

    if (isset($payload['vehicle'])) { $normalized['vehicle'] = $vehicle; }
    if (isset($payload['meta'])) { $normalized['meta'] = $payload['meta']; }
    if (isset($payload['description'])) { $normalized['description'] = $payload['description']; }

    $payload = $normalized;

  }

}
