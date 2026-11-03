<?php

namespace Tests\Feature;

use App\Services\Backup\GoogleDriveConnection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupGoogleConnectionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = base_path('../.runtime/backup-tests/'.Str::uuid());
        config(['backup.root' => $this->root, 'backup.google.client_id' => 'client', 'backup.google.client_secret' => 'secret', 'backup.no_billing_confirmed' => true]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_authorization_only_requests_file_scope_and_offline_access(): void
    {
        $this->withSession(['google_backup_admin' => 12]);
        $url = app(GoogleDriveConnection::class)->authorizationUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('https://www.googleapis.com/auth/drive.file', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertNotEmpty($query['state']);
    }

    public function test_callback_rejects_invalid_state_before_network(): void
    {
        $this->expectException(\RuntimeException::class);
        app(GoogleDriveConnection::class)->callback('code', 'fake');
        Http::assertNothingSent();
    }

    public function test_token_is_encrypted_and_refresh_preserves_old_refresh_token(): void
    {
        $this->withSession(['google_backup_admin' => 12]);
        $connection = app(GoogleDriveConnection::class);
        parse_str(parse_url($connection->authorizationUrl(), PHP_URL_QUERY), $query);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()->push(['access_token' => 'first', 'refresh_token' => 'keep-secret', 'expires_in' => 1, 'scope' => 'https://www.googleapis.com/auth/drive.file'])
                ->push(['access_token' => 'second', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['permissionId' => 'owner-1', 'emailAddress' => 'owner@example.test']]),
        ]);
        $connection->callback('code', $query['state']);
        $this->assertStringNotContainsString('keep-secret', file_get_contents($this->root.'/google.enc'));
        $this->travel(2)->minutes();
        $this->assertSame('second', $connection->accessToken());
        $this->assertSame('owner@example.test', $connection->status()['email']);
    }

    public function test_billing_confirmation_is_required_before_google_authorization(): void
    {
        config(['backup.no_billing_confirmed' => false]);
        $connection = app(GoogleDriveConnection::class);
        $this->assertFalse($connection->configured());
        $this->expectException(\RuntimeException::class);
        $connection->authorizationUrl();
    }

    public function test_revoked_refresh_token_changes_status_to_reconnect(): void
    {
        $this->withSession(['google_backup_admin' => 12]);
        $connection = app(GoogleDriveConnection::class);
        parse_str(parse_url($connection->authorizationUrl(), PHP_URL_QUERY), $query);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()->push(['access_token' => 'first', 'refresh_token' => 'refresh', 'expires_in' => 1, 'scope' => 'https://www.googleapis.com/auth/drive.file'])->push(['error' => 'invalid_grant'], 400),
            'www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['permissionId' => 'owner', 'emailAddress' => 'owner@example.test']]),
        ]);
        try {
            $connection->callback('code', $query['state']);
            $this->travel(2)->minutes();
            try {
                $connection->accessToken();
                $this->fail('Revoked grant must fail.');
            } catch (\DomainException $exception) {
                $this->assertFalse($connection->status()['connected']);
            }
        } finally {
            $this->travelBack();
        }
    }
}
