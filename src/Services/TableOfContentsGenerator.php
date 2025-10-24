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
        
        $html = '<div class="toc-container page-container">';
        
        for ($i = $firstPageEntries; $i < count($this->tocEntries); $i++) {
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
     * Calculer combien d'entrées peuvent tenir sur la première page
     * Approche optimiste qui maximise l'utilisation de l'espace
     */
    private function calculateFirstPageEntries(): int
    {
        $totalEntries = count($this->tocEntries);
        
        // Approche progressive: on teste différents pourcentages
        // pour trouver la répartition optimale
        $testRatios = [0.65, 0.70, 0.75, 0.60, 0.55]; // Commencer par 65% sur la première page
        
        foreach ($testRatios as $ratio) {
            $firstPageCount = (int)round($totalEntries * $ratio);
            
            // S'assurer qu'on a au moins 2 entrées sur chaque page
            if ($firstPageCount >= 2 && ($totalEntries - $firstPageCount) >= 2) {
                return $firstPageCount;
            }
        }
        
        // Fallback : répartition simple 60/40
        $firstPageCount = (int)ceil($totalEntries * 0.6);
        
        // S'assurer qu'on ne dépasse pas les limites
        return max(2, min($firstPageCount, $totalEntries - 2));
    }

    /**
     * Générer les pages supplémentaires de la TOC (désactivé - TOC naturelle)
     */
    public function generateAdditionalPages(): array
    {
        return []; // Plus de pages supplémentaires - la TOC continue naturellement
    }

    /**
     * Extraire les entrées TOC à partir des données JSON
     */
    public function extractTOCFromData(array $data): void
    {
        $currentPage = 4; // Commencer après couverture, licence et TOC
        
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
                
                $this->addEntry($chapterTitle, 'TITRE1', $currentPage);
                $currentPage++;
                
                // Ajouter les sous-chapitres
                if (isset($chapter['subchapter'])) {
                    foreach ($chapter['subchapter'] as $subchapter) {
                        $subchapterTitle = $subchapter['subchapter'] ?? '';
                        $this->addEntry($subchapterTitle, 'TITRE2', $currentPage);
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
        $this->addEntry('WIKIPEDIA', 'TITRE1', ++$currentPage);
        $this->addEntry('SITES WEB', 'TITRE1', ++$currentPage);
        $this->addEntry('SUGGESTIONS', 'TITRE1', ++$currentPage);
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