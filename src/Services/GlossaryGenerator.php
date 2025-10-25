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
        $seenEntries = []; // Pour éviter les doublons
        
        foreach ($glossaryEntries as $entry) {
            $acronym = $entry['acronym'] ?? '';
            $definition = $entry['definition'] ?? '';
            $detail = $entry['detail'] ?? '';

            if (!empty($acronym) || !empty($definition)) {
                // Créer une clé unique pour détecter les doublons
                $entryKey = strtolower(trim($acronym . '|' . $definition));
                
                // Vérifier si cette entrée n'a pas déjà été ajoutée
                if (!isset($seenEntries[$entryKey])) {
                    $results[] = [
                        'acronym' => $acronym,
                        'definition' => $definition,
                        'detail' => $detail
                    ];
                    $seenEntries[$entryKey] = true;
                }
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
     * @param array $acronymAnchors Structure des ancres pour les liens
     * @param array $firstOccurrenceFound Premières occurrences trouvées
     * @return array Array of HTML strings for each page
     */
    public function generateGlossaryHtml(?array $glossaryContent, string $language, array $acronymAnchors = [], array $firstOccurrenceFound = []): array
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
            // Utiliser ENT_NOQUOTES pour ne pas échapper l'apostrophe, puis échapper manuellement les guillemets doubles
            $acronym = str_replace('"', '&quot;', htmlspecialchars($entry['acronym'] ?? '', ENT_NOQUOTES, 'UTF-8'));
            $definition = str_replace('"', '&quot;', htmlspecialchars($entry['definition'] ?? '', ENT_NOQUOTES, 'UTF-8'));
            $detail = str_replace('"', '&quot;', htmlspecialchars($entry['detail'] ?? '', ENT_NOQUOTES, 'UTF-8'));
            

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

            // Titre de l'entrée (acronyme - définition) avec liens
            if (!empty($acronym) && !empty($definition)) {
                $entryText = $acronym . ' - ' . $definition;
                $entryWithLinks = $this->createGlossaryEntryWithLinks($entryText, $acronymAnchors, $firstOccurrenceFound);
                $entryHtml .= '<h3 class="glossary-entry-title">' . $entryWithLinks . '</h3>';
            } elseif (!empty($acronym)) {
                $entryWithLinks = $this->createGlossaryEntryWithLinks($acronym, $acronymAnchors, $firstOccurrenceFound);
                $entryHtml .= '<h3 class="glossary-entry-title">' . $entryWithLinks . '</h3>';
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

        // Pas besoin d'ancre ici car le template ebook.html a déjà id="glossary" sur la div page
        $html = '<div class="glossary-container page-container">';
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

    /**
     * Créer une entrée de glossaire avec les liens retour
     *
     * @param string $text Texte de l'entrée de glossaire
     * @param array $acronymAnchors Structure des ancres
     * @param array $firstOccurrenceFound Premières occurrences trouvées
     * @return string Texte modifié avec les liens retour
     */
    private function createGlossaryEntryWithLinks(string $text, array $acronymAnchors, array $firstOccurrenceFound): string
    {
        $parts = explode(' - ', $text, 2);
        $acronym = trim($parts[0]);
        $definition = $parts[1] ?? '';

        if (!isset($acronymAnchors[$acronym])) {
            return str_replace('"', '&quot;', htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8'));
        }

        $anchors = $acronymAnchors[$acronym];

        // Créer l'ancre de destination du glossaire
        $anchorHtml = '<a id="' . htmlspecialchars($anchors['gloss'], ENT_QUOTES) . '" name="' . htmlspecialchars($anchors['gloss'], ENT_QUOTES) . '"></a>';

        // Si première occurrence trouvée → lien retour cliquable
        if (isset($firstOccurrenceFound[$acronym])) {
            $linkHtml = '<a href="' . htmlspecialchars($anchors['links']['back'], ENT_QUOTES) . '" style="">' . 
                       str_replace('"', '&quot;', htmlspecialchars($acronym . ' - ' . $definition, ENT_NOQUOTES, 'UTF-8')) . '</a>';

            return $anchorHtml . $linkHtml;
        } else {
            // Pas de première occurrence → texte simple
            return $anchorHtml . str_replace('"', '&quot;', htmlspecialchars($acronym . ' - ' . $definition, ENT_NOQUOTES, 'UTF-8'));
        }
    }
}
