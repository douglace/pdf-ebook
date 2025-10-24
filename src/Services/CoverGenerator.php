<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer les données de couverture de l'ebook
 */
class CoverGenerator
{
    private string $assetsPath;

    public function __construct(string $projectRoot)
    {
        $this->assetsPath = $projectRoot . '/assets';
    }

    /**
     * Générer les données nécessaires pour la couverture
     */
    public function generateCoverData(EbookData $ebookData): array
    {
        $coverData = $ebookData->getCoverData();

        // Préparer les chemins des images
        $degradeImagePath = $this->assetsPath . '/images/degrade.jpg';
        $coverImagePath = $this->assetsPath . '/image.jpg';
        $logoImagePath = $this->assetsPath . '/images/smartbook.png';
        $watermarkImagePath = $this->assetsPath . '/images/encycloai.png';

        // Préparer les chemins des polices
        $fontRegularPath = $this->assetsPath . '/fonts/quicksand/Quicksand-Regular.ttf';
        $fontBoldPath = $this->assetsPath . '/fonts/quicksand/Quicksand-Bold.ttf';

        // Convertir les images et polices en base64
        return [
            'degradeBase64' => $this->imageToBase64($degradeImagePath),
            'coverBase64' => $this->imageToBase64($coverImagePath),
            'logoBase64' => $this->imageToBase64($logoImagePath),
            'watermarkBase64' => $this->imageToBase64($watermarkImagePath),
            'fontRegularBase64' => $this->fontToBase64($fontRegularPath),
            'fontBoldBase64' => $this->fontToBase64($fontBoldPath),
            'title' => $this->escapeHtml($coverData['title']),
            'subtitle' => $this->escapeHtml($coverData['subtitle']),
            'categoryLabel' => $this->escapeHtml($coverData['categoryLabel']),
            'categoryColorCss' => $coverData['categoryColorCss']
        ];
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