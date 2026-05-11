# Parser Report: Arithmetic

- Canonical LR(1) states: 31
- LALR states: 17
- ACTION entries: 74
- GOTO entries: 7
- Conflicts: 20
- Unresolved conflicts: 0

## Tokens

- `0` `EOF` display `EOF`
- `1` `NUMBER` display `number`
- `2` `PLUS` display `+`
- `3` `MINUS` display `-`
- `4` `STAR` display `*`
- `5` `SLASH` display `/`
- `6` `LPAREN` display `(`
- `7` `RPAREN` display `)`

## Non-terminals

- `0` `$accept`
- `1` `expr`

## Productions

- `0` $accept -> expr EOF
- `1` expr -> left=expr PLUS right=expr
- `2` expr -> left=expr MINUS right=expr
- `3` expr -> left=expr STAR right=expr
- `4` expr -> left=expr SLASH right=expr
- `5` expr -> MINUS value=expr
- `6` expr -> LPAREN value=expr RPAREN
- `7` expr -> n=NUMBER

## Precedence

- level `1` `PLUS` `left`
- level `1` `MINUS` `left`
- level `2` `STAR` `left`
- level `2` `SLASH` `left`
- level `3` `UMINUS` `right`

## Conflicts

### Conflict 1

- State: `10`
- Token: `PLUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 6`
- Incoming action: `reduce expr -> MINUS value=expr`
- Resolution: PLUS has lower precedence than UMINUS

### Conflict 2

- State: `10`
- Token: `MINUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 7`
- Incoming action: `reduce expr -> MINUS value=expr`
- Resolution: MINUS has lower precedence than UMINUS

### Conflict 3

- State: `10`
- Token: `STAR`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 8`
- Incoming action: `reduce expr -> MINUS value=expr`
- Resolution: STAR has lower precedence than UMINUS

### Conflict 4

- State: `10`
- Token: `SLASH`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 9`
- Incoming action: `reduce expr -> MINUS value=expr`
- Resolution: SLASH has lower precedence than UMINUS

### Conflict 5

- State: `12`
- Token: `PLUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 6`
- Incoming action: `reduce expr -> left=expr PLUS right=expr`
- Resolution: left associativity chooses reduce

### Conflict 6

- State: `12`
- Token: `MINUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `reduce expr -> left=expr PLUS right=expr`
- Incoming action: `shift 7`
- Resolution: left associativity chooses reduce

### Conflict 7

- State: `12`
- Token: `STAR`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `reduce expr -> left=expr PLUS right=expr`
- Incoming action: `shift 8`
- Resolution: STAR has higher precedence than PLUS

### Conflict 8

- State: `12`
- Token: `SLASH`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `reduce expr -> left=expr PLUS right=expr`
- Incoming action: `shift 9`
- Resolution: SLASH has higher precedence than PLUS

### Conflict 9

- State: `13`
- Token: `PLUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 6`
- Incoming action: `reduce expr -> left=expr MINUS right=expr`
- Resolution: left associativity chooses reduce

### Conflict 10

- State: `13`
- Token: `MINUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 7`
- Incoming action: `reduce expr -> left=expr MINUS right=expr`
- Resolution: left associativity chooses reduce

### Conflict 11

- State: `13`
- Token: `STAR`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `reduce expr -> left=expr MINUS right=expr`
- Incoming action: `shift 8`
- Resolution: STAR has higher precedence than MINUS

### Conflict 12

- State: `13`
- Token: `SLASH`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `reduce expr -> left=expr MINUS right=expr`
- Incoming action: `shift 9`
- Resolution: SLASH has higher precedence than MINUS

### Conflict 13

- State: `14`
- Token: `PLUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 6`
- Incoming action: `reduce expr -> left=expr STAR right=expr`
- Resolution: PLUS has lower precedence than STAR

### Conflict 14

- State: `14`
- Token: `MINUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 7`
- Incoming action: `reduce expr -> left=expr STAR right=expr`
- Resolution: MINUS has lower precedence than STAR

### Conflict 15

- State: `14`
- Token: `STAR`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 8`
- Incoming action: `reduce expr -> left=expr STAR right=expr`
- Resolution: left associativity chooses reduce

### Conflict 16

- State: `14`
- Token: `SLASH`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `reduce expr -> left=expr STAR right=expr`
- Incoming action: `shift 9`
- Resolution: left associativity chooses reduce

### Conflict 17

- State: `15`
- Token: `PLUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 6`
- Incoming action: `reduce expr -> left=expr SLASH right=expr`
- Resolution: PLUS has lower precedence than SLASH

### Conflict 18

- State: `15`
- Token: `MINUS`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 7`
- Incoming action: `reduce expr -> left=expr SLASH right=expr`
- Resolution: MINUS has lower precedence than SLASH

### Conflict 19

- State: `15`
- Token: `STAR`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 8`
- Incoming action: `reduce expr -> left=expr SLASH right=expr`
- Resolution: left associativity chooses reduce

### Conflict 20

