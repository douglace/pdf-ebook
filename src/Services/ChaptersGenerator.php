<?php

declare(strict_types=1);

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

class ChaptersGenerator
{
    /**
     * Extract chapters content from JSON data
     *
     * @param EbookData $ebookData
     * @return array|null Array of chapters with subchapters or null if not found
     */
    public function extractChaptersContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();

        $chapters = $rawData['chapters']['chapters'] ?? [];

        if (empty($chapters)) {
            return null;
        }

        $results = [];

        foreach ($chapters as $chapter) {
            $chapterInfo = $chapter['chapter_info_title'] ?? [];
            $numeroChapter = $chapterInfo['numero_chapter'] ?? '';
            $chapterTitle = $chapterInfo['chapter'] ?? '';

            // Skip introduction (0) and conclusion (-1) as they are handled separately
            if ($numeroChapter === '0' || $numeroChapter === '-1') {
                continue;
            }

            $subchapters = [];

            foreach ($chapter['subchapters'] ?? [] as $subchapter) {
                $subchapterInfo = $subchapter['subchapter_info_title'] ?? [];
                $subchapterText = $subchapter['subchapter_info_text'] ?? [];

                $numeroSubchapter = $subchapterInfo['numero_subchapter'] ?? '';
                $subchapterTitle = $subchapterInfo['subchapter'] ?? '';
                $paragraphs = $subchapterText['paragraphs'] ?? [];

                $paragraphTexts = [];
                foreach ($paragraphs as $paragraph) {
                    $paragraphTexts[] = $paragraph['paragraph_text'] ?? '';
                }

                if (!empty($subchapterTitle) || !empty($paragraphTexts)) {
                    $subchapters[] = [
                        'numero' => $numeroSubchapter,
                        'title' => $subchapterTitle,
                        'paragraphs' => $paragraphTexts
                    ];
                }
            }

            $results[] = [
                'numero' => $numeroChapter,
                'title' => $chapterTitle,
                'subchapters' => $subchapters
            ];
        }

