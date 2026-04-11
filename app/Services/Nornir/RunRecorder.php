<?php

declare(strict_types=1);

namespace App\Services\Nornir;

use App\Data\Shared\StartRunData;
use App\Models\Run;
use App\Models\RunEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RunRecorder
{
    private const int FAILURE_SUMMARY_LIMIT = 1000;

    public function start(StartRunData $data): Run
    {
        $run = Run::query()->firstOrNew([
            'subsystem' => $data->subsystem,
            'operation' => $data->operation,
            'idempotency_key' => $data->idempotencyKey,
        ]);

        if ($run->exists && $run->status === Run::STATUS_RUNNING) {
            $run->forceFill([
                'status' => Run::STATUS_CANCELLED,
                'finished_at' => Carbon::now(),
                'failure_summary' => 'Previous attempt interrupted before completion.',
            ])->save();

            $this->appendEvent($run, 'run_interrupted', [
                'status' => Run::STATUS_CANCELLED,
                'reason' => 'stale-running-restart',
            ]);
        }

        $run->fill([
            'parent_run_id' => $data->parentRunId,
            'status' => Run::STATUS_RUNNING,
            'input_scope' => $data->inputScope,
            'started_at' => Carbon::now(),
            'finished_at' => null,
            'failure_summary' => null,
        ]);

        $run->save();

        $this->appendEvent($run, 'run_started', [
            'status' => Run::STATUS_RUNNING,
        ]);

        $run->refresh();

        return $run;
    }

    public function complete(Run $run): Run
    {
        $run->forceFill([
            'status' => Run::STATUS_SUCCEEDED,
            'finished_at' => Carbon::now(),
            'failure_summary' => null,
        ])->save();

        $this->appendEvent($run, 'run_succeeded', [
            'status' => Run::STATUS_SUCCEEDED,
        ]);

        $run->refresh();

        return $run;
    }

    public function fail(Run $run, string $failureSummary): Run
    {
        $failureSummary = $this->summarizeFailure($failureSummary);

        $run->forceFill([
            'status' => Run::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'failure_summary' => $failureSummary,
        ])->save();

        $this->appendEvent($run, 'run_failed', [
            'status' => Run::STATUS_FAILED,
            'failure_summary' => $failureSummary,
        ]);

        $run->refresh();

        return $run;
    }

    public function markPartial(Run $run, ?string $summary = null): Run
    {
        $summary = $this->summarizeFailure($summary);

        $run->forceFill([
            'status' => Run::STATUS_PARTIALLY_COMPLETED,
            'finished_at' => Carbon::now(),
            'failure_summary' => $summary,
        ])->save();

        $this->appendEvent($run, 'run_partially_completed', [
            'status' => Run::STATUS_PARTIALLY_COMPLETED,
            'failure_summary' => $summary,
        ]);

        $run->refresh();

        return $run;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function appendEvent(Run $run, string $event, array $payload = []): RunEvent
    {
        $runEvent = new RunEvent([
            'event' => $event,
            'payload' => $payload,
            'occurred_at' => Carbon::now(),
        ]);

        $run->events()->save($runEvent);

        return $runEvent;
    }

    private function summarizeFailure(?string $failureSummary): ?string
    {
        if ($failureSummary === null) {
            return null;
        }

        if (Str::length($failureSummary) <= self::FAILURE_SUMMARY_LIMIT) {
            return $failureSummary;
        }

        return Str::substr($failureSummary, 0, self::FAILURE_SUMMARY_LIMIT - 3).'...';
    }
}
