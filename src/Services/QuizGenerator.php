<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer les pages de quiz de l'ebook
 */
class QuizGenerator
{
    /**
     * Dimensions de la page A4 en pixels (à 96 DPI)
     * 297mm = 1123px, 210mm = 794px
     */
    private const PAGE_HEIGHT_PX = 1123;
    private const PAGE_WIDTH_PX = 794;

    /**
     * Marges et espacements en pixels
     * - Top du conteneur : 80px (défini dans CSS)
     * - Marge de sécurité en bas pour les questions : 60px (pour éviter de dépasser)
     * - Marge de sécurité en bas pour les réponses : 30px (plus agressive pour mieux remplir)
     */
    private const CONTAINER_TOP_PX = 80;
    private const SAFETY_MARGIN_BOTTOM_PX = 60;
    private const SAFETY_MARGIN_BOTTOM_ANSWERS_PX = 30;

    /**
     * Hauteur du titre "QUIZ" en pixels (estimation basée sur page-title)
     */
    private const TITLE_HEIGHT_PX = 50;

    /**
     * Hauteur de l'introduction en pixels
     */
    private const INTRODUCTION_HEIGHT_PX = 50;

    /**
     * Paramètres CSS pour le calcul de hauteur
     * Basés sur les valeurs réelles du fichier styles.css
     */
    private const QUESTION_FONT_SIZE_PX = 12;
    private const QUESTION_LINE_HEIGHT = 1.6;
    private const QUESTION_MARGIN_BOTTOM_PX = 25;
    private const QUESTION_TEXT_MARGIN_BOTTOM_PX = 10;

    private const ANSWER_FONT_SIZE_PX = 11;
    private const ANSWER_LINE_HEIGHT = 1.5;
    private const ANSWER_MARGIN_BOTTOM_PX = 6;
    private const ANSWERS_LEFT_MARGIN_PX = 20;

    private const ANSWER_ENTRY_MARGIN_BOTTOM_PX = 30;
    private const ANSWER_QUESTION_MARGIN_BOTTOM_PX = 10;
    private const ANSWER_CORRECT_MARGIN_BOTTOM_PX = 10;
    private const ANSWER_EXPLANATION_MARGIN_BOTTOM_PX = 10;

    /**
     * Largeur approximative du conteneur en pixels pour calculer le nombre de caractères par ligne
     * page-container: left: 120px, right: 80px
     * Donc largeur = 794 - 120 - 80 = 594px
     */
    private const CONTAINER_WIDTH_PX = 594;

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
        $currentPageHeightPx = 0; // Hauteur actuelle de la page en pixels
        $pageNumber = 0; // Compteur de pages

        // Introduction du quiz
        $introduction = '<div class="quiz-introduction">';
        $introduction .= '<p>Pour vérifier les connaissances acquises, nous vous proposons le quiz suivant.</p>';
        $introduction .= '</div>';

        $currentPageHtml .= $introduction;
        $currentPageHeightPx += self::INTRODUCTION_HEIGHT_PX;

        foreach ($quizContent['questions'] as $index => $question) {
            $questionNumber = $index + 1;

            // Calculer la hauteur estimée de cette question complète
            $questionHeightPx = $this->estimateQuestionHeight($question);

            // Calculer la hauteur disponible sur la page actuelle
            $availableHeightPx = $this->getAvailableHeightForPage($pageNumber);

            // Vérifier si l'ajout de cette question dépasserait la limite (avec marge de sécurité de 60px)
            if ($currentPageHeightPx > 0 && ($currentPageHeightPx + $questionHeightPx) > ($availableHeightPx - self::SAFETY_MARGIN_BOTTOM_PX)) {
                // Sauvegarder la page actuelle et commencer une nouvelle page
                $pages[] = $this->wrapPageContent($currentPageHtml, $pageNumber === 0);
                $currentPageHtml = '';
                $currentPageHeightPx = 0;
                $pageNumber++;
            }

            // Construire le HTML complet de la question avec TOUTES ses réponses
            $questionHtml = '<div class="quiz-question">';
            $questionHtml .= '<p class="quiz-question-text"><strong>' . $questionNumber . '. ' . htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') . '</strong></p>';

            // Ajouter TOUTES les réponses
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

            // Ajouter la question complète à la page actuelle
            $currentPageHtml .= $questionHtml;
            $currentPageHeightPx += $questionHeightPx;
        }

        // Ajouter les réponses correctes à la fin (obsolète, mais on garde pour compatibilité)
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
        $currentPageHeightPx = 0; // Hauteur actuelle de la page en pixels
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
                // Calculer la hauteur estimée de cette entrée complète (question + réponse + explication)
                $answerEntryHeightPx = $this->estimateAnswerEntryHeight($question, $correctAnswer, $language);

                // Calculer la hauteur disponible sur la page actuelle
                $availableHeightPx = $this->getAvailableHeightForPage($pageNumber);

                // Vérifier si l'ajout de cette entrée dépasserait la limite (avec marge de sécurité réduite pour mieux remplir)
                if ($currentPageHeightPx > 0 && ($currentPageHeightPx + $answerEntryHeightPx) > ($availableHeightPx - self::SAFETY_MARGIN_BOTTOM_ANSWERS_PX)) {
                    // Sauvegarder la page actuelle et commencer une nouvelle page
                    $pages[] = $this->wrapAnswersPageContent($currentPageHtml, $pageNumber === 0, $title);
                    $currentPageHtml = '';
                    $currentPageHeightPx = 0;
                    $pageNumber++;
                }

                // Construire le HTML complet de cette entrée (question + réponse + explication)
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

