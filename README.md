# Phison

Phison is a dependency-free LALR(1) parser generator for PHP. It reads a
small `.y` grammar DSL and emits a PHP parser class that consumes an
external `TokenStreamInterface`.

## Install

```bash
composer require ydah/phison --dev
```

Use `vendor/bin/phison` from installed projects, or `bin/phison` when working
from this repository.

## Generate a parser

```bash
bin/phison generate examples/arithmetic/arithmetic.y \
  --output examples/arithmetic/Generated/ArithmeticParser.php \
  --target-php=8.2 \
  --table-layout=hybrid \
  --report build/arithmetic-report.md
```

The generator does not create a lexer. Grammar files embed PHP semantic
actions and must be treated as trusted source code.

## Validate a grammar

```bash
bin/phison validate examples/arithmetic/arithmetic.y
```

## Inspect a grammar

```bash
bin/phison inspect examples/arithmetic/arithmetic.y --state=0
```

## Dump the automaton

```bash
bin/phison generate examples/arithmetic/arithmetic.y \
  --output build/ArithmeticParser.php \
  --dump-automaton build/arithmetic-automaton.txt
```

## Implemented options

- `--namespace` and `--class` override the generated parser name.
- `--target-php=8.2|8.3|8.4|8.5` controls generated PHP syntax. PHP 8.3+
  targets use typed class constants.
- `--table-layout=array|switch|packed|hybrid` controls ACTION/GOTO lookup
  generation.
- `--report` writes a Markdown grammar, conflict, and state report.
- `--html-report` writes the same report as a standalone HTML file.
- `--dump-automaton` prints or writes the LALR automaton.
- `--lint` runs `php -l` on the generated parser.

## Benchmark

```bash
composer benchmark
php benchmarks/arithmetic.php 50000 packed
```

The arithmetic benchmark regenerates the example parser in a temporary file
and reports elapsed time plus parses per second for the selected table layout.

## Releases

Release tags use `vMAJOR.MINOR.PATCH` and are intended to be published through
Packagist. See [docs/release.md](docs/release.md) for the versioning policy and
release checklist.

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
