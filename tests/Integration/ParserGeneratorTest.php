<?php

declare(strict_types=1);

namespace Phison\Tests\Integration;

use Example\Arithmetic\ArithmeticLexer;
use Phison\CodeGen\CodegenOptions;
use Phison\CodeGen\ParserEmitter;
use Phison\Dsl\DslParser;
use Phison\Grammar\Grammar;
use Phison\Grammar\GrammarNormalizer;
use Phison\Lalr\CanonicalLr1ThenMergeBuilder;
use Phison\Lalr\Conflict;
use Phison\Lalr\ItemSetCollection;
use Phison\Lalr\ParseTable;
use Phison\Lalr\ParseTableBuilder;
use Phison\Runtime\ParseError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserGeneratorTest extends TestCase
{
    private static Grammar $grammar;
    private static ItemSetCollection $collection;
    private static ParseTable $table;

    public static function setUpBeforeClass(): void
    {
        $grammarFile = __DIR__ . '/../../examples/arithmetic/arithmetic.y';
        $outputFile = __DIR__ . '/../../examples/arithmetic/Generated/ArithmeticParser.php';

        $document = (new DslParser())->parseFile($grammarFile);
        self::$grammar = (new GrammarNormalizer())->normalize($document);
        self::$collection = (new CanonicalLr1ThenMergeBuilder())->build(self::$grammar);
        self::$table = (new ParseTableBuilder())->build(self::$grammar, self::$collection);

        if (!is_dir(dirname($outputFile))) {
            mkdir(dirname($outputFile), 0777, true);
        }

        $generated = (new ParserEmitter())->emit(self::$grammar, self::$table, new CodegenOptions())->contents;
        file_put_contents($outputFile, $generated);

        require_once $outputFile;
        require_once __DIR__ . '/../../examples/arithmetic/Lexer.php';
    }

    public function testArithmeticGrammarHasNoUnresolvedConflicts(): void
    {
        self::assertSame(0, self::$table->unresolvedConflictCount());
    }

    #[DataProvider('arithmeticCases')]
    public function testGeneratedArithmeticParser(string $input, int $expected): void
    {
        $parser = new \Example\Arithmetic\Generated\ArithmeticParser();

        self::assertSame($expected, $parser->parse((new ArithmeticLexer($input))->tokens()));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function arithmeticCases(): iterable
    {
        yield 'addition' => ['1 + 2', 3];
        yield 'precedence' => ['1 + 2 * 3', 7];
        yield 'grouping' => ['(1 + 2) * 3', 9];
        yield 'unary minus' => ['-1 + 2', 1];
        yield 'left subtraction' => ['1 - 2 - 3', -4];
        yield 'left division' => ['8 / 2 / 2', 2];
    }

    #[DataProvider('tableLayouts')]
    public function testGeneratedParserTableLayouts(string $layout): void
    {
        $className = 'Arithmetic' . ucfirst($layout) . 'Parser';
        $namespace = 'Example\\Arithmetic\\Generated' . ucfirst($layout);
        $layoutOutput = sys_get_temp_dir() . '/' . $className . '.php';
        $layoutCode = (new ParserEmitter())->emit(
            self::$grammar,
            self::$table,
            new CodegenOptions($namespace, $className, '8.2', $layout),
        )->contents;
        file_put_contents($layoutOutput, $layoutCode);
        require_once $layoutOutput;

        $parserClass = $namespace . '\\' . $className;
        $parser = new $parserClass();

        self::assertSame(15, $parser->parse((new ArithmeticLexer('1 + 2 * (3 + 4)'))->tokens()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tableLayouts(): iterable
    {
        yield 'array' => ['array'];
        yield 'switch' => ['switch'];
        yield 'packed' => ['packed'];
        yield 'hybrid' => ['hybrid'];
    }

    public function testTargetPhpControlsTypedConstants(): void
    {
        $php82Code = (new ParserEmitter())->emit(self::$grammar, self::$table, new CodegenOptions('Example\\Target82', 'Target82Parser', '8.2'))->contents;
        $php83Code = (new ParserEmitter())->emit(self::$grammar, self::$table, new CodegenOptions('Example\\Target83', 'Target83Parser', '8.3'))->contents;

        self::assertStringNotContainsString('public const int T_EOF', $php82Code);
        self::assertStringNotContainsString('private const array TOKEN_NAMES', $php82Code);
        self::assertStringContainsString('public const int T_EOF', $php83Code);
        self::assertStringContainsString('private const array TOKEN_NAMES', $php83Code);
    }

    public function testParseErrorMessageIncludesContextTokens(): void
    {
        $parser = new \Example\Arithmetic\Generated\ArithmeticParser();

        try {
            $parser->parse((new ArithmeticLexer('1 + * 2'))->tokens());
            self::fail('Expected invalid arithmetic input to throw ParseError.');
        } catch (ParseError $error) {
            $message = $error->getMessage();
        }

        self::assertStringContainsString('Unexpected token STAR("*")', $message);
        self::assertStringContainsString('expected number, -, (', $message);
        self::assertStringContainsString('Previous tokens: NUMBER("1"), PLUS("+")', $message);
        self::assertStringContainsString('Next tokens: NUMBER("2"), EOF', $message);
    }

    public function testAmbiguousArithmeticReportsUnresolvedConflicts(): void
    {
        $table = $this->buildTable(<<<'LRG'
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
LRG);

        self::assertGreaterThan(0, $table->unresolvedConflictCount());
    }

    public function testDanglingElseConflictCanBeResolvedWithPrecedence(): void
    {
        $unresolved = $this->buildTable(<<<'LRG'
grammar DanglingElse
start stmt

token IF
token EXPR
token THEN
token ELSE
token OTHER

rule stmt {
    IF EXPR THEN stmt => php {
        return null;
    }

  | IF EXPR THEN stmt ELSE stmt => php {
        return null;
    }

  | OTHER => php {
        return null;
    }
}
LRG);

        $resolved = $this->buildTable(<<<'LRG'
grammar ResolvedDanglingElse
start stmt

token IF
token EXPR
token THEN
token ELSE
token OTHER

precedence right THEN
precedence right ELSE

rule stmt {
    IF EXPR THEN stmt %prec THEN => php {
        return null;
    }

  | IF EXPR THEN stmt ELSE stmt => php {
        return null;
    }

  | OTHER => php {
        return null;
    }
}
LRG);

        self::assertGreaterThan(0, $unresolved->unresolvedConflictCount());
        self::assertSame(0, $resolved->unresolvedConflictCount());
    }

    public function testLalrSpecificConflictReportsReduceReduce(): void
    {
        $table = $this->buildTable(<<<'LRG'
grammar LalrSpecific
start s

token A_T
token B_T
token C_T
token D_T
token E_T

rule s {
    A_T x D_T => php {
        return null;
    }

  | B_T y D_T => php {
        return null;
    }

  | A_T y E_T => php {
        return null;
    }

  | B_T x E_T => php {
        return null;
    }
}

rule x {
    C_T => php {
        return null;
    }
}

rule y {
    C_T => php {
        return null;
    }
}
LRG);

        self::assertContains(
            Conflict::REDUCE_REDUCE,
            array_map(static fn (Conflict $conflict): string => $conflict->kind, $table->conflicts),
        );
    }

    public function testReduceReduceConflictIsUnresolved(): void
    {
        $table = $this->buildTable(<<<'LRG'
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
LRG);

        self::assertGreaterThan(0, $table->unresolvedConflictCount());
    }

    public function testGeneratedParserMatchesGoldenFile(): void
    {
        $generated = (new ParserEmitter())->emit(self::$grammar, self::$table, new CodegenOptions())->contents;

        $this->assertMatchesGolden('ArithmeticParser.golden.php', $generated);
    }

    public function testMarkdownReportMatchesGoldenFile(): void
    {
        $report = (new \Phison\Report\MarkdownReport())->render(self::$grammar, self::$collection, self::$table);

        $this->assertMatchesGolden('arithmetic-report.golden.md', $report);
    }

    public function testAutomatonDumpMatchesGoldenFile(): void
    {
        $report = (new \Phison\Report\AutomatonReport())->render(self::$grammar, self::$collection, self::$table);

        $this->assertMatchesGolden('arithmetic-automaton.golden.txt', $report);
    }

    private function buildTable(string $source): ParseTable
    {
        $document = (new DslParser())->parse($source);
        $grammar = (new GrammarNormalizer())->normalize($document);
        $collection = (new CanonicalLr1ThenMergeBuilder())->build($grammar);

        return (new ParseTableBuilder())->build($grammar, $collection);
    }

    private function assertMatchesGolden(string $fileName, string $actual): void
    {
        $expectedPath = __DIR__ . '/../Fixtures/expected/' . $fileName;

        self::assertFileExists($expectedPath);
        self::assertSame(
            $this->normalizeNewlines(file_get_contents($expectedPath) ?: ''),
            $this->normalizeNewlines($actual),
        );
    }

    private function normalizeNewlines(string $contents): string
    {
        return str_replace("\r\n", "\n", $contents);
    }
}
