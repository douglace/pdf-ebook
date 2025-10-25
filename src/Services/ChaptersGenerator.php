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
        $currentPageHeight = 0;

        // Dimensions réelles basées sur styles.css :
        // - Hauteur page : 297mm ≈ 1120px
        // - chapter-container : top 80px
        // - Espace disponible : 1120px - 80px - 60px (bottom safety) = 980px

        // Hauteurs des éléments (basé sur CSS) :
        // - chapter-title (h1) : font 18px × line-height 1.3 + margin-bottom 18px = 41px
        // - subchapter-title (h2) : font 14px × line-height 1.4 + margin-top 15px + margin-bottom 10px = 45px
        // - paragraph : font 12px × line-height 1.5 + margin-bottom 12px = 30px par ligne estimée

        $safetyMargin = 30; // pixels de marge de sécurité
        $maxPageHeight = 980 - $safetyMargin; // 950px disponibles

        $chapterTitleHeight = 41; // pixels
        $subchapterTitleHeight = 45; // pixels
        $paragraphLineHeight = 18; // 12px × 1.5 line-height
        $paragraphMargin = 12; // margin-bottom

        // Compteur pour générer des ancres cohérentes avec la TOC
        $chapterCounter = 1;

        foreach ($chaptersContent as $chapter) {
            $originalChapterNumber = $chapter['numero'];
            $chapterTitle = htmlspecialchars($chapter['title'], ENT_QUOTES, 'UTF-8');

            // Nouvelle page pour chaque chapitre (TITRE1)
            if (!empty($currentPageHtml)) {
                // Fermer la page précédente
                $currentPageHtml .= '</div>'; // Close chapter-container
                $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);
                $currentPageHtml = '';
                $currentPageHeight = 0;
            }

            // Commencer une nouvelle page avec le titre du chapitre
            $currentPageHtml .= '<div class="chapter-container page-container">';
            // Utiliser le compteur pour générer des ancres cohérentes (1, 2, 3, 4, 5, 6, 7, 8...)
            $anchorId = 'chapter-' . $chapterCounter;
            $currentPageHtml .= '<h1 id="' . $anchorId . '" class="chapter-title page-title">' . $chapterTitle . '</h1>';
            $currentPageHeight += $chapterTitleHeight;

            // Traiter les sous-chapitres
            foreach ($chapter['subchapters'] as $subchapter) {
                $subchapterNumber = $subchapter['numero'];
                $subchapterTitle = htmlspecialchars($subchapter['title'], ENT_QUOTES, 'UTF-8');

                // Vérifier si le titre du sous-chapitre rentre sur la page actuelle
                if ($currentPageHeight + $subchapterTitleHeight > $maxPageHeight) {
                    // Créer une nouvelle page
                    $currentPageHtml .= '</div>'; // Close chapter-container
                    $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);

                    // Démarrer une nouvelle page
                    $currentPageHtml = '<div class="chapter-container page-container">';
                    $currentPageHeight = 0;
                }

                // Ajouter le sous-chapitre avec ancre cohérente
                $subchapterAnchorId = 'chapter-' . $chapterCounter . '-subchapter-' . $subchapterNumber;
                $currentPageHtml .= '<h2 id="' . $subchapterAnchorId . '" class="subchapter-title">' . $subchapterTitle . '</h2>';
                $currentPageHeight += $subchapterTitleHeight;

                // Ajouter les paragraphes avec gestion des sauts de page et injection de liens
                foreach ($subchapter['paragraphs'] as $paragraph) {
                    // Estimer la hauteur du paragraphe
                    // On compte environ 80 caractères par ligne (police 12px dans une largeur ~600px)
                    $estimatedLines = max(1, ceil(strlen($paragraph) / 80));
                    $paragraphHeight = ($estimatedLines * $paragraphLineHeight) + $paragraphMargin;

                    // Vérifier si le paragraphe rentre sur la page actuelle
                    if ($currentPageHeight + $paragraphHeight > $maxPageHeight && $currentPageHeight > 0) {
                        // Le paragraphe ne rentre pas, créer une nouvelle page
                        $currentPageHtml .= '</div>'; // Close chapter-container
                        $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);

                        // Démarrer une nouvelle page
                        $currentPageHtml = '<div class="chapter-container page-container">';
                        $currentPageHeight = 0;
                    }

                    // Injecter les liens vers les acronymes
                    $paragraphWithLinks = $this->injectGlossaryLinks($paragraph, $acronymAnchors, $firstOccurrenceFound);
                    $currentPageHtml .= '<p class="chapter-paragraph">' . $paragraphWithLinks . '</p>';
                    $currentPageHeight += $paragraphHeight;
                }
            }
            
            // Incrémenter le compteur pour le prochain chapitre
            $chapterCounter++;
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