                // Ajouter l'entrée complète à la page actuelle
                $currentPageHtml .= $answerHtml;
                $currentPageHeightPx += $answerEntryHeightPx;
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
     * Obtenir la hauteur disponible pour une page donnée
     * 
     * @param int $pageNumber Numéro de la page (0 = première page)
     * @return int Hauteur disponible en pixels
     */
    private function getAvailableHeightForPage(int $pageNumber): int
    {
        // Hauteur totale de la page
        $totalHeight = self::PAGE_HEIGHT_PX;
        
        // Moins la marge du haut du conteneur
        $availableHeight = $totalHeight - self::CONTAINER_TOP_PX;
        
        // Moins la hauteur du titre si c'est la première page
        if ($pageNumber === 0) {
            $availableHeight -= self::TITLE_HEIGHT_PX;
        }
        
        return $availableHeight;
    }

    /**
     * Estimer la hauteur d'une question complète (question + toutes les réponses)
     * 
     * @param array $question Données de la question
     * @return int Hauteur estimée en pixels
     */
    private function estimateQuestionHeight(array $question): int
    {
        $height = 0;
        
        // Hauteur de la question elle-même
        $questionText = $question['question'];
        $questionLines = $this->calculateTextLines($questionText, self::QUESTION_FONT_SIZE_PX, self::CONTAINER_WIDTH_PX);
        $height += $questionLines * (self::QUESTION_FONT_SIZE_PX * self::QUESTION_LINE_HEIGHT);
        $height += self::QUESTION_TEXT_MARGIN_BOTTOM_PX; // Marge après le texte de la question
        
        // Hauteur de chaque réponse
        if (!empty($question['answers'])) {
            foreach ($question['answers'] as $answer) {
                // Format: "a – texte de la réponse"
                $answerText = $answer['letter'] . ' – ' . $answer['text'];
                $answerLines = $this->calculateTextLines($answerText, self::ANSWER_FONT_SIZE_PX, self::CONTAINER_WIDTH_PX - self::ANSWERS_LEFT_MARGIN_PX);
                $height += $answerLines * (self::ANSWER_FONT_SIZE_PX * self::ANSWER_LINE_HEIGHT);
                $height += self::ANSWER_MARGIN_BOTTOM_PX; // Marge après chaque réponse
            }
        }
        
        // Marge après la question complète
        $height += self::QUESTION_MARGIN_BOTTOM_PX;
        
        return intval($height);
    }

    /**
     * Estimer la hauteur d'une entrée de réponse (question + réponse + explication)
     * 
     * @param array $question Données de la question
     * @param array $correctAnswer Réponse correcte
     * @param string $language Langue pour les labels
     * @return int Hauteur estimée en pixels
     */
    private function estimateAnswerEntryHeight(array $question, array $correctAnswer, string $language): int
    {
        $height = 0;
        
        // Hauteur de la question
        $questionText = $question['question'];
        $questionLines = $this->calculateTextLines($questionText, self::QUESTION_FONT_SIZE_PX, self::CONTAINER_WIDTH_PX);
        $height += $questionLines * (self::QUESTION_FONT_SIZE_PX * self::QUESTION_LINE_HEIGHT);
        $height += self::ANSWER_QUESTION_MARGIN_BOTTOM_PX;
        
        // Hauteur de la réponse correcte
        $answerLabel = $this->getAnswerLabel($language);
        $answerText = $answerLabel . ' ' . strtoupper($correctAnswer['letter']) . ' – ' . $correctAnswer['text'];
        $answerLines = $this->calculateTextLines($answerText, self::QUESTION_FONT_SIZE_PX, self::CONTAINER_WIDTH_PX);
        $height += $answerLines * (self::QUESTION_FONT_SIZE_PX * self::QUESTION_LINE_HEIGHT);
        $height += self::ANSWER_CORRECT_MARGIN_BOTTOM_PX;
        
        // Hauteur de l'explication si présente
        if (!empty($correctAnswer['explanation'])) {
            $explanationLabel = $this->getExplanationLabel($language);
            $explanationText = $explanationLabel . ' ' . $correctAnswer['explanation'];
            $explanationLines = $this->calculateTextLines($explanationText, self::QUESTION_FONT_SIZE_PX, self::CONTAINER_WIDTH_PX);
            $height += $explanationLines * (self::QUESTION_FONT_SIZE_PX * self::QUESTION_LINE_HEIGHT);
            $height += self::ANSWER_EXPLANATION_MARGIN_BOTTOM_PX;
        }
        
        // Marge après l'entrée complète (réduite pour permettre un meilleur remplissage)
        // On utilise seulement la moitié de la marge pour l'estimation, car la dernière entrée n'a pas besoin de toute la marge
        $height += intval(self::ANSWER_ENTRY_MARGIN_BOTTOM_PX * 0.7);
        
        return intval($height);
    }

    /**
     * Calculer le nombre de lignes nécessaires pour afficher un texte
     * 
     * @param string $text Texte à mesurer
     * @param int $fontSize Taille de la police en pixels
     * @param int $containerWidth Largeur du conteneur en pixels
     * @return int Nombre de lignes estimé
     */
    private function calculateTextLines(string $text, int $fontSize, int $containerWidth): int
    {
        // Estimation approximative : environ 0.6 caractères par pixel de largeur
        // (basé sur une police moyenne de 12px)
        // Ajustement selon la taille de police
        $charsPerLine = intval($containerWidth / ($fontSize * 0.6));
        
        if ($charsPerLine <= 0) {
            $charsPerLine = 50; // Valeur par défaut
        }
        
        $textLength = mb_strlen($text);
        $lines = max(1, intval(ceil($textLength / $charsPerLine)));
        
        return $lines;
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
