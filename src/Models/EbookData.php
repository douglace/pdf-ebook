<?php

namespace Vex6\Phppdf\Models;

/**
 * Classe pour extraire et gérer les données d'un ebook depuis le JSON
 */
class EbookData
{
    private array $data;

    // Tables de correspondance des catégories
    private const CAT_COLOR = [
        "Arts" => [242, 199, 211],
        "Cinema" => [245, 218, 223],
        "Culture" => [255, 235, 205],
        "Economy" => [200, 215, 230],
        "Geography" => [180, 215, 180],
        "History" => [230, 190, 180],
        "Literature" => [220, 200, 230],
        "Music" => [255, 220, 220],
        "Philosophy" => [200, 200, 220],
        "Politics" => [215, 180, 180],
        "Psychology" => [230, 210, 200],
        "Religion" => [210, 200, 190],
        "Science" => [180, 200, 220],
        "Sociology" => [210, 220, 200],
        "Sports" => [255, 230, 200],
        "Technology" => [87, 109, 114],
        "Others" => [0, 0, 0]
    ];

    private const TRAD_CAT = [
        "Arts" => "Arts",
        "Cinema" => "Cinéma",
        "Culture" => "Culture",
        "Economy" => "Économie",
        "Geography" => "Géographie",
        "History" => "Histoire",
        "Literature" => "Littérature",
        "Music" => "Musique",
        "Philosophy" => "Philosophie",
        "Politics" => "Politique",
        "Psychology" => "Psychologie",
        "Religion" => "Religion",
        "Science" => "Science",
        "Sociology" => "Sociologie",
        "Sports" => "Sports",
        "Technology" => "Technologie",
        "Others" => "Autres"
    ];

    public function __construct(string $jsonPath)
    {
        if (!file_exists($jsonPath)) {
            throw new \Exception("Le fichier JSON n'existe pas: {$jsonPath}");
        }

        $jsonContent = file_get_contents($jsonPath);
        $this->data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Erreur lors du parsing du JSON: " . json_last_error_msg());
        }
    }

    /**
     * Obtenir l'ID de l'ebook
     */
    public function getId(): string
    {
        return $this->data['_id'] ?? $this->data['id_ebook'] ?? '';
    }

    /**
     * Obtenir le titre principal
     */
    public function getTitle(): string
    {
        return $this->data['titles']['title'] ?? '';
    }

    /**
     * Extraire le sous-titre depuis formatted_text
     */
    public function getSubtitle(): string
    {
        $formatted = $this->data['formatted_text'] ?? '';

        // Chercher entre [DEBUT_CHAMP_SOUS_TITRE] et [FIN_CHAMP_SOUS_TITRE]
        $subtitle = $this->extractBetween(
            $formatted,
            '[DEBUT_CHAMP_SOUS_TITRE]',
            '[FIN_CHAMP_SOUS_TITRE]'
        );

        // Fallback sur titles.subtitle si non trouvé
        if (empty($subtitle)) {
            $subtitle = $this->data['titles']['subtitle'] ?? '';
        }

        return trim($subtitle);
    }

    /**
     * Obtenir la langue (normalisée en minuscules)
     */
    public function getLanguage(): string
    {
        $lang = $this->data['initialize_data']['language'] ?? 'français';
        return strtolower($lang);
    }

    /**
     * Obtenir la catégorie principale
     */
    public function getCategory(): string
    {
        return $this->data['categories']['principalCategory'] ?? 'Others';
    }

    /**
     * Obtenir la couleur RGB de la catégorie
     */
    public function getCategoryColor(): array
    {
        $category = $this->getCategory();
        return self::CAT_COLOR[$category] ?? self::CAT_COLOR['Others'];
    }

    /**
     * Obtenir le label de la catégorie (traduit selon la langue)
     */
    public function getCategoryLabel(): string
    {
        $category = $this->getCategory();
        $language = $this->getLanguage();

        if ($language === 'english') {
            return $category;
        }

        return self::TRAD_CAT[$category] ?? $category;
    }

    /**
     * Extraire du texte entre deux balises
     */
    private function extractBetween(string $text, string $startTag, string $endTag): string
    {
        $startIdx = strpos($text, $startTag);
        if ($startIdx === false) {
            return '';
        }

        $startIdx += strlen($startTag);
        $endIdx = strpos($text, $endTag, $startIdx);

        if ($endIdx === false) {
            return '';
        }

        return substr($text, $startIdx, $endIdx - $startIdx);
    }

    /**
     * Obtenir toutes les données formatées pour la couverture
     */
    public function getCoverData(): array
    {
        $categoryColor = $this->getCategoryColor();

        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'subtitle' => $this->getSubtitle(),
            'language' => $this->getLanguage(),
            'category' => $this->getCategory(),
            'categoryLabel' => $this->getCategoryLabel(),
            'categoryColorRgb' => $categoryColor,
            'categoryColorCss' => sprintf('rgb(%d, %d, %d)', ...$categoryColor)
        ];
    }
}
