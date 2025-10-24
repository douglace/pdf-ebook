<?php

namespace Vex6\Phppdf\Services;

/**
 * Service pour générer la section quiz avec questions et réponses
 */
class QuizGenerator
{
    /**
     * Générer le HTML de la section quiz
     */
    public function generateQuizHtml(array $quizBlocks, ?string $quizAnswers = null, array $acronymLinks = []): string
    {
        if (empty($quizBlocks)) {
            return '';
        }

        $html = '<div class="page page-quiz">';
        $html .= '<div class="quiz-watermark"><img src="{{WATERMARK_IMAGE}}" alt=""></div>';
        $html .= '<div class="quiz-container page-container">';
        
        // Titre de la section
        $html .= '<a id="quiz" name="quiz"></a>';
        $html .= '<h1 class="section-title">QUIZ</h1>';

        // Introduction standard
        $html .= '<p class="quiz-intro">Pour vérifier les connaissances acquises, nous vous proposons le quiz suivant.</p>';

        // Générer les questions et réponses
        foreach ($quizBlocks as $block) {
            $tag = $block['tag'];
            $text = $block['text'];

            // Ignorer le titre QUIZ lui-même
            if ($tag === 'TITRE1' && stripos($text, 'QUIZ') !== false) {
                continue;
            }

            // Injecter les liens de glossaire si applicable
            if ($this->shouldInjectGlossaryLinks($tag) && !empty($acronymLinks)) {
                $text = $this->injectGlossaryLinks($text, $acronymLinks);
            }

            $html .= $this->generateQuizBlockHtml($tag, $text);
        }

        // Ajouter les réponses correctes si disponibles
        if (!empty($quizAnswers)) {
            $html .= '<div class="quiz-answers">';
            $html .= '<h3 class="quiz-answers-title">RÉPONSES</h3>';
            $html .= '<p class="quiz-answers-text">' . htmlspecialchars($quizAnswers, ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Générer le HTML pour un bloc de quiz
     */
    private function generateQuizBlockHtml(string $tag, string $text): string
    {
        switch ($tag) {
            case 'ENTREE_QUIZ':
                // Vérifier si c'est une réponse (a/b/c/d)
                if ($this->isQuizAnswer($text)) {
                    return '<p class="quiz-answer">' . $text . '</p>';
                } else {
                    return '<div class="quiz-question">' . $text . '</div>';
                }
                
            case 'ENTREE_QUIZ_ROW':
                // Réponse de quiz (déjà convertie depuis NORMAL)
                if ($this->isQuizAnswer($text)) {
                    return '<p class="quiz-answer">' . $text . '</p>';
                } else {
                    return '<p class="quiz-text">' . $text . '</p>';
                }
                
            case 'NORMAL':
                return '<p class="quiz-text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
                
            default:
                return '<p class="quiz-text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }

    /**
     * Détecter si un texte est une réponse de quiz (a/b/c/d)
     */
    private function isQuizAnswer(string $text): bool
    {
        // Regex selon la spécification: /^\\s*[a-d]\\s*[-–]\\s/i
        return preg_match('/^\s*[a-d]\s*[-–]\s/i', $text) === 1;
    }

    /**
     * Déterminer si on doit injecter les liens de glossaire
     */
    private function shouldInjectGlossaryLinks(string $tag): bool
    {
        // Injecter dans tous les types de contenu quiz
        return true;
    }

    /**
     * Injecter les liens de glossaire dans le texte
     */
    private function injectGlossaryLinks(string $text, array $acronymLinks): string
    {
        if (empty($acronymLinks)) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // Créer le pattern regex pour tous les acronymes
        $acronyms = array_keys($acronymLinks);
        $escapedAcronyms = array_map('preg_quote', $acronyms);
        $pattern = '/(\s*)(\b(?:' . implode('|', $escapedAcronyms) . ')\b)(\s*)/u';

        $result = preg_replace_callback($pattern, function($match) use ($acronymLinks) {
            $spaceBefore = $match[1];
            $acronym = $match[2];
            $spaceAfter = $match[3];

            if (!isset($acronymLinks[$acronym])) {
                return htmlspecialchars($match[0], ENT_QUOTES, 'UTF-8');
            }

            $linkInfo = $acronymLinks[$acronym];
            $link = '<a href="#' . $linkInfo['anchor'] . '" class="glossary-link">' . 
                   htmlspecialchars($acronym, ENT_QUOTES, 'UTF-8') . 
                   '</a>';

            return htmlspecialchars($spaceBefore, ENT_QUOTES, 'UTF-8') . 
                   $link . 
                   htmlspecialchars($spaceAfter, ENT_QUOTES, 'UTF-8');
        }, $text);

        return $result;
    }

    /**
     * Extraire le nombre de questions de quiz
     */
    public function countQuizQuestions(array $quizBlocks): int
    {
        $questionCount = 0;

        foreach ($quizBlocks as $block) {
            $tag = $block['tag'];
            $text = $block['text'];

            // Compter les questions (ENTREE_QUIZ qui ne sont pas des réponses a/b/c/d)
            if ($tag === 'ENTREE_QUIZ' && !$this->isQuizAnswer($text)) {
                // Ignorer le titre QUIZ lui-même
                if (!(stripos($text, 'QUIZ') !== false)) {
                    $questionCount++;
                }
            }
        }

        return $questionCount;
    }

    /**
     * Valider le format des réponses de quiz
     */
    public function validateQuizAnswers(?string $answers): bool
    {
        if (empty($answers)) {
            return false;
        }

        // Vérifier le format: "1-a, 2-c, 3-d, ..."
        return preg_match('/^\d+-[a-d](\s*,\s*\d+-[a-d])*$/', $answers) === 1;
    }

    /**
     * Formater les réponses de quiz pour l'affichage
     */
    public function formatQuizAnswers(?string $answers): ?string
    {
        if (empty($answers) || !$this->validateQuizAnswers($answers)) {
            return null;
        }

        // Nettoyer les espaces autour des virgules
        return preg_replace('/\s*,\s*/', ', ', $answers);
    }

    /**
     * Extraire les statistiques du quiz
     */
    public function getQuizStats(array $quizBlocks): array
    {
        $stats = [
            'questions' => 0,
            'answers' => 0,
            'total_blocks' => count($quizBlocks)
        ];

        foreach ($quizBlocks as $block) {
            $tag = $block['tag'];
            $text = $block['text'];

            if ($tag === 'ENTREE_QUIZ') {
                if ($this->isQuizAnswer($text)) {
                    $stats['answers']++;
                } else {
                    // Ignorer le titre QUIZ lui-même
                    if (!(stripos($text, 'QUIZ') !== false)) {
                        $stats['questions']++;
                    }
                }
            } elseif ($tag === 'ENTREE_QUIZ_ROW' && $this->isQuizAnswer($text)) {
                $stats['answers']++;
            }
        }

        return $stats;
    }
}