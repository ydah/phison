grammar Config

namespace Example\Config\Generated
parser ConfigParser
start document

token SECTION display "section"
token IDENT display "identifier"
token STRING display "string"
token NUMBER display "number"
token BOOL display "boolean"
token EQUAL display "="
token NEWLINE display "newline"

rule document {
    entries => php {
        $config = [];
        $section = null;
        foreach ($v1 as $entry) {
            if ($entry === null) {
                continue;
            }

            if ($entry[0] === 'section') {
                $section = $entry[1];
                $config[$section] ??= [];
                continue;
            }

            if ($section === null) {
                $config[$entry[1]] = $entry[2];
                continue;
            }

            $config[$section][$entry[1]] = $entry[2];
        }

        return $config;
    }
}

rule entries {
    entry entries => php {
        $entries = $v2;
        array_unshift($entries, $v1);
        return $entries;
    }

  | => php {
        return [];
    }
}

rule entry {
    NEWLINE => php {
        return null;
    }

  | SECTION NEWLINE => php {
        return ['section', $v1];
    }

  | IDENT EQUAL value NEWLINE => php {
        return ['pair', $v1, $v3];
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

  | BOOL => php {
        return $v1;
    }
}
