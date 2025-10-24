<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour parser le formatted_text et extraire toutes les zones de contenu
 */
class FormattedTextParser
{
    /**
     * Parser le formatted_text et extraire toutes les zones
     * 
     * Retourne:
     * [
     *     'titre' => string,
     *     'sous_titre' => string,
     *     'langue' => string,
     *     'corps' => [
     *         ['tag' => 'TITRE1', 'text' => '...'],
     *         ['tag' => 'NORMAL', 'text' => '...'],
     *         ...
     *     ],
     *     'toc' => [
     *         ['level' => 'TITRE1', 'title' => '...'],
     *         ['level' => 'TITRE2', 'title' => '...'],
     *         ...
     *     ],
     *     'glossaire_links' => []
     * ]
     */
    public function parseZones(EbookData $ebookData): array
    {
        $rawData = $ebookData->getRawData();
        $formattedText = $rawData['formatted_text'] ?? '';

        if (empty($formattedText)) {
            return [
                'titre' => '',
                'sous_titre' => '',
                'langue' => 'Français',
                'corps' => [],
                'toc' => [],
                'glossaire_links' => []
            ];
        }

        $lines = explode("\n", $formattedText);
        $result = [
            'titre' => '',
            'sous_titre' => '',
            'langue' => 'Français',
            'corps' => [],
            'toc' => [],
            'glossaire_links' => []
        ];

        // Phase 1: Extraire les champs spéciaux pour la couverture
        $result = $this->extractSpecialFields($lines, $result);

        // Phase 2: Extraire tous les blocs de contenu taggés
        $result = $this->extractContentBlocks($lines, $result);

        return $result;
    }

