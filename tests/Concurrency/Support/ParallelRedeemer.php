<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Tests\Concurrency\Support;

use RuntimeException;

/**
 * Runs redemption attempts in parallel, one OS process (and DB connection) per attempt.
 *
 * Every worker boots and connects first, then waits at a barrier. Only when all of
 * them are ready does the parent release them together; otherwise process start-up
 * jitter would spread the attempts out and the race would never actually happen.
 */
final class ParallelRedeemer
{
    /**
     * @param  list<array{code: string, order: int, user: int}>  $attempts
     * @return list<string> one outcome per attempt, see worker.php
     */
    public static function run(array $attempts): array
    {
        $workers = [];

        foreach ($attempts as $attempt) {
            $stderr = (string) tempnam(sys_get_temp_dir(), 'coupon-worker-');
            $process = proc_open(
                [PHP_BINARY, __DIR__.'/worker.php', $attempt['code'], (string) $attempt['order'], (string) $attempt['user']],
                // stderr goes to a file: an unread stderr pipe can fill up and block the worker.
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderr, 'w']],
                $pipes,
            );

            if (! is_resource($process)) {
                throw new RuntimeException('Could not start a redemption worker.');
            }

            $workers[] = ['process' => $process, 'stdin' => $pipes[0], 'stdout' => $pipes[1], 'stderr' => $stderr];
        }

        foreach ($workers as $worker) {
            $line = trim((string) fgets($worker['stdout']));

            if ($line !== 'ready') {
                throw new RuntimeException("Worker failed to start: {$line} ".file_get_contents($worker['stderr']));
            }
        }

        foreach ($workers as $worker) {
            fwrite($worker['stdin'], "go\n");
            fflush($worker['stdin']);
        }

        $outcomes = [];

        foreach ($workers as $worker) {
            $outcomes[] = trim((string) stream_get_contents($worker['stdout']));
            fclose($worker['stdin']);
            fclose($worker['stdout']);
            proc_close($worker['process']);
            @unlink($worker['stderr']);
        }

        return $outcomes;
    }
}