        return empty($results) ? null : $results;
    }

    /**
     * Generate HTML pages for chapters
     * Returns an array of complete page HTML strings
     *
     * @param array|null $chaptersContent
     * @param string $watermarkImageBase64
     * @param array $acronymAnchors Structure des ancres pour les liens
     * @param array $firstOccurrenceFound Premières occurrences trouvées
     * @return array Array of page HTML strings
     */
    public function generateChaptersPages(?array $chaptersContent, string $watermarkImageBase64, array $acronymAnchors = [], array &$firstOccurrenceFound = []): array
    {
        if ($chaptersContent === null || empty($chaptersContent)) {
            return [];
        }

        $pages = [];
        $currentPageHtml = '';
        $currentPageContentLength = 0;

        // Capacité estimée par page (en caractères) - Optimisé pour mieux remplir les pages
        // Première page d'un chapitre a le titre (H1), donc moins d'espace
        $maxLengthFirstPageChapter = 2800;  // ~2800 caractères (augmenté)
        $maxLengthNormalPage = 4200;  // ~4200 caractères pour les pages suivantes (augmenté)

        // Marge de sécurité réduite pour mieux utiliser l'espace
        $safetyMargin = 0.95;

        $isFirstPageOfChapter = true;

        foreach ($chaptersContent as $chapter) {
            $chapterNumber = $chapter['numero'];
            $chapterTitle = htmlspecialchars($chapter['title'], ENT_QUOTES, 'UTF-8');

            // Nouvelle page pour chaque chapitre (TITRE1)
            if (!empty($currentPageHtml)) {
                // Fermer la page précédente
                $currentPageHtml .= '</div>'; // Close chapter-container
                $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);
                $currentPageHtml = '';
                $currentPageContentLength = 0;
            }

            // Commencer une nouvelle page avec le titre du chapitre
            $isFirstPageOfChapter = true;
            $currentPageHtml .= '<div class="chapter-container page-container">';
            $anchorId = 'chapter-' . $chapterNumber;
            $currentPageHtml .= '<h1 id="' . $anchorId . '" class="chapter-title page-title">' . $chapterNumber . '. ' . $chapterTitle . '</h1>';
            $currentPageContentLength += strlen($chapterTitle);

            // Traiter les sous-chapitres
            foreach ($chapter['subchapters'] as $subchapter) {
                $subchapterNumber = $subchapter['numero'];
                $subchapterTitle = htmlspecialchars($subchapter['title'], ENT_QUOTES, 'UTF-8');

                // Calculer la longueur du sous-chapitre
                $subchapterContentLength = strlen($subchapterTitle);
                foreach ($subchapter['paragraphs'] as $paragraph) {
                    $subchapterContentLength += strlen($paragraph);
                }

                // Déterminer la capacité maximale
                $maxLength = $isFirstPageOfChapter ? $maxLengthFirstPageChapter : $maxLengthNormalPage;
                $effectiveMaxLength = $maxLength * $safetyMargin;

                // Vérifier si on doit créer une nouvelle page
                // Permettre aux paragraphes de continuer sur la page suivante
                if ($currentPageContentLength > 0 && ($currentPageContentLength + $subchapterContentLength) > $effectiveMaxLength) {
                    // Si c'est un sous-chapitre complet qui ne rentre pas, créer une nouvelle page
                    if ($subchapterContentLength > ($effectiveMaxLength * 0.3)) {
                        // Sauvegarder la page actuelle
                        $currentPageHtml .= '</div>'; // Close chapter-container
                        $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);

                        // Démarrer une nouvelle page
                        $currentPageHtml = '<div class="chapter-container page-container">';
                        $currentPageContentLength = 0;
                        $isFirstPageOfChapter = false;
                    }
                }

                // Ajouter le sous-chapitre avec ancre
                $subchapterAnchorId = 'chapter-' . $chapterNumber . '-subchapter-' . $subchapterNumber;
                $currentPageHtml .= '<h2 id="' . $subchapterAnchorId . '" class="subchapter-title">' . $subchapterNumber . ' ' . $subchapterTitle . '</h2>';
                $currentPageContentLength += strlen($subchapterTitle);

                // Ajouter les paragraphes avec gestion des sauts de page et injection de liens
                foreach ($subchapter['paragraphs'] as $paragraph) {
                    $paragraphLength = strlen($paragraph);
                    
                    // Vérifier si le paragraphe rentre sur la page actuelle
                    if ($currentPageContentLength + $paragraphLength > $effectiveMaxLength && $currentPageContentLength > 0) {
                        // Le paragraphe ne rentre pas, créer une nouvelle page
                        $currentPageHtml .= '</div>'; // Close chapter-container
                        $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);

                        // Démarrer une nouvelle page
                        $currentPageHtml = '<div class="chapter-container page-container">';
                        $currentPageContentLength = 0;
                        $isFirstPageOfChapter = false;
                    }
                    
                    // Injecter les liens vers les acronymes
                    $paragraphWithLinks = $this->injectGlossaryLinks($paragraph, $acronymAnchors, $firstOccurrenceFound);
                    $currentPageHtml .= '<p class="chapter-paragraph">' . $paragraphWithLinks . '</p>';
                    $currentPageContentLength += $paragraphLength;
                }
            }
        }

        // Ajouter la dernière page si elle contient du contenu
        if (!empty($currentPageHtml)) {
            $currentPageHtml .= '</div>'; // Close chapter-container
            $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);
        }

        return $pages;
    }

    /**
     * Wrap content in page structure with gradient and watermark
     *
     * @param string $content
     * @param string $watermarkImageBase64
     * @return string
     */
    private function wrapInPageStructure(string $content, string $watermarkImageBase64): string
    {
        return '
    <div class="page">
        <!-- Bande verticale gauche avec dégradé -->
        <div class="left-gradient"></div>

        <!-- Filigrane Encyclo-AI (logo oblique) -->
        <div class="chapter-watermark">
            <img src="' . $watermarkImageBase64 . '" alt="Encyclo-AI">
        </div>

        <!-- Contenu du chapitre -->
        ' . $content . '
    </div>';
    }

    /**
     * Check if chapters content exists
     *
     * @param EbookData $ebookData
     * @return bool
     */
    public function hasChaptersContent(EbookData $ebookData): bool
    {
        $content = $this->extractChaptersContent($ebookData);
        return $content !== null && !empty($content);
    }

    /**
     * Injecter automatiquement les liens vers les acronymes dans le texte
     *
     * @param string $text Texte à modifier
     * @param array $acronymAnchors Structure des ancres
     * @param array $firstOccurrenceFound Référence pour tracker les premières occurrences
     * @return string Texte modifié avec les liens HTML
     */
    private function injectGlossaryLinks(string $text, array $acronymAnchors, array &$firstOccurrenceFound): string
    {
        if (empty($acronymAnchors)) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // Créer le pattern regex pour tous les acronymes
        $escapedAcronyms = array_map('preg_quote', array_keys($acronymAnchors));
        $pattern = '/(\\s*)(\\b(?:' . implode('|', $escapedAcronyms) . ')\\b)(\\s*)/u';

        return preg_replace_callback($pattern, function($match) use ($acronymAnchors, &$firstOccurrenceFound) {
            $spaceBefore = $match[1];
            $acronym = $match[2];
            $spaceAfter = $match[3];

            if (!isset($acronymAnchors[$acronym])) {
                return htmlspecialchars($match[0], ENT_QUOTES, 'UTF-8');
            }

            $anchors = $acronymAnchors[$acronym];
            $prefix = '';

            // Première occurrence : ajouter l'ancre de retour
            if (!isset($firstOccurrenceFound[$acronym])) {
                $firstOccurrenceFound[$acronym] = true;
                $prefix = '<a id="' . htmlspecialchars($anchors['back'], ENT_QUOTES) . '" name="' . htmlspecialchars($anchors['back'], ENT_QUOTES) . '"></a>';
            }

            // Créer le lien vers le glossaire (couleur bleue #0066CC)
            $link = '<a href="' . htmlspecialchars($anchors['links']['gloss'], ENT_QUOTES) . '" style="color: #0066CC; text-decoration: underline;">' . 
                    htmlspecialchars($acronym, ENT_QUOTES) . '</a>';

            return $spaceBefore . $prefix . $link . $spaceAfter;
        }, $text);
    }
}
