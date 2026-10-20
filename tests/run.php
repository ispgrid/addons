<?php

/*
 * Tests for bin/check in its offline mode (format and immutability). Run: php tests/run.php
 */

$root = dirname(__DIR__);
$fixtures = __DIR__.'/fixtures';
$count = 0;
$failed = 0;

/**
 * Runs bin/check with the arguments and returns [exit code, stderr].
 *
 * @param  list<string>  $args
 * @return array{0: int, 1: string}
 */
function runCheck(string $root, array $args): array
{
    $command = array_merge([PHP_BINARY, $root.'/bin/check'], $args);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    return [proc_close($process), $stderr];
}

/**
 * @param  list<string>  $args
 */
function expectCheck(string $name, array $args, int $code, string $reason = ''): void
{
    global $root, $count, $failed;
    $count++;
    [$gotCode, $stderr] = runCheck($root, $args);
    if ($gotCode !== $code || ($reason !== '' && ! str_contains($stderr, $reason))) {
        $failed++;
        printf("FAIL %s: exit %d, want %d; stderr: %s\n", $name, $gotCode, $code, trim($stderr));
    }
}

expectCheck('valid', ['--offline', "$fixtures/valid.json"], 0);
expectCheck('bad name', ['--offline', "$fixtures/bad-name.json"], 1, 'name');
expectCheck('bad commit', ['--offline', "$fixtures/bad-commit.json"], 1, 'commit');
expectCheck('bad version', ['--offline', "$fixtures/bad-version.json"], 1, 'version');
expectCheck('unknown key', ['--offline', "$fixtures/unknown-key.json"], 1, 'unknown key url');
expectCheck('empty core', ['--offline', "$fixtures/empty-core.json"], 1, 'core');
expectCheck('changed commit', ['--offline', "--base=$fixtures/valid.json", "$fixtures/changed-commit.json"], 1, 'already listed');
expectCheck('added yank', ['--offline', "--base=$fixtures/valid.json", "$fixtures/added-yank.json"], 0);
expectCheck('the registry itself', ['--offline', "$root/registry.json"], 0);

printf("%d/%d passed\n", $count - $failed, $count);
exit($failed === 0 ? 0 : 1);
