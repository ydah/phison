grammar Filter

namespace Example\Filter\Generated
parser FilterParser
start query

token IDENT display "identifier"
token STRING display "string"
token NUMBER display "number"
token COLON display ":"
token AND display "AND"
token OR display "OR"
token NOT display "NOT"
token LPAREN display "("
token RPAREN display ")"

precedence left OR
precedence left AND
precedence right NOT

rule query {
    expr => php {
        return $v1;
    }
}

rule expr {
    left=expr OR right=expr => php {
        return ['or', $left, $right];
    }

  | left=expr AND right=expr => php {
        return ['and', $left, $right];
    }

  | NOT value=expr %prec NOT => php {
        return ['not', $value];
    }

  | LPAREN value=expr RPAREN => php {
        return $value;
    }

  | term => php {
        return $v1;
    }
}

rule term {
    field=IDENT COLON value=value => php {
        return ['term', $field, $value];
    }

  | IDENT => php {
        return ['term', 'text', $v1];
    }
}

rule value {
    IDENT => php {
        return $v1;
    }

  | STRING => php {
        return $v1;
    }

  | NUMBER => php {
        return $v1;
    }
}
