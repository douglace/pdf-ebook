<?php

namespace Vex6\Phppdf\Services;

class HtmlCleaner
{
    public static function removeEmptyPages(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);

        $pages = [
            'quiz-page',
            'glossary-page',
            'wikipedia-page',
            'sites-web-page',
            'suggestions-page',
        ];

        foreach ($pages as $page){
            $nodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' $page ')]
            [not(.//*[contains(concat(' ', normalize-space(@class), ' '), ' page-container ')])]");
    
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        
        return $dom->saveHTML();
    }
}