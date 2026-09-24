<?php

namespace Tests\Feature;

use App\Models\Info;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayloadIconsTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00', 'Europe/Berlin'));
    $this->withSession(['authenticated' => true]);
  }

  protected function tearDown(): void
  {
    Carbon::setTestNow();
    parent::tearDown();
  }

  private function infoRequest(array $payload, ?int $id = null): array
  {
    return [
      'id' => $id, 'payload' => $payload,
      'is_permanent' => true, 'is_allday' => true,
    ];
  }

  public static function icons(): array
  {
    return array_map(fn ($icon) => [$icon], [
      'mdi-information', 'mdi-alert', 'mdi-wrench',
      'mdi-broom', 'mdi-boom-gate', 'mdi-lightning-bolt',
    ]);
  }

  #[DataProvider('icons')]
  public function test_notice_icons_survive_creation_editing_and_monitor_delivery(string $icon): void
  {
    $this->postJson('/set-info', $this->infoRequest([
      'title' => 'Notice', 'icon' => $icon,
    ]))->assertSuccessful();
    $info = Info::sole();
    $this->assertSame($icon, $info->payload['icon']);
    $this->getJson('/monitor-poll')->assertOk()->assertJsonPath('infos.0.icon', $icon);

    // Change the saved choice, then restore it while editing the title.
    $replacement = $icon === 'mdi-information' ? 'mdi-alert' : 'mdi-information';
    $this->postJson('/set-info', $this->infoRequest([
      'title' => 'Notice', 'icon' => $replacement,
    ], $info->id))->assertSuccessful();
    $this->assertSame($replacement, $info->fresh()->payload['icon']);
    $this->postJson('/set-info', $this->infoRequest([
      'title' => 'Edited notice', 'icon' => $icon,
    ], $info->id))->assertSuccessful();
    $this->assertDatabaseCount('infos', 1);
    $this->assertSame($icon, $info->fresh()->payload['icon']);
    $this->getJson('/monitor-poll')->assertOk()
      ->assertJsonPath('infos.0.title', 'Edited notice')
      ->assertJsonPath('infos.0.icon', $icon);
  }

  public function test_notices_allow_missing_and_null_icons(): void
  {
    $this->postJson('/set-info', $this->infoRequest(['title' => 'Legacy']))->assertSuccessful();
    $info = Info::sole();
    $this->assertArrayNotHasKey('icon', $info->payload);
    $this->postJson('/set-info', $this->infoRequest([
      'title' => 'Legacy', 'icon' => null,
    ], $info->id))->assertSuccessful();
    $this->assertArrayNotHasKey('icon', $info->fresh()->payload);
    $this->getJson('/monitor-poll')->assertOk()->assertJsonPath('infos.0.type', 'info');
  }

  public static function invalidIcons(): array
  {
    return [['mdi-unknown'], ['mdi-ambulance'], ['mdi-trash-can'], [42], [['mdi-alert']]];
  }

  #[DataProvider('invalidIcons')]
  public function test_notices_reject_unsupported_icons(mixed $icon): void
  {
    $this->postJson('/set-info', $this->infoRequest([
      'title' => 'Invalid', 'icon' => $icon,
    ]))->assertUnprocessable()->assertJsonValidationErrors('payload.icon');
    $this->assertDatabaseCount('infos', 0);
  }

  public function test_monitor_supplies_trash_icons_for_existing_tasks_only(): void
  {
    foreach (['trash', null, 'other-module'] as $tag) {
      Task::create([
        'payload' => ['title' => $tag ?? 'Manual', 'meta' => 'Abfallkalender'],
        'autotag' => $tag, 'dueto' => '2026-09-23 18:00:00',
      ]);
    }
    $response = $this->getJson('/monitor-poll')->assertOk()->assertJsonCount(3, 'tasks');
    $tasks = collect($response->json('tasks'))->keyBy('title');
    $this->assertSame('mdi-trash-can', $tasks['trash']['icon']);
    $this->assertArrayNotHasKey('icon', $tasks['Manual']);
    $this->assertArrayNotHasKey('icon', $tasks['other-module']);
    $this->assertArrayNotHasKey('icon', Task::where('autotag', 'trash')->sole()->payload);
  }
}