    /**
     * Extraire les champs spéciaux de couverture
     */
    private function extractSpecialFields(array $lines, array $result): array
    {
        $text = implode("\n", $lines);

        // Extraire le titre
        if (preg_match('/\[DEBUT_CHAMP_TITRE\](.*?)\[FIN_CHAMP_TITRE\]/s', $text, $matches)) {
            $result['titre'] = trim($matches[1]);
        }

        // Extraire le sous-titre
        if (preg_match('/\[DEBUT_CHAMP_SOUS_TITRE\](.*?)\[FIN_CHAMP_SOUS_TITRE\]/s', $text, $matches)) {
            $result['sous_titre'] = trim($matches[1]);
        }

        // Extraire la langue
        if (preg_match('/\[DEBUT_CHAMP_LANGUE\](.*?)\[FIN_CHAMP_LANGUE\]/s', $text, $matches)) {
            $result['langue'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * Extraire tous les blocs de contenu taggés
     */
    private function extractContentBlocks(array $lines, array $result): array
    {
        $i = 0;
        $totalLines = count($lines);

        while ($i < $totalLines) {
            $line = trim($lines[$i]);

            // Ignorer les lignes vides
            if (empty($line)) {
                $i++;
                continue;
            }

            // Ignorer les champs spéciaux (déjà traités)
            if (preg_match('/^\[DEBUT_CHAMP_/', $line) || preg_match('/^\[FIN_CHAMP_/', $line)) {
                $i++;
                continue;
            }

            // Détecter les tags de contenu
            if (preg_match('/^\[([A-Z_]+)\]\s*(.*)$/i', $line, $matches)) {
                $tag = strtoupper($matches[1]);
                $initialText = $matches[2];

                // Lire le contenu multi-lignes
                $contentLines = [$initialText];
                $i++;

                // Continuer à lire tant qu'on ne rencontre pas un nouveau tag ou champ spécial
                while ($i < $totalLines) {
                    $nextLine = trim($lines[$i]);

                    // Arrêter si ligne vide
                    if (empty($nextLine)) {
                        break;
                    }

                    // Arrêter si nouveau tag
                    if (preg_match('/^\[([A-Z_]+)\]/', $nextLine)) {
                        break;
                    }

                    // Arrêter si champ spécial
                    if (preg_match('/^\[DEBUT_CHAMP_/', $nextLine) || preg_match('/^\[FIN_CHAMP_/', $nextLine)) {
                        break;
                    }

                    $contentLines[] = $nextLine;
                    $i++;
                }

                // Concaténer le contenu avec des espaces
                $text = trim(implode(' ', $contentLines));

                // Traitement spécial pour WIKI_LIEN (enlever la balise fermante)
                if ($tag === 'WIKI_LIEN' && preg_match('/^(.*?)\s*\[WIKI_LIEN\]$/', $text, $wikiMatch)) {
                    $text = trim($wikiMatch[1]);
                }

                // Ajouter le bloc de contenu
                if (!empty($text)) {
                    $result['corps'][] = [
                        'tag' => $tag,
                        'text' => $text
                    ];

                    // Ajouter à la TOC si c'est un titre
                    if ($tag === 'TITRE1' || $tag === 'TITRE2') {
                        $result['toc'][] = [
                            'level' => $tag,
                            'title' => $text
                        ];
                    }
                }
            } else {
                $i++;
            }
        }

        return $result;
    }

    /**
     * Obtenir les sections spécifiques par ordre logique
     */
    public function getSectionsByOrder(array $zones): array
    {
        $sections = [
            'introduction' => [],
            'main_chapters' => [],
            'conclusion' => [],
            'glossaire' => [],
            'quiz' => [],
            'wikipedia' => [],
            'websites' => [],
            'suggestions' => []
        ];

        $currentSection = 'main_chapters';
        $inQuiz = false;

        foreach ($zones['corps'] as $entry) {
            $tag = $entry['tag'];
            $text = $entry['text'];

            // Détecter les sections spéciales par titre
            if ($tag === 'TITRE1') {
                $titleUpper = strtoupper($text);
                
                if (strpos($titleUpper, 'INTRODUCTION') !== false) {
                    $currentSection = 'introduction';
                } elseif (strpos($titleUpper, 'CONCLUSION') !== false) {
                    $currentSection = 'conclusion';
                } elseif (strpos($titleUpper, 'GLOSSAIRE') !== false) {
                    $currentSection = 'glossaire';
                } elseif (strpos($titleUpper, 'QUIZ') !== false) {
                    $currentSection = 'quiz';
                    $inQuiz = true;
                } elseif (strpos($titleUpper, 'WIKIPEDIA') !== false) {
                    $currentSection = 'wikipedia';
                    $inQuiz = false;
                } elseif (strpos($titleUpper, 'SITES WEB') !== false) {
                    $currentSection = 'websites';
                    $inQuiz = false;
                } elseif (strpos($titleUpper, 'SUGGESTIONS') !== false) {
                    $currentSection = 'suggestions';
                    $inQuiz = false;
                } else {
                    $currentSection = 'main_chapters';
                    $inQuiz = false;
                }
            }

            // Traitement spécial pour les réponses de quiz
            if ($tag === 'ENTREE_QUIZ') {
                $inQuiz = true;
            }

            // Convertir NORMAL en ENTREE_QUIZ_ROW si on est dans une section quiz
            if ($inQuiz && $tag === 'NORMAL') {
                $tag = 'ENTREE_QUIZ_ROW';
            } elseif ($inQuiz && $tag !== 'ENTREE_QUIZ') {
                $inQuiz = false;
            }

            $sections[$currentSection][] = [
                'tag' => $tag,
                'text' => $text
            ];
        }

        return $sections;
    }

    /**
     * Extraire tous les acronymes du glossaire
     */
    public function extractAcronyms(array $zones): array
    {
        $acronyms = [];

        foreach ($zones['corps'] as $entry) {
            if ($entry['tag'] === 'ENTREE_GLOSSAIRE') {
                // Format attendu : "ACRONYME - Définition complète"
                $parts = explode(' - ', $entry['text'], 2);
                $acronym = trim($parts[0]);
                $definition = $parts[1] ?? '';

                if (!empty($acronym)) {
                    $acronyms[] = [
                        'acronym' => $acronym,
                        'definition' => $definition,
                        'full_text' => $entry['text']
                    ];
                }
            }
        }

        return $acronyms;
    }

    /**
     * Extraire les réponses de quiz depuis le formatted_text
     */
    public function extractQuizAnswers(EbookData $ebookData): ?string
    {
        $rawData = $ebookData->getRawData();
        $formattedText = $rawData['formatted_text'] ?? '';

        if (empty($formattedText)) {
            return null;
        }

        $lines = explode("\n", $formattedText);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Chercher une ligne contenant "REPONSES"
            if (stripos($line, 'REPONSES') !== false) {
                // Regarder la ligne suivante
                if (isset($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    // Vérifier si c'est bien une ligne de réponses (format: 1-a, 2-c, etc.)
                    if (preg_match('/^\d+-[a-d]/', $nextLine)) {
                        return $nextLine;
                    }
                }
            }
        }

        return null;
    }
}