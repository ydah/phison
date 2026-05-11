<?php

declare(strict_types=1);

require __DIR__ . '/../../src/autoload.php';
require __DIR__ . '/../Support/Token.php';
require __DIR__ . '/../Support/ArrayTokenStream.php';

$generated = __DIR__ . '/Generated/FilterParser.php';
if (!is_file($generated)) {
    fwrite(STDERR, "Generate the parser first:\n");
    fwrite(STDERR, "  bin/phison generate examples/filter/filter.y --output examples/filter/Generated/FilterParser.php\n");
    exit(1);
}

require $generated;
require __DIR__ . '/Lexer.php';

$input = $argv[1] ?? 'status:open AND (label:bug OR assignee:"Ada Lovelace") AND -archived';
$lexer = new Example\Filter\FilterLexer($input);
$parser = new Example\Filter\Generated\FilterParser();

echo json_encode($parser->parse($lexer->tokens()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
