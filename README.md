# Phison

Phison is a dependency-free LALR(1) parser generator for PHP. It reads a
small `.y` grammar DSL and emits a PHP parser class that consumes an
external `TokenStreamInterface`.

## Generate a parser

```bash
bin/phison generate examples/arithmetic/arithmetic.y \
  --output examples/arithmetic/Generated/ArithmeticParser.php
```

The generator does not create a lexer. Grammar files embed PHP semantic
actions and must be treated as trusted source code.

## Validate a grammar

```bash
bin/phison validate examples/arithmetic/arithmetic.y
```

## DSL sketch

```text
grammar Arithmetic
namespace App\Generated
parser ArithmeticParser
start expr

token NUMBER display "number"
token PLUS display "+"

precedence left PLUS

rule expr {
    left=expr PLUS right=expr => php {
        return $left + $right;
    }

  | n=NUMBER => php {
        return (int) $n;
    }
}
```
