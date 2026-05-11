<?php

declare(strict_types=1);

namespace Phison\Cli;

use Phison\CodeGen\CodegenOptions;
use Phison\CodeGen\ParserEmitter;
use Phison\Dsl\DslParser;
use Phison\Grammar\Grammar;
use Phison\Grammar\GrammarIssue;
use Phison\Grammar\GrammarNormalizer;
use Phison\Grammar\GrammarValidator;
use Phison\Lalr\CanonicalLr1ThenMergeBuilder;
use Phison\Lalr\ItemSetCollection;
use Phison\Lalr\ParseTable;
use Phison\Lalr\ParseTableBuilder;
use Phison\Report\AutomatonReport;
use Phison\Report\MarkdownReport;

final class Application
{
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);

        try {
            return match ($command) {
                'generate' => $this->generate($args),
                'validate' => $this->validate($args),
                'inspect' => $this->inspect($args),
                'help', '--help', '-h' => $this->help(),
                default => $this->unknown($command),
            };
        } catch (\Throwable $error) {
            fwrite(STDERR, 'error: ' . $error->getMessage() . "\n");
            return 1;
        }
    }

    /**
     * @param list<string> $args
     */
    private function generate(array $args): int
    {
        [$positionals, $options] = $this->parseArgs($args);
        $grammarFile = $positionals[0] ?? throw new \InvalidArgumentException('Missing grammar file.');
        $output = $options['output'] ?? throw new \InvalidArgumentException('Missing --output.');

        [$grammar, $collection, $table] = $this->build($grammarFile);
        $this->assertConflictPolicy($table, $options);

        $generated = (new ParserEmitter())->emit(
            $grammar,
            $table,
            new CodegenOptions(
                $options['namespace'] ?? null,
                $options['class'] ?? null,
                $options['target-php'] ?? '8.2',
                $options['table-layout'] ?? 'array',
            ),
        );

        $directory = dirname($output);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create output directory: ' . $directory);
        }

        if (file_put_contents($output, $generated->contents) === false) {
            throw new \RuntimeException('Unable to write output file: ' . $output);
        }

        if ($this->boolOption($options['lint'] ?? false)) {
            $this->lintGeneratedFile($output);
        }

        if (isset($options['report'])) {
            $report = (new MarkdownReport())->render($grammar, $collection, $table);
            $reportDirectory = dirname($options['report']);
            if ($reportDirectory !== '' && $reportDirectory !== '.' && !is_dir($reportDirectory) && !mkdir($reportDirectory, 0777, true) && !is_dir($reportDirectory)) {
                throw new \RuntimeException('Unable to create report directory: ' . $reportDirectory);
            }

            file_put_contents($options['report'], $report);
        }

        fwrite(STDOUT, 'Generated ' . $output . "\n");

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function validate(array $args): int
    {
        [$positionals, $options] = $this->parseArgs($args);
        $grammarFile = $positionals[0] ?? throw new \InvalidArgumentException('Missing grammar file.');

        [, $collection, $table] = $this->build($grammarFile);
        $this->assertConflictPolicy($table, $options);

        fwrite(
            STDOUT,
            'Grammar valid. States: ' . count($collection->states)
            . ', ACTION entries: ' . $table->actionCount()
            . ', GOTO entries: ' . $table->gotoCount()
            . ', unresolved conflicts: ' . $table->unresolvedConflictCount()
            . "\n",
        );

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function inspect(array $args): int
    {
        [$positionals, $options] = $this->parseArgs($args);
        $grammarFile = $positionals[0] ?? throw new \InvalidArgumentException('Missing grammar file.');

        [$grammar, $collection, $table] = $this->build($grammarFile);
        if (isset($options['state'])) {
            fwrite(STDOUT, (new AutomatonReport())->renderState($grammar, $collection, $table, (int) $options['state']));
            return 0;
        }

        fwrite(
            STDOUT,
            'Grammar ' . $grammar->name . ': canonical states ' . $collection->canonicalStateCount
            . ', LALR states ' . count($collection->states)
            . ', conflicts ' . count($table->conflicts)
            . "\n",
        );

        return 0;
    }

    private function help(): int
    {
        fwrite(STDOUT, <<<'TEXT'
Usage:
  phison generate <grammar.y> --output <Parser.php> [--namespace Ns] [--class Parser] [--report report.md]
  phison validate <grammar.y>
  phison inspect <grammar.y> [--state N]

Options:
  --target-php=8.2|8.3|8.4|8.5
  --expect-conflicts=N
  --fail-on-conflict=false

TEXT);

        return 0;
    }

    private function unknown(string $command): int
    {
        fwrite(STDERR, 'Unknown command: ' . $command . "\n");
        $this->help();

        return 1;
    }

    /**
     * @return array{0:Grammar, 1:ItemSetCollection, 2:ParseTable}
     */
    private function build(string $grammarFile): array
    {
        $document = (new DslParser())->parseFile($grammarFile);
        $grammar = (new GrammarNormalizer())->normalize($document);
        $issues = (new GrammarValidator())->validate($grammar);
        $errors = array_values(array_filter(
            $issues,
            static fn (GrammarIssue $issue): bool => $issue->severity === GrammarIssue::ERROR,
        ));
        if ($errors !== []) {
            throw new \RuntimeException(implode("\n", array_map(
                static fn (GrammarIssue $issue): string => $issue->message,
                $errors,
            )));
        }

        $collection = (new CanonicalLr1ThenMergeBuilder())->build($grammar);
        $table = (new ParseTableBuilder())->build($grammar, $collection);

        return [$grammar, $collection, $table];
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function assertConflictPolicy(ParseTable $table, array $options): void
    {
        $unresolved = $table->unresolvedConflictCount();
        $expected = isset($options['expect-conflicts']) ? (int) $options['expect-conflicts'] : null;
        if ($expected !== null && $unresolved !== $expected) {
            throw new \RuntimeException('Expected ' . $expected . ' unresolved conflicts, found ' . $unresolved . '.');
        }

        $failOnConflict = $this->boolOption($options['fail-on-conflict'] ?? true);
        if ($expected === null && $failOnConflict && $unresolved > 0) {
            throw new \RuntimeException('Grammar has ' . $unresolved . ' unresolved conflict(s).');
        }
    }

    /**
     * @param string|bool $value
     */
    private function boolOption($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return !in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
    }

    /**
     * @param list<string> $args
     * @return array{0:list<string>, 1:array<string, string|bool>}
     */
    private function parseArgs(array $args): array
    {
        $positionals = [];
        $options = [];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (!str_starts_with($arg, '--')) {
                $positionals[] = $arg;
                continue;
            }

            $option = substr($arg, 2);
            if (str_starts_with($option, 'no-')) {
                $options[substr($option, 3)] = false;
                continue;
            }

            if (str_contains($option, '=')) {
                [$name, $value] = explode('=', $option, 2);
                $options[$name] = $value;
                continue;
            }

            if (($args[$i + 1] ?? null) !== null && !str_starts_with($args[$i + 1], '--')) {
                $options[$option] = $args[++$i];
                continue;
            }

            $options[$option] = true;
        }

        return [$positionals, $options];
    }

    private function lintGeneratedFile(string $path): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException("Generated parser failed php -l:\n" . implode("\n", $output));
        }
    }
}
