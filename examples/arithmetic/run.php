<?php

declare(strict_types=1);

require __DIR__ . '/../../src/autoload.php';

$generated = __DIR__ . '/Generated/ArithmeticParser.php';
if (!is_file($generated)) {
    fwrite(STDERR, "Generate the parser first:\n");
    fwrite(STDERR, "  bin/phison generate examples/arithmetic/arithmetic.y --output examples/arithmetic/Generated/ArithmeticParser.php\n");
    exit(1);
}

require $generated;
require __DIR__ . '/Lexer.php';

$input = $argv[1] ?? '1 + 2 * (3 + 4)';
$lexer = new Example\Arithmetic\ArithmeticLexer($input);
$parser = new Example\Arithmetic\Generated\ArithmeticParser();

echo $parser->parse($lexer->tokens()) . PHP_EOL;
