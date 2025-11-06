<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer les pages de quiz de l'ebook
 */
class QuizGenerator
{
    /**
     * Extraire le contenu du quiz depuis la section quiz du JSON
     */
    public function extractQuizContent(EbookData $ebookData): ?array
    {
        $rawData = $ebookData->getRawData();
        $quizData = $rawData['quiz'] ?? null;

        if (empty($quizData) || empty($quizData['questions'])) {
            return null;
        }

        $quizContent = [
            'questions' => []
        ];

        // Convertir la structure JSON en format attendu par generateQuizHtml
        foreach ($quizData['questions'] as $questionData) {
            $question = [
                'question' => $this->cleanTextEncoding($questionData['question']),
                'answers' => []
            ];

            // Trier les choix par numero_answer pour avoir l'ordre correct
            $choices = $questionData['choices'];
            usort($choices, function($a, $b) {
                return intval($a['numero_answer']) - intval($b['numero_answer']);
            });

            // Convertir les choix en réponses avec lettres
            $letters = ['a', 'b', 'c', 'd'];
            $letterIndex = 0;
            
            foreach ($choices as $choice) {
                if ($letterIndex < count($letters)) {
                    $question['answers'][] = [
                        'letter' => $letters[$letterIndex],
                        'text' => $this->cleanTextEncoding($choice['answer']),
                        'isCorrect' => $choice['good'] ?? false,
                        'explanation' => $this->cleanTextEncoding($choice['explanation'] ?? '')
                    ];
                    $letterIndex++;
                }
            }

            $quizContent['questions'][] = $question;
        }

        return $quizContent;
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

            // Construire d'abord le HTML de cette question
            $questionHtml = '<div class="quiz-question">';
            $questionHtml .= '<p class="quiz-question-text"><strong>' . $questionNumber . '. ' . htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') . '</strong></p>';

            // Ajouter les réponses
            if (!empty($question['answers'])) {
                $questionHtml .= '<div class="quiz-answers">';
                foreach ($question['answers'] as $answer) {
                    $questionHtml .= '<p class="quiz-answer">';
                    $questionHtml .= '<span class="answer-letter">' . htmlspecialchars($answer['letter'], ENT_QUOTES, 'UTF-8') . '</span> – ';
                    // Nettoyer et corriger l'encodage du texte
                    $cleanText = $this->cleanTextEncoding($answer['text']);
                    $questionHtml .= $cleanText;
                    $questionHtml .= '</p>';
                }
                $questionHtml .= '</div>';
            }

            $questionHtml .= '</div>';

            // Vérifier si cette question tient sur la page actuelle
            // Si on a déjà atteint la limite et qu'on n'est pas sur la première question
            if ($questionsOnCurrentPage >= $maxQuestionsPerPage && $index > 0) {
                // Sauvegarder la page actuelle et commencer une nouvelle
                $pages[] = $this->wrapPageContent($currentPageHtml, $pageNumber === 0);
                $currentPageHtml = '';
                $questionsOnCurrentPage = 0;
                $pageNumber++;
            }

            // Ajouter la question à la page actuelle
            $currentPageHtml .= $questionHtml;
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
     * Générer les pages de réponses du quiz
     * Retourne un tableau de pages HTML avec les réponses et explications
     */
    public function generateQuizAnswersHtml(?array $quizContent, string $language = 'français'): array
    {
        if (empty($quizContent) || empty($quizContent['questions'])) {
            return [];
        }

        $pages = [];
        $currentPageHtml = '';
        $answersOnCurrentPage = 0;
        $maxAnswersPerPage = 5; // Nombre moyen de réponses par page
        $pageNumber = 0; // Compteur de pages
        $title = $this->getAnswersPageTitle($language);

        foreach ($quizContent['questions'] as $index => $question) {
            $questionNumber = $index + 1;
            
            // Trouver la réponse correcte
            $correctAnswer = null;
            foreach ($question['answers'] as $answer) {
                if ($answer['isCorrect']) {
                    $correctAnswer = $answer;
                    break;
                }
            }

            if ($correctAnswer) {
                // Construire d'abord le HTML de cette réponse
                $answerHtml = '<div class="quiz-answer-entry">';
                $answerHtml .= '<p class="quiz-answer-question"><strong>' . $questionNumber . '. ' . htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') . '</strong></p>';
                
                $answerHtml .= '<p class="quiz-answer-correct">';
                $answerLabel = $this->getAnswerLabel($language);
                $answerHtml .= '<strong>' . htmlspecialchars($answerLabel, ENT_QUOTES, 'UTF-8') . '</strong> ';
                $answerHtml .= '<span class="answer-letter">' . htmlspecialchars(strtoupper($correctAnswer['letter']), ENT_QUOTES, 'UTF-8') . '</span> – ';
                $answerHtml .= htmlspecialchars($correctAnswer['text'], ENT_QUOTES, 'UTF-8');
                $answerHtml .= '</p>';

                if (!empty($correctAnswer['explanation'])) {
                    $answerHtml .= '<p class="quiz-answer-explanation">';
                    $explanationLabel = $this->getExplanationLabel($language);
                    $answerHtml .= '<strong>' . htmlspecialchars($explanationLabel, ENT_QUOTES, 'UTF-8') . '</strong> ';
                    $answerHtml .= htmlspecialchars($correctAnswer['explanation'], ENT_QUOTES, 'UTF-8');
                    $answerHtml .= '</p>';
                }

                $answerHtml .= '</div>';

                // Vérifier si cette réponse tient sur la page actuelle
                // Si on a déjà atteint la limite et qu'on n'est pas sur la première réponse
                if ($answersOnCurrentPage >= $maxAnswersPerPage && $index > 0) {
                    // Sauvegarder la page actuelle et commencer une nouvelle
                    $pages[] = $this->wrapAnswersPageContent($currentPageHtml, $pageNumber === 0, $title);
                    $currentPageHtml = '';
                    $answersOnCurrentPage = 0;
                    $pageNumber++;
                }

                // Ajouter la réponse à la page actuelle
                $currentPageHtml .= $answerHtml;
                $answersOnCurrentPage++;
            }
        }

        // Ajouter la dernière page
        if (!empty($currentPageHtml)) {
            $pages[] = $this->wrapAnswersPageContent($currentPageHtml, $pageNumber === 0, $title);
        }

        return $pages;
    }

    /**
     * Envelopper le contenu de la page de réponses dans un conteneur
     */
    private function wrapAnswersPageContent(string $content, bool $isFirstPage, string $title): string
    {
        $titleHtml = $isFirstPage ? '<h1 class="quiz-answers-page-title page-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' : '';
        return '<div class="quiz-answers-container page-container">' . $titleHtml . $content . '</div>';
    }

    /**
     * Obtenir le titre de la page de réponses selon la langue
     */
    private function getAnswersPageTitle(string $language): string
    {
        $language = strtolower($language);
        if ($language === 'english' || $language === 'en') {
            return 'ANSWERS';
        }
        return 'RÉPONSES';
    }

    /**
     * Obtenir le label "Réponse" selon la langue
     */
    private function getAnswerLabel(string $language): string
    {
        $language = strtolower($language);
        if ($language === 'english' || $language === 'en') {
            return 'Answer:';
        }
        return 'Réponse :';
    }

    /**
     * Obtenir le label "Explication" selon la langue
     */
    private function getExplanationLabel(string $language): string
    {
        $language = strtolower($language);
        if ($language === 'english' || $language === 'en') {
            return 'Explanation:';
        }
        return 'Explication :';
    }

    /**
     * Nettoyer et corriger l'encodage du texte
     */
    private function cleanTextEncoding(string $text): string
    {
        // Supprimer les caractères de remplacement UTF-8 ()
        $text = str_replace('', '', $text);
        
        // Supprimer seulement les caractères de contrôle vraiment problématiques
        // (pas les caractères UTF-8 valides dans la plage 80-9F)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Supprimer les caractères Unicode problématiques
        $text = preg_replace('/[\x{FFFD}]/u', '', $text);
        
        // Nettoyer les espaces
        $text = trim($text);
        
        // Échapper les caractères HTML dangereux
        $text = str_replace(['<', '>', '&'], ['&lt;', '&gt;', '&amp;'], $text);
        
        return $text;
    }
}
