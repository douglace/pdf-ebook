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
    private ConclusionGenerator $conclusionGenerator;
    private QuizGenerator $quizGenerator;
    private GlossaryGenerator $glossaryGenerator;
    private WikipediaGenerator $wikipediaGenerator;
    private WebsitesGenerator $websitesGenerator;
    private SuggestionsGenerator $suggestionsGenerator;
    private ChaptersGenerator $chaptersGenerator;
    private GlossaryLinkManager $glossaryLinkManager;

    public function __construct(string $projectRoot)
    {
        $this->templatePath = $projectRoot . '/src/Templates/ebook.html';
        $this->stylesPath = $projectRoot . '/src/Templates/styles.css';
        $this->coverGenerator = new CoverGenerator($projectRoot);
        $this->licenseGenerator = new LicenseGenerator();
        $this->tocGenerator = new TableOfContentsGenerator();
        $this->introductionGenerator = new IntroductionGenerator();
        $this->conclusionGenerator = new ConclusionGenerator();
        $this->quizGenerator = new QuizGenerator();
        $this->glossaryGenerator = new GlossaryGenerator();
        $this->wikipediaGenerator = new WikipediaGenerator();
        $this->websitesGenerator = new WebsitesGenerator();
        $this->suggestionsGenerator = new SuggestionsGenerator();
        $this->chaptersGenerator = new ChaptersGenerator();
        $this->glossaryLinkManager = new GlossaryLinkManager();
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

        // Générer les données de couverture
        $coverData = $this->coverGenerator->generateCoverData($ebookData);

        // Générer les données de licence
        $licenseText = $this->licenseGenerator->getLicenseText($language);
        $creationDate = $this->licenseGenerator->formatCreationDate($createdTimestamp, $language);

        // Générer la table des matières
        $this->tocGenerator->setLanguage($language);
        $this->tocGenerator->extractTOCFromData($ebookData->getRawData());
        $tocHtml = $this->tocGenerator->generateTOCHtml();
        $tocPage2Html = $this->tocGenerator->generateTOCPage2Html();

        // === SYSTÈME DE LIENS GLOSSAIRE ===
        // Identifier les acronymes depuis les entrées de glossaire
        $rawData = $ebookData->getRawData();
        $zones = $this->parseZones($rawData['formatted_text'] ?? '');
        $acronyms = $this->glossaryLinkManager->identifyAcronyms($zones);
        
        // Créer les ancres de liens (simulation pour HTML - pas de TCPDF ici)
        $acronymAnchors = [];
        $usedSlugs = [];
        foreach ($acronyms as $acronym) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $acronym));
            $glossSlug = 'gloss-' . $slug;
            $backSlug = 'back-' . $slug;
            $counter = 1;

            while (isset($usedSlugs[$glossSlug])) {
                $glossSlug = 'gloss-' . $slug . '-' . (++$counter);
                $backSlug = 'back-' . $slug . '-' . $counter;
            }

            $usedSlugs[$glossSlug] = true;
            $usedSlugs[$backSlug] = true;

            $acronymAnchors[$acronym] = [
                'gloss' => $glossSlug,
                'back' => $backSlug,
                'links' => [
                    'gloss' => '#' . $glossSlug,  // Lien HTML vers le glossaire
                    'back' => '#' . $backSlug,    // Lien HTML de retour
                ],
            ];
        }
        $firstOccurrenceFound = [];

        // Générer l'introduction
        $introductionContent = $this->introductionGenerator->extractIntroductionContent($ebookData);
        $introductionHtml = $this->introductionGenerator->generateIntroductionHtml($introductionContent, $acronymAnchors, $firstOccurrenceFound);

        // Générer les chapitres (corps de l'ebook) - pages dynamiques
        $chaptersContent = $this->chaptersGenerator->extractChaptersContent($ebookData);
        $chaptersPages = $this->chaptersGenerator->generateChaptersPages($chaptersContent, $coverData['watermarkBase64'], $acronymAnchors, $firstOccurrenceFound);

        // Générer la conclusion
        $conclusionContent = $this->conclusionGenerator->extractConclusionContent($ebookData);
        $conclusionHtml = $this->conclusionGenerator->generateConclusionHtml($conclusionContent, $acronymAnchors, $firstOccurrenceFound);

        // Générer le quiz
        $quizContent = $this->quizGenerator->extractQuizContent($ebookData);
        $quizPages = $this->quizGenerator->generateQuizHtml($quizContent);
        $quizTitleHtml = $this->quizGenerator->generateQuizTitleHtml();

        // Construire le HTML des pages de quiz
        $quizPage1Html = '';
        $quizPage2Html = '';
        $quizPage3Html = '';

        if (!empty($quizPages)) {
            $quizPage1Html = $quizTitleHtml . ($quizPages[0] ?? '');
            $quizPage2Html = $quizPages[1] ?? '';
            $quizPage3Html = $quizPages[2] ?? '';
        }

        // Générer Wikipedia
        $wikipediaContent = $this->wikipediaGenerator->extractWikipediaContent($ebookData);
        $wikipediaPages = $this->wikipediaGenerator->generateWikipediaHtml($wikipediaContent, $language);
        $wikipediaTitleHtml = $this->wikipediaGenerator->generateWikipediaTitleHtml();

        // Construire le HTML des pages de Wikipedia
        $wikipediaPage1Html = '';
        $wikipediaPage2Html = '';
        $wikipediaPage3Html = '';

        if (!empty($wikipediaPages)) {
            $wikipediaPage1Html = $wikipediaTitleHtml . ($wikipediaPages[0] ?? '');
            $wikipediaPage2Html = $wikipediaPages[1] ?? '';
            $wikipediaPage3Html = $wikipediaPages[2] ?? '';
        }

        // Générer Sites Web
        $websitesContent = $this->websitesGenerator->extractWebsitesContent($ebookData);
        $websitesPages = $this->websitesGenerator->generateWebsitesHtml($websitesContent, $language);
        $websitesTitleHtml = $this->websitesGenerator->generateWebsitesTitleHtml();

        // Construire le HTML des pages de Sites Web
        $websitesPage1Html = '';
        $websitesPage2Html = '';
        $websitesPage3Html = '';

        if (!empty($websitesPages)) {
            $websitesPage1Html = $websitesTitleHtml . ($websitesPages[0] ?? '');
            $websitesPage2Html = $websitesPages[1] ?? '';
            $websitesPage3Html = $websitesPages[2] ?? '';
        }

        // Générer Glossaire
        $glossaryContent = $this->glossaryGenerator->extractGlossaryContent($ebookData);
        $glossaryPages = $this->glossaryGenerator->generateGlossaryHtml($glossaryContent, $language, $acronymAnchors, $firstOccurrenceFound);
        $glossaryTitleHtml = $this->glossaryGenerator->generateGlossaryTitleHtml($language);

        // Construire le HTML des pages de Glossaire
        $glossaryPage1Html = '';
        $glossaryPage2Html = '';
        $glossaryPage3Html = '';

        if (!empty($glossaryPages) && !empty($glossaryContent)) {
            // Page 1: Title + content + close container
            $glossaryPage1Html = $glossaryTitleHtml . ($glossaryPages[0] ?? '') . '</div>';

            // Pages 2 et 3: Wrap content in proper container
            $glossaryPage2Html = $this->glossaryGenerator->wrapPageContent($glossaryPages[1] ?? '');
            $glossaryPage3Html = $this->glossaryGenerator->wrapPageContent($glossaryPages[2] ?? '');
        }

        // Générer Suggestions
        $suggestionsContent = $this->suggestionsGenerator->extractSuggestionsContent($ebookData);
        $suggestionsPages = $this->suggestionsGenerator->generateSuggestionsHtml($suggestionsContent, $language);
        $suggestionsTitleHtml = $this->suggestionsGenerator->generateSuggestionsTitleHtml($language);

        // Construire le HTML des pages de Suggestions
        $suggestionsPage1Html = '';
        $suggestionsPage2Html = '';
        $suggestionsPage3Html = '';

        if (!empty($suggestionsPages)) {
            // Page 1: Title + content + close container
            $suggestionsPage1Html = $suggestionsTitleHtml . ($suggestionsPages[0] ?? '') . '</div>';

            // Pages 2 et 3: Wrap content in proper container
            $suggestionsPage2Html = $this->suggestionsGenerator->wrapPageContent($suggestionsPages[1] ?? '');
            $suggestionsPage3Html = $this->suggestionsGenerator->wrapPageContent($suggestionsPages[2] ?? '');
        }

        // Remplacer les placeholders dans le CSS
        $stylesReplaced = str_replace(
            ['{{FONT_REGULAR}}', '{{FONT_BOLD}}', '{{DEGRADE_IMAGE}}', 'rgb(255, 230, 200); /* Sera remplacé par {{CATEGORY_COLOR}} */'],
            [$coverData['fontRegularBase64'], $coverData['fontBoldBase64'], $coverData['degradeBase64'], $coverData['categoryColorCss']],
            $styles
        );

        // Concaténer toutes les pages de chapitres
        $allChaptersHtml = implode("\n", $chaptersPages);

        // Remplacer les placeholders dans le HTML
        $replacements = [
            '{{STYLES}}' => $stylesReplaced,
            '{{COVER_IMAGE}}' => $coverData['coverBase64'],
            '{{LOGO_IMAGE}}' => $coverData['logoBase64'],
            '{{WATERMARK_IMAGE}}' => $coverData['watermarkBase64'],
            '{{TITLE}}' => $coverData['title'],
            '{{SUBTITLE}}' => $coverData['subtitle'],
            '{{CATEGORY_LABEL}}' => $coverData['categoryLabel'],
            '{{CATEGORY_COLOR}}' => $coverData['categoryColorCss'],
            '{{LICENSE_TEXT}}' => $licenseText,
            '{{CREATION_DATE}}' => $creationDate,
            '{{TOC_CONTENT}}' => $tocHtml,
            '{{TOC_CONTENT_PAGE2}}' => $tocPage2Html,
            '{{INTRODUCTION_CONTENT}}' => $introductionHtml,
            '{{CHAPTERS_PAGES}}' => $allChaptersHtml,
            '{{CONCLUSION_CONTENT}}' => $conclusionHtml,
            '{{GLOSSARY_CONTENT_PAGE1}}' => $glossaryPage1Html,
            '{{GLOSSARY_CONTENT_PAGE2}}' => $glossaryPage2Html,
            '{{GLOSSARY_CONTENT_PAGE3}}' => $glossaryPage3Html,
            '{{QUIZ_CONTENT_PAGE1}}' => $quizPage1Html,
            '{{QUIZ_CONTENT_PAGE2}}' => $quizPage2Html,
            '{{QUIZ_CONTENT_PAGE3}}' => $quizPage3Html,
            '{{WIKIPEDIA_CONTENT_PAGE1}}' => $wikipediaPage1Html,
            '{{WIKIPEDIA_CONTENT_PAGE2}}' => $wikipediaPage2Html,
            '{{WIKIPEDIA_CONTENT_PAGE3}}' => $wikipediaPage3Html,
            '{{WEBSITES_CONTENT_PAGE1}}' => $websitesPage1Html,
            '{{WEBSITES_CONTENT_PAGE2}}' => $websitesPage2Html,
            '{{WEBSITES_CONTENT_PAGE3}}' => $websitesPage3Html,
            '{{SUGGESTIONS_CONTENT_PAGE1}}' => $suggestionsPage1Html,
            '{{SUGGESTIONS_CONTENT_PAGE2}}' => $suggestionsPage2Html,
            '{{SUGGESTIONS_CONTENT_PAGE3}}' => $suggestionsPage3Html
        ];


        $html = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $html;
    }

    /**
     * Parser le formatted_text pour extraire les zones
     * Basé sur la fonction parseZones du document EBOOK_GENERATION_COMPLETE.md
     */
    private function parseZones(string $formattedText): array
    {
        $zones = [
            'titre' => '',
            'sous_titre' => '',
            'langue' => '',
            'corps' => [],
            'toc' => [],
            'glossaire_links' => []
        ];

        if (empty($formattedText)) {
            return $zones;
        }

        $lines = explode("\n", $formattedText);
        $currentParagraph = '';
        $collectingContent = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Extraire les champs spéciaux
            if (preg_match('/\[DEBUT_CHAMP_TITRE\](.*?)\[FIN_CHAMP_TITRE\]/', $line, $matches)) {
                $zones['titre'] = trim($matches[1]);
                continue;
            }
            if (preg_match('/\[DEBUT_CHAMP_SOUS_TITRE\](.*?)\[FIN_CHAMP_SOUS_TITRE\]/', $line, $matches)) {
                $zones['sous_titre'] = trim($matches[1]);
                continue;
            }
            if (preg_match('/\[DEBUT_CHAMP_LANGUE\](.*?)\[FIN_CHAMP_LANGUE\]/', $line, $matches)) {
                $zones['langue'] = trim($matches[1]);
                continue;
            }

            // Détecter les balises de contenu
            if (preg_match('/^\[(TITRE1|TITRE2|NORMAL|ENTREE_BIBLIOGRAPHIE|ENTREE_GLOSSAIRE|ENTREE_QUIZ|WIKI_LIEN)\]\s*(.+)$/i', $line, $matches)) {
                $tag = strtoupper($matches[1]);
                $textContent = trim($matches[2]);

                // Traitement spécial pour WIKI_LIEN (balise fermante)
                if ($tag === 'WIKI_LIEN' && preg_match('/^(.*?)\s*\[WIKI_LIEN\]$/', $textContent, $wiki_match)) {
                    $textContent = trim($wiki_match[1]);
                }

                // Ajouter l'entrée au corps
                $zones['corps'][] = [
                    'tag' => $tag,
                    'text' => $textContent
                ];

                // Ajouter à la TOC si c'est un titre
                if ($tag === 'TITRE1' || $tag === 'TITRE2') {
                    $zones['toc'][] = [
                        'level' => $tag,
                        'title' => $textContent
                    ];
                }

                $collectingContent = true;
                continue;
            }

            // Si on collecte du contenu et que la ligne n'est pas vide et ne commence pas par une balise
            if ($collectingContent && !empty($line) && !preg_match('/^\[/', $line)) {
                // Continuer le paragraphe précédent
                if (!empty($zones['corps'])) {
                    $lastIndex = count($zones['corps']) - 1;
                    $zones['corps'][$lastIndex]['text'] .= ' ' . $line;
                }
            }
        }

        return $zones;
    }

}
