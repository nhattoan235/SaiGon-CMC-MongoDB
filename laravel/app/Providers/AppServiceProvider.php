<?php

namespace App\Providers;

use App\Jobs\CreateBackupJob;
use App\Services\Backup\BackupWriteGate;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BackupWriteGate::class);
    }

    public function boot(): void
    {
        $readOnly = ['list', 'help', 'about', 'route:list', 'config:show', 'db:show', 'db:table', 'schedule:list',
            'schedule:run', 'schedule:work', 'queue:work', 'queue:listen', 'serve', 'dev', 'tinker', 'test',
            'cmms:backup-create', 'cmms:backup-verify', 'cmms:backup-restore', 'cmms:backup-tick', 'cmms:backup-check', 'cmms:backup-setup'];
        $heldCommands = [];
        Event::listen(CommandStarting::class, function (CommandStarting $event) use ($readOnly, &$heldCommands): void {
            $hold = $event->command !== null && ! in_array($event->command, $readOnly, true);
            if ($hold) {
                app(BackupWriteGate::class)->acquireShared();
            }
            $heldCommands[] = $hold;
        });
        Event::listen(CommandFinished::class, function () use (&$heldCommands): void {
            if (array_pop($heldCommands)) {
                app(BackupWriteGate::class)->releaseShared();
            }
        });
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            if ($event->job->resolveName() !== CreateBackupJob::class) {
                app(BackupWriteGate::class)->acquireShared();
            }
        });
        foreach ([JobProcessed::class, JobExceptionOccurred::class] as $eventClass) {
            Event::listen($eventClass, function ($event): void {
                if ($event->job->resolveName() !== CreateBackupJob::class) {
                    app(BackupWriteGate::class)->releaseShared();
                }
            });
        }
    }
}
