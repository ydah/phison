<?php

declare(strict_types=1);

use Example\Arithmetic\ArithmeticLexer;
use Example\Arithmetic\Generated\ArithmeticParser;
use Phison\CodeGen\CodegenOptions;
use Phison\CodeGen\ParserEmitter;
use Phison\CodeGen\TableLayout;
use Phison\Dsl\DslParser;
use Phison\Grammar\GrammarNormalizer;
use Phison\Lalr\CanonicalLr1ThenMergeBuilder;
use Phison\Lalr\ParseTableBuilder;

require __DIR__ . '/../src/autoload.php';

$iterations = (int) ($argv[1] ?? 10000);
if ($iterations < 1) {
    throw new \InvalidArgumentException('Iterations must be greater than zero.');
}

$layout = (string) ($argv[2] ?? TableLayout::PACKED);
if (!in_array($layout, TableLayout::all(), true)) {
    throw new \InvalidArgumentException('Unsupported table layout: ' . $layout);
}

$input = (string) ($argv[3] ?? '1 + 2 * (3 + 4) - 5 + 6 * 7');
$grammarFile = __DIR__ . '/../examples/arithmetic/arithmetic.y';

$document = (new DslParser())->parseFile($grammarFile);
$grammar = (new GrammarNormalizer())->normalize($document);
$collection = (new CanonicalLr1ThenMergeBuilder())->build($grammar);
$table = (new ParseTableBuilder())->build($grammar, $collection);

$output = sys_get_temp_dir() . '/phison-arithmetic-benchmark-' . $layout . '.php';
$generated = (new ParserEmitter())->emit(
    $grammar,
    $table,
    new CodegenOptions(null, null, '8.2', $layout),
);

if (file_put_contents($output, $generated->contents) === false) {
    throw new \RuntimeException('Unable to write benchmark parser: ' . $output);
}

require $output;
require __DIR__ . '/../examples/arithmetic/Lexer.php';

$parser = new ArithmeticParser();
$parse = static fn (): mixed => $parser->parse((new ArithmeticLexer($input))->tokens());
$expected = $parse();
$warmupIterations = min(1000, $iterations);

for ($i = 0; $i < $warmupIterations; $i++) {
    if ($parse() !== $expected) {
        throw new \RuntimeException('Benchmark parser returned an unstable result during warmup.');
    }
}

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    if ($parse() !== $expected) {
        throw new \RuntimeException('Benchmark parser returned an unstable result.');
    }
}
$elapsedNs = hrtime(true) - $start;
$elapsedSeconds = $elapsedNs / 1_000_000_000;
$parsesPerSecond = $elapsedSeconds > 0.0 ? $iterations / $elapsedSeconds : INF;

printf("layout: %s\n", $layout);
printf("iterations: %d\n", $iterations);
printf("elapsed_ms: %.3f\n", $elapsedSeconds * 1000);
printf("parses_per_sec: %.2f\n", $parsesPerSecond);
printf("result: %s\n", (string) $expected);
