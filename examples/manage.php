<?php

declare(strict_types=1);

/**
 * @return list<array{name:string, grammar:string, output:string, run:string}>
 */
function phison_examples(): array
{
    return [
        [
            'name' => 'arithmetic',
            'grammar' => 'examples/arithmetic/arithmetic.y',
            'output' => 'examples/arithmetic/Generated/ArithmeticParser.php',
            'run' => 'examples/arithmetic/run.php',
        ],
        [
            'name' => 'json',
            'grammar' => 'examples/json/json.y',
            'output' => 'examples/json/Generated/JsonParser.php',
            'run' => 'examples/json/run.php',
        ],
        [
            'name' => 'csv',
            'grammar' => 'examples/csv/csv.y',
            'output' => 'examples/csv/Generated/CsvParser.php',
            'run' => 'examples/csv/run.php',
        ],
        [
            'name' => 'filter',
            'grammar' => 'examples/filter/filter.y',
            'output' => 'examples/filter/Generated/FilterParser.php',
            'run' => 'examples/filter/run.php',
        ],
        [
            'name' => 'config',
            'grammar' => 'examples/config/config.y',
            'output' => 'examples/config/Generated/ConfigParser.php',
            'run' => 'examples/config/run.php',
        ],
    ];
}

$root = dirname(__DIR__);
chdir($root);

$mode = $argv[1] ?? 'help';
$requestedNames = array_slice($argv, 2);
$examples = array_values(array_filter(
    phison_examples(),
    static fn (array $example): bool => $requestedNames === [] || in_array($example['name'], $requestedNames, true),
));

if ($examples === []) {
    fwrite(STDERR, 'No matching examples: ' . implode(', ', $requestedNames) . PHP_EOL);
    exit(1);
}

$exitCode = match ($mode) {
    'validate' => validate_examples($examples),
    'generate' => generate_examples($examples),
    'run' => run_examples($examples),
    default => print_examples_help(),
};

exit($exitCode);

/**
 * @param list<array{name:string, grammar:string, output:string, run:string}> $examples
 */
function validate_examples(array $examples): int
{
    foreach ($examples as $example) {
        $code = run_command([PHP_BINARY, 'bin/phison', 'validate', $example['grammar']]);
        if ($code !== 0) {
            return $code;
        }
    }

    return 0;
}

/**
 * @param list<array{name:string, grammar:string, output:string, run:string}> $examples
 */
function generate_examples(array $examples): int
{
    foreach ($examples as $example) {
        $code = run_command([
            PHP_BINARY,
            'bin/phison',
            'generate',
            $example['grammar'],
            '--output',
            $example['output'],
            '--lint',
        ]);
        if ($code !== 0) {
            return $code;
        }
    }

    return 0;
}

/**
 * @param list<array{name:string, grammar:string, output:string, run:string}> $examples
 */
function run_examples(array $examples): int
{
    $code = generate_examples($examples);
    if ($code !== 0) {
        return $code;
    }

    foreach ($examples as $example) {
        fwrite(STDOUT, '== ' . $example['name'] . ' ==' . PHP_EOL);
        $code = run_command([PHP_BINARY, $example['run']]);
        if ($code !== 0) {
            return $code;
        }
    }

    return 0;
}

function print_examples_help(): int
{
    fwrite(STDOUT, <<<'TEXT'
Usage:
  php examples/manage.php validate [name...]
  php examples/manage.php generate [name...]
  php examples/manage.php run [name...]

TEXT);

    return 1;
}

/**
 * @param list<string> $command
 */
function run_command(array $command): int
{
    $line = implode(' ', array_map('escapeshellarg', $command));
    passthru($line, $code);

    return (int) $code;
}
