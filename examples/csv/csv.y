grammar Csv

namespace Example\Csv\Generated
parser CsvParser
start document

token FIELD display "field"
token COMMA display ","
token NEWLINE display "newline"

rule document {
    records trailing_newlines => php {
        return $v1;
    }
}

rule records {
    record => php {
        return [$v1];
    }

  | records NEWLINE record => php {
        $records = $v1;
        $records[] = $v3;
        return $records;
    }
}

rule record {
    fields => php {
        return $v1;
    }
}

rule fields {
    FIELD => php {
        return [$v1];
    }

  | fields COMMA FIELD => php {
        $fields = $v1;
        $fields[] = $v3;
        return $fields;
    }
}

rule trailing_newlines {
    NEWLINE trailing_newlines => php {
        return null;
    }

  | => php {
        return null;
    }
}
