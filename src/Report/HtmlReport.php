<?php

declare(strict_types=1);

namespace Phison\Report;

use Phison\Grammar\Grammar;
use Phison\Lalr\ItemSetCollection;
use Phison\Lalr\ParseTable;

final class HtmlReport
{
    public function render(Grammar $grammar, ItemSetCollection $collection, ParseTable $table): string
    {
        $markdown = (new MarkdownReport())->render($grammar, $collection, $table);
        $body = $this->markdownToHtml($markdown);

        return '<!doctype html>' . "\n"
            . '<html lang="en">' . "\n"
            . '<head>' . "\n"
            . '  <meta charset="utf-8">' . "\n"
            . '  <title>Parser Report: ' . self::escape($grammar->name) . '</title>' . "\n"
            . '  <style>body{font-family:system-ui,sans-serif;line-height:1.45;max-width:1100px;margin:2rem auto;padding:0 1rem;color:#172033}code,pre{background:#f6f8fa;border-radius:4px}code{padding:.1rem .25rem}pre{padding:1rem;overflow:auto}h1,h2,h3{line-height:1.2}li{margin:.25rem 0}</style>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . $body
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    private function markdownToHtml(string $markdown): string
    {
        $html = [];
        $codeLines = [];
        $inCodeBlock = false;
        $inList = false;

        foreach (explode("\n", trim($markdown)) as $line) {
            if ($inCodeBlock) {
                if ($line === '```') {
                    $html[] = '<pre><code>' . self::escape(implode("\n", $codeLines)) . '</code></pre>';
                    $codeLines = [];
                    $inCodeBlock = false;
                    continue;
                }

                $codeLines[] = $line;
                continue;
            }

            if ($line === '```text') {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }

                $inCodeBlock = true;
                continue;
            }

            if ($line === '') {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }

                continue;
            }

            $html[] = $this->lineToHtml($line, $inList);
        }

        if ($inCodeBlock) {
            $html[] = '<pre><code>' . self::escape(implode("\n", $codeLines)) . '</code></pre>';
        }

        if ($inList) {
            $html[] = '</ul>';
        }

        return implode("\n", $html) . "\n";
    }

    private function lineToHtml(string $line, bool &$inList): string
    {
        if (str_starts_with($line, '- ')) {
            if (!$inList) {
                $inList = true;
                return '<ul>' . "\n" . '<li>' . self::inline(substr($line, 2)) . '</li>';
            }

            return '<li>' . self::inline(substr($line, 2)) . '</li>';
        }

        if ($inList) {
            $inList = false;
            return '</ul>' . "\n" . $this->lineToHtml($line, $inList);
        }

        if (str_starts_with($line, '### ')) {
            return '<h3>' . self::inline(substr($line, 4)) . '</h3>';
        }

        if (str_starts_with($line, '## ')) {
            return '<h2>' . self::inline(substr($line, 3)) . '</h2>';
        }

        if (str_starts_with($line, '# ')) {
            return '<h1>' . self::inline(substr($line, 2)) . '</h1>';
        }

        return '<p>' . self::inline($line) . '</p>';
    }

    private static function inline(string $text): string
    {
        $escaped = self::escape($text);

        return preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
