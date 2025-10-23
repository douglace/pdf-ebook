# Guide Complet de Génération des Ebooks Encyclo-AI

Ce document décrit en détail **l'ensemble du processus de génération des ebooks SmartBooks** à partir des données JSON, en complément de [ebook.md](ebook.md) qui se concentre sur la page de couverture.

---

## Table des Matières

1. [Vue d'ensemble du système](#1-vue-densemble-du-système)
2. [Parsing du formatted_text](#2-parsing-du-formatted_text)
3. [Système de placeholders internes](#3-système-de-placeholders-internes)
4. [Système de glossaire et liens automatiques](#4-système-de-glossaire-et-liens-automatiques)
5. [Page de mentions légales](#5-page-de-mentions-légales)
6. [Table des matières (TOC)](#6-table-des-matières-toc)
7. [Génération du contenu principal](#7-génération-du-contenu-principal)
8. [Sections finales (Glossaire, Quiz, Bibliographie)](#8-sections-finales)
9. [Gestion de la justification du texte](#9-gestion-de-la-justification-du-texte)
10. [En-têtes, pieds de page et filigranes](#10-en-têtes-pieds-de-page-et-filigranes)
11. [Architecture technique (TCPDF)](#11-architecture-technique-tcpdf)
12. [Flux complet de génération](#12-flux-complet-de-génération)

---

## 1. Vue d'ensemble du système

### 1.1 Architecture globale

La génération d'ebook se décompose en plusieurs phases :

```
Données JSON
    ↓
Parse formatted_text → Extraction des zones
    ↓
Identification des acronymes → Création des ancres de liens
    ↓
Initialisation PDF (TCPDF/StyledPDF)
    ↓
Page 1: Couverture (voir ebook.md)
    ↓
Page 2: Mentions légales
    ↓
Réservation espace TOC (pages vides)
    ↓
Génération contenu (chapitres, texte)
    ↓
Sections finales (glossaire, quiz, bibliographie)
    ↓
Retour en arrière → Génération TOC
    ↓
Sauvegarde PDF final
```

### 1.2 Fichiers clés

| Fichier | Rôle |
|---------|------|
| `encyclo-ai-ebook-generator.php` | Fonctions principales : `generate_ebook_pdf()`, `parseZones()`, `addCover()` |
| `class/StyledPDF.php` | Classe PDF étendue, gestion des styles et du rendu |
| `class/TOCManager.php` | Gestion complète de la table des matières |

### 1.3 Classes et responsabilités

**StyledPDF** (extends TCPDF) :
- Gère les styles typographiques (`TITRE1`, `TITRE2`, `NORMAL`, etc.)
- Implémente `writeStyled()` pour rendre le texte avec styles
- Traite les placeholders internes (`{{EAIEG_SET_DEST:...}}`, `{{EAIEG_LINK:...}}`)
- Gère la justification du texte (avec ou sans liens)
- Dessine les en-têtes, pieds de page et filigranes

**TOCManager** :
- Collecte les entrées de la table des matières
- Estime le nombre de pages nécessaires
- Réserve l'espace dans le document
- Génère la TOC complète après le contenu

---

## 2. Parsing du formatted_text

### 2.1 Fonction `parseZones()`

**Localisation** : `encyclo-ai-ebook-generator.php:2685`

Cette fonction parse le champ `formatted_text` du JSON et extrait :
- Les champs de la page de couverture
- Tous les blocs de contenu taggés
- Les entrées pour la table des matières

**Structure retournée** :

```php
[
    'titre' => string,           // Titre principal
    'sous_titre' => string,      // Sous-titre
    'langue' => string,          // Langue (Français/English)
    'corps' => [                 // Tous les blocs de contenu
        ['tag' => 'TITRE1', 'text' => '...'],
        ['tag' => 'NORMAL', 'text' => '...'],
        ...
    ],
    'toc' => [                   // Entrées TOC
        ['level' => 'TITRE1', 'title' => '...'],
        ['level' => 'TITRE2', 'title' => '...'],
        ...
    ],
    'glossaire_links' => []      // (réservé)
]
```

### 2.2 Balises de champs spéciaux

Extraites en premier pour la page de couverture :

```
[DEBUT_CHAMP_TITRE]Mon Titre[FIN_CHAMP_TITRE]
[DEBUT_CHAMP_SOUS_TITRE]Mon Sous-titre[FIN_CHAMP_SOUS_TITRE]
[DEBUT_CHAMP_LANGUE]Français[FIN_CHAMP_LANGUE]
```

### 2.3 Balises de contenu

Tags reconnus dans le corps du document :

| Tag | Usage |
|-----|-------|
| `[TITRE1]` | Titre de chapitre (niveau 1) |
| `[TITRE2]` | Titre de sous-chapitre (niveau 2) |
| `[NORMAL]` | Paragraphe de texte normal |
| `[ENTREE_BIBLIOGRAPHIE]` | Titre d'une entrée bibliographique |
| `[ENTREE_GLOSSAIRE]` | Entrée de glossaire (format : "Acronyme - Définition") |
| `[ENTREE_QUIZ]` | Question de quiz |
| `[WIKI_LIEN]` | Lien URL (format : `[WIKI_LIEN] url [WIKI_LIEN]`) |

### 2.4 Règles de lecture multi-lignes

**Algorithme** :

1. Lire la ligne contenant le tag et le texte qui suit
2. Continuer à lire les lignes suivantes **tant qu'elles ne commencent pas** par :
   - Un nouveau tag `[TAG]`
   - Un champ spécial `[DEBUT_CHAMP_...]`
   - Une ligne vide
3. Concaténer toutes les lignes avec des espaces
4. Trimmer le résultat final

**Exemple** :

```
[NORMAL] Ceci est un paragraphe
qui continue sur plusieurs lignes
jusqu'à la prochaine balise.

[TITRE1] Nouveau chapitre
```

Résultat :
```php
[
    ['tag' => 'NORMAL', 'text' => 'Ceci est un paragraphe qui continue sur plusieurs lignes jusqu'à la prochaine balise.'],
    ['tag' => 'TITRE1', 'text' => 'Nouveau chapitre']
]
```

### 2.5 Traitement spécial : WIKI_LIEN

Les balises `[WIKI_LIEN]` sont fermantes, elles doivent être retirées du texte :

```php
if ($tag === 'WIKI_LIEN' && preg_match('/^(.*?)\s*\[WIKI_LIEN\]$/', $textContent, $wiki_match)) {
    $textContent = trim($wiki_match[1]);
}
```

**Avant** :
```
[WIKI_LIEN] https://example.com [WIKI_LIEN]
```

**Après** :
```php
['tag' => 'WIKI_LIEN', 'text' => 'https://example.com']
```

---

## 3. Système de placeholders internes

### 3.1 Types de placeholders

Le système utilise des placeholders spéciaux pour gérer les liens internes et le HTML personnalisé **après le parsing initial**.

#### 3.1.1 `{{EAIEG_SET_DEST:ID}}`

**Rôle** : Créer une ancre de destination pour les liens internes.

**Format** :
```
{{EAIEG_SET_DEST:123}}
```

**Paramètre** : `123` = ID numérique du lien interne

**Action dans StyledPDF** :
```php
if (preg_match('/^{{EAIEG_SET_DEST:(\d+)}}$/', $part, $match)) {
    $this->SetLink((int) $match[1], -1, -1);
    continue;
}
```

**Usage** : Positionné juste avant la première occurrence d'un acronyme dans le texte, pour permettre le lien retour depuis le glossaire.

#### 3.1.2 `{{EAIEG_LINK:ID:Texte:Couleur}}`

**Rôle** : Créer un lien cliquable vers une destination.

**Format** :
```
{{EAIEG_LINK:123:WKL:0066CC}}
```

**Paramètres** :
- `123` = ID de la destination (créée avec `SET_DEST`)
- `WKL` = Texte affiché (label)
- `0066CC` = Couleur hexadécimale (optionnel, défaut : `#0066CC`)

**Action dans StyledPDF** :
```php
if (preg_match('/^{{EAIEG_LINK:([^}]*)}}$/', $part, $match)) {
    $payload = $match[1];
    $chunks = explode(':', $payload, 4);
    $linkId = (int) $chunks[0];
    $label = $chunks[1] ?? 'Lien';
    $colorHex = $chunks[2] ?? '0066CC';

    // Parser la couleur
    $colorOverride = $this->parseHexColor($colorHex);

    // Rendre le lien avec soulignement (sauf dans glossaire)
    $this->SetTextColorArray($colorOverride);
    $this->SetFont($currentFont, $currentStyle . 'U', $currentSize);
    $this->Write($lineHeight, $label, $linkId);

    // Restaurer le style
    $this->SetFont($currentFont, $currentStyle, $currentSize);
    $this->SetTextColorArray($style['color']);
}
```

**Style** :
- Texte **souligné** dans le contenu normal
- Texte **non souligné** dans les entrées de glossaire (lignes 240-244 de StyledPDF.php)

#### 3.1.3 `{{EAIEG_HTML:...}}`

**Rôle** : Insérer du HTML personnalisé directement dans le PDF.

**Format** :
```
{{EAIEG_HTML:<a name="back-wkl" id="back-wkl"></a>}}
```

**Action dans StyledPDF** :
```php
if (preg_match('/^{{EAIEG_HTML:(.*?)}}$/', $part, $match)) {
    $html = $this->normalizeQuotedHtml($match[1]);
    $this->writeHTML($html, false, false, false, false, '');
    continue;
}
```

**Cas vide** : `{{EAIEG_HTML:}}` est ignoré.

**Usage** : Créer des ancres HTML nommées pour améliorer la compatibilité des liens internes.

### 3.2 Traitement des placeholders

**Méthode** : `StyledPDF::writeStyled()` (ligne 88)

**Algorithme** :

1. **Séparer le texte** en segments normaux et placeholders :
   ```php
   $parts = preg_split('/({{EAIEG_(?:SET_DEST|LINK|HTML):[^}]*}})/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
   ```

2. **Pour chaque segment** :
   - Si c'est un `SET_DEST` → Créer l'ancre de destination
   - Si c'est un `LINK` → Rendre le lien cliquable
   - Si c'est un `HTML` → Rendre le HTML
   - Sinon → Accumuler dans un buffer texte

3. **Rendre le buffer texte** accumulé avec justification appropriée

4. **Nettoyage** : Supprimer les placeholders non traités

### 3.3 Gestion de la position après les liens

**Problème** : Après un lien, si on est trop proche de la marge droite, le texte suivant peut déborder.

**Solution** (lignes 253-263 de StyledPDF.php) :

```php
// Vérifier s'il reste assez d'espace sur la ligne
$margins = $this->getMargins();
$rightMargin = (float) $margins['right'];
$pageWidth = $this->getPageWidth();
$maxX = $pageWidth - $rightMargin;

// Si on est à moins de 50 points de la marge droite, passer à la ligne
if ($this->GetX() > ($maxX - 50)) {
    $this->Ln($lineHeight);
}
```

---

## 4. Système de glossaire et liens automatiques

### 4.1 Identification des acronymes

**Phase 1 : Scan du formatted_text**

Lors de la génération, on scanne tous les blocs `ENTREE_GLOSSAIRE` pour extraire les acronymes :

```php
foreach ($zones['corps'] as $entry) {
    if ($entry['tag'] === 'ENTREE_GLOSSAIRE') {
        // Format attendu : "ACRONYME - Définition complète"
        $parts = explode(' - ', $entry['text'], 2);
        $acronym = trim($parts[0]);
        $definition = $parts[1] ?? '';

        // Stocker l'acronyme
        $acronyms[] = $acronym;
    }
}
```

**Format attendu** :
```
[ENTREE_GLOSSAIRE] WKL - World Kiteboarding League
```

### 4.2 Création des ancres de liens

**Phase 2 : Génération des IDs de liens internes**

Pour chaque acronyme identifié, on crée deux liens internes :

```php
$acronymAnchors = [];

foreach ($acronyms as $acronym) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $acronym));

    // Gérer les collisions de slugs
    $glossSlug = 'gloss-' . $slug;
    $backSlug = 'back-' . $slug;
    $counter = 1;

    while (isset($usedSlugs[$glossSlug])) {
        $glossSlug = 'gloss-' . $slug . '-' . (++$counter);
        $backSlug = 'back-' . $slug . '-' . $counter;
    }

    $usedSlugs[$glossSlug] = true;
    $usedSlugs[$backSlug] = true;

    $acronymAnchors[$acronym] = [
        'gloss' => $glossSlug,           // Slug pour l'entrée glossaire
        'back' => $backSlug,             // Slug pour le retour
        'links' => [
            'gloss' => $pdf->AddLink(),  // ID numérique TCPDF
            'back' => $pdf->AddLink(),   // ID numérique TCPDF
        ],
    ];
}
```

**Résultat** :
```php
[
    'WKL' => [
        'gloss' => 'gloss-wkl',
        'back' => 'back-wkl',
        'links' => [
            'gloss' => 1,  // ID TCPDF
            'back' => 2,   // ID TCPDF
        ],
    ],
    // ...
]
```

### 4.3 Injection automatique des liens

**Phase 3 : Modifier le texte des blocs de contenu**

Pour chaque bloc de texte (sauf les entrées de glossaire), on injecte automatiquement les liens vers les acronymes :

```php
function injectGlossaryLinks($text, $acronymAnchors, &$firstOccurrenceFound) {
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

        // Créer le lien vers le glossaire
        $link = sprintf(
            '{{EAIEG_LINK:%d:%s:0066CC}}',
            $anchors['links']['gloss'],
            htmlspecialchars($acronym, ENT_QUOTES)
        );

        return $spaceBefore . $prefix . $link . $spaceAfter;
    }, $text);
}
```

**Important** : Préserver les espaces avant et après l'acronyme pour maintenir la mise en forme.

**Exemple** :

**Avant** :
```
Le kitesurf est un sport où la WKL organise des compétitions. La WKL est reconnue.
```

**Après (première occurrence de WKL)** :
```
Le kitesurf est un sport où la {{EAIEG_SET_DEST:2}}{{EAIEG_HTML:<a name="back-wkl" id="back-wkl"></a>}}{{EAIEG_LINK:1:WKL:0066CC}} organise des compétitions. La {{EAIEG_LINK:1:WKL:0066CC}} est reconnue.
```

### 4.4 Rendu des entrées de glossaire

**Phase 4 : Créer les liens retour dans le glossaire**

Lors du rendu de la section glossaire, chaque entrée est transformée :

```php
if ($entry['tag'] === 'ENTREE_GLOSSAIRE') {
    $parts = explode(' - ', $entry['text'], 2);
    $acronym = trim($parts[0]);
    $definition = $parts[1] ?? '';

    if (isset($acronymAnchors[$acronym])) {
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
                '{{EAIEG_LINK:%d:%s - %s}}',
                $anchors['links']['back'],
                htmlspecialchars($acronym, ENT_QUOTES),
                htmlspecialchars($definition, ENT_QUOTES)
            );

            $modifiedText = $destPlaceholder . $htmlPlaceholder . $linkPlaceholder;
        } else {
            // Pas de première occurrence → texte simple
            $modifiedText = $destPlaceholder . $htmlPlaceholder . htmlspecialchars($acronym . ' - ' . $definition, ENT_QUOTES);
        }

        $pdf->writeStyled('ENTREE_GLOSSAIRE', $modifiedText);
    }
}
```

**Résultat** : L'entrée de glossaire "WKL - World Kiteboarding League" devient cliquable et ramène à la première occurrence dans le texte.

### 4.5 Exclusion des injections

**Règle critique** : Ne PAS injecter de liens dans :
- Les entrées de glossaire elles-mêmes (`ENTREE_GLOSSAIRE`)
- Les paragraphes suivant une entrée de glossaire (flag `$inGlossaryParagraph`)

**Implémentation dans StyledPDF** (lignes 95-101) :

```php
// Variable d'état
private $in_glossary_paragraph = false;

// Dans writeStyled()
if ($tag === 'ENTREE_GLOSSAIRE') {
    $this->in_glossary_paragraph = true;
} elseif ($tag !== 'NORMAL') {
    $this->in_glossary_paragraph = false;
}

// Condition d'injection
$shouldInjectLinks = ($tag !== 'ENTREE_GLOSSAIRE') &&
                     !($tag === 'NORMAL' && $this->in_glossary_paragraph);
```

---

## 5. Page de mentions légales

### 5.1 Contenu

**Localisation** : Page 2 du PDF

**Texte selon la langue** :

#### Français

```
© 2025 Encyclo-AI. Tous droits réservés.

Cet ebook est protégé par le droit d'auteur et d'autres lois sur la propriété intellectuelle.
Toute reproduction, distribution, ou utilisation non autorisée de cet Ebook est strictement
interdite, sauf disposition contraire de la licence d'utilisation.

En téléchargeant ou en utilisant cet Ebook, vous acceptez d'être lié par les termes du
contrat de licence de Encyclo-AI. Pour consulter le contrat de licence complet, veuillez
visiter : https://www.encyclo-ai.com/licence-smartbook-encyclo-ai-francais/

Toute violation des termes de cette licence peut entraîner la résiliation immédiate de vos
droits d'utilisation et peut donner lieu à des poursuites judiciaires.
```

#### Anglais

```
© 2025 Encyclo-AI. All rights reserved.

This Ebook is protected by copyright and other intellectual property laws. Any unauthorized
reproduction, distribution, or use of this Ebook is strictly prohibited, except as expressly
permitted under the terms of the license agreement.

By downloading or using this Ebook, you agree to be bound by the terms of the Encyclo-AI
license agreement. To review the full license agreement, please visit:
https://www.encyclo-ai.com/licence-smartbook-encyclo-ai-anglais/

Any violation of the terms of this license may result in the immediate termination of your
rights to use this Ebook and may lead to legal action.
```

### 5.2 Date de création

**Format** : `Smartbook Créé / Generated : {date}`

**Source** : Champ `created_at` du JSON

**Traitement** :

```php
$createdAt = $data['created_at'] ?? null;
$date = null;

// Essayer de parser la date MongoDB ou timestamp
if ($createdAt) {
    if (is_array($createdAt) && isset($createdAt['$date'])) {
        // Format MongoDB : {"$date": 1698765432000}
        $timestamp = $createdAt['$date'] / 1000;
        $date = date('j F Y', $timestamp);
    } elseif (is_numeric($createdAt)) {
        $date = date('j F Y', $createdAt);
    } elseif (is_string($createdAt)) {
        $timestamp = strtotime($createdAt);
        if ($timestamp) {
            $date = date('j F Y', $timestamp);
        }
    }
}

if (!$date) {
    $date = date('j F Y'); // Date actuelle par défaut
}

// Traduire le mois si français
if ($language === 'Français') {
    $months_fr = [
        'January' => 'janvier', 'February' => 'février', 'March' => 'mars',
        'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
        'July' => 'juillet', 'August' => 'août', 'September' => 'septembre',
        'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre'
    ];
    $date = str_replace(array_keys($months_fr), array_values($months_fr), $date);
}
```

**Exemples** :
- Français : `Smartbook Créé : 21 octobre 2025`
- Anglais : `Generated : 21 October 2025`

### 5.3 Mise en page

**Position Y** : 420px

**Éléments** :

1. Texte de licence (justifié, police quicksand 10pt, couleur [50,50,50])
2. Ligne de séparation noire (3px d'épaisseur)
   - Coordonnées : de (90, 745) à (540, 745)
3. Logo Smartbook (position : (441, 745), taille : 100×62px)
4. URL : `www.encyclo-ai.com`

**Espacement** : 10px entre chaque section de texte

---

## 6. Table des matières (TOC)

### 6.1 Architecture du TOCManager

**Classe** : `TOCManager` (fichier `class/TOCManager.php`)

**Responsabilités** :
1. Collecter les entrées de TOC pendant la génération du contenu
2. Estimer le nombre de pages nécessaires
3. Réserver l'espace dans le document
4. Générer la TOC complète après tout le contenu

### 6.2 Réservation d'espace

**Méthode** : `TOCManager::reserveTOCSpace()`

**Algorithme** :

1. **Mémoriser la page actuelle** :
   ```php
   $this->toc_start_page = $this->pdf->getPage();
   ```

2. **Estimer le nombre de pages nécessaires** (voir section 6.3)

3. **Ajouter des pages vides** :
   ```php
   for ($i = 0; $i < $estimated_pages; $i++) {
       $this->pdf->AddPage();
   }
   ```

4. **Retourner le numéro de page de début TOC**

**Important** : Cette réservation se fait AVANT la génération du contenu, mais la TOC sera remplie APRÈS (car on a besoin des numéros de page finaux).

### 6.3 Estimation du nombre de pages

**Méthode** : `TOCManager::estimateTOCPages()`

**Algorithme** (lignes 114-149) :

```php
private function estimateTOCPages() {
    // Hauteur de page - marges haut/bas
    $pageHeight = $this->pdf->getPageHeight();
    $linesPerPage = max(1, floor(($pageHeight - 60) / $this->line_height));

    // Titre + espace initial
    $totalLines = 5;

    foreach ($this->toc_links as $entry) {
        // Déterminer le style selon le niveau
        if ($entry['level'] === 'TITRE1') {
            $fontSize = 12;
            $fontStyle = 'B';
            $indent = 0;
        } else {
            $fontSize = 10;
            $fontStyle = '';
            $indent = 15;
        }

        // Calculer la largeur disponible pour le titre
        $pageWidth = $this->pdf->getPageWidth();
        $usableWidth = $pageWidth - $this->margin_left - $this->margin_right;
        $pageNumWidth = 15;
        $dotsWidth = 20;
        $availableWidth = max(10, $usableWidth - $indent - $pageNumWidth - $dotsWidth);

        // Wrapper le titre
        $wrappedLines = $this->wrapTextToWidth($entry['title'], $availableWidth, $fontSize, $fontStyle);
        if (empty($wrappedLines)) {
            $wrappedLines = [''];
        }

        // Compter les lignes + espace entre entrées
        $totalLines += count($wrappedLines) + 1;
    }

    return max(1, (int) ceil($totalLines / $linesPerPage));
}
```

**Paramètres** :
- `line_height` : 15px
- Marges : haut/bas = 60px total
- Espace après chaque entrée : 1 ligne
- Espace supplémentaire pour TITRE1 : +2px

### 6.4 Collecte des entrées

**Méthode** : `TOCManager::addTOCEntry()`

Appelée pendant la génération du contenu, pour chaque TITRE1 ou TITRE2 :

```php
if ($tag === 'TITRE1' || $tag === 'TITRE2') {
    $currentPage = $pdf->getPage();
    $link = $pdf->AddLink();  // Créer un lien interne
    $pdf->SetLink($link, -1, -1);  // Définir la destination ici

    $tocManager->addTOCEntry($text, $tag, $currentPage, $link);
}
```

**Structure d'une entrée** :

```php
[
    'title' => 'Titre nettoyé',  // Sans placeholders
    'level' => 'TITRE1',         // ou 'TITRE2'
    'page' => 5,                 // Numéro de page
    'link' => 3,                 // ID du lien interne
]
```

### 6.5 Nettoyage des titres pour TOC

**Méthode** : `TOCManager::sanitizeTitleForTOC()` (lignes 40-87)

**Opérations** :

1. **Supprimer tous les placeholders** :
   ```php
   $value = preg_replace('/\{\{EAIEG_[^}]+\}\}/u', '', $value);
   ```

2. **Extraire le texte des liens** :
   ```php
   // {{EAIEG_LINK:123:Texte:color}} → "Texte"
   if (preg_match('/^\{\{EAIEG_LINK:(.*?)\}\}$/u', $part, $match)) {
       $payload = $match[1];
       $chunks = explode(':', $payload, 4);
       $label = $chunks[1] ?? '';
       $buffer .= $label;
   }
   ```

3. **Retirer les balises HTML** :
   ```php
   $buffer = strip_tags($buffer);
   ```

4. **Normaliser les espaces** :
   ```php
   $buffer = preg_replace('/\s+/u', ' ', $buffer);
   ```

5. **Trimmer le résultat**

### 6.6 Génération de la TOC

**Méthode** : `TOCManager::generateTOC()` (lignes 154-194)

**Algorithme** :

1. **Revenir à la page de début TOC** :
   ```php
   $this->pdf->setPage($this->toc_start_page);
   ```

2. **Désactiver les sauts automatiques** (pour éviter d'ajouter des pages en fin de document) :
   ```php
   $autoBreakEnabled = $this->pdf->getAutoPageBreak();
   $previousBreakMargin = $this->pdf->getBreakMargin();
   $this->pdf->SetAutoPageBreak(false, 0);
   ```

3. **Rendre le titre** :
   ```php
   $this->pdf->SetY(50);
   $this->pdf->writeStyled('TITRE1', 'TABLE DES MATIÈRES');
   $this->pdf->Ln(26);
   ```

4. **Pour chaque entrée** :
   - Calculer la hauteur nécessaire
   - Vérifier s'il reste de la place sur la page
   - Si non → Ajouter une nouvelle page
   - Rendre l'entrée

5. **Restaurer les sauts automatiques** :
   ```php
   $this->pdf->SetAutoPageBreak($autoBreakEnabled, $previousBreakMargin);
   ```

### 6.7 Rendu d'une ligne TOC

**Méthode** : `TOCManager::renderTOCLine()` (lignes 199-272)

**Structure d'une ligne** :

```
[Titre du chapitre...................]123
```

**Éléments** :
- Titre (peut s'étendre sur plusieurs lignes)
- Points de guidage (`.........`)
- Numéro de page (aligné à droite, cliquable)

**Styles selon le niveau** :

| Niveau | Police | Taille | Style | Couleur | Indentation |
|--------|--------|--------|-------|---------|-------------|
| TITRE1 | quicksand | 12pt | Bold | [0,0,0] | 0px |
| TITRE2 | quicksand | 10pt | Regular | [64,64,64] | 15px |

**Algorithme** :

1. **Wrapper le titre** selon la largeur disponible :
   ```php
   $pageWidth = $this->pdf->getPageWidth();
   $usableWidth = $pageWidth - $this->margin_left - $this->margin_right;
   $pageNumWidth = 15;
   $dotsWidth = 20;
   $availableWidth = $usableWidth - $indent - $pageNumWidth - $dotsWidth;

   $lines = $this->wrapTextToWidth($entry['title'], $availableWidth, $fontSize, $fontStyle);
   ```

2. **Rendre la première ligne** avec points et numéro de page :
   ```php
   $firstLine = array_shift($lines);
   $firstLineWidth = $this->pdf->GetStringWidth($firstLine);

   // Rendre le titre dans une cellule
   $this->pdf->Cell($availableWidth, $this->line_height, $firstLine, 0, 0, 'L', false, $entry['link']);

   // Calculer et dessiner les points
   $dotsX = $startX + $firstLineWidth + 2;
   $dotsSpace = $pageX - $dotsX - 5;
   $dotWidth = $this->pdf->GetStringWidth('.');
   $numDots = floor($dotsSpace / $dotWidth);
   $dots = str_repeat('.', max(3, $numDots));

   $this->pdf->SetXY($dotsX, $startY);
   $this->pdf->SetTextColor(128, 128, 128); // Gris
   $this->pdf->Write($this->line_height, $dots);

   // Numéro de page (aligné à droite)
   $this->pdf->SetXY($pageX - 10, $startY);
   $this->pdf->SetTextColor($color[0], $color[1], $color[2]);
   $this->pdf->Write($this->line_height, $entry['page']);
   ```

3. **Rendre les lignes supplémentaires** (si titre long) :
   ```php
   foreach ($lines as $continuation) {
       $this->pdf->SetX($startX);
       $this->pdf->Cell($availableWidth, $this->line_height, $continuation, 0, 0, 'L');
       $this->pdf->Ln($this->line_height);
   }
   ```

4. **Espacement** :
   - 2px après chaque entrée
   - +2px après les TITRE1

### 6.8 Wrapping de texte pour TOC

**Méthode** : `TOCManager::wrapTextToWidth()` (lignes 329-363)

**Algorithme** :

1. Séparer le texte en segments (split sur espaces) :
   ```php
   $segments = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
   ```

2. Pour chaque segment :
   - Essayer de l'ajouter à la ligne actuelle
   - Si ça dépasse la largeur max → Finir la ligne actuelle, commencer une nouvelle
   - Si un segment seul est trop long → Le découper caractère par caractère

3. Retourner un tableau de lignes

**Gestion des segments longs** : `TOCManager::splitLongSegment()` (lignes 368-395)

---

## 7. Génération du contenu principal

### 7.1 Ordre des sections

1. Introduction (chapitre numéro `0`, si présent)
2. Chapitres principaux (chapitres numérotés `1`, `2`, `3`, etc.)
3. Conclusion (chapitre numéro `-1`, si présent)
4. Glossaire
5. Quiz
6. Bibliographie (Wikipedia, Sites Web)
7. Suggestions

### 7.2 Traitement des blocs

**Boucle principale** (dans `generate_ebook_pdf()`) :

```php
$isQuizRow = false;
$firstOccurrenceFound = [];

foreach ($zones['corps'] as $entry) {
    $tag = $entry['tag'];
    $text = $entry['text'];

    // === Détection des lignes de quiz ===
    if ($tag === 'ENTREE_QUIZ') {
        $isQuizRow = true;
    }

    if ($isQuizRow && $tag === 'NORMAL') {
        $tag = 'ENTREE_QUIZ_ROW';
    } elseif ($isQuizRow && $tag !== 'ENTREE_QUIZ') {
        $isQuizRow = false;
    }

    // === Injection de liens glossaire ===
    $shouldInjectLinks = ($tag !== 'ENTREE_GLOSSAIRE') &&
                         !($tag === 'NORMAL' && $inGlossaryParagraph);

    if ($shouldInjectLinks && !empty($acronymAnchors)) {
        $text = injectGlossaryLinks($text, $acronymAnchors, $firstOccurrenceFound);
    }

    // === Nouvelle page pour TITRE1 ===
    if ($tag === 'TITRE1') {
        $pdf->AddPage();
    }

    // === Ajout à la TOC ===
    if ($tag === 'TITRE1' || $tag === 'TITRE2') {
        $currentPage = $pdf->getPage();
        $link = $pdf->AddLink();
        $pdf->SetLink($link, -1, -1);
        $tocManager->addTOCEntry($text, $tag, $currentPage, $link);
    }

    // === Rendu du bloc ===
    $pdf->writeStyled($tag, $text);
}
```

### 7.3 Règles spéciales

#### TITRE1 : Nouvelle page

Chaque TITRE1 déclenche un saut de page :

```php
if ($tag === 'TITRE1') {
    $pdf->AddPage();
}
```

**Exception** : Le premier TITRE1 après la TOC ne nécessite pas de nouvelle page (on est déjà sur une page vierge).

#### TITRE2 : Pas de saut de page

Les TITRE2 s'affichent à la suite du contenu précédent.

#### Quiz : Conversion NORMAL → ENTREE_QUIZ_ROW

**Règle** : Après un tag `ENTREE_QUIZ`, tous les tags `NORMAL` suivants deviennent `ENTREE_QUIZ_ROW` jusqu'à rencontrer un autre type de tag.

**Raison** : Les réponses de quiz (lignes a/b/c/d) doivent avoir un espacement réduit.

**Détection des réponses** (dans StyledPDF, lignes 129-139) :

```php
if ($tag === 'ENTREE_QUIZ') {
    $isQuizAnswer = preg_match('/^\s*[a-d]\s*[-–]\s/i', $text) === 1;

    if ($isQuizAnswer) {
        // Réduire l'espacement
        $style['margin_top'] = 4;
        $style['margin_bottom'] = 6;
    }
}
```

**Regex** : `/^\s*[a-d]\s*[-–]\s/i`

**Exemples détectés** :
```
a – Réponse A
b - Réponse B
c  –  Réponse C
```

### 7.4 Gestion de l'état glossaire

**Variable** : `$inGlossaryParagraph` (dans StyledPDF)

**Règle** (lignes 95-101) :

```php
if ($tag === 'ENTREE_GLOSSAIRE') {
    $this->in_glossary_paragraph = true;
} elseif ($tag !== 'NORMAL') {
    $this->in_glossary_paragraph = false;
}
```

**Impact** :
1. Pas d'injection de liens dans les paragraphes de glossaire
2. Justification forcée de toutes les lignes (y compris la dernière)

---

## 8. Sections finales

### 8.1 Section Glossaire

**Titre** :
```php
$pdf->writeStyled('TITRE1', 'GLOSSAIRE');
```

**Génération des entrées** :

1. **Trier les acronymes** par ordre alphabétique :
   ```php
   $sortedAcronyms = $data['acronyms']['acronymes'] ?? [];
   usort($sortedAcronyms, function($a, $b) {
       return strcasecmp($a['acronym'], $b['acronym']);
   });
   ```

2. **Pour chaque acronyme** :
   ```php
   foreach ($sortedAcronyms as $item) {
       $acronym = $item['acronym'];
       $definition = $item['definition'];
       $detail = $item['detail'] ?? '';

       // Créer l'entrée avec liens (voir section 4.4)
       $titleText = createGlossaryEntry($acronym, $definition, $acronymAnchors, $firstOccurrenceFound);
       $pdf->writeStyled('ENTREE_GLOSSAIRE', $titleText);

       // Paragraphe de détail
       if ($detail) {
           $pdf->writeStyled('NORMAL', $detail);
       }
   }
   ```

**Justification spéciale** (lignes 103-127 de StyledPDF) :

Dans les paragraphes de glossaire, on force la justification de **toutes** les lignes (y compris la dernière) :

```php
if ($tag === 'NORMAL' && $this->in_glossary_paragraph) {
    // Nettoyer les placeholders
    $cleanText = preg_replace('/\{\{EAIEG_SET_DEST:[^}]+\}\}/', '', $text);
    $cleanText = preg_replace('/\{\{EAIEG_HTML:.*?\}\}/', '', $cleanText);

    // Créer le HTML justifié
    $htmlText = '<p style="text-align:justify; margin:0; padding:0;">' .
                htmlspecialchars($cleanText, ENT_QUOTES, 'UTF-8') .
                '</p>';

    // Appliquer les marges
    if (isset($style['margin_top'])) {
        $this->Ln($style['margin_top']);
    }

    // Rendu HTML justifié
    $this->SetFont($style['font'], $style['style'], $style['size']);
    $this->SetTextColorArray($style['color']);
    $this->writeHTML($htmlText, false, false, false, false, '');

    if (isset($style['margin_bottom'])) {
        $this->Ln($style['margin_bottom']);
    }

    return; // Sortir de writeStyled()
}
```

### 8.2 Section Quiz

**Titre et introduction** :

```php
$pdf->writeStyled('TITRE1', 'QUIZ');
$pdf->writeStyled('NORMAL', 'Pour vérifier les connaissances acquises, nous vous proposons le quiz suivant.');
```

**Questions** : Extraites du `formatted_text`, déjà parsées dans `$zones['corps']`.

**Réponses** : À la fin du quiz, on affiche les réponses correctes.

**Extraction des réponses** :

```php
// Parser le formatted_text pour trouver la ligne des réponses
// Format attendu : "REPONSES" suivi de "1-a, 2-c, 3-d, ..."
$lines = explode("\n", $formattedText);
$answersLine = '';

foreach ($lines as $line) {
    if (stripos($line, 'REPONSES') !== false) {
        $nextLine = next($lines);
        if ($nextLine && preg_match('/^\d+-[a-d]/', $nextLine)) {
            $answersLine = $nextLine;
            break;
        }
    }
}

if ($answersLine) {
    $pdf->writeStyled('NORMAL', '');
    $pdf->writeStyled('NORMAL', 'REPONSES');
    $pdf->writeStyled('NORMAL', $answersLine);
}
```

**Exemple** :
```
REPONSES
1-a, 2-c, 3-d, 4-a, 5-c, 6-c, 7-b, 8-a, 9-a, 10-b
```

### 8.3 Section Bibliographie - Wikipedia

**Titre** :

```php
$pdf->writeStyled('TITRE1', 'WIKIPEDIA');
$pdf->writeStyled('NORMAL',
    'Pour approfondir le sujet, nous vous suggérons les liens vers les pages Wikipédia suivantes. ' .
    'Les pages référencées sont en anglais. Vous pourrez ensuite accéder à la page dans la langue de votre choix.'
);
```

**Entrées** :

```php
$wikipedias = $data['bibliography']['wikipedias'] ?? [];

foreach ($wikipedias as $wiki) {
    $title = $wiki['title'] ?? '';
    $description = $wiki['description'] ?? '';
    $link = $wiki['link'] ?? '';

    if ($title) {
        $pdf->writeStyled('ENTREE_BIBLIOGRAPHIE', $title);
    }

    if ($description) {
        $pdf->writeStyled('NORMAL', $description);
    }

    if ($link) {
        $pdf->writeStyled('WIKI_LIEN', $link);
    }
}
```

**Style WIKI_LIEN** :
- Police : quicksand Italic 10pt
- Couleur : [0, 0, 255] (bleu)
- Alignement : Left
- Marges : top=1px, bottom=3px

### 8.4 Section Bibliographie - Sites Web

**Titre** :

```php
$pdf->writeStyled('TITRE1', 'SITES WEB');
$pdf->writeStyled('NORMAL',
    'Pour approfondir le sujet, nous vous suggérons de consulter les sites Web suivants.'
);
```

**Entrées** :

```php
$websites = $data['bibliography']['websites_nocommercial'] ?? [];

foreach ($websites as $site) {
    $name = $site['website_name'] ?? '';
    $description = $site['website_description'] ?? '';
    $url = $site['website_url'] ?? '';

    if ($name) {
        $pdf->writeStyled('ENTREE_BIBLIOGRAPHIE', $name);
    }

    if ($description) {
        $pdf->writeStyled('NORMAL', $description);
    }

    if ($url) {
        $pdf->writeStyled('WIKI_LIEN', $url);
    }
}
```

### 8.5 Section Suggestions

**Titre** :

```php
$pdf->writeStyled('TITRE1', 'SUGGESTIONS');
$pdf->writeStyled('NORMAL',
    'Pour approfondir le sujet, nous vous suggérons d\'utiliser Encyclo-AI pour créer les ' .
    'Smartbooks suivants. Le titre et la synthèse proposés pourront être utilisés pour configurer ' .
    'la génération d\'un nouveau Smartbook par Encyclo-AI.'
);
```

**Entrées** : Extraites du `formatted_text` après la section "SUGGESTIONS".

**Format** :
```php
// Les suggestions sont déjà dans $zones['corps']
// Format : ENTREE_BIBLIOGRAPHIE + NORMAL
foreach ($suggestionsBlocs as $bloc) {
    $pdf->writeStyled($bloc['tag'], $bloc['text']);
}
```

---

## 9. Gestion de la justification du texte

### 9.1 Deux méthodes de justification

#### Méthode 1 : Justification manuelle (texte simple)

**Usage** : Texte **sans placeholders** de liens.

**Implémentation** : `StyledPDF::renderPlainBuffer()` (lignes 302-344)

**Algorithme** :

1. **Wrapper le texte** en lignes qui tiennent dans la largeur disponible
2. **Pour chaque ligne** :
   - Si c'est la dernière ligne → Alignement `'L'` (gauche)
   - Sinon → Alignement `'J'` (justifié)
3. **Rendre avec `Cell()`** :
   ```php
   foreach ($wrapped as $i => $line) {
       $alignCode = ($i === $lastIndex) ? 'L' : 'J';
       $this->Cell($usableWidth, $lineHeight, $line, 0, 0, $alignCode);
       $this->Ln($lineHeight);
   }
   ```

**Avantage** : Contrôle précis, dernière ligne non étirée.

**Inconvénient** : Ne fonctionne pas avec les liens internes (car `Cell()` n'accepte qu'un seul lien).

#### Méthode 2 : Justification HTML (texte avec liens)

**Usage** : Texte **avec placeholders** de liens.

**Implémentation** : `StyledPDF::renderTextWithLinksJustified()` (lignes 445-520)

**Algorithme** :

1. **Traiter les placeholders SET_DEST** :
   ```php
   $text = preg_replace_callback('/{{EAIEG_SET_DEST:(\d+)}}/', function($match) {
       $this->SetLink((int) $match[1], -1, -1);
       return '';
   }, $text);
   ```

2. **Convertir les LINK en balises HTML** :
   ```php
   $text = preg_replace_callback('/{{EAIEG_LINK:([^}]*)}}/', function($match) {
       $payload = $match[1];
       $chunks = explode(':', $payload, 4);
       $linkId = (int) $chunks[0];
       $label = $chunks[1] ?? 'Lien';
       $colorHex = $chunks[2] ?? '0066CC';

       $colorArray = $this->parseHexColor($colorHex);
       $colorCSS = sprintf('rgb(%d,%d,%d)', $colorArray[0], $colorArray[1], $colorArray[2]);

       $labelEscaped = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

       // Format TCPDF : @linkID pour lien interne
       return '<a href="@' . $linkId . '" style="color:' . $colorCSS . '; text-decoration:underline;">' .
              $labelEscaped . '</a>';
   }, $text);
   ```

3. **Échapper le reste du texte** (sauf les balises HTML) :
   ```php
   $parts = preg_split('/(<a[^>]*>.*?<\/a>|<[^>]+>)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
   $escapedText = '';

   foreach ($parts as $part) {
       if (preg_match('/^<[^>]+>/', $part)) {
           $escapedText .= $part; // Garder les balises
       } else {
           $escapedText .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
       }
   }
   ```

4. **Construire le HTML justifié** :
   ```php
   $html = '<p style="text-align:justify; margin:0; padding:0; ' .
           'font-size:' . $fontSize . 'pt; ' .
           'font-family:' . $fontFamily . '; ' .
           'color:rgb(' . implode(',', $color) . '); ' .
           'line-height:' . $lineHeightRatio . ';">' .
           $escapedText .
           '</p>';
   ```

5. **Rendre avec `writeHTMLCell()`** :
   ```php
   $margins = $this->getMargins();
   $leftMargin = (float) $margins['left'];
   $rightMargin = (float) $margins['right'];
   $w = $this->getPageWidth() - $leftMargin - $rightMargin;
   $currentY = $this->GetY();

   $this->writeHTMLCell($w, 0, $leftMargin, $currentY, $html, 0, 1, false, true, 'J', true);
   ```

**Avantage** : Supporte les liens cliquables ET la justification.

**Inconvénient** : La dernière ligne est aussi justifiée (étirement des espaces).

### 9.2 Cas spécial : Glossaire

**Problème** : Dans le glossaire, on veut que **toutes** les lignes soient justifiées (y compris la dernière).

**Solution** : Utiliser la méthode HTML pour forcer la justification complète.

**Implémentation** (lignes 103-127 de StyledPDF) :

```php
if ($tag === 'NORMAL' && $this->in_glossary_paragraph) {
    // Nettoyer les placeholders
    $cleanText = preg_replace('/\{\{EAIEG_SET_DEST:[^}]+\}\}/', '', $text);
    $cleanText = preg_replace('/\{\{EAIEG_HTML:.*?\}\}/', '', $cleanText);

    // HTML justifié
    $htmlText = '<p style="text-align:justify; margin:0; padding:0;">' .
                htmlspecialchars($cleanText, ENT_QUOTES, 'UTF-8') .
                '</p>';

    // Appliquer marges et style
    if (isset($style['margin_top'])) {
        $this->Ln($style['margin_top']);
    }

    $this->SetFont($style['font'], $style['style'], $style['size']);
    $this->SetTextColorArray($style['color']);

    $this->writeHTML($htmlText, false, false, false, false, '');

    if (isset($style['margin_bottom'])) {
        $this->Ln($style['margin_bottom']);
    }

    return; // Sortir de writeStyled()
}
```

### 9.3 Wrapping de texte

**Méthode** : `StyledPDF::wrapPlainTextToWidth()` (lignes 346-375)

**Algorithme** :

1. **Séparer sur les espaces** :
   ```php
   $segments = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
   ```

2. **Pour chaque segment** :
   - Essayer de l'ajouter à la ligne actuelle
   - Mesurer la largeur avec `GetStringWidth()`
   - Si ça dépasse → Finir la ligne, commencer une nouvelle
   - Si un mot seul est trop long → Le découper caractère par caractère

3. **Retourner** un tableau de lignes

**Gestion des mots longs** : `StyledPDF::splitLongPlainSegment()` (lignes 377-399)

```php
private function splitLongPlainSegment(string $segment, float $maxWidth): array {
    $length = mb_strlen($segment, 'UTF-8');
    $line = '';
    $splitIndex = 0;

    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($segment, $i, 1, 'UTF-8');
        $candidate = $line . $char;

        if ($line !== '' && $this->GetStringWidth($candidate) > $maxWidth) {
            break;
        }

        $line = $candidate;
        $splitIndex = $i + 1;
    }

    if ($line === '') {
        // Au moins un caractère
        $line = mb_substr($segment, 0, 1, 'UTF-8');
        $splitIndex = 1;
    }

    $remaining = mb_substr($segment, $splitIndex, null, 'UTF-8');
    return [$line, $remaining];
}
```

---

## 10. En-têtes, pieds de page et filigranes

### 10.1 En-tête

**Méthode** : `StyledPDF::Header()` (lignes 46-64)

**Éléments** :

1. **Image dégradé gauche** (toutes les pages) :
   ```php
   // Fichier : assets/smartbook/degrade.jpg
   // Position : (0, 0)
   // Taille : 35px × hauteur page
   // DPI : 300

   $this->SetMargins(0, 0, 0);
   $this->SetAutoPageBreak(false);
   $this->Image(
       plugin_dir_path(__FILE__) . '../assets/smartbook/degrade.jpg',
       0, 0, 35, $this->getPageHeight(),
       '', '', '', false, 300
   );
   $this->SetMargins($margins['left'], $margins['top'], $margins['right']);
   $this->SetAutoPageBreak(true, 30);
   ```

2. **Filigrane** (pages 2+) :
   ```php
   if ($this->getPage() > 1) {
       $this->addWatermark();
   }
   ```

### 10.2 Filigrane

**Méthode** : `StyledPDF::addWatermark()` (lignes 79-86)

**Calcul de position** :

```php
private function addWatermark() {
    $pageWidth = $this->getPageWidth();
    $pageHeight = $this->getPageHeight();

    // Taille : 60% de la plus petite dimension
    $size = min($pageWidth, $pageHeight) * 0.6;

    // Centrer
    $x = ($pageWidth - $size) / 2;
    $y = ($pageHeight - $size) / 2;

    // Fichier : assets/smartbook/filigrane.png
    $this->Image(
        plugin_dir_path(__FILE__) . '../assets/smartbook/filigrane.png',
        $x, $y, $size, $size
    );
}
```

**Transparence** : Gérée via le canal alpha du fichier PNG.

### 10.3 Pied de page

**Méthode** : `StyledPDF::Footer()` (lignes 66-77)

**Règle** : Afficher le numéro de page **uniquement après la fin de la TOC**.

```php
public function Footer() {
    $pageNum = $this->getPage();

    // Ne pas afficher sur page 1 (couverture) ni sur les pages TOC
    if ($pageNum > $this->toc_manager->getTocEndPage()) {
        $this->SetY(-35); // 35px du bas
        $this->SetFont('quicksand', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, $pageNum, 0, 0, 'R'); // Aligné à droite
    }
}
```

**Méthode `getTocEndPage()`** :

```php
public function getTocEndPage() {
    return $this->toc_start_page + $this->estimateTOCPages();
}
```

**Exemple** :
- TOC commence page 3
- TOC estimée à 2 pages
- `getTocEndPage()` retourne 5 (3 + 2)
- Les numéros de page s'affichent à partir de la page 6

---

## 11. Architecture technique (TCPDF)

### 11.1 Configuration PDF

**Initialisation** :

```php
$pdf = new StyledPDF();
$pdf->SetCreator('Encyclo-AI');
$pdf->SetAuthor('Encyclo-AI');
$pdf->SetTitle($title);
$pdf->SetSubject($subtitle);
$pdf->SetKeywords(implode(', ', $keywords));

// Marges
$pdf->SetMargins(90, 50, 50); // Gauche, Haut, Droite
$pdf->SetAutoPageBreak(true, 30); // Marge bottom

// Police par défaut
$pdf->SetFont('quicksand', '', 10);

// Interligne global (pour HTML)
$pdf->setCellHeightRatio(1.30); // 130%
```

### 11.2 Polices

**Famille** : Quicksand

**Styles disponibles** :
- `''` (Regular)
- `'B'` (Bold)
- `'I'` (Italic, si disponible)
- `'U'` (Underline - modificateur)

**Font Subsetting** : Désactivé

```php
$this->setFontSubsetting(false);
```

**Raison** : Évite les problèmes de compatibilité avec les liens internes et le rendu multi-fichiers.

### 11.3 Styles typographiques

**Définition** : Tableau `StyledPDF::$styles` (lignes 7-20)

**Exemple** :

```php
'NORMAL' => [
    'font' => 'quicksand',
    'style' => '',
    'size' => 10,
    'color' => [50, 50, 50],
    'align' => 'J',             // Justifié
    'margin_top' => 10,
    'margin_bottom' => 10,
    'line_height' => 13,
],
```

**Tags disponibles** : Voir [ebook.md section 3.1](ebook.md#31-définition-des-styles)

### 11.4 Liens internes TCPDF

**Création d'un lien** :

```php
$linkId = $pdf->AddLink(); // Retourne un ID numérique (ex: 1, 2, 3...)
```

**Définir une destination** :

```php
$pdf->SetLink($linkId, $yPosition, $pageNumber);

// Pour la page actuelle, position actuelle :
$pdf->SetLink($linkId, -1, -1);
```

**Utiliser le lien** :

Méthode 1 (texte simple) :
```php
$pdf->Write($lineHeight, $text, $linkId);
```

Méthode 2 (HTML) :
```php
$html = '<a href="@' . $linkId . '">Texte du lien</a>';
$pdf->writeHTML($html);
```

**Format** : `@{linkId}` pour les liens internes (ex: `@1`, `@2`)

### 11.5 Gestion des pages

**Ajouter une page** :

```php
$pdf->AddPage($orientation, $format, $keepmargins, $tocpage);
```

**Aller à une page spécifique** :

```php
$pdf->setPage($pageNumber);
```

**Obtenir la page actuelle** :

```php
$currentPage = $pdf->getPage();
```

**Note** : Utilisé pour la génération de la TOC (retour en arrière).

### 11.6 Position et dimensions

**Obtenir la position** :

```php
$x = $pdf->GetX();
$y = $pdf->GetY();
```

**Définir la position** :

```php
$pdf->SetX($x);
$pdf->SetY($y);
$pdf->SetXY($x, $y);
```

**Dimensions de page** :

```php
$width = $pdf->getPageWidth();
$height = $pdf->getPageHeight();
```

**Marges** :

```php
$margins = $pdf->getMargins();
// Retourne : ['left' => 90, 'top' => 50, 'right' => 50]
```

### 11.7 Mesure de texte

**Largeur d'une chaîne** :

```php
$width = $pdf->GetStringWidth($text);
```

**Important** : Dépend de la police, du style et de la taille actuels.

```php
$pdf->SetFont('quicksand', 'B', 12);
$width = $pdf->GetStringWidth('Mon texte');
```

---

## 12. Flux complet de génération

### 12.1 Diagramme de flux

```
┌─────────────────────────────────────┐
│  Charger données JSON               │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  Parser formatted_text              │
│  → Extraction zones (titre, corps)  │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  Scanner ENTREE_GLOSSAIRE           │
│  → Identifier tous les acronymes    │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  Créer ancres de liens              │
│  → AddLink() pour chaque acronyme   │
│    (gloss + back)                   │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  Initialiser PDF                    │
│  → new StyledPDF()                  │
│  → Configurer marges, polices       │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  PAGE 1: Couverture                 │
│  → addCover() [voir ebook.md]       │
│  → Titre, sous-titre                │
│  → Catégorie, logo                  │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  PAGE 2: Mentions légales           │
│  → Texte français/anglais           │
│  → Date de création                 │
│  → Ligne + logo                     │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  Réserver espace TOC                │
│  → estimateTOCPages()               │
│  → AddPage() × N                    │
│  → Mémoriser toc_start_page         │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  CONTENU PRINCIPAL                  │
│  Pour chaque bloc dans corps:       │
│  ├─ Si TITRE1 → AddPage()           │
│  ├─ Si TITRE1/2 → addTOCEntry()     │
│  ├─ Injecter liens glossaire        │
│  ├─ Détecter quiz rows              │
│  └─ writeStyled(tag, text)          │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  SECTION GLOSSAIRE                  │
│  → Trier acronymes (alphabétique)   │
│  → Pour chaque: ENTREE_GLOSSAIRE    │
│    + paragraphe NORMAL              │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  SECTION QUIZ                       │
│  → Questions (déjà dans corps)      │
│  → Réponses (extraites)             │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  BIBLIOGRAPHIE                      │
│  → Wikipedia (titre + lien)         │
│  → Sites Web (nom + URL)            │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  SUGGESTIONS                        │
│  → Titres + descriptions            │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  GÉNÉRATION TOC                     │
│  → setPage(toc_start_page)          │
│  → SetAutoPageBreak(false)          │
│  → Rendre titre                     │
│  → Pour chaque entrée TOC:          │
│    ├─ Wrapper titre                 │
│    ├─ Rendre ligne + points + page  │
│    └─ Gérer sauts de page           │
│  → SetAutoPageBreak(true)           │
└─────────────┬───────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  SAUVEGARDE PDF                     │
│  → Output($filename, 'F')           │
└─────────────────────────────────────┘
```

### 12.2 Ordre d'exécution détaillé

**Étape 1 : Préparation des données**

```php
// Charger le JSON
$data = json_decode($row->result, true);

// Parser le formatted_text
$zones = parseZones($data['formatted_text']);

// Identifier les acronymes
$acronyms = [];
foreach ($zones['corps'] as $entry) {
    if ($entry['tag'] === 'ENTREE_GLOSSAIRE') {
        $parts = explode(' - ', $entry['text'], 2);
        $acronyms[] = trim($parts[0]);
    }
}

// Créer les ancres
$acronymAnchors = [];
$usedSlugs = [];

foreach ($acronyms as $acronym) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $acronym));
    $glossSlug = 'gloss-' . $slug;
    $backSlug = 'back-' . $slug;

    // Gérer collisions...

    $acronymAnchors[$acronym] = [
        'gloss' => $glossSlug,
        'back' => $backSlug,
        'links' => [
            'gloss' => $pdf->AddLink(),
            'back' => $pdf->AddLink(),
        ],
    ];
}

$firstOccurrenceFound = [];
```

**Étape 2 : Initialisation PDF**

```php
$pdf = new StyledPDF();
$tocManager = $pdf->getTocManager();

// Configuration...
$pdf->SetMargins(90, 50, 50);
$pdf->SetAutoPageBreak(true, 30);
$pdf->SetFont('quicksand', '', 10);
```

**Étape 3 : Page de couverture**

```php
$pdf->AddPage();
addCover($pdf, $smartbook_id);
// ... (voir ebook.md)
```

**Étape 4 : Page de mentions légales**

```php
$pdf->AddPage();
// Rendre texte de licence...
// Rendre date...
// Rendre logo...
```

**Étape 5 : Réservation TOC**

```php
$tocManager->reserveTOCSpace();
// → Ajoute N pages vides
```

**Étape 6 : Contenu principal**

```php
$isQuizRow = false;

foreach ($zones['corps'] as $entry) {
    $tag = $entry['tag'];
    $text = $entry['text'];

    // Quiz detection
    if ($tag === 'ENTREE_QUIZ') {
        $isQuizRow = true;
    }
    if ($isQuizRow && $tag === 'NORMAL') {
        $tag = 'ENTREE_QUIZ_ROW';
    } elseif ($isQuizRow && $tag !== 'ENTREE_QUIZ') {
        $isQuizRow = false;
    }

    // Injection liens
    $shouldInject = ($tag !== 'ENTREE_GLOSSAIRE') &&
                    !($tag === 'NORMAL' && $pdf->in_glossary_paragraph);

    if ($shouldInject && !empty($acronymAnchors)) {
        $text = injectGlossaryLinks($text, $acronymAnchors, $firstOccurrenceFound);
    }

    // Nouvelle page pour TITRE1
    if ($tag === 'TITRE1') {
        $pdf->AddPage();
    }

    // TOC
    if ($tag === 'TITRE1' || $tag === 'TITRE2') {
        $currentPage = $pdf->getPage();
        $link = $pdf->AddLink();
        $pdf->SetLink($link, -1, -1);
        $tocManager->addTOCEntry($text, $tag, $currentPage, $link);
    }

    // Rendu
    $pdf->writeStyled($tag, $text);
}
```

**Étape 7 : Sections finales**

```php
// Glossaire
$pdf->AddPage();
$pdf->writeStyled('TITRE1', 'GLOSSAIRE');
// ...

// Quiz
$pdf->AddPage();
$pdf->writeStyled('TITRE1', 'QUIZ');
// ...

// Bibliographie
$pdf->AddPage();
$pdf->writeStyled('TITRE1', 'WIKIPEDIA');
// ...
```

**Étape 8 : Génération TOC**

```php
$tocManager->generateTOC();
// → Retour page toc_start_page
// → Rendre la TOC complète
```

**Étape 9 : Sauvegarde**

```php
$filename = eaieg_resolve_pdf_filename($entry_id, $row, $data);
$paths = eaieg_get_pdf_paths($filename);

$pdf->Output($paths['path'], 'F'); // 'F' = save to file
```

### 12.3 Points de synchronisation critiques

**Point 1 : Création des IDs de liens**

Les `AddLink()` doivent être appelés **avant** toute injection de placeholders dans le texte.

**Point 2 : Réservation TOC**

Doit se faire **avant** la génération du contenu, pour que les numéros de page soient corrects.

**Point 3 : Génération TOC**

Doit se faire **après** tout le contenu, pour avoir les numéros de page finaux.

**Point 4 : Gestion de l'état glossaire**

La variable `$in_glossary_paragraph` doit être mise à jour dans `writeStyled()` avant toute autre opération.

---

## Conclusion

Ce document couvre l'intégralité du processus de génération des ebooks SmartBooks Encyclo-AI, depuis le parsing du JSON jusqu'à la sauvegarde du PDF final. Les points critiques sont :

1. **Parsing multi-lignes** du formatted_text
2. **Système de placeholders** pour les liens internes
3. **Injection automatique** des liens vers le glossaire
4. **Deux méthodes de justification** (manuelle vs HTML)
5. **Réservation et génération de la TOC** en deux temps
6. **Gestion des états** (quiz rows, paragraphes glossaire)
7. **Synchronisation** des IDs de liens internes

Pour la page de couverture spécifiquement, consulter [ebook.md](ebook.md).

---

**Version** : 1.0
**Date** : 2025-01-23
**Auteur** : Documentation générée pour Encyclo-AI
**Complément de** : ebook.md
