<?php

namespace Vex6\Phppdf\Services;

/**
 * Service pour générer le contenu principal de l'ebook (chapitres)
 */
class MainContentGenerator
{
    /**
     * Générer le HTML du contenu principal
     */
    public function generateMainContentHtml(array $sections, array $acronymLinks = []): string
    {
        $html = '';

        // 1. Introduction (si présente)
        if (!empty($sections['introduction'])) {
            $html .= $this->generateSectionHtml($sections['introduction'], 'introduction', $acronymLinks);
        }

        // 2. Chapitres principaux
        if (!empty($sections['main_chapters'])) {
            $html .= $this->generateSectionHtml($sections['main_chapters'], 'main-chapters', $acronymLinks);
        }

        // 3. Conclusion (si présente)
        if (!empty($sections['conclusion'])) {
            $html .= $this->generateSectionHtml($sections['conclusion'], 'conclusion', $acronymLinks);
        }

        return $html;
    }

    /**
     * Générer le HTML d'une section
     */
    private function generateSectionHtml(array $blocks, string $sectionId, array $acronymLinks = []): string
    {
        $html = '';
        $currentPage = 0;

        foreach ($blocks as $block) {
            $tag = $block['tag'];
            $text = $block['text'];

            // Nouvelle page pour chaque TITRE1
            if ($tag === 'TITRE1') {
                if ($currentPage > 0) {
                    $html .= '</div>'; // Fermer la page précédente
                }
                $currentPage++;
                $pageId = $sectionId . '-page-' . $currentPage;
                $html .= '<div class="page page-content" id="' . $pageId . '">';
                $html .= '<div class="content-watermark"><img src="{{WATERMARK_IMAGE}}" alt=""></div>';
                $html .= '<div class="content-container page-container">';
            }

            // Injecter les liens de glossaire si applicable
            if ($this->shouldInjectGlossaryLinks($tag) && !empty($acronymLinks)) {
                $text = $this->injectGlossaryLinks($text, $acronymLinks);
            }

            // Générer le HTML selon le type de contenu
            $html .= $this->generateBlockHtml($tag, $text);
        }

        // Fermer la dernière page si nécessaire
        if ($currentPage > 0) {
            $html .= '</div></div>'; // Fermer content-container et page
        }

        return $html;
    }

    /**
     * Générer le HTML pour un bloc de contenu
     */
    private function generateBlockHtml(string $tag, string $text): string
    {
        $escapedText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        switch ($tag) {
            case 'TITRE1':
                $anchorId = $this->generateAnchorId($text);
                return '<a id="' . $anchorId . '" name="' . $anchorId . '"></a>' .
                       '<h1 class="chapter-title">' . $escapedText . '</h1>';
                
            case 'TITRE2':
                $anchorId = $this->generateAnchorId($text);
                return '<a id="' . $anchorId . '" name="' . $anchorId . '"></a>' .
                       '<h2 class="chapter-subtitle">' . $escapedText . '</h2>';
                
            case 'NORMAL':
                return '<p class="content-paragraph">' . $text . '</p>'; // Text déjà échappé ou avec liens
                
            default:
                return '<p class="content-paragraph">' . $escapedText . '</p>';
        }
    }

    /**
     * Générer un ID d'ancre à partir du texte
     */
    private function generateAnchorId(string $text): string
    {
        // Nettoyer le texte pour créer un ID valide
        $id = strtolower($text);
        $id = preg_replace('/[^a-z0-9\s-]/', '', $id);
        $id = preg_replace('/\s+/', '-', $id);
        $id = trim($id, '-');
        
        // Limiter la longueur
        if (strlen($id) > 50) {
            $id = substr($id, 0, 50);
            $id = rtrim($id, '-');
        }
        
        return $id ?: 'content-' . uniqid();
    }

    /**
     * Déterminer si on doit injecter les liens de glossaire
     */
    private function shouldInjectGlossaryLinks(string $tag): bool
    {
        // Ne pas injecter dans les entrées de glossaire elles-mêmes
        return !in_array($tag, ['ENTREE_GLOSSAIRE']);
    }

    /**
     * Injecter les liens de glossaire dans le texte
     */
    private function injectGlossaryLinks(string $text, array $acronymLinks): string
    {
        if (empty($acronymLinks)) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // Créer le pattern regex pour tous les acronymes
        $acronyms = array_keys($acronymLinks);
        $escapedAcronyms = array_map('preg_quote', $acronyms);
        $pattern = '/(\s*)(\b(?:' . implode('|', $escapedAcronyms) . ')\b)(\s*)/u';

        $result = preg_replace_callback($pattern, function($match) use ($acronymLinks) {
            $spaceBefore = $match[1];
            $acronym = $match[2];
            $spaceAfter = $match[3];

            if (!isset($acronymLinks[$acronym])) {
                return htmlspecialchars($match[0], ENT_QUOTES, 'UTF-8');
            }

            $linkInfo = $acronymLinks[$acronym];
            $link = '<a href="#' . $linkInfo['anchor'] . '" class="glossary-link">' . 
                   htmlspecialchars($acronym, ENT_QUOTES, 'UTF-8') . 
                   '</a>';

            return htmlspecialchars($spaceBefore, ENT_QUOTES, 'UTF-8') . 
                   $link . 
                   htmlspecialchars($spaceAfter, ENT_QUOTES, 'UTF-8');
        }, $text);

        // Échapper le reste du texte qui n'a pas été traité
        return $result;
    }

    /**
     * Extraire les entrées TOC depuis les sections
     */
    public function extractTOCEntries(array $sections): array
    {
        $tocEntries = [];
        $pageNumber = 6; // Commencer après couverture, licence, TOC et introduction

        // Introduction
        if (!empty($sections['introduction'])) {
            $pageNumber = $this->addSectionToTOC($sections['introduction'], $tocEntries, $pageNumber);
        }

        // Chapitres principaux
        if (!empty($sections['main_chapters'])) {
            $pageNumber = $this->addSectionToTOC($sections['main_chapters'], $tocEntries, $pageNumber);
        }

        // Conclusion
        if (!empty($sections['conclusion'])) {
            $pageNumber = $this->addSectionToTOC($sections['conclusion'], $tocEntries, $pageNumber);
        }

        return $tocEntries;
    }

    /**
     * Ajouter une section à la TOC
     */
    private function addSectionToTOC(array $blocks, array &$tocEntries, int $startPage): int
    {
        $currentPage = $startPage;

        foreach ($blocks as $block) {
            $tag = $block['tag'];
            $text = $block['text'];

            if ($tag === 'TITRE1') {
                $tocEntries[] = [
                    'title' => $text,
                    'level' => 'TITRE1',
                    'page' => $currentPage,
                    'anchor' => $this->generateAnchorId($text)
                ];
                $currentPage++;
            } elseif ($tag === 'TITRE2') {
                $tocEntries[] = [
                    'title' => $text,
                    'level' => 'TITRE2',
                    'page' => $currentPage,
                    'anchor' => $this->generateAnchorId($text)
                ];
            }
        }

        return $currentPage;
    }

    /**
     * Compter le nombre de pages du contenu principal
     */
    public function countContentPages(array $sections): int
    {
        $pageCount = 0;

        $allSections = [
            $sections['introduction'] ?? [],
            $sections['main_chapters'] ?? [],
            $sections['conclusion'] ?? []
        ];

        foreach ($allSections as $section) {
            foreach ($section as $block) {
                if ($block['tag'] === 'TITRE1') {
                    $pageCount++;
                }
            }
        }

        return max(1, $pageCount);
    }
}