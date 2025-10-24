<?php

declare(strict_types=1);

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

class GlossaryGenerator
{
    /**
     * Extract glossary content from JSON data
     *
     * @param EbookData $ebookData
     * @return array|null Array of glossary entries or null if not found
     */
    public function extractGlossaryContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();

        // The glossary data is in: acronyms > acronymes > array
        $acronymsData = $rawData['acronyms'] ?? [];
        $glossaryEntries = $acronymsData['acronymes'] ?? [];

        if (empty($glossaryEntries)) {
            return null;
        }

        $results = [];
        foreach ($glossaryEntries as $entry) {
            $acronym = $entry['acronym'] ?? '';
            $definition = $entry['definition'] ?? '';
            $detail = $entry['detail'] ?? '';

            if (!empty($acronym) || !empty($definition)) {
                $results[] = [
                    'acronym' => $acronym,
                    'definition' => $definition,
                    'detail' => $detail
                ];
            }
        }

        // Sort alphabetically by acronym
        usort($results, function($a, $b) {
            return strcasecmp($a['acronym'], $b['acronym']);
        });

        return empty($results) ? null : $results;
    }

    /**
     * Generate HTML for glossary pages
     *
     * @param array|null $glossaryContent
     * @param string $language
     * @return array Array of HTML strings for each page
     */
    public function generateGlossaryHtml(?array $glossaryContent, string $language): array
    {
        if ($glossaryContent === null || empty($glossaryContent)) {
            return ['', '', ''];
        }

        $pages = [];
        $currentPageHtml = '';
        $currentPageLength = 0;
        $currentPageNumber = 0;

        // Capacité estimée par page (en caractères)
        // Page 1 a moins d'espace car elle contient le titre
        $maxLengthPage1 = 2500;  // ~2500 caractères pour la première page
        $maxLengthOtherPages = 4000;  // ~4000 caractères pour les autres pages

        // Marge de sécurité pour éviter les débordements (85% de la capacité max)
        $safetyMargin = 0.85;

        foreach ($glossaryContent as $index => $entry) {
            $acronym = htmlspecialchars($entry['acronym'] ?? '', ENT_QUOTES, 'UTF-8');
            $definition = htmlspecialchars($entry['definition'] ?? '', ENT_QUOTES, 'UTF-8');
            $detail = htmlspecialchars($entry['detail'] ?? '', ENT_QUOTES, 'UTF-8');

            // Calculer la longueur estimée de cette entrée (acronym + definition + detail)
            $entryLength = strlen($acronym) + strlen($definition) + strlen($detail);

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
            $entryHtml = '<div class="glossary-entry">';

            // Titre de l'entrée (acronyme - définition)
            if (!empty($acronym) && !empty($definition)) {
                $entryHtml .= '<h3 class="glossary-entry-title">' . $acronym . ' - ' . $definition . '</h3>';
            } elseif (!empty($acronym)) {
                $entryHtml .= '<h3 class="glossary-entry-title">' . $acronym . '</h3>';
            }

            // Détail (paragraphe)
            if (!empty($detail)) {
                $entryHtml .= '<p class="glossary-detail">' . $detail . '</p>';
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
     * Generate title HTML for the first glossary page
     *
     * @param string $language
     * @return string
     */
    public function generateGlossaryTitleHtml(string $language): string
    {
        $title = 'GLOSSAIRE';

        $html = '<a id="glossaire" name="glossaire"></a>';
        $html .= '<div class="glossary-container page-container">';
        $html .= '<h1 class="glossary-title page-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';

        return $html;
    }

    /**
     * Wrap glossary page content with proper container
     *
     * @param string $content
     * @return string
     */
    public function wrapPageContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        return '<div class="glossary-container page-container">' . $content . '</div>';
    }

    /**
     * Check if glossary content exists
     *
     * @param EbookData $ebookData
     * @return bool
     */
    public function hasGlossaryContent(EbookData $ebookData): bool
    {
        $content = $this->extractGlossaryContent($ebookData);
        return $content !== null && !empty($content);
    }
}
