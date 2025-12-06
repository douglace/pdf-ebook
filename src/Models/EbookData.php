<?php

namespace Vex6\Phppdf\Models;

/**
 * Classe pour extraire et gérer les données d'un ebook depuis le JSON
 */
class EbookData
{
    protected array $data;

    private $formatted_text = null;

    // Tables de correspondance des catégories
    private const CAT_COLOR = [
        "Art de Vivre" => [240, 83, 125],
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
        "Others" => "Autres",
        'Lifestyle'       => 'Art de Vivre',
        'Law / Politics'  => 'Droit / Politique',
        'Environment'     => 'Environnement',
        'Medicine'        => 'Médecine',
    ];



    public function resetFormattedText(): void
    {
        $this->formatted_text = null;
    }

    /**
     * Constructeur qui accepte soit un chemin de fichier JSON, soit une chaîne JSON, soit un tableau
     *
     * @param string|array $jsonPathOrData Chemin vers un fichier JSON, chaîne JSON, ou tableau de données
     * @throws \Exception Si le JSON est invalide ou le fichier introuvable
     */
    public function __construct($jsonPathOrData)
    {
        // Validation initiale: ne pas accepter null ou vide
        if ($jsonPathOrData === null) {
            throw new \Exception("Le paramètre ne peut pas être null");
        }

        // Cas 1: Tableau de données déjà décodé
        if (is_array($jsonPathOrData)) {
            if (empty($jsonPathOrData)) {
                throw new \Exception("Le tableau de données ne peut pas être vide");
            }
            $this->data = $jsonPathOrData;
            return;
        }

        // Cas 2: Chaîne qui pourrait être un chemin de fichier ou du JSON
        if (is_string($jsonPathOrData)) {
            // Validation: ne pas accepter une chaîne vide
            if (trim($jsonPathOrData) === '') {
                throw new \Exception("La chaîne JSON ne peut pas être vide");
            }

            // Vérifier si c'est un chemin de fichier valide
            if (file_exists($jsonPathOrData)) {
                $jsonContent = file_get_contents($jsonPathOrData);
                if ($jsonContent === false) {
                    throw new \Exception("Impossible de lire le fichier: " . $jsonPathOrData);
                }
                // Supprimer le BOM UTF-8 si présent
                if (substr($jsonContent, 0, 3) === "\xEF\xBB\xBF") {
                    $jsonContent = substr($jsonContent, 3);
                }
            } else {
                // Sinon, traiter comme une chaîne JSON directe
                $jsonContent = $jsonPathOrData;
                // Supprimer le BOM UTF-8 si présent
                if (substr($jsonContent, 0, 3) === "\xEF\xBB\xBF") {
                    $jsonContent = substr($jsonContent, 3);
                }
            }

            if ($jsonContent === null || !$jsonContent) {
                throw new \Exception("La chaîne JSON ne peut pas être vide ");
            }

            // Réinitialiser les erreurs JSON précédentes
            json_decode('{}');
            
            $data = json_decode($jsonContent, true);
            
            // Vérifier d'abord s'il y a une erreur JSON
            $jsonError = json_last_error();
            if ($jsonError !== JSON_ERROR_NONE) {
                throw new \Exception("Erreur lors du parsing du JSON: " . json_last_error_msg() . " (code: " . $jsonError . ")");
            }
            
            // Ensuite vérifier que le résultat est un tableau/objet
            if ($data === null || !is_array($data)) {
                throw new \Exception("Le JSON décodé doit être un tableau/objet, type reçu: " . gettype($data));
            }
            
            $this->data = $data;

            return;
        }

        $this->resetFormattedText();
        $this->getFormattedText();

        throw new \Exception("Le paramètre doit être un chemin de fichier, une chaîne JSON ou un tableau");
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
     * Récupérer le formatted_text depuis l'API Python
     * Cette méthode fait un appel API pour récupérer le formatted_text à jour
     * 
     * @return string Le formatted_text récupéré depuis l'API
     * @throws \Exception Si l'appel API échoue
     */
    public function getFormattedText(): string
    {
        if ($this->formatted_text) {
            return $this->formatted_text;
        }

        $ebook_id = $this->getId();
        if (empty($ebook_id)) {
            throw new \Exception("Impossible de récupérer le formatted_text : ID ebook manquant");
        }

        // URL de l'API Python
        $api_url = "http://54.38.35.141:8080/get/ebook/id/" . urlencode($ebook_id) . "/formatted_text";
        
        // Configuration cURL optimisée
        $ch = curl_init($api_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300, // 5 minutes pour les gros fichiers
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: WordPress-EncycloAI/1.0',
            ],
            CURLOPT_BUFFERSIZE => 32768, // 32KB buffer
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
        ]);
        
        // Retry en cas d'échec
        $max_retries = 3;
        $response = false;
        $http_code = 0;
        $curl_error = '';
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            if ($response !== false && $http_code === 200 && empty($curl_error)) {
                break; // Succès
            }
            
            if ($attempt < $max_retries) {
                sleep(pow(2, $attempt - 1)); // Backoff exponentiel
            }
        }
        
        curl_close($ch);

        if ($curl_error) {
            throw new \Exception("Erreur cURL lors de la récupération du formatted_text : " . $curl_error);
        }

        if ($http_code !== 200) {
            throw new \Exception("Erreur API lors de la récupération du formatted_text : Code HTTP " . $http_code);
        }

        if ($response === false || empty($response)) {
            throw new \Exception("Réponse vide de l'API lors de la récupération du formatted_text");
        }

        // Décoder le JSON pour extraire le formatted_text
        $data = json_decode($response, true);
        
        if ($data === null || !isset($data['formatted_text'])) {
            // Fallback : utiliser le formatted_text du JSON local si disponible
            if (isset($this->data['formatted_text'])) {
                return $this->data['formatted_text'];
            }
            throw new \Exception("Le formatted_text n'a pas été trouvé dans la réponse de l'API");
        }
        
        $formatted_text = $data['formatted_text'];
        
        // Si c'est null ou vide, utiliser le fallback
        if (empty($formatted_text) && isset($this->data['formatted_text'])) {
            return $this->data['formatted_text'];
        }
        
        if (empty($formatted_text)) {
            throw new \Exception("Le formatted_text est vide pour cet ebook");
        }

        $this->formatted_text = $formatted_text;
        
        return $formatted_text;
    }

    /**
     * Extraire le sous-titre depuis formatted_text
     */
    public function getSubtitle(): string
    {
        try {
            $formatted = $this->getFormattedText();
        } catch (\Exception $e) {
            // Fallback sur le formatted_text local si l'API échoue
            $formatted = $this->data['formatted_text'] ?? '';
        }

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

    /**
     * Obtenir les données brutes complètes
     * Note: Le formatted_text dans ces données peut être obsolète.
     * Utilisez getFormattedText() pour obtenir la version à jour depuis l'API.
     */
    public function getRawData(): array
    {
        return $this->data;
    }

    /**
     * Obtenir les données brutes complètes avec le formatted_text à jour depuis l'API
     * Cette méthode récupère le formatted_text depuis l'API et l'injecte dans les données
     * 
     * @return array Les données avec le formatted_text à jour
     */
    public function getRawDataWithFormattedText(): array
    {
        $data = $this->data;
        try {
            $data['formatted_text'] = $this->getFormattedText();
        } catch (\Exception $e) {
            // Si l'API échoue, on garde le formatted_text local
            // Le formatted_text local sera déjà dans $data si présent
        }
        return $data;
    }
}
