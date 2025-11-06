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
        $entriesOnCurrentPage = 0;
        $maxEntriesPerPage = 4; // Nombre moyen d'entrées par page

        // Introduction de Wikipedia
        $introText = $this->getIntroductionText($language);
        $introduction = '<div class="wikipedia-introduction">';
        $introduction .= '<p>' . htmlspecialchars($introText, ENT_QUOTES, 'UTF-8') . '</p>';
        $introduction .= '</div>';

        $currentPageHtml .= $introduction;
        $entriesOnCurrentPage = 0; // L'intro compte pour de l'espace

        foreach ($wikipediaContent as $index => $entry) {
            // Estimer si cette entrée tient sur la page actuelle
            if ($entriesOnCurrentPage >= $maxEntriesPerPage && $index > 0) {
                // Sauvegarder la page actuelle et commencer une nouvelle
                // La première page a le titre, les autres non
                $isFirstPage = empty($pages);
                $pages[] = $this->wrapPageContent($currentPageHtml, $isFirstPage);
                $currentPageHtml = '';
                $entriesOnCurrentPage = 0;
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
            $entriesOnCurrentPage++;
        }

        // Ajouter la dernière page
        if (!empty($currentPageHtml)) {
            // La première page a le titre, les autres non
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
