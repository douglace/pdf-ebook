<?php

namespace Vex6\Phppdf\Services;

/**
 * Service pour générer la section suggestions d'autres Smartbooks
 */
class SuggestionsGenerator
{
    /**
     * Générer le HTML de la section suggestions
     */
    public function generateSuggestionsHtml(array $suggestionsBlocks, array $acronymLinks = []): string
    {
        if (empty($suggestionsBlocks)) {
            return '';
        }

        $html = '<div class="page page-suggestions">';
        $html .= '<div class="suggestions-watermark"><img src="{{WATERMARK_IMAGE}}" alt=""></div>';
        $html .= '<div class="suggestions-container page-container">';
        
        // Titre de la section
        $html .= '<a id="suggestions" name="suggestions"></a>';
        $html .= '<h1 class="section-title">SUGGESTIONS</h1>';

        // Introduction standard
        $html .= '<p class="suggestions-intro">';
        $html .= 'Pour approfondir le sujet, nous vous suggérons d\'utiliser Encyclo-AI pour créer les ';
        $html .= 'Smartbooks suivants. Le titre et la synthèse proposés pourront être utilisés pour configurer ';
        $html .= 'la génération d\'un nouveau Smartbook par Encyclo-AI.';
        $html .= '</p>';

        // Générer les suggestions
        $currentSuggestion = null;
        
        foreach ($suggestionsBlocks as $block) {
            $tag = $block['tag'];
            $text = $block['text'];

            // Ignorer le titre SUGGESTIONS lui-même
            if ($tag === 'TITRE1' && stripos($text, 'SUGGESTIONS') !== false) {
                continue;
            }

            // Injecter les liens de glossaire si applicable
            if ($this->shouldInjectGlossaryLinks($tag) && !empty($acronymLinks)) {
                $text = $this->injectGlossaryLinks($text, $acronymLinks);
            }

            $html .= $this->generateSuggestionBlockHtml($tag, $text);
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Générer le HTML pour un bloc de suggestion
     */
    private function generateSuggestionBlockHtml(string $tag, string $text): string
    {
        switch ($tag) {
            case 'ENTREE_BIBLIOGRAPHIE':
                // Titre de la suggestion
                return '<div class="suggestion-entry">' .
                       '<h3 class="suggestion-title">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</h3>';
                
            case 'NORMAL':
                // Description/synthèse de la suggestion
                return '<p class="suggestion-description">' . $text . '</p>' .
                       '</div>'; // Fermer l'entrée
                
            default:
                return '<p class="suggestion-text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }

    /**
     * Déterminer si on doit injecter les liens de glossaire
     */
    private function shouldInjectGlossaryLinks(string $tag): bool
    {
        // Injecter dans les descriptions mais pas dans les titres
        return $tag === 'NORMAL';
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

        return $result;
    }

    /**
     * Compter le nombre de suggestions
     */
    public function countSuggestions(array $suggestionsBlocks): int
    {
        $suggestionCount = 0;

        foreach ($suggestionsBlocks as $block) {
            $tag = $block['tag'];
            $text = $block['text'];

            // Compter les entrées de suggestion (ENTREE_BIBLIOGRAPHIE qui ne sont pas le titre)
            if ($tag === 'ENTREE_BIBLIOGRAPHIE') {
                // Ignorer le titre SUGGESTIONS lui-même
                if (!(stripos($text, 'SUGGESTIONS') !== false)) {
                    $suggestionCount++;
                }
            }
        }

        return $suggestionCount;
    }

    /**
     * Extraire les suggestions sous forme de liste structurée
     */
    public function extractSuggestionsData(array $suggestionsBlocks): array
    {
        $suggestions = [];
        $currentSuggestion = null;

        foreach ($suggestionsBlocks as $block) {
            $tag = $block['tag'];
            $text = $block['text'];

            // Ignorer le titre SUGGESTIONS lui-même
            if ($tag === 'TITRE1' && stripos($text, 'SUGGESTIONS') !== false) {
                continue;
            }

            if ($tag === 'ENTREE_BIBLIOGRAPHIE') {
                // Sauvegarder la suggestion précédente si elle existe
                if ($currentSuggestion !== null) {
                    $suggestions[] = $currentSuggestion;
                }
                
                // Commencer une nouvelle suggestion
                $currentSuggestion = [
                    'title' => $text,
                    'description' => ''
                ];
            } elseif ($tag === 'NORMAL' && $currentSuggestion !== null) {
                // Ajouter la description à la suggestion courante
                $currentSuggestion['description'] = $text;
            }
        }

        // Ajouter la dernière suggestion
        if ($currentSuggestion !== null) {
            $suggestions[] = $currentSuggestion;
        }

        return $suggestions;
    }

    /**
     * Valider les données de suggestions
     */
    public function validateSuggestionsData(array $suggestionsBlocks): array
    {
        $suggestions = $this->extractSuggestionsData($suggestionsBlocks);
        $validation = [
            'valid' => true,
            'issues' => [],
            'stats' => [
                'total' => count($suggestions),
                'with_descriptions' => 0,
                'without_descriptions' => 0
            ]
        ];

        foreach ($suggestions as $index => $suggestion) {
            if (empty($suggestion['title'])) {
                $validation['valid'] = false;
                $validation['issues'][] = "Suggestion #" . ($index + 1) . ": titre manquant";
            }

            if (empty($suggestion['description'])) {
                $validation['stats']['without_descriptions']++;
                $validation['issues'][] = "Suggestion #" . ($index + 1) . ": description manquante";
            } else {
                $validation['stats']['with_descriptions']++;
            }
        }

        return $validation;
    }

    /**
     * Obtenir les statistiques des suggestions
     */
    public function getSuggestionsStats(array $suggestionsBlocks): array
    {
        $suggestions = $this->extractSuggestionsData($suggestionsBlocks);
        
        $stats = [
            'total_suggestions' => count($suggestions),
            'total_blocks' => count($suggestionsBlocks),
            'with_descriptions' => 0,
            'average_description_length' => 0,
            'longest_title' => '',
            'shortest_title' => ''
        ];

        $totalDescriptionLength = 0;
        $titleLengths = [];

        foreach ($suggestions as $suggestion) {
            if (!empty($suggestion['description'])) {
                $stats['with_descriptions']++;
                $totalDescriptionLength += strlen($suggestion['description']);
            }

            if (!empty($suggestion['title'])) {
                $titleLengths[] = strlen($suggestion['title']);
                
                if (empty($stats['longest_title']) || strlen($suggestion['title']) > strlen($stats['longest_title'])) {
                    $stats['longest_title'] = $suggestion['title'];
                }
                
                if (empty($stats['shortest_title']) || strlen($suggestion['title']) < strlen($stats['shortest_title'])) {
                    $stats['shortest_title'] = $suggestion['title'];
                }
            }
        }

        if ($stats['with_descriptions'] > 0) {
            $stats['average_description_length'] = round($totalDescriptionLength / $stats['with_descriptions']);
        }

        return $stats;
    }
}