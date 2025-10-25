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
    
    public static function addPageNumbers(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        
        // Exclure la page de couverture et les pages TOC
        $pages = $xpath->query("
            //div[contains(concat(' ', normalize-space(@class), ' '), ' page ')
                and not(contains(., 'cover-title'))
                and not(contains(., 'TOC_CONTENT'))
            ]
        ");

        $pageNumber = 1;
        
        foreach ($pages as $page) {
            $classes = explode(' ', $page->getAttribute('class'));	
            if (in_array('toc-page', $classes) || in_array('cover-page', $classes)) {
                $pageNumber++;
                continue;
            }
            // Créer l'élément de numérotation
            $pageNumDiv = $dom->createElement('div');
            $pageNumDiv->setAttribute('class', 'page-number');
            $pageNumDiv->textContent = $pageNumber;
            
            // Ajouter à la fin de la page
            $page->appendChild($pageNumDiv);
            
            $pageNumber++;
        }
        
        return $dom->saveHTML();
    }
}