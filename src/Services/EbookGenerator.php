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
    private string $assetsPath;

    public function __construct(string $projectRoot)
    {
        $this->templatePath = $projectRoot . '/src/Templates/ebook.html';
        $this->stylesPath = $projectRoot . '/src/Templates/styles.css';
        $this->assetsPath = $projectRoot . '/assets';
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
        $coverData = $ebookData->getCoverData();
        $language = $ebookData->getLanguage();

        // Préparer les chemins des images
        $degradeImagePath = $this->assetsPath . '/images/degrade.jpg';
        $coverImagePath = $this->assetsPath . '/image.jpg';
        $logoImagePath = $this->assetsPath . '/images/smartbook.png';
        $watermarkImagePath = $this->assetsPath . '/images/encycloai.png';

        // Préparer les chemins des polices
        $fontRegularPath = $this->assetsPath . '/fonts/quicksand/Quicksand-Regular.ttf';
        $fontBoldPath = $this->assetsPath . '/fonts/quicksand/Quicksand-Bold.ttf';

        // Convertir les images et polices en base64
        $degradeBase64 = $this->imageToBase64($degradeImagePath);
        $coverBase64 = $this->imageToBase64($coverImagePath);
        $logoBase64 = $this->imageToBase64($logoImagePath);
        $watermarkBase64 = $this->imageToBase64($watermarkImagePath);
        $fontRegularBase64 = $this->fontToBase64($fontRegularPath);
        $fontBoldBase64 = $this->fontToBase64($fontBoldPath);

        // Générer le texte de licence
        $licenseText = $this->getLicenseText($language);
        $creationDate = $this->formatCreationDate($createdTimestamp, $language);

        // Remplacer les placeholders dans le CSS
        $stylesReplaced = str_replace(
            ['{{FONT_REGULAR}}', '{{FONT_BOLD}}', '{{DEGRADE_IMAGE}}', '{{CATEGORY_COLOR}}'],
            [$fontRegularBase64, $fontBoldBase64, $degradeBase64, $coverData['categoryColorCss']],
            $styles
        );

        // Remplacer les placeholders dans le HTML
        $replacements = [
            '{{STYLES}}' => $stylesReplaced,
            '{{COVER_IMAGE}}' => $coverBase64,
            '{{LOGO_IMAGE}}' => $logoBase64,
            '{{WATERMARK_IMAGE}}' => $watermarkBase64,
            '{{TITLE}}' => $this->escapeHtml($coverData['title']),
            '{{SUBTITLE}}' => $this->escapeHtml($coverData['subtitle']),
            '{{CATEGORY_LABEL}}' => $this->escapeHtml($coverData['categoryLabel']),
            '{{LICENSE_TEXT}}' => $licenseText,
            '{{CREATION_DATE}}' => $creationDate
        ];

        $html = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $html;
    }

    /**
     * Obtenir le texte de licence selon la langue
     */
    private function getLicenseText(string $language): string
    {
        if ($language === 'english') {
            return $this->getEnglishLicenseText();
        }

        return $this->getFrenchLicenseText();
    }

    /**
     * Texte de licence en français
     */
    private function getFrenchLicenseText(): string
    {
        $paragraphs = [
            '© 2025 Encyclo-AI. Tous droits réservés.',

            'Cet ebook est protégé par le droit d\'auteur et d\'autres lois sur la propriété intellectuelle. Toute reproduction, distribution, ou utilisation non autorisée de cet Ebook est strictement interdite, sauf disposition contraire de la licence d\'utilisation.',

            'En téléchargeant ou en utilisant cet Ebook, vous acceptez d\'être lié par les termes du contrat de licence de Encyclo-AI. Pour consulter le contrat de licence complet, veuillez visiter : https://www.encyclo-ai.com/licence-smartbook-encyclo-ai-francais/ .',

            'Toute violation des termes de cette licence peut entraîner la résiliation immédiate de vos droits d\'utilisation et peut donner lieu à des poursuites judiciaires.'
        ];

        return $this->formatLicenseParagraphs($paragraphs);
    }

    /**
     * Texte de licence en anglais
     */
    private function getEnglishLicenseText(): string
    {
        $paragraphs = [
            '© 2025 Encyclo-AI. All rights reserved.',

            'This Ebook is protected by copyright and other intellectual property laws. Any unauthorized '
            . 'reproduction, distribution, or use of this Ebook is strictly prohibited, except as expressly '
            . 'permitted under the terms of the license agreement.',

            'By downloading or using this Ebook, you agree to be bound by the terms of the Encyclo-AI '
            . 'license agreement. To review the full license agreement, please visit: '
            . 'https://www.encyclo-ai.com/licence-smartbook-encyclo-ai-anglais/',

            'Any violation of the terms of this license may result in the immediate termination of your '
            . 'rights to use this Ebook and may lead to legal action.'
        ];

        return $this->formatLicenseParagraphs($paragraphs);
    }

    /**
     * Formater les paragraphes en HTML
     */
    private function formatLicenseParagraphs(array $paragraphs): string
    {
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $html .= '<div class="license-text">' . htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        return $html;
    }

    /**
     * Formater la date de création
     */
    private function formatCreationDate(?int $timestamp, string $language): string
    {
        if (!$timestamp) {
            $timestamp = time();
        }

        $date = date('j F Y', $timestamp);

        // Traduire le mois si français
        if ($language === 'français') {
            $monthsFr = [
                'January' => 'janvier', 'February' => 'février', 'March' => 'mars',
                'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
                'July' => 'juillet', 'August' => 'août', 'September' => 'septembre',
                'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre'
            ];
            $date = str_replace(array_keys($monthsFr), array_values($monthsFr), $date);
            return 'Smartbook Créé : ' . $date;
        }

        return 'Generated : ' . $date;
    }

    /**
     * Convertir une image en base64
     */
    private function imageToBase64(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        }

        $imageData = file_get_contents($imagePath);
        $base64 = base64_encode($imageData);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imagePath);
        finfo_close($finfo);

        return "data:{$mimeType};base64,{$base64}";
    }

    /**
     * Convertir une police en base64
     */
    private function fontToBase64(string $fontPath): string
    {
        if (!file_exists($fontPath)) {
            throw new \Exception("Police non trouvée: {$fontPath}");
        }

        $fontData = file_get_contents($fontPath);
        $base64 = base64_encode($fontData);

        return "data:font/truetype;base64,{$base64}";
    }

    /**
     * Échapper les caractères HTML
     */
    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
