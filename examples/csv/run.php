<?php

declare(strict_types=1);

require __DIR__ . '/../../src/autoload.php';
require __DIR__ . '/../Support/Token.php';
require __DIR__ . '/../Support/ArrayTokenStream.php';

$generated = __DIR__ . '/Generated/CsvParser.php';
if (!is_file($generated)) {
    fwrite(STDERR, "Generate the parser first:\n");
    fwrite(STDERR, "  bin/phison generate examples/csv/csv.y --output examples/csv/Generated/CsvParser.php\n");
    exit(1);
}

require $generated;
require __DIR__ . '/Lexer.php';

$input = $argv[1] ?? "name,email,active\nAda Lovelace,ada@example.test,true\nGrace Hopper,grace@example.test,false";
$lexer = new Example\Csv\CsvLexer($input);
$parser = new Example\Csv\Generated\CsvParser();

echo json_encode($parser->parse($lexer->tokens()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
