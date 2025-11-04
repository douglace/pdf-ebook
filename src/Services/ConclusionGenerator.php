<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer la page de conclusion de l'ebook
 */
class ConclusionGenerator
{
    /**
     * Extraire le contenu de la conclusion depuis le formatted_text
     */
    public function extractConclusionContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();
        $formattedText = $rawData['formatted_text'] ?? '';

        if (empty($formattedText)) {
            return null;
        }

        $lines = explode("\n", $formattedText);
        $conclusionContent = [];
        $collectingConclusion = false;
        $currentParagraph = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // Détecter le début de la conclusion (TITRE1 contenant "CONCLUSION")
            if (preg_match('/^\[TITRE1\].*CONCLUSION/i', $line)) {
                $collectingConclusion = true;
                // Extraire le titre de la conclusion
                if (preg_match('/^\[TITRE1\]\s*(.+)$/i', $line, $matches)) {
                    $conclusionContent['title'] = trim($matches[1]);
                }
                continue;
            }

            // Si on collecte la conclusion
            if ($collectingConclusion) {
                // Arrêter la collecte si on rencontre un nouveau TITRE1 (mais pas la conclusion)
                if (preg_match('/^\[TITRE1\]/', $line) && !preg_match('/CONCLUSION/i', $line)) {
                    break;
                }

                // Traiter les paragraphes normaux
                if (preg_match('/^\[NORMAL\]\s*(.+)$/i', $line, $matches)) {
                    if (!empty($currentParagraph)) {
                        $conclusionContent['paragraphs'][] = trim($currentParagraph);
                        $currentParagraph = '';
                    }
                    $currentParagraph = trim($matches[1]);
                } elseif (!empty($line) && !preg_match('/^\[/', $line)) {
                    // Ligne de continuation du paragraphe
                    $currentParagraph .= ' ' . $line;
                }
            }
        }

        // Ajouter le dernier paragraphe s'il existe
        if (!empty($currentParagraph)) {
            $conclusionContent['paragraphs'][] = trim($currentParagraph);
        }

        return !empty($conclusionContent) ? $conclusionContent : null;
    }

    /**
     * Générer le HTML de la page de conclusion
     */
    public function generateConclusionHtml(?array $conclusionContent, array $acronymAnchors = [], array &$firstOccurrenceFound = []): string
    {
        if (empty($conclusionContent)) {
            return '';
        }

        $html = '';

        // Ajouter le titre de la conclusion avec ancre
        if (isset($conclusionContent['title'])) {
            $html .= '<div class="conclusion-container page-container">';
            $html .= '<a id="conclusion" name="conclusion"></a>';
            $html .= '<h1 class="conclusion-title page-title">' . htmlspecialchars($conclusionContent['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        }

        // Ajouter les paragraphes avec injection de liens vers le glossaire
        if (isset($conclusionContent['paragraphs']) && is_array($conclusionContent['paragraphs'])) {
            foreach ($conclusionContent['paragraphs'] as $paragraph) {
                $paragraphWithLinks = $this->injectGlossaryLinks($paragraph, $acronymAnchors, $firstOccurrenceFound);
                $html .= '<p class="conclusion-paragraph">' . $paragraphWithLinks . '</p>';
            }
        }

        if (isset($conclusionContent['title'])) {
            $html .= '</div>';
        }

        return $html;
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
