# Examples

Each example has a `.y` grammar, a handwritten lexer, and a small `run.php`
driver. Generate all example parsers with:

```bash
composer generate:examples
```

Run all examples with their default inputs:

```bash
composer run:examples
```

## Catalog

- `arithmetic`: expression grammar with precedence and semantic actions.
- `json`: practical JSON subset that builds PHP arrays and scalar values.
- `csv`: RFC 4180-style single-line-field CSV parser for table data.
- `filter`: search/filter query parser that emits an AST.
- `config`: INI-like configuration parser with sections and typed values.

You can target one example by passing its name to the manager:

```bash
php examples/manage.php generate json
php examples/json/run.php '{"name":"phison","enabled":true}'
```
