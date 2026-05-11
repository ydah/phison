<?php

declare(strict_types=1);

require __DIR__ . '/../../src/autoload.php';
require __DIR__ . '/../Support/Token.php';
require __DIR__ . '/../Support/ArrayTokenStream.php';

$generated = __DIR__ . '/Generated/ConfigParser.php';
if (!is_file($generated)) {
    fwrite(STDERR, "Generate the parser first:\n");
    fwrite(STDERR, "  bin/phison generate examples/config/config.y --output examples/config/Generated/ConfigParser.php\n");
    exit(1);
}

require $generated;
require __DIR__ . '/Lexer.php';

$input = $argv[1] ?? <<<CONFIG
app = phison
debug = true

[database]
host = "localhost"
port = 5432
CONFIG;

$lexer = new Example\Config\ConfigLexer($input);
$parser = new Example\Config\Generated\ConfigParser();

echo json_encode($parser->parse($lexer->tokens()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
