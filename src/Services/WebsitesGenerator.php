<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer les pages Sites Web de l'ebook
 */
class WebsitesGenerator
{

    private string $language = 'français';

    /**
     * Définir la langue pour les libellés de la TOC
     */
    public function setLanguage(string $language): void
    {
        $this->language = strtolower($language);
    }


    /**
     * Extraire le contenu Sites Web depuis les données JSON
     */
    public function extractWebsitesContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();
        $websites = $rawData['bibliography']['websites_nocommercial'] ?? [];

        if (empty($websites)) {
            return null;
        }

        $websitesContent = [];

        foreach ($websites as $site) {
            $name = $site['website_name'] ?? '';
            $description = $site['website_description'] ?? '';
            $url = $site['website_url'] ?? '';

            if (!empty($name) || !empty($description) || !empty($url)) {
                $websitesContent[] = [
                    'name' => $name,
                    'description' => $description,
                    'url' => $url
                ];
            }
        }

        return !empty($websitesContent) ? $websitesContent : null;
    }

    /**
     * Générer le HTML des pages Sites Web
     * Retourne un tableau de pages HTML
     */
    /**
     * Générer le HTML des pages Sites Web
     * Retourne un tableau de pages HTML
     */
    public function generateWebsitesHtml(?array $websitesContent, string $language = 'français', array $acronymAnchors = [], array &$firstOccurrenceFound = []): array
    {
        if (empty($websitesContent)) {
            return [];
        }

        $pages = [];
        $currentPageHtml = '';
        $entriesOnCurrentPage = 0;
        $maxEntriesPerPage = 4; // Nombre moyen d'entrées par page

        // Introduction de Sites Web
        $introText = $this->getIntroductionText($language);
        $introduction = '<div class="websites-introduction">';
        $introduction .= '<p>' . htmlspecialchars($introText, ENT_QUOTES, 'UTF-8') . '</p>';
        $introduction .= '</div>';

        $currentPageHtml .= $introduction;
        $entriesOnCurrentPage = 0; // L'intro compte pour de l'espace

        foreach ($websitesContent as $index => $entry) {
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
            $currentPageHtml .= '<div class="websites-entry">';

            // Nom du site
            if (!empty($entry['name'])) {
                $currentPageHtml .= '<h3 class="websites-entry-title">';
                $currentPageHtml .= $this->injectGlossaryLinks($entry['name'], $acronymAnchors, $firstOccurrenceFound);
                $currentPageHtml .= '</h3>';
            }

            // Description
            if (!empty($entry['description'])) {
                $currentPageHtml .= '<p class="websites-description">';
                $currentPageHtml .= $this->injectGlossaryLinks($entry['description'], $acronymAnchors, $firstOccurrenceFound);
                $currentPageHtml .= '</p>';
            }

            // URL
            if (!empty($entry['url'])) {
                $currentPageHtml .= '<p class="websites-link">';
                $currentPageHtml .= '<a href="' . htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') . '" target="_blank">';
                $currentPageHtml .= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8');
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

    private function injectGlossaryLinks(string $text, array $acronymAnchors, array &$firstOccurrenceFound): string
    {
        // 1. D'abord sécuriser tout le texte
        $safeText = str_replace('"', '&quot;', htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8'));

        if (empty($acronymAnchors)) {
            return $safeText;
        }

        // 2. Préparer les acronymes pour la recherche
        $escapedAcronymsMap = [];
        $escapedAcronymsPattern = [];

        foreach ($acronymAnchors as $rawAcronym => $anchors) {
            $escapedAcronym = str_replace('"', '&quot;', htmlspecialchars($rawAcronym, ENT_NOQUOTES, 'UTF-8'));
            $escapedAcronymsMap[$escapedAcronym] = $rawAcronym;
            $escapedForRegex = preg_quote($escapedAcronym, '/');

            $startBoundary = preg_match('/^\w/u', $rawAcronym) ? '\\b' : '';
            $endBoundary = preg_match('/\w$/u', $rawAcronym) ? '\\b' : '';

            $escapedAcronymsPattern[] = $startBoundary . $escapedForRegex . $endBoundary;
        }

        $pattern = '/(\\s*)(' . implode('|', $escapedAcronymsPattern) . ')(\\s*)/u';

        return preg_replace_callback($pattern, function ($match) use ($acronymAnchors, $escapedAcronymsMap, &$firstOccurrenceFound) {
            $spaceBefore = $match[1];
            $foundEscapedAcronym = $match[2];
            $spaceAfter = $match[3];

            if (!isset($escapedAcronymsMap[$foundEscapedAcronym])) {
                return $match[0];
            }

            $rawAcronym = $escapedAcronymsMap[$foundEscapedAcronym];
            $anchors = $acronymAnchors[$rawAcronym];
            $prefix = '';

            // Première occurrence : ajouter l'ancre de retour
            if (!isset($firstOccurrenceFound[$rawAcronym])) {
                $firstOccurrenceFound[$rawAcronym] = true;
                $prefix = '<a id="' . htmlspecialchars($anchors['back'], ENT_QUOTES) . '" name="' . htmlspecialchars($anchors['back'], ENT_QUOTES) . '"></a>';
            }

            // Créer le lien vers le glossaire (couleur bleue #0066CC)
            $link = '<a href="' . htmlspecialchars($anchors['links']['gloss'], ENT_QUOTES) . '" style="color: #0066CC; text-decoration: underline;">' .
                $foundEscapedAcronym . '</a>';

            return $spaceBefore . $prefix . $link . $spaceAfter;
        }, $safeText);
    }

    /**
     * Envelopper le contenu dans un conteneur de page
     */
    private function wrapPageContent(string $content, bool $includeTitle = false): string
    {
        $titleHtml = '';
        if ($includeTitle) {
            $title = $this->isFrench() ? "SITES WEB" : "WEB SITES";
            $titleHtml = '<h1 class="websites-title page-title">' . $title . '</h1>';
        }
        return '<div class="websites-container page-container">' . $titleHtml . $content . '</div>';
    }


    public function isFrench(): bool
    {
        return $this->language === 'français';
    }

    public function isEnglish(): bool
    {
        return $this->language === 'english';
    }

    /**
     * Générer un titre pour la première page de Sites Web
     */
    public function generateWebsitesTitleHtml(): string
    {
        return '<a id="websites" name="websites"></a>';
    }

    /**
     * Obtenir le texte d'introduction selon la langue
     */
    private function getIntroductionText(string $language): string
    {
        if (strtolower($language) === 'english') {
            return 'To deepen your understanding of the subject, we suggest consulting the following websites.';
        }

        return 'Pour approfondir le sujet, nous vous suggérons de consulter les sites Web suivants.';
    }

    /**
     * Vérifier si Sites Web est disponible pour cet ebook
     */
    public function hasWebsitesContent(EbookData $ebookData): bool
    {
        $content = $this->extractWebsitesContent($ebookData);
        return $content !== null && !empty($content);
    }
}
