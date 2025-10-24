<?php

namespace Vex6\Phppdf\Services;

use Vex6\Phppdf\Models\EbookData;

/**
 * Service pour générer les sections de bibliographie (Wikipedia et Sites Web)
 */
class BibliographyGenerator
{
    /**
     * Générer le HTML de la section Wikipedia
     */
    public function generateWikipediaHtml(EbookData $ebookData, array $acronymLinks = []): string
    {
        $rawData = $ebookData->getRawData();
        $wikipedias = $rawData['bibliography']['wikipedias'] ?? [];

        $html = '<div class="page page-wikipedia">';
        $html .= '<div class="wikipedia-watermark"><img src="{{WATERMARK_IMAGE}}" alt=""></div>';
        $html .= '<div class="wikipedia-container page-container">';
        
        // Titre de la section
        $html .= '<a id="wikipedia" name="wikipedia"></a>';
        $html .= '<h1 class="section-title">WIKIPEDIA</h1>';

        // Introduction standard
        $html .= '<p class="bibliography-intro">';
        $html .= 'Pour approfondir le sujet, nous vous suggérons les liens vers les pages Wikipédia suivantes. ';
        $html .= 'Les pages référencées sont en anglais. Vous pourrez ensuite accéder à la page dans la langue de votre choix.';
        $html .= '</p>';

        // Générer les entrées Wikipedia
        if (!empty($wikipedias)) {
            foreach ($wikipedias as $wiki) {
                $title = $wiki['title'] ?? '';
                $description = $wiki['description'] ?? '';
                $link = $wiki['link'] ?? '';

                $html .= '<div class="bibliography-entry">';

                // Titre de l'entrée
                if (!empty($title)) {
                    $html .= '<h3 class="bibliography-title">';
                    $html .= htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                    $html .= '</h3>';
                }

                // Description
                if (!empty($description)) {
                    // Injecter les liens de glossaire si applicable
                    $processedDescription = $description;
                    if (!empty($acronymLinks)) {
                        $processedDescription = $this->injectGlossaryLinks($description, $acronymLinks);
                    } else {
                        $processedDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                    }
                    
                    $html .= '<p class="bibliography-description">';
                    $html .= $processedDescription;
                    $html .= '</p>';
                }

                // Lien
                if (!empty($link)) {
                    $html .= '<p class="bibliography-link">';
                    $html .= '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" class="wiki-link">';
                    $html .= htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
                    $html .= '</a>';
                    $html .= '</p>';
                }

                $html .= '</div>';
            }
        } else {
            $html .= '<p class="no-content">Aucune référence Wikipedia disponible pour ce Smartbook.</p>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Générer le HTML de la section Sites Web
     */
    public function generateWebsitesHtml(EbookData $ebookData, array $acronymLinks = []): string
    {
        $rawData = $ebookData->getRawData();
        $websites = $rawData['bibliography']['websites_nocommercial'] ?? [];

        $html = '<div class="page page-websites">';
        $html .= '<div class="websites-watermark"><img src="{{WATERMARK_IMAGE}}" alt=""></div>';
        $html .= '<div class="websites-container page-container">';
        
        // Titre de la section
        $html .= '<a id="sites-web" name="sites-web"></a>';
        $html .= '<h1 class="section-title">SITES WEB</h1>';

        // Introduction standard
        $html .= '<p class="bibliography-intro">';
        $html .= 'Pour approfondir le sujet, nous vous suggérons de consulter les sites Web suivants.';
        $html .= '</p>';

        // Générer les entrées Sites Web
        if (!empty($websites)) {
            foreach ($websites as $site) {
                $name = $site['website_name'] ?? '';
                $description = $site['website_description'] ?? '';
                $url = $site['website_url'] ?? '';

                $html .= '<div class="bibliography-entry">';

                // Nom du site
                if (!empty($name)) {
                    $html .= '<h3 class="bibliography-title">';
                    $html .= htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                    $html .= '</h3>';
                }

                // Description
                if (!empty($description)) {
                    // Injecter les liens de glossaire si applicable
                    $processedDescription = $description;
                    if (!empty($acronymLinks)) {
                        $processedDescription = $this->injectGlossaryLinks($description, $acronymLinks);
                    } else {
                        $processedDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                    }
                    
                    $html .= '<p class="bibliography-description">';
                    $html .= $processedDescription;
                    $html .= '</p>';
                }

                // URL
                if (!empty($url)) {
                    $html .= '<p class="bibliography-link">';
                    $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="wiki-link">';
                    $html .= htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                    $html .= '</a>';
                    $html .= '</p>';
                }

                $html .= '</div>';
            }
        } else {
            $html .= '<p class="no-content">Aucun site web de référence disponible pour ce Smartbook.</p>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Injecter les liens de glossaire dans le texte
     */
    private function injectGlossaryLinks(string $text, array $acronymLinks): string
    {
        if (empty($acronymLinks)) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // Créer le pattern regex pour tous les acronymes
        $acronyms = array_keys($acronymLinks);
        $escapedAcronyms = array_map('preg_quote', $acronyms);
        $pattern = '/(\s*)(\b(?:' . implode('|', $escapedAcronyms) . ')\b)(\s*)/u';

        $result = preg_replace_callback($pattern, function($match) use ($acronymLinks) {
            $spaceBefore = $match[1];
            $acronym = $match[2];
            $spaceAfter = $match[3];

            if (!isset($acronymLinks[$acronym])) {
                return htmlspecialchars($match[0], ENT_QUOTES, 'UTF-8');
            }

            $linkInfo = $acronymLinks[$acronym];
            $link = '<a href="#' . $linkInfo['anchor'] . '" class="glossary-link">' . 
                   htmlspecialchars($acronym, ENT_QUOTES, 'UTF-8') . 
                   '</a>';

            return htmlspecialchars($spaceBefore, ENT_QUOTES, 'UTF-8') . 
                   $link . 
                   htmlspecialchars($spaceAfter, ENT_QUOTES, 'UTF-8');
        }, $text);

        return $result;
    }

    /**
     * Compter le nombre d'entrées Wikipedia
     */
    public function countWikipediaEntries(EbookData $ebookData): int
    {
        $rawData = $ebookData->getRawData();
        $wikipedias = $rawData['bibliography']['wikipedias'] ?? [];
        return count($wikipedias);
    }

    /**
     * Compter le nombre d'entrées Sites Web
     */
    public function countWebsiteEntries(EbookData $ebookData): int
    {
        $rawData = $ebookData->getRawData();
        $websites = $rawData['bibliography']['websites_nocommercial'] ?? [];
        return count($websites);
    }

    /**
     * Vérifier si la bibliographie a du contenu
     */
    public function hasBibliographyContent(EbookData $ebookData): array
    {
        return [
            'has_wikipedia' => $this->countWikipediaEntries($ebookData) > 0,
            'has_websites' => $this->countWebsiteEntries($ebookData) > 0,
            'wikipedia_count' => $this->countWikipediaEntries($ebookData),
            'websites_count' => $this->countWebsiteEntries($ebookData)
        ];
    }

    /**
     * Obtenir les statistiques de la bibliographie
     */
    public function getBibliographyStats(EbookData $ebookData): array
    {
        $rawData = $ebookData->getRawData();
        
        $wikipedias = $rawData['bibliography']['wikipedias'] ?? [];
        $websites = $rawData['bibliography']['websites_nocommercial'] ?? [];
        
        $stats = [
            'wikipedia' => [
                'count' => count($wikipedias),
                'with_links' => 0,
                'with_descriptions' => 0
            ],
            'websites' => [
                'count' => count($websites),
                'with_urls' => 0,
                'with_descriptions' => 0
            ]
        ];

        // Analyser Wikipedia
        foreach ($wikipedias as $wiki) {
            if (!empty($wiki['link'])) {
                $stats['wikipedia']['with_links']++;
            }
            if (!empty($wiki['description'])) {
                $stats['wikipedia']['with_descriptions']++;
            }
        }

        // Analyser Sites Web
        foreach ($websites as $site) {
            if (!empty($site['website_url'])) {
                $stats['websites']['with_urls']++;
            }
            if (!empty($site['website_description'])) {
                $stats['websites']['with_descriptions']++;
            }
        }

        return $stats;
    }
}