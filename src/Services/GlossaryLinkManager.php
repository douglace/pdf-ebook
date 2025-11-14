<?php

declare(strict_types=1);

namespace Vex6\Phppdf\Services;

/**
 * Gestionnaire des liens automatiques vers le glossaire
 * Implémente le système décrit dans EBOOK_GENERATION_COMPLETE.md section 4
 */
class GlossaryLinkManager
{
    private array $acronymAnchors = [];
    private array $usedSlugs = [];
    private array $firstOccurrenceFound = [];

    /**
     * Identifier tous les acronymes depuis les entrées de glossaire
     *
     * @param array $zones Les zones parsées du formatted_text
     * @return array Liste des acronymes identifiés
     */
    public function identifyAcronyms(array $zones): array
    {
        $acronyms = [];

        foreach ($zones['corps'] as $entry) {
            if ($entry['tag'] === 'ENTREE_GLOSSAIRE') {
                // Format attendu : "ACRONYME - Définition complète"
                $parts = explode(' - ', $entry['text'], 2);
                $acronym = trim($parts[0]);
                
                if (!empty($acronym)) {
                    $acronyms[] = $acronym;
                }
            }
        }

        return array_unique($acronyms);
    }

    /**
     * Créer les ancres de liens pour tous les acronymes
     *
     * @param array $acronyms Liste des acronymes
     * @param callable $addLinkCallback Fonction pour créer un lien TCPDF (AddLink)
     * @return array Structure des ancres créées
     */
    public function createAcronymAnchors(array $acronyms, callable $addLinkCallback): array
    {
        $this->acronymAnchors = [];
        $this->usedSlugs = [];

        foreach ($acronyms as $acronym) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $acronym));

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
                'gloss' => $glossSlug,           // Slug pour l'entrée glossaire
                'back' => $backSlug,             // Slug pour le retour
                'links' => [
                    'gloss' => $addLinkCallback(),  // ID numérique TCPDF
                    'back' => $addLinkCallback(),   // ID numérique TCPDF
                ],
            ];
        }

        return $this->acronymAnchors;
    }

    /**
     * Injecter automatiquement les liens vers les acronymes dans le texte
     *
     * @param string $text Texte à modifier
     * @param array $acronymAnchors Structure des ancres
     * @param array $firstOccurrenceFound Référence pour tracker les premières occurrences
     * @return string Texte modifié avec les liens
     */
    public function injectGlossaryLinks(string $text, array $acronymAnchors, array &$firstOccurrenceFound): string
    {
        if (empty($acronymAnchors)) {
            return $text;
        }

        // Créer le pattern regex pour tous les acronymes
        $escapedAcronyms = array_map('preg_quote', array_keys($acronymAnchors));
        $pattern = '/(\\s*)(\\b(?:' . implode('|', $escapedAcronyms) . ')\\b)(\\s*)/u';

        return preg_replace_callback($pattern, function($match) use ($acronymAnchors, &$firstOccurrenceFound) {
            $spaceBefore = $match[1];
            $acronym = $match[2];
            $spaceAfter = $match[3];

            if (!isset($acronymAnchors[$acronym])) {
                return $match[0];
            }

            $anchors = $acronymAnchors[$acronym];
            $prefix = '';

            // Première occurrence : ajouter l'ancre de retour
            if (!isset($firstOccurrenceFound[$acronym])) {
                $firstOccurrenceFound[$acronym] = true;
                $prefix = sprintf(
                    '{{EAIEG_SET_DEST:%d}}{{EAIEG_HTML:<a name="%s" id="%s"></a>}}',
                    $anchors['links']['back'],
                    htmlspecialchars($anchors['back'], ENT_QUOTES),
                    htmlspecialchars($anchors['back'], ENT_QUOTES)
                );
            }

            // Créer le lien vers le glossaire (couleur bleue #0066CC)
            $link = sprintf(
                '{{EAIEG_LINK:%d:%s:0066CC}}',
                $anchors['links']['gloss'],
                htmlspecialchars($acronym, ENT_QUOTES)
            );

            return $spaceBefore . $prefix . $link . $spaceAfter;
        }, $text);
    }

    /**
     * Modifier une entrée de glossaire pour ajouter les liens retour
     *
     * @param string $text Texte de l'entrée de glossaire
     * @param array $acronymAnchors Structure des ancres
     * @param array $firstOccurrenceFound Premières occurrences trouvées
     * @return string Texte modifié avec les liens retour
     */
    public function createGlossaryEntryWithLinks(string $text, array $acronymAnchors, array $firstOccurrenceFound): string
    {
        $parts = explode(' - ', $text, 2);
        $acronym = trim($parts[0]);
        $definition = $parts[1] ?? '';

        if (!isset($acronymAnchors[$acronym])) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        $anchors = $acronymAnchors[$acronym];

        // Créer l'ancre de destination du glossaire
        $destPlaceholder = sprintf('{{EAIEG_SET_DEST:%d}}', $anchors['links']['gloss']);
        $htmlPlaceholder = sprintf(
            '{{EAIEG_HTML:<a name="%s" id="%s"></a>}}',
            htmlspecialchars($anchors['gloss'], ENT_QUOTES),
            htmlspecialchars($anchors['gloss'], ENT_QUOTES)
        );

        // Si première occurrence trouvée → lien retour cliquable
        if (isset($firstOccurrenceFound[$acronym])) {
            $linkPlaceholder = sprintf(
                '{{EAIEG_LINK:%d:%s - %s:0066CC}}',
                $anchors['links']['back'],
                htmlspecialchars($acronym, ENT_QUOTES),
                htmlspecialchars($definition, ENT_QUOTES)
            );

            return $destPlaceholder . $htmlPlaceholder . $linkPlaceholder;
        } else {
            // Pas de première occurrence → texte simple
            return $destPlaceholder . $htmlPlaceholder . htmlspecialchars($acronym . ' - ' . $definition, ENT_QUOTES);
        }
    }

    /**
     * Vérifier si un tag doit recevoir l'injection de liens
     *
     * @param string $tag Tag du bloc
     * @param bool $inGlossaryParagraph Si on est dans un paragraphe de glossaire
     * @return bool True si l'injection est autorisée
     */
    public function shouldInjectLinks(string $tag, bool $inGlossaryParagraph): bool
    {
        return ($tag !== 'ENTREE_GLOSSAIRE') &&
               !($tag === 'NORMAL' && $inGlossaryParagraph);
    }

    /**
     * Obtenir les ancres créées
     *
     * @return array
     */
    public function getAcronymAnchors(): array
    {
        return $this->acronymAnchors;
    }

    /**
     * Réinitialiser l'état
     */
    public function reset(): void
    {
        $this->acronymAnchors = [];
        $this->usedSlugs = [];
        $this->firstOccurrenceFound = [];
    }
}
