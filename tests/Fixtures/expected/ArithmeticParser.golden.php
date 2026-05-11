<?php

declare(strict_types=1);

namespace Example\Arithmetic\Generated;

use Phison\Runtime\ParseError;
use Phison\Runtime\ParserInterface;
use Phison\Runtime\SourceRange;
use Phison\Runtime\TokenStreamInterface;

final class ArithmeticParser implements ParserInterface
{
    public const T_EOF = 0;
    public const T_NUMBER = 1;
    public const T_PLUS = 2;
    public const T_MINUS = 3;
    public const T_STAR = 4;
    public const T_SLASH = 5;
    public const T_LPAREN = 6;
    public const T_RPAREN = 7;

    private const TOKEN_NAMES = array (
      0 => 'EOF',
      1 => 'NUMBER',
      2 => 'PLUS',
      3 => 'MINUS',
      4 => 'STAR',
      5 => 'SLASH',
      6 => 'LPAREN',
      7 => 'RPAREN',
    );
    private const TOKEN_DISPLAY = array (
      0 => 'EOF',
      1 => 'number',
      2 => '+',
      3 => '-',
      4 => '*',
      5 => '/',
      6 => '(',
      7 => ')',
    );
    private const PRODUCTION_LENGTH = array (
      0 => 2,
      1 => 3,
      2 => 3,
      3 => 3,
      4 => 3,
      5 => 2,
      6 => 3,
      7 => 1,
    );
    private const PRODUCTION_LHS = array (
      0 => 0,
      1 => 1,
      2 => 1,
      3 => 1,
      4 => 1,
      5 => 1,
      6 => 1,
      7 => 1,
    );
    private const EXPECTED = array (
      0 => 
      array (
        0 => 1,
        1 => 3,
        2 => 6,
      ),
      1 => 
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
      ),
      2 => 
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
      ),
      3 => 
      array (
        0 => 1,
        1 => 3,
        2 => 6,
      ),
      4 => 
      array (
        0 => 1,
        1 => 3,
        2 => 6,
      ),
      5 => 
      array (
        0 => 0,
      ),
      6 => 
      array (
        0 => 1,
        1 => 3,
        2 => 6,
      ),
      7 => 
      array (
        0 => 1,
        1 => 3,
        2 => 6,
      ),
      8 => 
      array (
        0 => 1,
        1 => 3,
        2 => 6,
      ),
      9 => 
      array (
        0 => 1,
        1 => 3,
        2 => 6,
      ),
      10 => 
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
      ),
      11 => 
      array (
        0 => 2,
        1 => 3,
        2 => 4,
        3 => 5,
        4 => 7,
      ),
      12 => 
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
      ),
      13 => 
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
      ),
      14 => 
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
      ),
      15 => 
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
      ),
      16 => 
      array (
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 4,
        4 => 5,
        5 => 7,
      ),
    );

    public function parse(TokenStreamInterface $tokens, mixed $context = null): mixed
    {
        $stateStack = [0];
        $valueStack = [];
        $locationStack = [];
        $tokenStack = [];
        $lookahead = $tokens->current();

        while (true) {
            $state = $stateStack[array_key_last($stateStack)];
            $tokenId = $lookahead->id();
            $action = $this->action($state, $tokenId);

            if ($action === null) {
                throw new ParseError(
                    $lookahead,
                    $this->expectedNames($state),
                    $tokens->previousTokens(5),
                    $tokens->nextTokens(5),
                );
            }

            if ($action > 0) {
                $stateStack[] = $action - 1;
                $valueStack[] = $lookahead->value();
                $locationStack[] = $lookahead->location();
                $tokenStack[] = $lookahead;
                if ($tokenId !== self::T_EOF) {
                    $tokens->advance();
                    $lookahead = $tokens->current();
                }
                continue;
            }

            if ($action < 0) {
                $rule = -$action - 1;
                $length = self::PRODUCTION_LENGTH[$rule];
                $rhsValues = $length === 0 ? [] : array_slice($valueStack, -$length);
                $rhsTokens = $length === 0 ? [] : array_slice($tokenStack, -$length);
                $rhsLocations = $length === 0 ? [] : array_slice($locationStack, -$length);

                for ($i = 0; $i < $length; $i++) {
                    array_pop($stateStack);
                    array_pop($valueStack);
                    array_pop($locationStack);
                    array_pop($tokenStack);
                }

                $value = $this->reduce($rule, $rhsValues, $rhsTokens, $rhsLocations, $context);
                $currentState = $stateStack[array_key_last($stateStack)];
                $lhs = self::PRODUCTION_LHS[$rule];
                $goto = $this->gotoState($currentState, $lhs);
                if ($goto === null) {
                    throw new \LogicException('Missing goto for state ' . $currentState . ' and lhs ' . $lhs . '.');
                }

                $stateStack[] = $goto;
                $valueStack[] = $value;
                $locationStack[] = $length === 0
                    ? $lookahead->location()
                    : SourceRange::merge($rhsLocations[0] ?? null, $rhsLocations[$length - 1] ?? null);
                $tokenStack[] = null;
                continue;
            }

            $acceptLength = self::PRODUCTION_LENGTH[0];
            return $acceptLength <= 1
                ? ($valueStack[array_key_last($valueStack)] ?? null)
                : ($valueStack[count($valueStack) - $acceptLength] ?? null);
        }
    }

    private const ACTION = array (
      0 => 
      array (
        1 => 3,
        3 => 4,
        6 => 5,
      ),
      1 => 
      array (
        0 => 6,
        2 => 7,
        3 => 8,
        4 => 9,
        5 => 10,
      ),
      2 => 
      array (
        0 => -8,
        2 => -8,
        3 => -8,
        4 => -8,
        5 => -8,
        7 => -8,
      ),
      3 => 
      array (
        1 => 3,
        3 => 4,
        6 => 5,
      ),
      4 => 
      array (
        1 => 3,
        3 => 4,
        6 => 5,
      ),
      5 => 
      array (
        0 => 0,
      ),
      6 => 
      array (
        1 => 3,
        3 => 4,
        6 => 5,
      ),
      7 => 
      array (
        1 => 3,
        3 => 4,
        6 => 5,
      ),
      8 => 
      array (
        1 => 3,
        3 => 4,
        6 => 5,
      ),
      9 => 
      array (
        1 => 3,
        3 => 4,
        6 => 5,
      ),
      10 => 
      array (
        0 => -6,
        2 => -6,
        3 => -6,
        4 => -6,
        5 => -6,
        7 => -6,
      ),
      11 => 
      array (
        2 => 7,
        3 => 8,
        4 => 9,
        5 => 10,
        7 => 17,
      ),
      12 => 
      array (
        0 => -2,
        2 => -2,
        3 => -2,
        4 => 9,
        5 => 10,
        7 => -2,
      ),
      13 => 
      array (
        0 => -3,
        2 => -3,
        3 => -3,
        4 => 9,
        5 => 10,
        7 => -3,
      ),
      14 => 
      array (
        0 => -4,
        2 => -4,
        3 => -4,
        4 => -4,
        5 => -4,
        7 => -4,
      ),
      15 => 
      array (
        0 => -5,
        2 => -5,
        3 => -5,
        4 => -5,
        5 => -5,
        7 => -5,
      ),
      16 => 
      array (
        0 => -7,
        2 => -7,
        3 => -7,
        4 => -7,
        5 => -7,
        7 => -7,
      ),
    );
    private const GOTO = array (
      0 => 
      array (
        1 => 1,
      ),
      3 => 
      array (
        1 => 10,
      ),
      4 => 
      array (
        1 => 11,
      ),
      6 => 
      array (
        1 => 12,
      ),
      7 => 
      array (
        1 => 13,
      ),
      8 => 
      array (
        1 => 14,
      ),
      9 => 
      array (
        1 => 15,
      ),
    );

    private function action(int $state, int $token): ?int
    {
        return self::ACTION[$state][$token] ?? null;
    }

    private function gotoState(int $state, int $nonTerminal): ?int
    {
        return self::GOTO[$state][$nonTerminal] ?? null;
    }

    /**
     * @return list<string>
     */
    private function expectedNames(int $state): array
    {
        $names = [];
        foreach (self::EXPECTED[$state] ?? [] as $tokenId) {
            $names[] = self::TOKEN_DISPLAY[$tokenId] ?? self::TOKEN_NAMES[$tokenId] ?? (string) $tokenId;
        }

        return $names;
    }

    private function reduce(int $rule, array $values, array $tokens, array $locations, mixed $context): mixed
    {
        switch ($rule) {
            case 1:
                $v1 = $values[0] ?? null;
                $t1 = $tokens[0] ?? null;
                $loc1 = $locations[0] ?? null;
                $left = $v1;
                $v2 = $values[1] ?? null;
                $t2 = $tokens[1] ?? null;
                $loc2 = $locations[1] ?? null;
                $v3 = $values[2] ?? null;
                $t3 = $tokens[2] ?? null;
                $loc3 = $locations[2] ?? null;
                $right = $v3;
                $yyval = null;
                $yyval = $left + $right;
                return $yyval;
            case 2:
                $v1 = $values[0] ?? null;
                $t1 = $tokens[0] ?? null;
                $loc1 = $locations[0] ?? null;
                $left = $v1;
                $v2 = $values[1] ?? null;
                $t2 = $tokens[1] ?? null;
                $loc2 = $locations[1] ?? null;
                $v3 = $values[2] ?? null;
                $t3 = $tokens[2] ?? null;
                $loc3 = $locations[2] ?? null;
                $right = $v3;
                $yyval = null;
                $yyval = $left - $right;
                return $yyval;
            case 3:
                $v1 = $values[0] ?? null;
                $t1 = $tokens[0] ?? null;
                $loc1 = $locations[0] ?? null;
                $left = $v1;
                $v2 = $values[1] ?? null;
                $t2 = $tokens[1] ?? null;
                $loc2 = $locations[1] ?? null;
                $v3 = $values[2] ?? null;
                $t3 = $tokens[2] ?? null;
                $loc3 = $locations[2] ?? null;
                $right = $v3;
                $yyval = null;
                $yyval = $left * $right;
                return $yyval;
            case 4:
                $v1 = $values[0] ?? null;
                $t1 = $tokens[0] ?? null;
                $loc1 = $locations[0] ?? null;
                $left = $v1;
                $v2 = $values[1] ?? null;
                $t2 = $tokens[1] ?? null;
                $loc2 = $locations[1] ?? null;
                $v3 = $values[2] ?? null;
                $t3 = $tokens[2] ?? null;
                $loc3 = $locations[2] ?? null;
                $right = $v3;
                $yyval = null;
                $yyval = $left / $right;
                return $yyval;
            case 5:
                $v1 = $values[0] ?? null;
                $t1 = $tokens[0] ?? null;
                $loc1 = $locations[0] ?? null;
                $v2 = $values[1] ?? null;
                $t2 = $tokens[1] ?? null;
                $loc2 = $locations[1] ?? null;
                $value = $v2;
                $yyval = null;
                $yyval = -$value;
                return $yyval;
            case 6:
                $v1 = $values[0] ?? null;
                $t1 = $tokens[0] ?? null;
                $loc1 = $locations[0] ?? null;
                $v2 = $values[1] ?? null;
                $t2 = $tokens[1] ?? null;
                $loc2 = $locations[1] ?? null;
                $value = $v2;
                $v3 = $values[2] ?? null;
                $t3 = $tokens[2] ?? null;
                $loc3 = $locations[2] ?? null;
                $yyval = null;
                $yyval = $value;
                return $yyval;
            case 7:
                $v1 = $values[0] ?? null;
                $t1 = $tokens[0] ?? null;
                $loc1 = $locations[0] ?? null;
                $n = $v1;
                $yyval = null;
                $yyval = (int) $n;
                return $yyval;
            default:
                throw new \LogicException('Unknown reduction rule: ' . (string) $rule);
        }
    }
}
