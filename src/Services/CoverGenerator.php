<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer le HTML de la page de couverture
 */
class CoverGenerator
{
    private string $templatePath;
    private string $assetsPath;

    public function __construct(string $projectRoot)
    {
        $this->templatePath = $projectRoot . '/src/Templates/cover.html';
        $this->assetsPath = $projectRoot . '/assets';
    }

    /**
     * Générer le HTML de la couverture
     */
    public function generate(EbookData $ebookData): string
    {
        if (!file_exists($this->templatePath)) {
            throw new \Exception("Template non trouvé: {$this->templatePath}");
        }

        $template = file_get_contents($this->templatePath);
        $coverData = $ebookData->getCoverData();

        // Préparer les chemins des images
        $degradeImagePath = $this->assetsPath . '/images/degrade.jpg';
        $coverImagePath = $this->assetsPath . '/image.jpg';
        $logoImagePath = $this->assetsPath . '/images/smartbook.png';

        // Préparer les chemins des polices
        $fontRegularPath = $this->assetsPath . '/fonts/quicksand/Quicksand-Regular.ttf';
        $fontBoldPath = $this->assetsPath . '/fonts/quicksand/Quicksand-Bold.ttf';

        // Convertir les images et polices en base64 pour l'embedding
        $degradeBase64 = $this->imageToBase64($degradeImagePath);
        $coverBase64 = $this->imageToBase64($coverImagePath);
        $logoBase64 = $this->imageToBase64($logoImagePath);
        $fontRegularBase64 = $this->fontToBase64($fontRegularPath);
        $fontBoldBase64 = $this->fontToBase64($fontBoldPath);

        // Remplacer les placeholders
        $replacements = [
            '{{FONT_REGULAR}}' => $fontRegularBase64,
            '{{FONT_BOLD}}' => $fontBoldBase64,
            '{{DEGRADE_IMAGE}}' => $degradeBase64,
            '{{COVER_IMAGE}}' => $coverBase64,
            '{{LOGO_IMAGE}}' => $logoBase64,
            '{{TITLE}}' => $this->escapeHtml($coverData['title']),
            '{{SUBTITLE}}' => $this->escapeHtml($coverData['subtitle']),
            '{{CATEGORY_COLOR}}' => $coverData['categoryColorCss'],
            '{{CATEGORY_LABEL}}' => $this->escapeHtml($coverData['categoryLabel'])
        ];

        $html = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $html;
    }

    /**
     * Convertir une image en base64 pour l'embedding
     */
    private function imageToBase64(string $imagePath): string
    {
        if (!file_exists($imagePath)) {
            // Retourner une image transparente par défaut
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        }

        $imageData = file_get_contents($imagePath);
        $base64 = base64_encode($imageData);

        // Déterminer le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imagePath);
        finfo_close($finfo);

        return "data:{$mimeType};base64,{$base64}";
    }

    /**
     * Calculer la position Y du titre (simplifiée)
     *
     * Position de référence: ligne Y=680
     * On estime la hauteur du texte en fonction du nombre de caractères
     */
    private function calculateTitlePosition(string $title, string $subtitle): int
    {
        // Constantes
        $lineY = 680;
        $textWidth = 542;

        // Estimation de la hauteur du sous-titre
        // On estime environ 30 caractères par ligne pour une taille de 19px
        $subtitleCharsPerLine = 35;
        $subtitleLineHeight = 27; // 19px * 1.4 line-height
        $subtitleLines = max(1, ceil(mb_strlen($subtitle) / $subtitleCharsPerLine));
        $subtitleHeight = $subtitleLines * $subtitleLineHeight;

        // Estimation de la hauteur du titre
        // On estime environ 22 caractères par ligne pour une taille de 26px
        $titleCharsPerLine = 25;
        $titleLineHeight = 34; // 26px * 1.3 line-height
        $titleLines = max(1, ceil(mb_strlen($title) / $titleCharsPerLine));
        $titleHeight = $titleLines * $titleLineHeight;

        // Calculer la position du titre (de bas en haut)
        // lineY - subtitleHeight - titleHeight - espacement
        $titleTop = $lineY - $subtitleHeight - $titleHeight - 130;

        // S'assurer que le titre ne dépasse pas en haut
        return max(60, $titleTop);
    }

    /**
     * Convertir une police en base64 pour l'embedding
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
