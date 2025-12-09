<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer la page d'introduction de l'ebook
 */
class IntroductionGenerator
{
    /**
     * Extraire le contenu de l'introduction depuis le formatted_text
     */
    public function extractIntroductionContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();
        $formattedText = $rawData['formatted_text'] ?? '';

        if (empty($formattedText)) {
            return null;
        }

        $lines = explode("\n", $formattedText);
        $introductionContent = [];
        $collectingIntroduction = false;
        $currentParagraph = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // Détecter le début de l'introduction
            // Très strict : le titre doit COMMENCER par "INTRODUCTION -" ou "INTRODUCTION:"
            // Exemples: "INTRODUCTION - Titre" (valide), "INTRODUCTION À LA..." (chapitre normal)
            if (preg_match('/^\[TITRE1\]\s*INTRODUCTION\s*[-:]/i', $line)) {
                $collectingIntroduction = true;
                // Extraire le titre de l'introduction
                if (preg_match('/^\[TITRE1\]\s*(.+)$/i', $line, $matches)) {
                    $introductionContent['title'] = trim($matches[1]);
                }
                continue;
            }

            // Si on collecte l'introduction
            if ($collectingIntroduction) {
                // Arrêter la collecte si on rencontre un nouveau TITRE1 (mais pas l'introduction)
                if (preg_match('/^\[TITRE1\]/', $line) && !preg_match('/^\s*INTRODUCTION\s*[-:]/i', $line)) {
                    break;
                }

                // Traiter les paragraphes normaux
                if (preg_match('/^\[NORMAL\]\s*(.+)$/i', $line, $matches)) {
                    if (!empty($currentParagraph)) {
                        $introductionContent['paragraphs'][] = trim($currentParagraph);
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
            $introductionContent['paragraphs'][] = trim($currentParagraph);
        }

        return !empty($introductionContent) ? $introductionContent : null;
    }

    /**
     * Générer le HTML de la page d'introduction
     */
    public function generateIntroductionHtml(?array $introductionContent, array $acronymAnchors = [], array &$firstOccurrenceFound = []): string
    {
        if (empty($introductionContent)) {
            return '';
        }

        $html = '';

        // Ajouter le titre de l'introduction avec ancre
        if (isset($introductionContent['title'])) {
            $html .= '<div class="introduction-container page-container">';
            $html .= '<a id="introduction" name="introduction"></a>';
            $html .= '<h1 class="introduction-title page-title">' . htmlspecialchars($introductionContent['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        }

        // Ajouter les paragraphes avec injection de liens vers le glossaire
        if (isset($introductionContent['paragraphs']) && is_array($introductionContent['paragraphs'])) {
            foreach ($introductionContent['paragraphs'] as $paragraph) {
                $paragraphWithLinks = $this->injectGlossaryLinks($paragraph, $acronymAnchors, $firstOccurrenceFound);
                $html .= '<p class="introduction-paragraph">' . $paragraphWithLinks . '</p>';
            }
        }

        if (isset($introductionContent['title'])) {
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
}