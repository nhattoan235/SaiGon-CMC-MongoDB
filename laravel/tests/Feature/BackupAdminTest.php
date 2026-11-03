<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\ResetsMongoDatabase;
use Tests\TestCase;

class BackupAdminTest extends TestCase
{
    use ResetsMongoDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $this->root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_guest_and_non_admin_cannot_use_backup_actions(): void
    {
        $id = (string) Str::uuid();
        $this->get('/staff/backups')->assertRedirect('/login');
        foreach (['customer', 'sales', 'warehouse', 'accounting', 'manager', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/staff/backups')->assertForbidden();
            $this->post('/staff/backups')->assertForbidden();
            $this->post('/staff/backups/'.$id.'/retry')->assertForbidden();
            $this->get('/staff/backups/'.$id.'/download')->assertForbidden();
            $this->post('/staff/backups/settings')->assertForbidden();
            $this->post('/staff/backups/google/connect')->assertForbidden();
            $this->get('/staff/backups/google/callback')->assertForbidden();
            $this->post('/staff/backups/google/disconnect')->assertForbidden();
        }
    }

    public function test_admin_sees_unconfigured_state_and_cannot_enable_unready_schedule(): void
    {
        config(['backup.google.client_id' => '', 'backup.google.client_secret' => '']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/staff/backups')->assertOk()->assertSee('Sao lưu dữ liệu')->assertSee('Chưa cấu hình')->assertDontSee('refresh_token');
        $this->post('/staff/backups/settings', ['time' => '20:00', 'enabled' => '1'])->assertSessionHasErrors();
        $this->post('/staff/backups/settings', ['time' => '99:70'])->assertSessionHasErrors('time');
        $this->post('/staff/backups')->assertSessionHasErrors();
    }

    public function test_locked_admin_is_rejected_and_arbitrary_run_path_is_not_downloaded(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'locked']));
        $this->get('/staff/backups')->assertForbidden();
        $this->post('/staff/backups')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->get('/staff/backups/not-a-uuid/download')->assertNotFound();
    }
}
