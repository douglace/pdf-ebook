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
     * @return array Array of page HTML strings
     */
    public function generateChaptersPages(?array $chaptersContent, string $watermarkImageBase64): array
    {
        if ($chaptersContent === null || empty($chaptersContent)) {
            return [];
        }

        $pages = [];
        $currentPageHtml = '';
        $currentPageContentLength = 0;

        // Capacité estimée par page (en caractères)
        // Première page d'un chapitre a le titre (H1), donc moins d'espace
        $maxLengthFirstPageChapter = 2000;  // ~2000 caractères
        $maxLengthNormalPage = 3500;  // ~3500 caractères pour les pages suivantes

        // Marge de sécurité
        $safetyMargin = 0.85;

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
            $currentPageHtml .= '<h1 class="chapter-title page-title">' . $chapterNumber . '. ' . $chapterTitle . '</h1>';
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
                if ($currentPageContentLength > 0 && ($currentPageContentLength + $subchapterContentLength) > $effectiveMaxLength) {
                    // Sauvegarder la page actuelle
                    $currentPageHtml .= '</div>'; // Close chapter-container
                    $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);

                    // Démarrer une nouvelle page
                    $currentPageHtml = '<div class="chapter-container page-container">';
                    $currentPageContentLength = 0;
                    $isFirstPageOfChapter = false;
                }

                // Ajouter le sous-chapitre
                $currentPageHtml .= '<h2 class="subchapter-title">' . $subchapterNumber . ' ' . $subchapterTitle . '</h2>';
                $currentPageContentLength += strlen($subchapterTitle);

                // Ajouter les paragraphes
                foreach ($subchapter['paragraphs'] as $paragraph) {
                    $paragraphHtml = htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8');
                    $currentPageHtml .= '<p class="chapter-paragraph">' . $paragraphHtml . '</p>';
                    $currentPageContentLength += strlen($paragraph);
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
}
