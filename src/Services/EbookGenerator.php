<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer le HTML complet de l'ebook (toutes les pages)
 */
class EbookGenerator
{
    private string $templatePath;
    private string $stylesPath;
    private CoverGenerator $coverGenerator;
    private LicenseGenerator $licenseGenerator;
    private TableOfContentsGenerator $tocGenerator;
    private IntroductionGenerator $introductionGenerator;
    private FormattedTextParser $parser;
    private MainContentGenerator $mainContentGenerator;
    private GlossaryGenerator $glossaryGenerator;
    private QuizGenerator $quizGenerator;
    private BibliographyGenerator $bibliographyGenerator;
    private SuggestionsGenerator $suggestionsGenerator;

    public function __construct(string $projectRoot)
    {
        $this->templatePath = $projectRoot . '/src/Templates/ebook.html';
        $this->stylesPath = $projectRoot . '/src/Templates/styles.css';
        $this->coverGenerator = new CoverGenerator($projectRoot);
        $this->licenseGenerator = new LicenseGenerator();
        $this->tocGenerator = new TableOfContentsGenerator();
        $this->introductionGenerator = new IntroductionGenerator();
        $this->parser = new FormattedTextParser();
        $this->mainContentGenerator = new MainContentGenerator();
        $this->glossaryGenerator = new GlossaryGenerator();
        $this->quizGenerator = new QuizGenerator();
        $this->bibliographyGenerator = new BibliographyGenerator();
        $this->suggestionsGenerator = new SuggestionsGenerator();
    }

    /**
     * Générer le HTML complet de l'ebook
     */
    public function generate(EbookData $ebookData, ?int $createdTimestamp = null): string
    {
        if (!file_exists($this->templatePath)) {
            throw new \Exception("Template non trouvé: {$this->templatePath}");
        }

        if (!file_exists($this->stylesPath)) {
            throw new \Exception("Fichier CSS non trouvé: {$this->stylesPath}");
        }

        $template = file_get_contents($this->templatePath);
        $styles = file_get_contents($this->stylesPath);
        $language = $ebookData->getLanguage();

        // Parser le formatted_text pour extraire toutes les zones
        $zones = $this->parser->parseZones($ebookData);
        $sections = $this->parser->getSectionsByOrder($zones);
        
        // Extraire les acronymes et initialiser le système de liens
        $acronyms = $this->parser->extractAcronyms($zones);
        $acronymLinks = $this->glossaryGenerator->initializeGlossaryLinks($acronyms);

        // Générer les données de couverture
        $coverData = $this->coverGenerator->generateCoverData($ebookData);

        // Générer les données de licence
        $licenseText = $this->licenseGenerator->getLicenseText($language);
        $creationDate = $this->licenseGenerator->formatCreationDate($createdTimestamp, $language);

        // Générer la table des matières complète
        $this->generateCompleteTOC($sections, $acronyms, $ebookData);
        $tocHtml = $this->tocGenerator->generateTOCHtml();
        $tocPage2Html = $this->tocGenerator->generateTOCPage2Html();

        // Générer l'introduction
        $introductionContent = $this->introductionGenerator->extractIntroductionContent($ebookData);
        $introductionHtml = $this->introductionGenerator->generateIntroductionHtml($introductionContent);

        // Générer le contenu principal
        $mainContentHtml = $this->mainContentGenerator->generateMainContentHtml($sections, $acronymLinks);

        // Générer le glossaire
        $glossaryHtml = $this->glossaryGenerator->generateGlossaryHtml($acronyms, $acronymLinks);

        // Générer le quiz
        $quizAnswers = $this->parser->extractQuizAnswers($ebookData);
        $quizHtml = $this->quizGenerator->generateQuizHtml($sections['quiz'], $quizAnswers, $acronymLinks);

        // Générer la bibliographie
        $wikipediaHtml = $this->bibliographyGenerator->generateWikipediaHtml($ebookData, $acronymLinks);
        $websitesHtml = $this->bibliographyGenerator->generateWebsitesHtml($ebookData, $acronymLinks);

        // Générer les suggestions
        $suggestionsHtml = $this->suggestionsGenerator->generateSuggestionsHtml($sections['suggestions'], $acronymLinks);

        // Remplacer les placeholders dans le CSS
        $stylesReplaced = str_replace(
            ['{{FONT_REGULAR}}', '{{FONT_BOLD}}', '{{DEGRADE_IMAGE}}', 'rgb(255, 230, 200); /* Sera remplacé par {{CATEGORY_COLOR}} */'],
            [$coverData['fontRegularBase64'], $coverData['fontBoldBase64'], $coverData['degradeBase64'], $coverData['categoryColorCss']],
            $styles
        );

        // Remplacer les placeholders dans le HTML
        $replacements = [
            '{{STYLES}}' => $stylesReplaced,
            '{{COVER_IMAGE}}' => $coverData['coverBase64'],
            '{{LOGO_IMAGE}}' => $coverData['logoBase64'],
            '{{WATERMARK_IMAGE}}' => $coverData['watermarkBase64'],
            '{{TITLE}}' => $coverData['title'],
            '{{SUBTITLE}}' => $coverData['subtitle'],
            '{{CATEGORY_LABEL}}' => $coverData['categoryLabel'],
            '{{LICENSE_TEXT}}' => $licenseText,
            '{{CREATION_DATE}}' => $creationDate,
            '{{TOC_CONTENT}}' => $tocHtml,
            '{{TOC_CONTENT_PAGE2}}' => $tocPage2Html,
            '{{INTRODUCTION_CONTENT}}' => $introductionHtml,
            '{{MAIN_CONTENT}}' => $mainContentHtml,
            '{{GLOSSARY_CONTENT}}' => $glossaryHtml,
            '{{QUIZ_CONTENT}}' => $quizHtml,
            '{{WIKIPEDIA_CONTENT}}' => $wikipediaHtml,
            '{{WEBSITES_CONTENT}}' => $websitesHtml,
            '{{SUGGESTIONS_CONTENT}}' => $suggestionsHtml
        ];

        $html = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $html;
    }

