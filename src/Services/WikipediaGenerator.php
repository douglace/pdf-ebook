<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer les pages Wikipedia de l'ebook
 */
class WikipediaGenerator
{
    /**
     * Extraire le contenu Wikipedia depuis les données JSON
     */
    public function extractWikipediaContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();
        $wikipedias = $rawData['bibliography']['wikipedias'] ?? [];

        if (empty($wikipedias)) {
            return null;
        }

        $wikipediaContent = [];

        foreach ($wikipedias as $wiki) {
            $title = $wiki['title'] ?? '';
            $description = $wiki['description'] ?? '';
            $link = $wiki['link'] ?? '';

            if (!empty($title) || !empty($description) || !empty($link)) {
                $wikipediaContent[] = [
                    'title' => $title,
                    'description' => $description,
                    'link' => $link
                ];
            }
        }

        return !empty($wikipediaContent) ? $wikipediaContent : null;
    }

    /**
     * Générer le HTML des pages Wikipedia
     * Retourne un tableau de pages HTML
     */
    public function generateWikipediaHtml(?array $wikipediaContent, string $language = 'français'): array
    {
        if (empty($wikipediaContent)) {
            return [];
        }

        $pages = [];
        $currentPageHtml = '';
        $currentPageHeight = 0;

        // Dimensions basées sur styles.css (similaire à ChaptersGenerator)
        // - Hauteur page : 297mm ≈ 1120px
        // - wikipedia-container : top 80px
        // - Espace disponible : 1120px - 80px - 30px (bottom safety pour numéro de page) = 1010px
        $safetyMargin = 20; // Marge réduite car les entrées Wikipedia sont plus compactes
        $maxPageHeight = 1010 - $safetyMargin; // 990px disponibles (augmenté de 940px)
        $entryBottomSafety = 15; // Marge réduite pour mieux remplir les pages (vs 30px précédemment)

        // Hauteurs estimées des éléments (basé sur CSS réel de styles.css)
        // - page-title (h1) : font 18px × line-height 1.3 + margin-bottom 18px = 41px
        // - wikipedia-introduction : font 12px × line-height 1.6 (≈19px/ligne) + margin-bottom 30px
        // - wikipedia-entry-title (h3) : font 14px × line-height 1.4 (≈20px) + margin-bottom 10px = ~30px
        // - wikipedia-description : font 12px × line-height 1.6 = 19px par ligne + margin-bottom 8px
        // - wikipedia-link : font 12px + margin-bottom 5px = ~20px
        // - wikipedia-entry : margin-bottom 30px
        $introductionHeight = 80; // Texte intro (2-3 lignes × 19px) + margin-bottom 30px
        $entryTitleHeight = 30; // 14px × 1.4 + margin-bottom 10px
        $descriptionLineHeight = 19; // 12px × 1.6
        $descriptionMargin = 8;
        $linkHeight = 20; // 12px + margin-bottom 5px
        $entryBottomMargin = 30; // margin-bottom de .wikipedia-entry

        // Introduction de Wikipedia
        $introText = $this->getIntroductionText($language);
        $introduction = '<div class="wikipedia-introduction">';
        $introduction .= '<p>' . htmlspecialchars($introText, ENT_QUOTES, 'UTF-8') . '</p>';
        $introduction .= '</div>';

        $currentPageHtml .= $introduction;
        $currentPageHeight += $introductionHeight;

        foreach ($wikipediaContent as $entry) {
            // Estimer la hauteur de cette entrée
            $entryHeight = $entryBottomMargin;

            if (!empty($entry['title'])) {
                $entryHeight += $entryTitleHeight;
            }

            if (!empty($entry['description'])) {
                // Estimer le nombre de lignes (environ 100 caractères par ligne pour être plus précis avec la largeur réelle)
                // Utiliser max(1, ...) pour permettre des descriptions courtes sur une seule ligne
                $descriptionLines = max(1, ceil(strlen($entry['description']) / 100));
                $entryHeight += ($descriptionLines * $descriptionLineHeight) + $descriptionMargin;
            }

            if (!empty($entry['link'])) {
                $entryHeight += $linkHeight;
            }

            // Vérifier si cette entrée tient sur la page actuelle
            // IMPORTANT: On utilise $entryBottomSafety comme dans ChaptersGenerator pour éviter
            // que les entrées arrivent trop près du bas de page
            if ($currentPageHeight + $entryHeight + $entryBottomSafety > $maxPageHeight && $currentPageHeight > 0) {
                // Sauvegarder la page actuelle et commencer une nouvelle
                $isFirstPage = empty($pages);
                $pages[] = $this->wrapPageContent($currentPageHtml, $isFirstPage);
                $currentPageHtml = '';
                $currentPageHeight = 0;
            }

            // Ajouter l'entrée
            $currentPageHtml .= '<div class="wikipedia-entry">';

            // Titre de l'entrée
            if (!empty($entry['title'])) {
                $currentPageHtml .= '<h3 class="wikipedia-entry-title">';
                $currentPageHtml .= htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8');
                $currentPageHtml .= '</h3>';
            }

            // Description
            if (!empty($entry['description'])) {
                $currentPageHtml .= '<p class="wikipedia-description">';
                $currentPageHtml .= htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8');
                $currentPageHtml .= '</p>';
            }

            // Lien
            if (!empty($entry['link'])) {
                $currentPageHtml .= '<p class="wikipedia-link">';
                $currentPageHtml .= '<a href="' . htmlspecialchars($entry['link'], ENT_QUOTES, 'UTF-8') . '" target="_blank">';
                $currentPageHtml .= htmlspecialchars($entry['link'], ENT_QUOTES, 'UTF-8');
                $currentPageHtml .= '</a>';
                $currentPageHtml .= '</p>';
            }

            $currentPageHtml .= '</div>';
            $currentPageHeight += $entryHeight;
        }

        // Ajouter la dernière page
        if (!empty($currentPageHtml)) {
            $isFirstPage = empty($pages);
            $pages[] = $this->wrapPageContent($currentPageHtml, $isFirstPage);
        }

        return $pages;
    }

    /**
     * Envelopper le contenu dans un conteneur de page
     */
    private function wrapPageContent(string $content, bool $includeTitle = false): string
    {
        $title = $includeTitle ? '<h1 class="wikipedia-title page-title">WIKIPEDIA</h1>' : '';
        return '<div class="wikipedia-container page-container">' . $title . $content . '</div>';
    }

    /**
     * Générer un titre pour la première page de Wikipedia
     */
    public function generateWikipediaTitleHtml(): string
    {
        return '<a id="wikipedia" name="wikipedia"></a>';
    }

    /**
     * Obtenir le texte d'introduction selon la langue
     */
    private function getIntroductionText(string $language): string
    {
        if (strtolower($language) === 'english') {
            return 'To deepen your understanding of the subject, we suggest the following links to Wikipedia pages. The referenced pages are in English. You can then access the page in the language of your choice.';
        }

        return 'Pour approfondir le sujet, nous vous suggérons les liens vers les pages Wikipédia suivantes. Les pages référencées sont en anglais. Vous pourrez ensuite accéder à la page dans la langue de votre choix.';
    }

    /**
     * Vérifier si Wikipedia est disponible pour cet ebook
     */
    public function hasWikipediaContent(EbookData $ebookData): bool
    {
        $content = $this->extractWikipediaContent($ebookData);
        return $content !== null && !empty($content);
    }
}
