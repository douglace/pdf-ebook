<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer les pages Wikipedia de l'ebook
 */
class WikipediaGenerator
{
    /**
     * Extraire le contenu Wikipedia depuis formatted_text
     */
    public function extractWikipediaContent(EbookData $ebookData): ?array
    {
        try {
            $formattedText = $ebookData->getFormattedText();
        } catch (\Exception $e) {
            // Fallback sur le formatted_text local si l'API échoue
            $rawData = $ebookData->getRawData();
            $formattedText = $rawData['formatted_text'] ?? '';
        }

        if (empty($formattedText)) {
            return null;
        }

        // Chercher la section [TITRE1] WIKIPEDIA
        if (!preg_match('/\[TITRE1\]\s*WIKIPEDIA/i', $formattedText)) {
            return null;
        }

        $results = [];
        $seenEntries = []; // Pour éviter les doublons

        // Extraire toutes les entrées ENTREE_BIBLIOGRAPHIE dans la section WIKIPEDIA
        // Structure: [ENTREE_BIBLIOGRAPHIE] {TITLE}
        // Suivi de [NORMAL] {description}
        // Et [WIKI_LIEN] {url} [WIKI_LIEN]

        // Trouver la position de la section WIKIPEDIA
        $wikiStartPos = stripos($formattedText, '[TITRE1] WIKIPEDIA');
        if ($wikiStartPos === false) {
            return null;
        }


        // Trouver la fin de la section WIKIPEDIA (prochain [TITRE1] ou fin du texte)
        $wikiSection = substr($formattedText, $wikiStartPos);
        $nextTitre1Pos = stripos($wikiSection, '[TITRE1]', strlen('[TITRE1] WIKIPEDIA'));
        if ($nextTitre1Pos !== false) {
            $wikiSection = substr($wikiSection, 0, $nextTitre1Pos);
        }



        // Split par ENTREE_BIBLIOGRAPHIE pour isoler chaque entrée
        $parts = preg_split('/\[ENTREE_BIBLIOGRAPHIE\]\s*/i', $wikiSection);

        foreach ($parts as $part) {
            if (empty(trim($part))) {
                continue;
            }

            // Extraire la première ligne : {TITLE}
            $lines = explode("\n", $part);
            $firstLine = trim($lines[0] ?? '');

            if (empty($firstLine)) {
                continue;
            }

            if (str_starts_with($firstLine, "[TITRE1] WIKIPEDIA")) {
                $firstLine = "";
            }

            $title = $firstLine;



            // Trouver la fin de l'entrée (prochain marqueur important)
            $endPos = min(
                strpos($part, '[TITRE1]') ?: PHP_INT_MAX,
                strpos($part, '[TITRE2]') ?: PHP_INT_MAX,
                strpos($part, '[ENTREE_QUIZ]') ?: PHP_INT_MAX,
                strpos($part, '[ENTREE_BIBLIOGRAPHIE]') ?: PHP_INT_MAX,
                strpos($part, '[ENTREE_GLOSSAIRE]') ?: PHP_INT_MAX
            );

            // Limiter $part à cette fin
            if ($endPos !== PHP_INT_MAX && $endPos > 0) {
                $part = substr($part, 0, $endPos);
            }

            // Extraire la description ([NORMAL])
            $description = '';
            preg_match_all('/\[NORMAL\]\s*([^\[]+)/', $part, $normalMatches);
            if (!empty($normalMatches[1])) {
                $description = trim(implode(' ', $normalMatches[1]));
            }

            // Extraire le lien ([WIKI_LIEN] url [WIKI_LIEN])
            $link = '';
            if (preg_match('/\[WIKI_LIEN\]\s*([^\[]+?)\s*\[WIKI_LIEN\]/i', $part, $linkMatches)) {
                $link = trim($linkMatches[1]);
            }

            // Créer une clé unique pour détecter les doublons
            $entryKey = strtolower(trim($title));

            // Vérifier si cette entrée n'a pas déjà été ajoutée
            if (!isset($seenEntries[$entryKey]) && (!empty($title) || !empty($description) || !empty($link))) {
                $results[] = [
                    'title' => $title,
                    'description' => $description,
                    'link' => $link
                ];
                $seenEntries[$entryKey] = true;
            }
        }

        return empty($results) ? null : $results;
    }

