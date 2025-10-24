<?php

declare(strict_types=1);

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

class SuggestionsGenerator
{
    /**
     * Extract suggestions content from JSON data
     *
     * @param EbookData $ebookData
     * @return array|null Array of suggestions entries or null if not found
     */
    public function extractSuggestionsContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();

        // The suggestions data has nested structure: suggestions > suggestions > array
        $suggestionsData = $rawData['suggestions'] ?? [];
        $suggestions = $suggestionsData['suggestions'] ?? [];

        if (empty($suggestions)) {
            return null;
        }

        $results = [];
        foreach ($suggestions as $suggestion) {
            $title = $suggestion['title'] ?? '';
            $description = $suggestion['description'] ?? '';

            if (!empty($title) || !empty($description)) {
                $results[] = [
                    'title' => $title,
                    'description' => $description
                ];
            }
        }

        return empty($results) ? null : $results;
    }
    
    /**
     * Generate HTML for suggestions pages
     * 
     * @param array|null $suggestionsContent
     * @param string $language
     * @return array Array of HTML strings for each page
     */
    public function generateSuggestionsHtml(?array $suggestionsContent, string $language): array
    {
        if ($suggestionsContent === null || empty($suggestionsContent)) {
            return ['', '', ''];
        }

        $pages = [];
        $currentPageHtml = '';
        $currentPageLength = 0;
        $currentPageNumber = 0;

        // Capacité estimée par page (en caractères)
        // Page 1 a moins d'espace car elle contient le titre et l'introduction
        $maxLengthPage1 = 2500;  // ~2500 caractères pour la première page
        $maxLengthOtherPages = 4000;  // ~4000 caractères pour les autres pages

        // Marge de sécurité pour éviter les débordements (85% de la capacité max)
        $safetyMargin = 0.85;

        foreach ($suggestionsContent as $index => $suggestion) {
            $title = htmlspecialchars($suggestion['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars($suggestion['description'] ?? '', ENT_QUOTES, 'UTF-8');

            // Calculer la longueur estimée de cette entrée
            $entryLength = strlen($title) + strlen($description);

            // Déterminer la capacité maximale pour la page actuelle
            $maxLength = ($currentPageNumber === 0) ? $maxLengthPage1 : $maxLengthOtherPages;
            $effectiveMaxLength = $maxLength * $safetyMargin;

            // Vérifier si l'ajout de cette entrée dépasserait la capacité
            // Mais toujours ajouter au moins une entrée par page
            if ($currentPageLength > 0 && ($currentPageLength + $entryLength) > $effectiveMaxLength) {
                // Sauvegarder la page actuelle
                $pages[] = $currentPageHtml;
                $currentPageHtml = '';
                $currentPageLength = 0;
                $currentPageNumber++;

                // Si on a déjà 3 pages, arrêter
                if (count($pages) >= 3) {
                    break;
                }
            }

            // Construire le HTML de l'entrée
            $entryHtml = '<div class="suggestions-entry">';

            if (!empty($title)) {
                $entryHtml .= '<h3 class="suggestions-entry-title">' . $title . '</h3>';
            }

            if (!empty($description)) {
                $entryHtml .= '<p class="suggestions-description">' . $description . '</p>';
            }

            $entryHtml .= '</div>';

            // Ajouter l'entrée à la page actuelle
            $currentPageHtml .= $entryHtml;
            $currentPageLength += $entryLength;
        }

        // Ajouter la dernière page si elle contient du contenu
        if (!empty($currentPageHtml) && count($pages) < 3) {
            $pages[] = $currentPageHtml;
        }

        // Ensure we have exactly 3 pages (pad with empty strings)
        while (count($pages) < 3) {
            $pages[] = '';
        }

        return array_slice($pages, 0, 3);
    }
    
    /**
     * Generate title HTML for the first suggestions page
     *
     * @param string $language
     * @return string
     */
    public function generateSuggestionsTitleHtml(string $language): string
    {
        $title = 'SUGGESTIONS';

        $introText = (strtolower($language) === 'english')
            ? "To delve deeper into the subject, we suggest using Encyclo-AI to create the following Smartbooks. The proposed title and summary can be used to configure the generation of a new Smartbook by Encyclo-AI."
            : "Pour approfondir le sujet, nous vous suggérons d'utiliser Encyclo-AI pour créer les Smartbooks suivants. Le titre et la synthèse proposés pourront être utilisés pour configurer la génération d'un nouveau Smartbook par Encyclo-AI.";

        $html = '<div class="suggestions-container page-container">';
        $html .= '<h1 id="suggestions" class="suggestions-title page-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        $html .= '<div class="suggestions-introduction">';
        $html .= '<p>' . htmlspecialchars($introText, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Wrap suggestions page content with proper container
     *
     * @param string $content
     * @return string
     */
    public function wrapPageContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        return '<div class="suggestions-container page-container">' . $content . '</div>';
    }
    
    /**
     * Check if suggestions content exists
     * 
     * @param EbookData $ebookData
     * @return bool
     */
    public function hasSuggestionsContent(EbookData $ebookData): bool
    {
        $content = $this->extractSuggestionsContent($ebookData);
        return $content !== null && !empty($content);
    }
}
