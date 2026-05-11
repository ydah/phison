grammar Arithmetic

namespace Example\Arithmetic\Generated
parser ArithmeticParser
start expr

token NUMBER display "number"
token PLUS display "+"
token MINUS display "-"
token STAR display "*"
token SLASH display "/"
token LPAREN display "("
token RPAREN display ")"

precedence left PLUS MINUS
precedence left STAR SLASH
precedence right UMINUS

rule expr {
    left=expr PLUS right=expr => php {
        return $left + $right;
    }

  | left=expr MINUS right=expr => php {
        return $left - $right;
    }

  | left=expr STAR right=expr => php {
        return $left * $right;
    }

  | left=expr SLASH right=expr => php {
        return $left / $right;
    }

  | MINUS value=expr %prec UMINUS => php {
        return -$value;
    }

  | LPAREN value=expr RPAREN => php {
        return $value;
    }

  | n=NUMBER => php {
        return (int) $n;
    }
}
