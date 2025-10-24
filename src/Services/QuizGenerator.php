<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer les pages de quiz de l'ebook
 */
class QuizGenerator
{
    /**
     * Extraire le contenu du quiz depuis le formatted_text
     */
    public function extractQuizContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();
        $formattedText = $rawData['formatted_text'] ?? '';

        if (empty($formattedText)) {
            return null;
        }

        $lines = explode("\n", $formattedText);
        $quizContent = [];
        $collectingQuiz = false;
        $currentQuestion = null;
        $answerLine = '';

        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);

            // Détecter le début du quiz (TITRE1 contenant "QUIZ")
            if (preg_match('/^\[TITRE1\].*QUIZ/i', $line)) {
                $collectingQuiz = true;
                continue;
            }

            // Si on collecte le quiz
            if ($collectingQuiz) {
                // Arrêter si on rencontre un nouveau TITRE1 (mais pas QUIZ)
                if (preg_match('/^\[TITRE1\]/', $line) && !preg_match('/QUIZ/i', $line)) {
                    break;
                }

                // Détecter une entrée de quiz (question)
                if (preg_match('/^\[ENTREE_QUIZ\]\s*(.+)$/i', $line, $matches)) {
                    // Sauvegarder la question précédente si elle existe
                    if ($currentQuestion !== null) {
                        $quizContent['questions'][] = $currentQuestion;
                    }

                    // Nouvelle question
                    $currentQuestion = [
                        'question' => $this->cleanTextEncoding(trim($matches[1])),
                        'answers' => []
                    ];
                    continue;
                }

                // Détecter les réponses (lignes NORMAL après ENTREE_QUIZ)
                if ($currentQuestion !== null && preg_match('/^\[NORMAL\]\s*([a-d])\s*[-–]\s*(.+)$/i', $line, $matches)) {
                    $currentQuestion['answers'][] = [
                        'letter' => strtolower(trim($matches[1])),
                        'text' => $this->cleanTextEncoding(trim($matches[2]))
                    ];
                    continue;
                }

                // Détecter la section REPONSES
                if (preg_match('/^\[NORMAL\]\s*REPONSES\s*$/i', $line)) {
                    // La ligne suivante contient les réponses
                    if (isset($lines[$lineIndex + 1])) {
                        $nextLine = trim($lines[$lineIndex + 1]);
                        if (preg_match('/^\[NORMAL\]\s*(.+)$/i', $nextLine, $matches)) {
                            $answerLine = $this->cleanTextEncoding(trim($matches[1]));
                        }
                    }
                }
            }
        }

        // Ajouter la dernière question
        if ($currentQuestion !== null) {
            $quizContent['questions'][] = $currentQuestion;
        }

        // Ajouter la ligne de réponses
        if (!empty($answerLine)) {
            $quizContent['answers'] = $answerLine;
        }

        return !empty($quizContent) ? $quizContent : null;
    }

    /**
     * Générer le HTML des pages de quiz
     * Retourne un tableau de pages HTML
     */
    public function generateQuizHtml(?array $quizContent): array
    {
        if (empty($quizContent) || empty($quizContent['questions'])) {
            return [];
        }

        $pages = [];
        $currentPageHtml = '';
        $questionsOnCurrentPage = 0;
        $maxQuestionsPerPage = 5; // Nombre moyen de questions par page
        $pageNumber = 0; // Compteur de pages

        // Introduction du quiz
        $introduction = '<div class="quiz-introduction">';
        $introduction .= '<p>Pour vérifier les connaissances acquises, nous vous proposons le quiz suivant.</p>';
        $introduction .= '</div>';

        $currentPageHtml .= $introduction;
        $questionsOnCurrentPage = 0; // L'intro compte pour de l'espace

        foreach ($quizContent['questions'] as $index => $question) {
            $questionNumber = $index + 1;

            // Estimer si cette question tient sur la page actuelle
            // (question + 4 réponses = environ 1 question)
            if ($questionsOnCurrentPage >= $maxQuestionsPerPage && $index > 0) {
                // Sauvegarder la page actuelle et commencer une nouvelle
                $pages[] = $this->wrapPageContent($currentPageHtml, $pageNumber === 0);
                $currentPageHtml = '';
                $questionsOnCurrentPage = 0;
                $pageNumber++;
            }

            // Ajouter la question
            $currentPageHtml .= '<div class="quiz-question">';
            $currentPageHtml .= '<p class="quiz-question-text"><strong>' . $questionNumber . '. ' . htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') . '</strong></p>';

            // Ajouter les réponses
            if (!empty($question['answers'])) {
                $currentPageHtml .= '<div class="quiz-answers">';
                foreach ($question['answers'] as $answer) {
                    $currentPageHtml .= '<p class="quiz-answer">';
                    $currentPageHtml .= '<span class="answer-letter">' . htmlspecialchars($answer['letter'], ENT_QUOTES, 'UTF-8') . '</span> – ';
                    // Nettoyer et corriger l'encodage du texte
                    $cleanText = $this->cleanTextEncoding($answer['text']);
                    $currentPageHtml .= $cleanText;
                    $currentPageHtml .= '</p>';
                }
                $currentPageHtml .= '</div>';

            }

            $currentPageHtml .= '</div>';
            $questionsOnCurrentPage++;
        }

        // Ajouter les réponses correctes à la fin
        if (!empty($quizContent['answers'])) {
            $currentPageHtml .= '<div class="quiz-answers-section">';
            $currentPageHtml .= '<p class="quiz-answers-title"><strong>RÉPONSES</strong></p>';
            $currentPageHtml .= '<p class="quiz-answers-text">' . htmlspecialchars($quizContent['answers'], ENT_QUOTES, 'UTF-8') . '</p>';
            $currentPageHtml .= '</div>';
        }

        // Ajouter la dernière page
        if (!empty($currentPageHtml)) {
            $pages[] = $this->wrapPageContent($currentPageHtml, $pageNumber === 0);
        }

        return $pages;
    }

    /**
     * Envelopper le contenu dans un conteneur de page
     */
    private function wrapPageContent(string $content, bool $isFirstPage = false): string
    {
        $titleHtml = $isFirstPage ? '<h1 class="quiz-title page-title">QUIZ</h1>' : '';
        return '<div class="quiz-container page-container">' . $titleHtml . $content . '</div>';
    }

    /**
     * Générer un titre pour la première page du quiz
     */
    public function generateQuizTitleHtml(): string
    {
        return '<a id="quiz" name="quiz"></a>';
    }

    /**
     * Nettoyer et corriger l'encodage du texte
     */
    private function cleanTextEncoding(string $text): string
    {
        // Supprimer les caractères de remplacement UTF-8 ()
        $text = str_replace('', '', $text);
        
        // Supprimer les caractères de contrôle et non imprimables
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x80-\x9F]/', '', $text);
        
        // Supprimer les caractères Unicode problématiques
        $text = preg_replace('/[\x{FFFD}]/u', '', $text);
        
        // Nettoyer les espaces
        $text = trim($text);
        
        // Échapper les caractères HTML dangereux
        $text = str_replace(['<', '>', '&'], ['&lt;', '&gt;', '&amp;'], $text);
        
        return $text;
    }
}
