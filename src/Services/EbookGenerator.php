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

    public function __construct(string $projectRoot)
    {
        $this->templatePath = $projectRoot . '/src/Templates/ebook.html';
        $this->stylesPath = $projectRoot . '/src/Templates/styles.css';
        $this->coverGenerator = new CoverGenerator($projectRoot);
        $this->licenseGenerator = new LicenseGenerator();
        $this->tocGenerator = new TableOfContentsGenerator();
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
        $this->tocGenerator->extractTOCFromData($ebookData->getRawData());
        $tocHtml = $this->tocGenerator->generateTOCHtml();
        $tocPage2Html = $this->tocGenerator->generateTOCPage2Html();

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
            '{{TOC_CONTENT_PAGE2}}' => $tocPage2Html
        ];


        $html = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $html;
    }

}
