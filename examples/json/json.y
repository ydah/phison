grammar Json

namespace Example\Json\Generated
parser JsonParser
start document

token LBRACE display "{"
token RBRACE display "}"
token LBRACKET display "["
token RBRACKET display "]"
token COLON display ":"
token COMMA display ","
token STRING display "string"
token NUMBER display "number"
token TRUE display "true"
token FALSE display "false"
token NULL display "null"

rule document {
    value => php {
        return $v1;
    }
}

rule value {
    object => php {
        return $v1;
    }

  | array => php {
        return $v1;
    }

  | STRING => php {
        return $v1;
    }

  | NUMBER => php {
        return $v1;
    }

  | TRUE => php {
        return true;
    }

  | FALSE => php {
        return false;
    }

  | NULL => php {
        return null;
    }
}

rule object {
    LBRACE members_opt RBRACE => php {
        return $v2;
    }
}

rule members_opt {
    members => php {
        return $v1;
    }

  | => php {
        return [];
    }
}

rule members {
    member => php {
        return [$v1[0] => $v1[1]];
    }

  | members COMMA member => php {
        $members = $v1;
        $members[$v3[0]] = $v3[1];
        return $members;
    }
}

rule member {
    STRING COLON value => php {
        return [$v1, $v3];
    }
}

rule array {
    LBRACKET elements_opt RBRACKET => php {
        return $v2;
    }
}

rule elements_opt {
    elements => php {
        return $v1;
    }

  | => php {
        return [];
    }
}

rule elements {
    value => php {
        return [$v1];
    }

  | elements COMMA value => php {
        $elements = $v1;
        $elements[] = $v3;
        return $elements;
    }
}
