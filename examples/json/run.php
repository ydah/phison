<?php

declare(strict_types=1);

require __DIR__ . '/../../src/autoload.php';
require __DIR__ . '/../Support/Token.php';
require __DIR__ . '/../Support/ArrayTokenStream.php';

$generated = __DIR__ . '/Generated/JsonParser.php';
if (!is_file($generated)) {
    fwrite(STDERR, "Generate the parser first:\n");
    fwrite(STDERR, "  bin/phison generate examples/json/json.y --output examples/json/Generated/JsonParser.php\n");
    exit(1);
}

require $generated;
require __DIR__ . '/Lexer.php';

$input = $argv[1] ?? '{"name":"phison","enabled":true,"tags":["parser","php"],"limits":{"max":3}}';
$lexer = new Example\Json\JsonLexer($input);
$parser = new Example\Json\Generated\JsonParser();

echo json_encode($parser->parse($lexer->tokens()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
