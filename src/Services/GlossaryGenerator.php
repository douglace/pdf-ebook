<?php

namespace Vex6\Phppdf\Services;

/**
 * Service pour générer le glossaire et gérer les liens automatiques vers les acronymes
 */
class GlossaryGenerator
{
    private array $acronymAnchors = [];
    private array $usedSlugs = [];

    /**
     * Initialiser le système de liens de glossaire
     */
    public function initializeGlossaryLinks(array $acronyms): array
    {
        $this->acronymAnchors = [];
        $this->usedSlugs = [];

        foreach ($acronyms as $acronymData) {
            $acronym = $acronymData['acronym'];
            $definition = $acronymData['definition'];

            $slug = $this->generateSlug($acronym);
            
            // Gérer les collisions de slugs
            $glossSlug = 'gloss-' . $slug;
            $backSlug = 'back-' . $slug;
            $counter = 1;

            while (isset($this->usedSlugs[$glossSlug])) {
                $glossSlug = 'gloss-' . $slug . '-' . (++$counter);
                $backSlug = 'back-' . $slug . '-' . $counter;
            }

            $this->usedSlugs[$glossSlug] = true;
            $this->usedSlugs[$backSlug] = true;

            $this->acronymAnchors[$acronym] = [
                'gloss' => $glossSlug,
                'back' => $backSlug,
                'anchor' => $glossSlug,
                'definition' => $definition,
                'full_text' => $acronymData['full_text'] ?? ($acronym . ' - ' . $definition)
            ];
        }

        return $this->acronymAnchors;
    }

