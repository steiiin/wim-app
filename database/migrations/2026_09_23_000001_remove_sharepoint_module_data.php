<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::transaction(function () {
      DB::table('settings')->whereIn('key', [
        'module_sharepoint_user',
        'module_sharepoint_pass',
        'module_sharepoint_secret',
        'module_sharepoint_link',
        'module_sharepoint_fetched',
      ])->delete();

      DB::table('events')->where('autotag', 'sharepoint')->delete();
    });
  }

  public function down(): void
  {
    // Deleted credentials and imported events cannot be reconstructed.
  }
};
