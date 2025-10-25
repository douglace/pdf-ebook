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
            if (preg_match('/^\[TITRE1\].*INTRODUCTION/i', $line)) {
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
                if (preg_match('/^\[TITRE1\]/', $line) && !preg_match('/INTRODUCTION/i', $line)) {
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
    public function generateIntroductionHtml(?array $introductionContent): string
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

        // Ajouter les paragraphes
        if (isset($introductionContent['paragraphs']) && is_array($introductionContent['paragraphs'])) {
            foreach ($introductionContent['paragraphs'] as $paragraph) {
                $html .= '<p class="introduction-paragraph">' . htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }

        if (isset($introductionContent['title'])) {
            $html .= '</div>';
        }

        return $html;
    }
}