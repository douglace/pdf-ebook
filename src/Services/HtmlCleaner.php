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

    /**
     * Nettoyer le TOC en supprimant les entrées correspondant aux pages qui n'existent pas
     */
    public static function cleanTOC(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Mapping des classes de pages vers les titres TOC
        $pageToTOCMapping = [
            'sites-web-page' => 'SITES WEB',
            'suggestions-page' => 'SUGGESTIONS', 
            'wikipedia-page' => 'WIKIPEDIA',
            'glossary-page' => 'GLOSSAIRE'
        ];

        foreach ($pageToTOCMapping as $pageClass => $tocTitle) {
            // Vérifier si la page existe
            $pageExists = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' $pageClass ')]")->length > 0;
            
            
            if (!$pageExists) {
                // Supprimer l'entrée TOC correspondante
                $tocEntries = $xpath->query("//div[contains(@class, 'toc-entry-')]");
                
                foreach ($tocEntries as $entry) {
                    $titleSpan = $xpath->query(".//span[@class='toc-entry-title']", $entry);
                    if ($titleSpan->length > 0) {
                        $title = trim($titleSpan->item(0)->textContent);
                        if (stripos($title, $tocTitle) !== false) {
                            $entry->parentNode->removeChild($entry);
                            break;
                        }
                    }
                }
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Mettre à jour la numérotation de la TOC avec les vrais numéros de page
     * basés sur les ancres trouvées dans le HTML généré
     */
    public static function updateTOCPageNumbers(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Étape 1 : Parcourir toutes les pages et mapper les ancres avec leurs numéros de page
        $anchorToPageMap = [];
        // Exclure page-number, ne garder que les vraies pages (divs avec class contenant " page " mais pas "page-number")
        $pages = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' page ') and not(contains(@class, 'page-number'))]");
        $pageNumber = 1;

        foreach ($pages as $page) {
            // Vérifier si la page elle-même a un ID
            $pageId = $page->getAttribute('id');
            if (!empty($pageId)) {
                $anchorToPageMap[$pageId] = $pageNumber;
            }

            // Trouver tous les éléments avec un ID (ancres) dans cette page
            $anchors = $xpath->query(".//*[@id]", $page);

            foreach ($anchors as $anchor) {
                $anchorId = $anchor->getAttribute('id');
                if (!empty($anchorId)) {
                    $anchorToPageMap[$anchorId] = $pageNumber;
                }
            }

            $pageNumber++;
        }

        // Étape 2 : Mettre à jour les numéros de page dans la TOC
        // Trouver tous les liens TOC avec href="#..."
        $tocLinks = $xpath->query("//div[contains(@class, 'toc-entry-')]//a[@href]");

        foreach ($tocLinks as $link) {
            $href = $link->getAttribute('href');

            // Extraire l'ancre (enlever le #)
            if (strpos($href, '#') === 0) {
                $anchorId = substr($href, 1);

                // Si on a trouvé cette ancre dans notre mapping
                if (isset($anchorToPageMap[$anchorId])) {
                    $correctPageNumber = $anchorToPageMap[$anchorId];

                    // Trouver l'élément parent qui contient le numéro de page
                    $parentEntry = $link->parentNode;
                    $pageSpans = $xpath->query(".//span[@class='toc-page']", $parentEntry);

                    if ($pageSpans->length > 0) {
                        $pageSpan = $pageSpans->item(0);
                        $pageSpan->textContent = $correctPageNumber;
                    }
                }
            }
        }

        // Étape 3 : Gérer les entrées TOC sans lien (GLOSSAIRE, etc.)
        // Pour ces cas, on cherche directement par titre ou on utilise une logique spéciale
        $tocEntriesWithoutLink = $xpath->query("//div[contains(@class, 'toc-entry-')]//span[@class='toc-entry-title' and not(parent::a)]");

        foreach ($tocEntriesWithoutLink as $titleSpan) {
            $title = trim($titleSpan->textContent);

            // Mapping spécial pour les sections sans ancre
            $specialMapping = [
                'GLOSSAIRE' => 'glossary-page',
                'QUIZ' => 'quiz-page',
                'WIKIPEDIA' => 'wikipedia-page',
                'SITES WEB' => 'sites-web-page',
                'SUGGESTIONS' => 'suggestions-page'
            ];

            foreach ($specialMapping as $tocTitle => $pageClass) {
                if (stripos($title, $tocTitle) !== false) {
                    // Chercher la page contenant cette section par classe
                    $sectionPages = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' $pageClass ')]");

                    if ($sectionPages->length > 0) {
                        // Compter à quelle position se trouve cette page
                        $targetPage = $sectionPages->item(0);
                        $allPages = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' page ') and not(contains(@class, 'page-number'))]");

                        for ($i = 0; $i < $allPages->length; $i++) {
                            if ($allPages->item($i)->isSameNode($targetPage)) {
                                $correctPageNumber = $i + 1;

                                // Mettre à jour le numéro de page
                                $parentEntry = $titleSpan->parentNode;
                                $pageSpans = $xpath->query(".//span[@class='toc-page']", $parentEntry);

                                if ($pageSpans->length > 0) {
                                    $pageSpan = $pageSpans->item(0);
                                    $pageSpan->textContent = $correctPageNumber;
                                }
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }

        return $dom->saveHTML();
    }
}