    /**
     * Générer le HTML des pages Wikipedia
     * Retourne un tableau de pages HTML
     */
    /**
     * Générer le HTML des pages Wikipedia
     * Retourne un tableau de pages HTML
     */
    public function generateWikipediaHtml(?array $wikipediaContent, string $language = 'français', array $acronymAnchors = [], array &$firstOccurrenceFound = []): array
    {
        if (empty($wikipediaContent)) {
            return [];
        }

        $pages = [];
        $currentPageHtml = '';
        $currentPageHeight = 0;

        // Dimensions basées sur styles.css (optimisées pour mieux remplir les pages)
        // - Hauteur page : 297mm ≈ 1120px
        // - wikipedia-container : top 80px
        // - Espace disponible : 1120px - 80px - 30px (bottom safety pour numéro de page) = 1010px
        $safetyMargin = 10; // Marge minimale pour éviter les débordements
        $maxPageHeight = 1010 - $safetyMargin; // 1000px disponibles (optimisé au maximum)
        $entryBottomSafety = 10; // Marge minimale pour éviter que les entrées arrivent trop près du bas

        // Hauteurs estimées des éléments (basé sur CSS réel de styles.css)
        // - page-title (h1) : font 35px × line-height 1.3 + margin-bottom 20px = ~66px
        // - wikipedia-introduction : font 12px × line-height 1.6 (≈19px/ligne) + margin-bottom 30px
        // - wikipedia-entry-title (h3) : font 14px × line-height 1.4 (≈20px) + margin-bottom 10px = ~30px
        // - wikipedia-description : font 12px × line-height 1.6 = 19px par ligne + margin-bottom 8px
        // - wikipedia-link : font 12px + margin-bottom 5px = ~20px
        // - wikipedia-entry : margin-bottom 30px
        $pageTitleHeight = 66; // Titre "WIKIPEDIA" sur la première page
        $introductionHeight = 0; // Introduction désactivée pour l'instant
        $entryTitleHeight = 30; // 14px × 1.4 + margin-bottom 10px
        $descriptionLineHeight = 19; // 12px × 1.6
        $descriptionMargin = 8;
        $linkHeight = 20; // 12px + margin-bottom 5px
        $entryBottomMargin = 30; // margin-bottom de .wikipedia-entry

        // Introduction de Wikipedia (désactivée pour l'instant)
        $introduction = '';
        $currentPageHtml .= $introduction;
        $currentPageHeight += $introductionHeight;

        foreach ($wikipediaContent as $index => $entry) {
            // Estimer la hauteur de cette entrée
            $entryHeight = $entryBottomMargin;

            if (!empty($entry['title'])) {
                $entryHeight += $entryTitleHeight;
            }

            if (!empty($entry['description'])) {
                // Estimer le nombre de lignes (environ 110 caractères par ligne pour mieux utiliser l'espace)
                // Largeur du conteneur : page-container left 120px, right 80px = ~590px de largeur
                // Avec font 12px, on peut mettre environ 110 caractères par ligne
                $descriptionLines = max(1, ceil(strlen($entry['description']) / 110));
                $entryHeight += ($descriptionLines * $descriptionLineHeight) + $descriptionMargin;
            }

            if (!empty($entry['link'])) {
                $entryHeight += $linkHeight;
            }

            // Pour la première page, ajouter la hauteur du titre si c'est la première entrée
            $titleHeightToAdd = 0;
            if (empty($pages) && $index === 0 && $currentPageHeight === 0) {
                $titleHeightToAdd = $pageTitleHeight;
            }

            // Vérifier si cette entrée tient sur la page actuelle
            // Pour les petites entrées, on peut être plus agressif et réduire la marge de sécurité
            $dynamicSafety = $entryHeight < 80 ? 5 : $entryBottomSafety; // Marge réduite pour petites entrées
            $totalHeightNeeded = $currentPageHeight + $titleHeightToAdd + $entryHeight + $dynamicSafety;
            $pageWasCreated = false;

            // Créer une nouvelle page si nécessaire (sauf pour la toute première entrée qui doit toujours être ajoutée)
            // On permet un léger dépassement (5px) pour mieux remplir les pages
            if ($totalHeightNeeded > ($maxPageHeight + 5) && ($index > 0 || !empty($currentPageHtml))) {
                // Sauvegarder la page actuelle et commencer une nouvelle
                $isFirstPage = empty($pages);
                $pages[] = $this->wrapPageContent($currentPageHtml, $isFirstPage);
                $currentPageHtml = '';
                $currentPageHeight = 0;
                $titleHeightToAdd = 0; // Réinitialiser car on n'a plus besoin du titre sur les pages suivantes
                $pageWasCreated = true;
            }

            // Ajouter la hauteur du titre si c'est la première entrée de la première page
            // (seulement si on n'a pas créé de nouvelle page juste avant)
            if (!$pageWasCreated && empty($pages) && $index === 0 && $currentPageHeight === 0) {
                $currentPageHeight += $pageTitleHeight;
            }

            // Ajouter l'entrée
            $currentPageHtml .= '<div class="wikipedia-entry">';

            // Titre de l'entrée
            if (!empty($entry['title'])) {
                $currentPageHtml .= '<h3 class="wikipedia-entry-title">';
                $currentPageHtml .= $this->injectGlossaryLinks($entry['title'], $acronymAnchors, $firstOccurrenceFound);
                $currentPageHtml .= '</h3>';
            }

            // Description
            if (!empty($entry['description'])) {
                $currentPageHtml .= '<p class="wikipedia-description">';
                $currentPageHtml .= $this->injectGlossaryLinks($entry['description'], $acronymAnchors, $firstOccurrenceFound);
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
