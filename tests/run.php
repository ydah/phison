<?php

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use Example\Arithmetic\ArithmeticLexer;
use Example\Arithmetic\Generated\ArithmeticParser;
use Phison\CodeGen\CodegenOptions;
use Phison\CodeGen\ParserEmitter;
use Phison\Dsl\DslParser;
use Phison\Grammar\GrammarNormalizer;
use Phison\Lalr\CanonicalLr1ThenMergeBuilder;
use Phison\Lalr\ParseTableBuilder;
use Phison\Runtime\ParseError;

$grammarFile = __DIR__ . '/../examples/arithmetic/arithmetic.y';
$outputFile = __DIR__ . '/../examples/arithmetic/Generated/ArithmeticParser.php';

$document = (new DslParser())->parseFile($grammarFile);
$grammar = (new GrammarNormalizer())->normalize($document);
$collection = (new CanonicalLr1ThenMergeBuilder())->build($grammar);
$table = (new ParseTableBuilder())->build($grammar, $collection);

if ($table->unresolvedConflictCount() !== 0) {
    throw new RuntimeException('Expected no unresolved conflicts.');
}

$generated = (new ParserEmitter())->emit($grammar, $table, new CodegenOptions())->contents;
if (!is_dir(dirname($outputFile))) {
    mkdir(dirname($outputFile), 0777, true);
}
file_put_contents($outputFile, $generated);

require $outputFile;
require __DIR__ . '/../examples/arithmetic/Lexer.php';

$cases = [
    '1 + 2' => 3,
    '1 + 2 * 3' => 7,
    '(1 + 2) * 3' => 9,
    '-1 + 2' => 1,
    '1 - 2 - 3' => -4,
    '8 / 2 / 2' => 2,
];

foreach ($cases as $input => $expected) {
    $actual = (new ArithmeticParser())->parse((new ArithmeticLexer($input))->tokens());
    if ($actual !== $expected) {
        throw new RuntimeException('Expected ' . $input . ' to be ' . $expected . ', got ' . (string) $actual);
    }
}

foreach (['array', 'switch', 'packed', 'hybrid'] as $layout) {
    $className = 'Arithmetic' . ucfirst($layout) . 'Parser';
    $namespace = 'Example\\Arithmetic\\Generated' . ucfirst($layout);
    $layoutOutput = sys_get_temp_dir() . '/' . $className . '.php';
    $layoutCode = (new ParserEmitter())->emit(
        $grammar,
        $table,
        new CodegenOptions($namespace, $className, '8.2', $layout),
    )->contents;
    file_put_contents($layoutOutput, $layoutCode);
    require $layoutOutput;

    $parserClass = $namespace . '\\' . $className;
    $actual = (new $parserClass())->parse((new ArithmeticLexer('1 + 2 * (3 + 4)'))->tokens());
    if ($actual !== 15) {
        throw new RuntimeException('Expected ' . $layout . ' layout parser to return 15, got ' . (string) $actual);
    }
}

$php82Code = (new ParserEmitter())->emit($grammar, $table, new CodegenOptions('Example\\Target82', 'Target82Parser', '8.2'))->contents;
if (str_contains($php82Code, 'public const int T_EOF') || str_contains($php82Code, 'private const array TOKEN_NAMES')) {
    throw new RuntimeException('PHP 8.2 target must not emit typed class constants.');
}

$php83Code = (new ParserEmitter())->emit($grammar, $table, new CodegenOptions('Example\\Target83', 'Target83Parser', '8.3'))->contents;
if (!str_contains($php83Code, 'public const int T_EOF') || !str_contains($php83Code, 'private const array TOKEN_NAMES')) {
    throw new RuntimeException('PHP 8.3 target should emit typed class constants.');
}

$syntaxError = null;
try {
    (new ArithmeticParser())->parse((new ArithmeticLexer('1 + * 2'))->tokens());
} catch (ParseError $error) {
    $syntaxError = $error;
}

if (!$syntaxError instanceof ParseError) {
    throw new RuntimeException('Expected invalid arithmetic input to throw ParseError.');
}

$message = $syntaxError->getMessage();
foreach (['Unexpected token STAR("*")', 'expected number, -, (', 'Previous tokens: NUMBER("1"), PLUS("+")', 'Next tokens: NUMBER("2"), EOF'] as $expectedText) {
    if (!str_contains($message, $expectedText)) {
        throw new RuntimeException('ParseError message is missing: ' . $expectedText . "\n" . $message);
    }
}

$ambiguousArithmetic = <<<'LRG'
grammar AmbiguousArithmetic
start expr

token NUMBER
token PLUS
token STAR

rule expr {
    expr PLUS expr => php {
        return null;
    }

  | expr STAR expr => php {
        return null;
    }

  | NUMBER => php {
        return $v1;
    }
}
LRG;

$ambiguousDocument = (new DslParser())->parse($ambiguousArithmetic);
$ambiguousGrammar = (new GrammarNormalizer())->normalize($ambiguousDocument);
$ambiguousCollection = (new CanonicalLr1ThenMergeBuilder())->build($ambiguousGrammar);
$ambiguousTable = (new ParseTableBuilder())->build($ambiguousGrammar, $ambiguousCollection);
if ($ambiguousTable->unresolvedConflictCount() === 0) {
    throw new RuntimeException('Expected ambiguous arithmetic to report unresolved conflicts.');
}

$reduceReduce = <<<'LRG'
grammar ReduceReduce
start s

token X

rule s {
    a => php {
        return $v1;
    }

  | b => php {
        return $v1;
    }
}

rule a {
    X => php {
        return 'a';
    }
}

rule b {
    X => php {
        return 'b';
    }
}
LRG;

$rrDocument = (new DslParser())->parse($reduceReduce);
$rrGrammar = (new GrammarNormalizer())->normalize($rrDocument);
$rrCollection = (new CanonicalLr1ThenMergeBuilder())->build($rrGrammar);
$rrTable = (new ParseTableBuilder())->build($rrGrammar, $rrCollection);
if ($rrTable->unresolvedConflictCount() === 0) {
    throw new RuntimeException('Expected reduce/reduce grammar to report unresolved conflicts.');
}

echo "ok\n";
