<?php

declare(strict_types=1);

namespace Phison\Grammar;

final class GrammarValidator
{
    /**
     * @return list<GrammarIssue>
     */
    public function validate(Grammar $grammar): array
    {
        return [
            ...$this->unusedTokenIssues($grammar),
            ...$this->unreachableNonTerminalIssues($grammar),
            ...$this->nonProductiveNonTerminalIssues($grammar),
        ];
    }

    /**
     * @return list<GrammarIssue>
     */
    public function errors(Grammar $grammar): array
    {
        return array_values(array_filter(
            $this->validate($grammar),
            static fn (GrammarIssue $issue): bool => $issue->severity === GrammarIssue::ERROR,
        ));
    }

    /**
     * @return list<GrammarIssue>
     */
    private function unusedTokenIssues(Grammar $grammar): array
    {
        $used = [Grammar::EOF => true];
        foreach ($grammar->productions as $production) {
            foreach ($production->rhs as $reference) {
                if ($reference->symbol instanceof Terminal) {
                    $used[$reference->symbol->name] = true;
                }
            }
        }

        $issues = [];
        foreach ($grammar->terminalsByName as $name => $terminal) {
            if (!isset($used[$name])) {
                $issues[] = new GrammarIssue(GrammarIssue::WARNING, 'Unused token: ' . $terminal->name);
            }
        }

        return $issues;
    }

    /**
     * @return list<GrammarIssue>
     */
    private function unreachableNonTerminalIssues(Grammar $grammar): array
    {
        $reachable = [$grammar->start->id => true];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($grammar->productions as $production) {
                if (!isset($reachable[$production->lhs->id])) {
                    continue;
                }

                foreach ($production->rhs as $reference) {
                    if ($reference->symbol instanceof NonTerminal && !isset($reachable[$reference->symbol->id])) {
                        $reachable[$reference->symbol->id] = true;
                        $changed = true;
                    }
                }
            }
        }

        $issues = [];
        foreach ($grammar->nonTerminalsById as $nonTerminal) {
            if ($nonTerminal->name === Grammar::ACCEPT) {
                continue;
            }

            if (!isset($reachable[$nonTerminal->id])) {
                $issues[] = new GrammarIssue(GrammarIssue::ERROR, 'Unreachable non-terminal: ' . $nonTerminal->name);
            }
        }

        return $issues;
    }

    /**
     * @return list<GrammarIssue>
     */
    private function nonProductiveNonTerminalIssues(Grammar $grammar): array
    {
        $productive = [];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($grammar->productions as $production) {
                if (isset($productive[$production->lhs->id])) {
                    continue;
                }

                $rhsProductive = true;
                foreach ($production->rhs as $reference) {
                    if ($reference->symbol instanceof Terminal) {
                        continue;
                    }

                    if (!$reference->symbol instanceof NonTerminal || !isset($productive[$reference->symbol->id])) {
                        $rhsProductive = false;
                        break;
                    }
                }

                if ($rhsProductive) {
                    $productive[$production->lhs->id] = true;
                    $changed = true;
                }
            }
        }

        $issues = [];
        foreach ($grammar->nonTerminalsById as $nonTerminal) {
            if ($nonTerminal->name === Grammar::ACCEPT) {
                continue;
            }

            if (!isset($productive[$nonTerminal->id])) {
                $issues[] = new GrammarIssue(GrammarIssue::ERROR, 'Non-productive non-terminal: ' . $nonTerminal->name);
            }
        }

        return $issues;
    }
}
