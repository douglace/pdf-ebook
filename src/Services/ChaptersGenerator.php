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
        // - subchapter-title (h2) : font 14px × line-height 1.4 + margin-top 33px + margin-bottom 33px = 81px
        // - paragraph : font 12px × line-height 1.5 + margin-bottom 12px = 30px par ligne estimée

        $safetyMargin = 40; // pixels de marge de sécurité générale
        $maxPageHeight = 980 - $safetyMargin; // 940px disponibles
        $paragraphBottomSafety = 60; // Marge supplémentaire pour éviter qu'un paragraphe arrive trop près du bas (~4 lignes)

        $chapterTitleHeight = 41; // pixels
        $subchapterTitleHeight = 45; // pixels
        $paragraphLineHeight = 16; // 12px × 1.5 line-height
        $paragraphMargin = 12; // margin-bottom

        // Marge critique pour éviter les titres orphelins (titre seul en bas de page)
        // Réduite pour permettre plus de flexibilité tout en protégeant contre les orphelins
        $orphanPreventionMargin = 80; // pixels minimum requis après un titre (équivaut à ~5 lignes de texte)

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

            // PROTECTION ANTI-ORPHELIN POUR TITRE DE CHAPITRE (H1)
            // Vérifier qu'il y a assez d'espace pour au moins un sous-titre après le titre du chapitre
            // Un titre de chapitre ne doit JAMAIS être seul en bas de page
            if (!empty($chapter['subchapters'])) {
                $minContentAfterChapterTitle = $subchapterTitleHeight + ($paragraphLineHeight * 2); // au moins 2 lignes de texte

                if ($currentPageHeight + $minContentAfterChapterTitle + $orphanPreventionMargin > $maxPageHeight) {
                    // Le titre de chapitre arriverait trop près du bas, le mettre sur une nouvelle page
                    $currentPageHtml .= '</div>'; // Close chapter-container
                    $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);

                    // Redémarrer la page avec le titre du chapitre
                    $currentPageHtml = '<div class="chapter-container page-container">';
                    $currentPageHtml .= '<h1 id="' . $anchorId . '" class="chapter-title page-title">' . $chapterTitle . '</h1>';
                    $currentPageHeight = $chapterTitleHeight;
                }
            }

            // Traiter les sous-chapitres
            foreach ($chapter['subchapters'] as $subchapter) {
                $subchapterNumber = $subchapter['numero'];
                $subchapterTitle = htmlspecialchars($subchapter['title'], ENT_QUOTES, 'UTF-8');
                


                // PROTECTION ANTI-ORPHELIN POUR SOUS-TITRE (H2)
                // Estimer la hauteur minimale du contenu qui doit suivre le sous-titre
                // Un sous-titre ne doit JAMAIS être seul en bas de page
                $minContentAfterSubtitle = 0;
                if (!empty($subchapter['paragraphs'])) {
                    $firstParagraph = $subchapter['paragraphs'][0];
                    // Estimer avec plus de précision (80 caractères par ligne)
                    $estimatedLines = max(2, ceil(strlen($firstParagraph) / 80)); // au moins 2 lignes
                    $minContentAfterSubtitle = ($estimatedLines * $paragraphLineHeight) + $paragraphMargin;
                }

                // Vérifier si le sous-titre + contenu minimum + marge anti-orphelin rentrent sur la page
                // Si non, mettre le sous-titre sur une nouvelle page
                $requiredSpaceForSubtitle = $subchapterTitleHeight + $minContentAfterSubtitle + $orphanPreventionMargin;
                if ($currentPageHeight + $requiredSpaceForSubtitle > $maxPageHeight) {
                    // Le sous-titre arriverait trop près du bas, créer une nouvelle page
                    $currentPageHtml .= '</div>'; // Close chapter-container
                    $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);

                    // Démarrer une nouvelle page
                    $currentPageHtml = '<div class="chapter-container page-container">';
                    $currentPageHeight = 0;
                }

                // Ajouter le sous-chapitre avec ancre cohérente
                $subchapterAnchorId = 'chapter-' . $chapterCounter . '-subchapter-' . $subchapterNumber;
                $subchapterHtml = '<h2 id="' . $subchapterAnchorId . '" class="subchapter-title">' . $subchapterTitle . '</h2>';
                $currentPageHtml .= $subchapterHtml;
                $currentPageHeight += $subchapterTitleHeight;

                // Ajouter les paragraphes avec gestion des sauts de page et injection de liens
                foreach ($subchapter['paragraphs'] as $paragraphIndex => $paragraph) {
                    // Pour le PREMIER paragraphe après un sous-titre : vérification spéciale
                    // Si c'est le premier paragraphe et qu'il ne peut pas rentrer sans aller au fond de la page,
                    // on déplace TOUT (sous-titre + paragraphe) sur la nouvelle page
                    if ($paragraphIndex === 0 && !empty($paragraph)) {
                        $estimatedLines = max(1, ceil(strlen($paragraph) / 80));
                        $firstParagraphHeight = ($estimatedLines * $paragraphLineHeight) + $paragraphMargin;

                        // Si ajouter ce premier paragraphe ferait qu'il arrive trop près du bas,
                        // on déplace le sous-titre ET le paragraphe sur une nouvelle page
                        if ($currentPageHeight + $firstParagraphHeight + $paragraphBottomSafety > $maxPageHeight) {
                            // Retirer le sous-titre de la page actuelle
                            $currentPageHtml = substr($currentPageHtml, 0, -strlen($subchapterHtml));
                            $currentPageHeight -= $subchapterTitleHeight;

                            // Créer une nouvelle page
                            $currentPageHtml .= '</div>'; // Close chapter-container
                            $pages[] = $this->wrapInPageStructure($currentPageHtml, $watermarkImageBase64);

                            // Démarrer une nouvelle page avec le sous-titre
                            $currentPageHtml = '<div class="chapter-container page-container">';
                            $currentPageHtml .= $subchapterHtml;
                            $currentPageHeight = $subchapterTitleHeight;
                        }
                    }

                    // Maintenant traiter le paragraphe normalement
                    // Estimer la hauteur du paragraphe avec précision (si ce n'est pas déjà fait pour le premier paragraphe)
                    if ($paragraphIndex !== 0) {
                        $estimatedLines = max(1, ceil(strlen($paragraph) / 80));
                        $paragraphHeight = ($estimatedLines * $paragraphLineHeight) + $paragraphMargin;
                    } else {
                        // Réutiliser le calcul déjà fait pour le premier paragraphe
                        $paragraphHeight = $firstParagraphHeight;
                    }

                    // PROTECTION STRICTE : éviter que le paragraphe arrive trop près du bas
                    // On utilise une marge de sécurité renforcée pour garantir une bonne lisibilité
                    // Note: pour le premier paragraphe, cette vérification a déjà été faite ci-dessus
                    if ($paragraphIndex > 0 && $currentPageHeight + $paragraphHeight + $paragraphBottomSafety > $maxPageHeight && $currentPageHeight > 0) {
                        // Le paragraphe arriverait trop près du bas, créer une nouvelle page
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
