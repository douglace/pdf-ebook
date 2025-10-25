<?php

namespace Vex6\Phppdf\Services;


/**
 * Service pour générer la table des matières de l'ebook
 */
class TableOfContentsGenerator
{
    private array $tocEntries = [];

    /**
     * Ajouter une entrée à la table des matières
     */
    public function addEntry(string $title, string $level, int $page, ?string $anchor = null): void
    {
        $this->tocEntries[] = [
            'title' => $this->sanitizeTitle($title),
            'level' => $level,
            'page' => $page,
            'anchor' => $anchor
        ];
    }

    /**
     * Générer le HTML de la première page de la table des matières
     */
    public function generateTOCHtml(): string
    {
        if (empty($this->tocEntries)) {
            return '';
        }
        
        $firstPageEntries = $this->calculateFirstPageEntries();
        
        $html = '<div class="toc-container page-container">';
        $html .= '<h1 class="toc-title">TABLE DES MATIÈRES</h1>';
        
        for ($i = 0; $i < $firstPageEntries && $i < count($this->tocEntries); $i++) {
            $entry = $this->tocEntries[$i];
            $class = $entry['level'] === 'TITRE1' ? 'toc-entry-level1' : 'toc-entry-level2';
            
            $html .= '<div class="' . $class . '">';
            
            // Créer un lien si une ancre est disponible
            if (!empty($entry['anchor'])) {
                $html .= '<a href="#' . $entry['anchor'] . '" class="toc-link">';
                $html .= '<span class="toc-entry-title">' . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</a>';
            } else {
                $html .= '<span class="toc-entry-title">' . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            
            $html .= '<span class="toc-dots"></span>';
            $html .= '<span class="toc-page">' . $entry['page'] . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Générer le HTML de la deuxième page de la table des matières
     * Stratégie : REMPLIR AU MAXIMUM la page 2 avant de passer à la page 3
     */
    public function generateTOCPage2Html(): string
    {
        if (empty($this->tocEntries)) {
            return '';
        }

        $firstPageEntries = $this->calculateFirstPageEntries();

        if ($firstPageEntries >= count($this->tocEntries)) {
            return '<div class="toc-container page-container"></div>';
        }

        // Calculer combien d'entrées peuvent tenir sur la page 2
        $page2Entries = $this->calculatePageCapacity($firstPageEntries);
        $endIndex = min($firstPageEntries + $page2Entries, count($this->tocEntries));

        $html = '<div class="toc-container page-container">';

        for ($i = $firstPageEntries; $i < $endIndex; $i++) {
            $entry = $this->tocEntries[$i];
            $class = $entry['level'] === 'TITRE1' ? 'toc-entry-level1' : 'toc-entry-level2';

            $html .= '<div class="' . $class . '">';

            // Créer un lien si une ancre est disponible
            if (!empty($entry['anchor'])) {
                $html .= '<a href="#' . $entry['anchor'] . '" class="toc-link">';
                $html .= '<span class="toc-entry-title">' . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</a>';
            } else {
                $html .= '<span class="toc-entry-title">' . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') . '</span>';
            }

            $html .= '<span class="toc-dots"></span>';
            $html .= '<span class="toc-page">' . $entry['page'] . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Calculer combien d'entrées peuvent tenir sur une page donnée
     * Page 2+ ont plus d'espace car pas de titre "TABLE DES MATIÈRES"
     * Basé sur les dimensions CSS réelles
     */
    private function calculatePageCapacity(int $startIndex): int
    {
        $totalEntries = count($this->tocEntries);

        // Espace disponible page 2+ : 980px (pas de titre)
        // Marge de sécurité pour éviter les coupures en bas de page
        $safetyMargin = 130; // pixels
        $availableHeight = 980 - $safetyMargin; // pixels
        $currentHeight = 0;
        $entryCount = 0;

        for ($i = $startIndex; $i < $totalEntries; $i++) {
            $entry = $this->tocEntries[$i];
            $entryHeight = $entry['level'] === 'TITRE1' ? 28 : 18;

            if ($currentHeight + $entryHeight <= $availableHeight) {
                $currentHeight += $entryHeight;
                $entryCount++;
            } else {
                break;
            }
        }

        return $entryCount;
    }

    /**
     * Calculer combien d'entrées peuvent tenir sur la première page
     * Stratégie : REMPLIR AU MAXIMUM la page 1 avant de passer à la page 2
     * Basé sur les dimensions CSS réelles
     */
    private function calculateFirstPageEntries(): int
    {
        $totalEntries = count($this->tocEntries);

        // Dimensions réelles basées sur styles.css :
        // - Hauteur page : 297mm ≈ 1120px
        // - toc-container : top 80px, bottom 60px → espace = 980px
        // - toc-title : 35px + margin 25px = 60px
        // - Espace disponible page 1 : 980px - 60px = 920px

        // Hauteur par type d'entrée (basé sur CSS) :
        // - TITRE1 : font 18px + margin 10px = 28px
        // - TITRE2 : font 12px + margin 6px = 18px

        // Marge de sécurité pour éviter les coupures en bas de page
        $safetyMargin = 130; // pixels
        $availableHeight = 920 - $safetyMargin; // pixels
        $currentHeight = 0;
        $entryCount = 0;

        foreach ($this->tocEntries as $entry) {
            $entryHeight = $entry['level'] === 'TITRE1' ? 28 : 18;

            // Vérifier si on peut encore ajouter cette entrée
            if ($currentHeight + $entryHeight <= $availableHeight) {
                $currentHeight += $entryHeight;
                $entryCount++;
            } else {
                // On a atteint la capacité max de la page
                break;
            }
        }

        // Si toutes les entrées rentrent, retourner le total
        if ($entryCount >= $totalEntries) {
            return $totalEntries;
        }

        // Sinon retourner ce qui rentre (avec minimum de sécurité)
        return max(3, $entryCount);
    }

    /**
     * Générer les pages supplémentaires de la TOC (si plus de 2 pages nécessaires)
     * Retourne un tableau de HTML pour les pages 3, 4, etc.
     * Stratégie : REMPLIR AU MAXIMUM chaque page avant de passer à la suivante
     */
    public function generateAdditionalPages(): array
    {
        if (empty($this->tocEntries)) {
            return [];
        }

        $firstPageEntries = $this->calculateFirstPageEntries();
        $page2Entries = $this->calculatePageCapacity($firstPageEntries);
        $totalEntries = count($this->tocEntries);

        // Si tout tient en 2 pages, pas besoin de pages supplémentaires
        if ($firstPageEntries + $page2Entries >= $totalEntries) {
            return [];
        }

        // Générer les pages 3, 4, 5, etc.
        $additionalPages = [];
        $currentIndex = $firstPageEntries + $page2Entries;

        while ($currentIndex < $totalEntries) {
            // Calculer combien d'entrées peuvent tenir sur cette page
            $pageCapacity = $this->calculatePageCapacity($currentIndex);
            $endIndex = min($currentIndex + $pageCapacity, $totalEntries);

            $pageHtml = '<div class="toc-container page-container">';

            for ($i = $currentIndex; $i < $endIndex; $i++) {
                $entry = $this->tocEntries[$i];
                $class = $entry['level'] === 'TITRE1' ? 'toc-entry-level1' : 'toc-entry-level2';

                $pageHtml .= '<div class="' . $class . '">';

                if (!empty($entry['anchor'])) {
                    $pageHtml .= '<a href="#' . $entry['anchor'] . '" class="toc-link">';
                    $pageHtml .= '<span class="toc-entry-title">' . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') . '</span>';
                    $pageHtml .= '</a>';
                } else {
                    $pageHtml .= '<span class="toc-entry-title">' . htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8') . '</span>';
                }

                $pageHtml .= '<span class="toc-dots"></span>';
                $pageHtml .= '<span class="toc-page">' . $entry['page'] . '</span>';
                $pageHtml .= '</div>';
            }

            $pageHtml .= '</div>';
            $additionalPages[] = $pageHtml;

            // Passer à la page suivante
            $currentIndex = $endIndex;
        }

        return $additionalPages;
    }

    /**
     * Extraire les entrées TOC à partir des données JSON
     */
    public function extractTOCFromData(array $data): void
    {
        $currentPage = 4; // Commencer après couverture, licence et TOC
        
        if($this->extractIntroductionFromData($data, $currentPage)) {
            $currentPage++;
        }

        // Ajouter l'introduction (chapitre 0) en premier
        if (isset($data['tdm']['chapters'])) {
            foreach ($data['tdm']['chapters'] as $chapter) {
                $chapterNumber = $chapter['numero_chapter'] ?? '';
                $chapterTitle = $chapter['chapter'] ?? '';
                
                if ($chapterNumber === '0') {
                    $this->addEntry('INTRODUCTION - ' . $chapterTitle, 'TITRE1', $currentPage, 'introduction');
                    $currentPage++;
                    break;
                }
            }
            
            // Puis ajouter les chapitres principaux (1, 2, 3, etc.)
            foreach ($data['tdm']['chapters'] as $chapter) {
                $chapterNumber = $chapter['numero_chapter'] ?? '';
                $chapterTitle = $chapter['chapter'] ?? '';
                
                // Ignorer les chapitres 0 et -1
                if ($chapterNumber === '0' || $chapterNumber === '-1') {
                    continue;
                }
                
                $anchorId = 'chapter-' . $chapterNumber;
                $this->addEntry($chapterTitle, 'TITRE1', $currentPage, $anchorId);
                $currentPage++;
                
                // Ajouter les sous-chapitres
                if (isset($chapter['subchapter'])) {
                    foreach ($chapter['subchapter'] as $subchapter) {
                        $subchapterTitle = $subchapter['subchapter'] ?? '';
                        $subchapterNumber = $subchapter['numero_subchapter'] ?? '';
                        $subchapterAnchorId = 'chapter-' . $chapterNumber . '-subchapter-' . $subchapterNumber;
                        $this->addEntry($subchapterTitle, 'TITRE2', $currentPage, $subchapterAnchorId);
                    }
                }
            }
            
            // Enfin ajouter la conclusion (chapitre -1)
            foreach ($data['tdm']['chapters'] as $chapter) {
                $chapterNumber = $chapter['numero_chapter'] ?? '';
                $chapterTitle = $chapter['chapter'] ?? '';

                if ($chapterNumber === '-1') {
                    $this->addEntry('CONCLUSION - ' . $chapterTitle, 'TITRE1', $currentPage, 'conclusion');
                    $currentPage++;
                    break;
                }
            }
        }
        
        // Ajouter les sections finales
        $this->addEntry('GLOSSAIRE', 'TITRE1', ++$currentPage);
        $this->addEntry('QUIZ', 'TITRE1', ++$currentPage, 'quiz');
        $this->addEntry('WIKIPEDIA', 'TITRE1', ++$currentPage, 'wikipedia');
        $this->addEntry('SITES WEB', 'TITRE1', ++$currentPage, 'websites');
        $this->addEntry('SUGGESTIONS', 'TITRE1', ++$currentPage, 'suggestions');
    }

    public function extractIntroductionFromData(array $data, int $currentPage): bool
    {
        $foundIntroduction = false;
        foreach ($data['tdm']['chapters'] as $chapter) {
            $chapterNumber = $chapter['numero_chapter'] ?? '';
            $chapterTitle = $chapter['chapter'] ?? '';
            
            if ($chapterNumber === '0') {
                $this->addEntry('INTRODUCTION - ' . $chapterTitle, 'TITRE1', $currentPage, 'introduction');
                $foundIntroduction = true;
                break;
            }
        }

        if (!$foundIntroduction) {
            if (isset($data['chapters']['chapters'])) {
                foreach ($data['chapters']['chapters'] as $chapter) {
                    $chapterNumber = $chapter['chapter_info_title']['numero_chapter'] ?? '';
                    $chapterTitle = $chapter['chapter_info_title']['chapter'] ?? '';
                    
                    if ($chapterNumber === '0') {
                        $this->addEntry('INTRODUCTION - ' . $chapterTitle, 'TITRE1', $currentPage, 'introduction');
                        $foundIntroduction = true;
                        break;
                    }
                }
            }
        }
        return $foundIntroduction;
    }

    /**
     * Nettoyer le titre pour la TOC
     */
    private function sanitizeTitle(string $title): string
    {
        // Supprimer les placeholders potentiels
        $title = preg_replace('/\{\{[^}]+\}\}/', '', $title);
        
        // Supprimer les balises HTML
        $title = strip_tags($title);
        
        // Normaliser les espaces
        $title = preg_replace('/\s+/', ' ', $title);
        
        return trim($title);
    }

    /**
     * Obtenir le nombre d'entrées
     */
    public function getEntryCount(): int
    {
        return count($this->tocEntries);
    }

    /**
     * Vérifier si la TOC a des entrées
     */
    public function hasEntries(): bool
    {
        return !empty($this->tocEntries);
    }

    /**
     * Réinitialiser les entrées
     */
    public function reset(): void
    {
        $this->tocEntries = [];
    }
}