- State: `15`
- Token: `SLASH`
- Kind: `shift/reduce`
- Status: `resolved`
- Existing action: `shift 9`
- Incoming action: `reduce expr -> left=expr SLASH right=expr`
- Resolution: left associativity chooses reduce

## Conflict States

### State 10

```text
State 10

  expr -> expr . PLUS expr [EOF]
  expr -> expr . PLUS expr [PLUS]
  expr -> expr . PLUS expr [MINUS]
  expr -> expr . PLUS expr [STAR]
  expr -> expr . PLUS expr [SLASH]
  expr -> expr . PLUS expr [RPAREN]
  expr -> expr . MINUS expr [EOF]
  expr -> expr . MINUS expr [PLUS]
  expr -> expr . MINUS expr [MINUS]
  expr -> expr . MINUS expr [STAR]
  expr -> expr . MINUS expr [SLASH]
  expr -> expr . MINUS expr [RPAREN]
  expr -> expr . STAR expr [EOF]
  expr -> expr . STAR expr [PLUS]
  expr -> expr . STAR expr [MINUS]
  expr -> expr . STAR expr [STAR]
  expr -> expr . STAR expr [SLASH]
  expr -> expr . STAR expr [RPAREN]
  expr -> expr . SLASH expr [EOF]
  expr -> expr . SLASH expr [PLUS]
  expr -> expr . SLASH expr [MINUS]
  expr -> expr . SLASH expr [STAR]
  expr -> expr . SLASH expr [SLASH]
  expr -> expr . SLASH expr [RPAREN]
  expr -> MINUS expr . [EOF]
  expr -> MINUS expr . [PLUS]
  expr -> MINUS expr . [MINUS]
  expr -> MINUS expr . [STAR]
  expr -> MINUS expr . [SLASH]
  expr -> MINUS expr . [RPAREN]

Actions:
  EOF  reduce expr -> MINUS value=expr
  PLUS  reduce expr -> MINUS value=expr
  MINUS  reduce expr -> MINUS value=expr
  STAR  reduce expr -> MINUS value=expr
  SLASH  reduce expr -> MINUS value=expr
  RPAREN  reduce expr -> MINUS value=expr

Gotos:
```

### State 12

```text
State 12

  expr -> expr . PLUS expr [EOF]
  expr -> expr . PLUS expr [PLUS]
  expr -> expr . PLUS expr [MINUS]
  expr -> expr . PLUS expr [STAR]
  expr -> expr . PLUS expr [SLASH]
  expr -> expr . PLUS expr [RPAREN]
  expr -> expr PLUS expr . [EOF]
  expr -> expr PLUS expr . [PLUS]
  expr -> expr PLUS expr . [MINUS]
  expr -> expr PLUS expr . [STAR]
  expr -> expr PLUS expr . [SLASH]
  expr -> expr PLUS expr . [RPAREN]
  expr -> expr . MINUS expr [EOF]
  expr -> expr . MINUS expr [PLUS]
  expr -> expr . MINUS expr [MINUS]
  expr -> expr . MINUS expr [STAR]
  expr -> expr . MINUS expr [SLASH]
  expr -> expr . MINUS expr [RPAREN]
  expr -> expr . STAR expr [EOF]
  expr -> expr . STAR expr [PLUS]
  expr -> expr . STAR expr [MINUS]
  expr -> expr . STAR expr [STAR]
  expr -> expr . STAR expr [SLASH]
  expr -> expr . STAR expr [RPAREN]
  expr -> expr . SLASH expr [EOF]
  expr -> expr . SLASH expr [PLUS]
  expr -> expr . SLASH expr [MINUS]
  expr -> expr . SLASH expr [STAR]
  expr -> expr . SLASH expr [SLASH]
  expr -> expr . SLASH expr [RPAREN]

Actions:
  EOF  reduce expr -> left=expr PLUS right=expr
  PLUS  reduce expr -> left=expr PLUS right=expr
  MINUS  reduce expr -> left=expr PLUS right=expr
  STAR  shift 8
  SLASH  shift 9
  RPAREN  reduce expr -> left=expr PLUS right=expr

Gotos:
```

### State 13

