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