    /**
     * Générer la table des matières complète avec toutes les sections
     */
    private function generateCompleteTOC(array $sections, array $acronyms, EbookData $ebookData): void
    {
        $this->tocGenerator->reset();
        $currentPage = 6; // Commencer après couverture, licence, TOC et introduction

        // Introduction (déjà gérée par TableOfContentsGenerator)
        if (!empty($sections['introduction'])) {
            $currentPage++;
        }

        // Contenu principal
        $mainContentEntries = $this->mainContentGenerator->extractTOCEntries($sections);
        foreach ($mainContentEntries as $entry) {
            $this->tocGenerator->addEntry($entry['title'], $entry['level'], $entry['page'], $entry['anchor']);
        }
        $currentPage += $this->mainContentGenerator->countContentPages($sections);

        // Glossaire
        if (!empty($acronyms)) {
            $this->tocGenerator->addEntry('GLOSSAIRE', 'TITRE1', $currentPage, 'glossaire');
            $currentPage++;
        }

        // Quiz
        if (!empty($sections['quiz'])) {
            $this->tocGenerator->addEntry('QUIZ', 'TITRE1', $currentPage, 'quiz');
            $currentPage++;
        }

        // Wikipedia
        if ($this->bibliographyGenerator->countWikipediaEntries($ebookData) > 0) {
            $this->tocGenerator->addEntry('WIKIPEDIA', 'TITRE1', $currentPage, 'wikipedia');
            $currentPage++;
        }

        // Sites Web
        if ($this->bibliographyGenerator->countWebsiteEntries($ebookData) > 0) {
            $this->tocGenerator->addEntry('SITES WEB', 'TITRE1', $currentPage, 'sites-web');
            $currentPage++;
        }

        // Suggestions
        if (!empty($sections['suggestions'])) {
            $this->tocGenerator->addEntry('SUGGESTIONS', 'TITRE1', $currentPage, 'suggestions');
        }
    }

}