```text
State 13

  expr -> expr . PLUS expr [EOF]
  expr -> expr . PLUS expr [PLUS]
  expr -> expr . PLUS expr [MINUS]
  expr -> expr . PLUS expr [STAR]
  expr -> expr . PLUS expr [SLASH]
  expr -> expr . PLUS expr [RPAREN]
  expr -> expr . MINUS expr [EOF]
  expr -> expr . MINUS expr [PLUS]
  expr -> expr . MINUS expr [MINUS]
  expr -> expr . MINUS expr [STAR]
  expr -> expr . MINUS expr [SLASH]
  expr -> expr . MINUS expr [RPAREN]
  expr -> expr MINUS expr . [EOF]
  expr -> expr MINUS expr . [PLUS]
  expr -> expr MINUS expr . [MINUS]
  expr -> expr MINUS expr . [STAR]
  expr -> expr MINUS expr . [SLASH]
  expr -> expr MINUS expr . [RPAREN]
  expr -> expr . STAR expr [EOF]
  expr -> expr . STAR expr [PLUS]
  expr -> expr . STAR expr [MINUS]
  expr -> expr . STAR expr [STAR]
  expr -> expr . STAR expr [SLASH]
  expr -> expr . STAR expr [RPAREN]
  expr -> expr . SLASH expr [EOF]
  expr -> expr . SLASH expr [PLUS]
  expr -> expr . SLASH expr [MINUS]
  expr -> expr . SLASH expr [STAR]
  expr -> expr . SLASH expr [SLASH]
  expr -> expr . SLASH expr [RPAREN]

Actions:
  EOF  reduce expr -> left=expr MINUS right=expr
  PLUS  reduce expr -> left=expr MINUS right=expr
  MINUS  reduce expr -> left=expr MINUS right=expr
  STAR  shift 8
  SLASH  shift 9
  RPAREN  reduce expr -> left=expr MINUS right=expr

Gotos:
```

### State 14

```text
State 14

  expr -> expr . PLUS expr [EOF]
  expr -> expr . PLUS expr [PLUS]
  expr -> expr . PLUS expr [MINUS]
  expr -> expr . PLUS expr [STAR]
  expr -> expr . PLUS expr [SLASH]
  expr -> expr . PLUS expr [RPAREN]
  expr -> expr . MINUS expr [EOF]
  expr -> expr . MINUS expr [PLUS]
  expr -> expr . MINUS expr [MINUS]
  expr -> expr . MINUS expr [STAR]
  expr -> expr . MINUS expr [SLASH]
  expr -> expr . MINUS expr [RPAREN]
  expr -> expr . STAR expr [EOF]
  expr -> expr . STAR expr [PLUS]
  expr -> expr . STAR expr [MINUS]
  expr -> expr . STAR expr [STAR]
  expr -> expr . STAR expr [SLASH]
  expr -> expr . STAR expr [RPAREN]
  expr -> expr STAR expr . [EOF]
  expr -> expr STAR expr . [PLUS]
  expr -> expr STAR expr . [MINUS]
  expr -> expr STAR expr . [STAR]
  expr -> expr STAR expr . [SLASH]
  expr -> expr STAR expr . [RPAREN]
  expr -> expr . SLASH expr [EOF]
  expr -> expr . SLASH expr [PLUS]
  expr -> expr . SLASH expr [MINUS]
  expr -> expr . SLASH expr [STAR]
  expr -> expr . SLASH expr [SLASH]
  expr -> expr . SLASH expr [RPAREN]

Actions:
  EOF  reduce expr -> left=expr STAR right=expr
  PLUS  reduce expr -> left=expr STAR right=expr
  MINUS  reduce expr -> left=expr STAR right=expr
  STAR  reduce expr -> left=expr STAR right=expr
  SLASH  reduce expr -> left=expr STAR right=expr
  RPAREN  reduce expr -> left=expr STAR right=expr

Gotos:
```

### State 15

```text
State 15

  expr -> expr . PLUS expr [EOF]
  expr -> expr . PLUS expr [PLUS]
  expr -> expr . PLUS expr [MINUS]
  expr -> expr . PLUS expr [STAR]
  expr -> expr . PLUS expr [SLASH]
  expr -> expr . PLUS expr [RPAREN]
  expr -> expr . MINUS expr [EOF]
  expr -> expr . MINUS expr [PLUS]
  expr -> expr . MINUS expr [MINUS]
  expr -> expr . MINUS expr [STAR]
  expr -> expr . MINUS expr [SLASH]
  expr -> expr . MINUS expr [RPAREN]
  expr -> expr . STAR expr [EOF]
  expr -> expr . STAR expr [PLUS]
  expr -> expr . STAR expr [MINUS]
  expr -> expr . STAR expr [STAR]
  expr -> expr . STAR expr [SLASH]
  expr -> expr . STAR expr [RPAREN]
  expr -> expr . SLASH expr [EOF]
  expr -> expr . SLASH expr [PLUS]
  expr -> expr . SLASH expr [MINUS]
  expr -> expr . SLASH expr [STAR]
  expr -> expr . SLASH expr [SLASH]
  expr -> expr . SLASH expr [RPAREN]
  expr -> expr SLASH expr . [EOF]
  expr -> expr SLASH expr . [PLUS]
  expr -> expr SLASH expr . [MINUS]
  expr -> expr SLASH expr . [STAR]
  expr -> expr SLASH expr . [SLASH]
  expr -> expr SLASH expr . [RPAREN]

Actions:
  EOF  reduce expr -> left=expr SLASH right=expr
  PLUS  reduce expr -> left=expr SLASH right=expr
  MINUS  reduce expr -> left=expr SLASH right=expr
  STAR  reduce expr -> left=expr SLASH right=expr
  SLASH  reduce expr -> left=expr SLASH right=expr
  RPAREN  reduce expr -> left=expr SLASH right=expr

Gotos:
```

