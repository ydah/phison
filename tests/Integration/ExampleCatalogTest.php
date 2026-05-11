<?php

declare(strict_types=1);

namespace Phison\Tests\Integration;

use Phison\CodeGen\CodegenOptions;
use Phison\CodeGen\ParserEmitter;
use Phison\Dsl\DslParser;
use Phison\Grammar\GrammarNormalizer;
use Phison\Lalr\CanonicalLr1ThenMergeBuilder;
use Phison\Lalr\ParseTableBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExampleCatalogTest extends TestCase
{
    /**
     * @param mixed $expected
     */
    #[DataProvider('exampleCases')]
    public function testExampleParserRuns(
        string $grammarFile,
        string $outputFile,
        string $lexerFile,
        string $lexerClass,
        string $parserClass,
        string $input,
        mixed $expected,
    ): void {
        $this->generateParser($grammarFile, $outputFile);

        require_once __DIR__ . '/../../examples/Support/Token.php';
        require_once __DIR__ . '/../../examples/Support/ArrayTokenStream.php';
        require_once $outputFile;
        require_once $lexerFile;

        $lexer = new $lexerClass($input);
        $parser = new $parserClass();

        self::assertSame($expected, $parser->parse($lexer->tokens()));
    }

    /**
     * @return iterable<string, array{string, string, string, class-string, class-string, string, mixed}>
     */
    public static function exampleCases(): iterable
    {
        yield 'json' => [
            __DIR__ . '/../../examples/json/json.y',
            __DIR__ . '/../../examples/json/Generated/JsonParser.php',
            __DIR__ . '/../../examples/json/Lexer.php',
            'Example\\Json\\JsonLexer',
            'Example\\Json\\Generated\\JsonParser',
            '{"name":"phison","enabled":true,"tags":["parser","php"],"limits":{"max":3}}',
            [
                'name' => 'phison',
                'enabled' => true,
                'tags' => ['parser', 'php'],
                'limits' => ['max' => 3],
            ],
        ];

        yield 'csv' => [
            __DIR__ . '/../../examples/csv/csv.y',
            __DIR__ . '/../../examples/csv/Generated/CsvParser.php',
            __DIR__ . '/../../examples/csv/Lexer.php',
            'Example\\Csv\\CsvLexer',
            'Example\\Csv\\Generated\\CsvParser',
            "name,email,active\nAda Lovelace,ada@example.test,true\nGrace Hopper,grace@example.test,false",
            [
                ['name', 'email', 'active'],
                ['Ada Lovelace', 'ada@example.test', 'true'],
                ['Grace Hopper', 'grace@example.test', 'false'],
            ],
        ];

        yield 'filter' => [
            __DIR__ . '/../../examples/filter/filter.y',
            __DIR__ . '/../../examples/filter/Generated/FilterParser.php',
            __DIR__ . '/../../examples/filter/Lexer.php',
            'Example\\Filter\\FilterLexer',
            'Example\\Filter\\Generated\\FilterParser',
            'status:open AND (label:bug OR assignee:"Ada Lovelace") AND -archived',
            [
                'and',
                ['and', ['term', 'status', 'open'], ['or', ['term', 'label', 'bug'], ['term', 'assignee', 'Ada Lovelace']]],
                ['not', ['term', 'text', 'archived']],
            ],
        ];

        yield 'config' => [
            __DIR__ . '/../../examples/config/config.y',
            __DIR__ . '/../../examples/config/Generated/ConfigParser.php',
            __DIR__ . '/../../examples/config/Lexer.php',
            'Example\\Config\\ConfigLexer',
            'Example\\Config\\Generated\\ConfigParser',
            "app = phison\ndebug = true\n\n[database]\nhost = \"localhost\"\nport = 5432",
            [
                'app' => 'phison',
                'debug' => true,
                'database' => [
                    'host' => 'localhost',
                    'port' => 5432,
                ],
            ],
        ];
    }

    private function generateParser(string $grammarFile, string $outputFile): void
    {
        $document = (new DslParser())->parseFile($grammarFile);
        $grammar = (new GrammarNormalizer())->normalize($document);
        $collection = (new CanonicalLr1ThenMergeBuilder())->build($grammar);
        $table = (new ParseTableBuilder())->build($grammar, $collection);
        $generated = (new ParserEmitter())->emit($grammar, $table, new CodegenOptions())->contents;

        if (!is_dir(dirname($outputFile))) {
            mkdir(dirname($outputFile), 0777, true);
        }

        file_put_contents($outputFile, $generated);
    }
}