    /**
     * Générer le HTML de la section glossaire
     */
    public function generateGlossaryHtml(array $acronyms, array $acronymLinks): string
    {
        if (empty($acronyms)) {
            return '';
        }

        // Trier les acronymes par ordre alphabétique
        $sortedAcronyms = $acronyms;
        usort($sortedAcronyms, function($a, $b) {
            return strcasecmp($a['acronym'], $b['acronym']);
        });

        $html = '<div class="page page-glossary">';
        $html .= '<div class="glossary-watermark"><img src="{{WATERMARK_IMAGE}}" alt=""></div>';
        $html .= '<div class="glossary-container page-container">';
        
        // Titre de la section
        $html .= '<a id="glossaire" name="glossaire"></a>';
        $html .= '<h1 class="section-title">GLOSSAIRE</h1>';

        // Introduction du glossaire
        $html .= '<p class="glossary-intro">Liste des acronymes et définitions utilisés dans ce Smartbook.</p>';

        // Génération des entrées
        foreach ($sortedAcronyms as $acronymData) {
            $acronym = $acronymData['acronym'];
            $definition = $acronymData['definition'];
            
            if (isset($acronymLinks[$acronym])) {
                $linkInfo = $acronymLinks[$acronym];
                
                // Créer l'ancre de destination du glossaire
                $html .= '<div class="glossary-entry">';
                $html .= '<a id="' . $linkInfo['gloss'] . '" name="' . $linkInfo['gloss'] . '"></a>';
                
                // Créer le lien de retour vers la première occurrence (si elle existe)
                $html .= '<h3 class="glossary-term">';
                $html .= '<a href="#' . $linkInfo['back'] . '" class="glossary-back-link">';
                $html .= htmlspecialchars($acronym, ENT_QUOTES, 'UTF-8');
                $html .= '</a>';
                $html .= '</h3>';
                
                $html .= '<p class="glossary-definition">';
                $html .= htmlspecialchars($definition, ENT_QUOTES, 'UTF-8');
                $html .= '</p>';
                $html .= '</div>';
            } else {
                // Pas de lien (cas d'erreur)
                $html .= '<div class="glossary-entry">';
                $html .= '<h3 class="glossary-term">';
                $html .= htmlspecialchars($acronym, ENT_QUOTES, 'UTF-8');
                $html .= '</h3>';
                $html .= '<p class="glossary-definition">';
                $html .= htmlspecialchars($definition, ENT_QUOTES, 'UTF-8');
                $html .= '</p>';
                $html .= '</div>';
            }
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Injecter les liens de glossaire dans un texte
     */
    public function injectGlossaryLinks(string $text, array $acronymLinks, array &$firstOccurrenceTracker = []): string
    {
        if (empty($acronymLinks)) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // Créer le pattern regex pour tous les acronymes
        $acronyms = array_keys($acronymLinks);
        $escapedAcronyms = array_map('preg_quote', $acronyms);
        $pattern = '/(\s*)(\b(?:' . implode('|', $escapedAcronyms) . ')\b)(\s*)/u';

        $result = preg_replace_callback($pattern, function($match) use ($acronymLinks, &$firstOccurrenceTracker) {
            $spaceBefore = $match[1];
            $acronym = $match[2];
            $spaceAfter = $match[3];

            if (!isset($acronymLinks[$acronym])) {
                return htmlspecialchars($match[0], ENT_QUOTES, 'UTF-8');
            }

            $linkInfo = $acronymLinks[$acronym];
            $prefix = '';

            // Première occurrence : ajouter l'ancre de retour
            if (!isset($firstOccurrenceTracker[$acronym])) {
                $firstOccurrenceTracker[$acronym] = true;
                $prefix = '<a id="' . $linkInfo['back'] . '" name="' . $linkInfo['back'] . '"></a>';
            }

            // Créer le lien vers le glossaire
            $link = '<a href="#' . $linkInfo['gloss'] . '" class="glossary-link">' . 
                   htmlspecialchars($acronym, ENT_QUOTES, 'UTF-8') . 
                   '</a>';

            return htmlspecialchars($spaceBefore, ENT_QUOTES, 'UTF-8') . 
                   $prefix . 
                   $link . 
                   htmlspecialchars($spaceAfter, ENT_QUOTES, 'UTF-8');
        }, $text);

        return $result;
    }

    /**
     * Obtenir les informations de liens pour un acronyme
     */
    public function getAcronymLinks(): array
    {
        return $this->acronymAnchors;
    }

    /**
     * Générer un slug à partir d'un acronyme
     */
    private function generateSlug(string $acronym): string
    {
        $slug = strtolower($acronym);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug ?: 'acronym-' . uniqid();
    }

    /**
     * Extraire les réponses de quiz depuis le texte
     */
    public function extractQuizAnswers(string $formattedText): ?string
    {
        $lines = explode("\n", $formattedText);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Chercher une ligne contenant "REPONSES"
            if (stripos($line, 'REPONSES') !== false) {
                // Regarder la ligne suivante
                if (isset($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    // Vérifier si c'est bien une ligne de réponses (format: 1-a, 2-c, etc.)
                    if (preg_match('/^\d+-[a-d]/', $nextLine)) {
                        return $nextLine;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Vérifier si on doit injecter des liens dans un tag donné
     */
    public function shouldInjectLinks(string $tag, bool $inGlossaryParagraph = false): bool
    {
        // Ne pas injecter dans les entrées de glossaire elles-mêmes
        if ($tag === 'ENTREE_GLOSSAIRE') {
            return false;
        }

        // Ne pas injecter dans les paragraphes suivant une entrée de glossaire
        if ($tag === 'NORMAL' && $inGlossaryParagraph) {
            return false;
        }

        return true;
    }

    /**
     * Nettoyer le texte pour la TOC (supprimer les placeholders et liens)
     */
    public function sanitizeForTOC(string $text): string
    {
        // Supprimer les ancres
        $text = preg_replace('/<a[^>]*id="[^"]*"[^>]*><\/a>/', '', $text);
        
        // Supprimer les liens mais garder le texte
        $text = preg_replace('/<a[^>]*>(.*?)<\/a>/', '$1', $text);
        
        // Supprimer les balises HTML restantes
        $text = strip_tags($text);
        
        // Normaliser les espaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Compter le nombre d'entrées de glossaire
     */
    public function countGlossaryEntries(array $acronyms): int
    {
        return count($acronyms);
    }
}