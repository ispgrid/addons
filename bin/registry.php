<?php

/*
 * Shared by bin/check and bin/add-version: reading registry.json and what GitHub says about an add-on.
 */

const NAME_PATTERN = '/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/';
const VERSION_PATTERN = '/^v[0-9]+\.[0-9]+\.[0-9]+(-rc[0-9]+)?$/';
const COMMIT_PATTERN = '/^[0-9a-f]{40}$/';
const SERIES_PATTERN = '/^[0-9]+\.[0-9]+$/';
const LICENSES = ['MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'ISC'];
const ADDON_KEYS = ['description', 'author', 'official', 'versions'];
const VERSION_KEYS = ['commit', 'core', 'yanked'];

/**
 * The decoded registry, or null when the file is missing or not JSON.
 *
 * @return array<string, mixed>|null
 */
function loadRegistry(string $file): ?array
{
    $json = @file_get_contents($file);
    if ($json === false) {
        return null;
    }
    $registry = json_decode($json, true);

    return is_array($registry) ? $registry : null;
}

/**
 * A one-line text of at most 200 characters without control characters.
 */
function isPlainText(mixed $text): bool
{
    return is_string($text) && $text !== '' && mb_strlen($text) <= 200 && ! preg_match('/[\x00-\x1f\x7f]/u', $text);
}

/**
 * What is wrong with the registry's format, one "<name> <version>: <reason>" per problem.
 *
 * @param  array<string, mixed>  $registry
 * @return list<string>
 */
function formatErrors(array $registry): array
{
    $errors = [];
    foreach (array_keys($registry) as $key) {
        if (! in_array($key, ['schema', 'addons'], true)) {
            $errors[] = "registry: unknown key $key";
        }
    }
    if (($registry['schema'] ?? null) !== 1) {
        $errors[] = 'registry: schema must be 1';
    }
    if (! is_array($registry['addons'] ?? null)) {
        return [...$errors, 'registry: addons must be an object'];
    }
    foreach ($registry['addons'] as $name => $addon) {
        if (! preg_match(NAME_PATTERN, (string) $name)) {
            $errors[] = "$name: the name must be vendor/package in lowercase letters, digits, _, . or -";

            continue;
        }
        if (! is_array($addon)) {
            $errors[] = "$name: must be an object";

            continue;
        }
        foreach (array_keys($addon) as $key) {
            if (! in_array($key, ADDON_KEYS, true)) {
                $errors[] = "$name: unknown key $key";
            }
        }
        if (! isPlainText($addon['description'] ?? null)) {
            $errors[] = "$name: description must be one line of at most 200 characters";
        }
        if (! isPlainText($addon['author'] ?? null)) {
            $errors[] = "$name: author must be one line of at most 200 characters";
        }
        if (! is_bool($addon['official'] ?? null)) {
            $errors[] = "$name: official must be true or false";
        }
        if (! is_array($addon['versions'] ?? null) || $addon['versions'] === []) {
            $errors[] = "$name: versions must list at least one version";

            continue;
        }
        foreach ($addon['versions'] as $version => $entry) {
            if (! preg_match(VERSION_PATTERN, (string) $version)) {
                $errors[] = "$name $version: the version must be a release tag like v1.2.3 or v1.2.3-rc1";

                continue;
            }
            if (! is_array($entry)) {
                $errors[] = "$name $version: must be an object";

                continue;
            }
            foreach (array_keys($entry) as $key) {
                if (! in_array($key, VERSION_KEYS, true)) {
                    $errors[] = "$name $version: unknown key $key";
                }
            }
            if (! is_string($entry['commit'] ?? null) || ! preg_match(COMMIT_PATTERN, $entry['commit'])) {
                $errors[] = "$name $version: commit must be the full 40-character commit SHA";
            }
            $core = $entry['core'] ?? null;
            if (! is_array($core) || $core === [] || ! array_is_list($core)
                || array_filter($core, fn ($series): bool => ! is_string($series) || ! preg_match(SERIES_PATTERN, $series)) !== []) {
                $errors[] = "$name $version: core must list the core series it supports, like [\"0.1\"]";
            }
            if (array_key_exists('yanked', $entry) && ! isPlainText($entry['yanked'])) {
                $errors[] = "$name $version: yanked must be the reason, one line of at most 200 characters";
            }
        }
    }

    return $errors;
}

/**
 * Versions already listed in the base registry that were changed or removed.
 *
 * @param  array<string, mixed>  $registry
 * @param  array<string, mixed>  $base
 * @return list<string>
 */
function immutabilityErrors(array $registry, array $base): array
{
    $errors = [];
    foreach ($base['addons'] ?? [] as $name => $addon) {
        foreach ($addon['versions'] ?? [] as $version => $entry) {
            $now = $registry['addons'][$name]['versions'][$version] ?? null;
            if ($now === null) {
                $errors[] = "$name $version: already listed, so it can't be removed (mark it yanked instead)";
            } elseif (($now['commit'] ?? null) !== ($entry['commit'] ?? null) || ($now['core'] ?? null) !== ($entry['core'] ?? null)) {
                $errors[] = "$name $version: already listed, so its commit and core can't change (list a new version instead)";
            }
        }
    }

    return $errors;
}

/**
 * The commit a tag of github.com/<name> points to (for an annotated tag, its commit), or null.
 */
function tagCommit(string $name, string $tag): ?string
{
    $process = proc_open(
        ['git', 'ls-remote', "https://github.com/$name", "refs/tags/$tag", "refs/tags/$tag^{}"],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        ['GIT_TERMINAL_PROMPT' => '0'] + getenv(),
    );
    $output = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    proc_close($process);
    $commit = null;
    foreach (preg_split('/\R/', trim($output)) as $line) {
        if (preg_match('/^([0-9a-f]{40})\s+refs\/tags\/(.+)$/', $line, $match)) {
            if ($match[2] === "$tag^{}" || ($match[2] === $tag && $commit === null)) {
                $commit = $match[1];
            }
        }
    }

    return $commit;
}

/**
 * The add-on's composer.json at a commit, or null.
 *
 * @return array<string, mixed>|null
 */
function composerAt(string $name, string $commit): ?array
{
    $json = @file_get_contents("https://raw.githubusercontent.com/$name/$commit/composer.json");
    $composer = $json === false ? null : json_decode($json, true);

    return is_array($composer) ? $composer : null;
}

/**
 * What GitHub shows that does not match the entry (the registry's format is already valid).
 *
 * @param  array<string, mixed>  $entry
 * @return list<string>
 */
function onlineErrors(string $name, string $version, array $entry): array
{
    $commit = tagCommit($name, $version);
    if ($commit === null) {
        return ["$name $version: github.com/$name has no tag $version"];
    }
    if ($commit !== $entry['commit']) {
        return ["$name $version: the tag points at $commit, not at the listed commit"];
    }
    $composer = composerAt($name, $commit);
    if ($composer === null) {
        return ["$name $version: composer.json could not be read at that commit"];
    }
    $errors = [];
    if (($composer['name'] ?? null) !== $name) {
        $errors[] = "$name $version: composer.json names the package ".json_encode($composer['name'] ?? null);
    }
    $licenses = (array) ($composer['license'] ?? []);
    if ($licenses === [] || array_diff($licenses, LICENSES) !== []) {
        $errors[] = "$name $version: the license must be one of ".implode(', ', LICENSES);
    }
    if (array_keys($composer['require'] ?? []) !== ['php']) {
        $errors[] = "$name $version: require may list only php (an add-on can't bring Composer packages)";
    }
    if (isset($composer['extra']['laravel'])) {
        $errors[] = "$name $version: extra.laravel is not allowed (list providers in extra.ispgrid)";
    }
    if (($composer['extra']['ispgrid']['core'] ?? null) !== $entry['core']) {
        $errors[] = "$name $version: core must equal extra.ispgrid.core in composer.json";
    }

    return $errors;
}
