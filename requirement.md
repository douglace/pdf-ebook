# Guide de génération de la première page (couverture) du Smartbook

Ce document est un guide autonome pour générer la première page d'un ebook Smartbook au format PDF. Il peut être utilisé comme prompt pour une IA qui devra lire un fichier JSON (ex: `ebook-626.json`) et construire la couverture.

---

## Vue d'ensemble

La première page (couverture) d'un Smartbook contient:
1. **Une bande verticale gauche** (dégradé décoratif) sur toute la hauteur
2. **Une image de couverture** (carrée, coin supérieur droit)
3. **Le titre** (gras, grande taille)
4. **Le sous-titre** (taille moyenne)
5. **Une ligne horizontale colorée** selon la catégorie
6. **Le nom de la catégorie** (traduit selon la langue)
7. **Un logo "Smartbook"** (coin inférieur droit)

---

## Extraction des données depuis le JSON

Le fichier JSON d'ebook contient toutes les informations nécessaires. Voici comment les extraire:

### 1. Identifiant de l'ebook
```
ebook._id  OU  ebook.id_ebook
Exemple: "ID-0-626"
```

### 2. Titre principal
```
ebook.titles.title
Exemple: "Comprendre les foules aujourd'hui"
```

### 3. Sous-titre (extrait de formatted_text)
Le sous-titre n'est PAS dans `ebook.titles.subtitle`, mais dans `ebook.formatted_text`.
Chercher le texte entre les balises:
```
[DEBUT_CHAMP_SOUS_TITRE] ... [FIN_CHAMP_SOUS_TITRE]
```

Exemple dans le JSON:
```
[NORMAL][DEBUT_CHAMP_SOUS_TITRE] EXPLOREZ LES DYNAMIQUES COLLECTIVES ET LEURS IMPACTS MODERNES [FIN_CHAMP_SOUS_TITRE]
```
Résultat: "EXPLOREZ LES DYNAMIQUES COLLECTIVES ET LEURS IMPACTS MODERNES"

**Note**: Si absent, utiliser `ebook.titles.subtitle` comme fallback.

### 4. Langue
```
ebook.initialize_data.language
Exemple: "Français"
```
Normaliser en minuscules: "français"

### 5. Catégorie
```
ebook.categories.principalCategory
Exemple: "Sociology"
```

---

## Tables de correspondance (catégories)

### Couleurs RGB par catégorie
```javascript
const CAT_COLOR = {
  "Arts": [242, 199, 211],
  "Cinema": [245, 218, 223],
  "Culture": [255, 235, 205],
  "Economy": [200, 215, 230],
  "Geography": [180, 215, 180],
  "History": [230, 190, 180],
  "Literature": [220, 200, 230],
  "Music": [255, 220, 220],
  "Philosophy": [200, 200, 220],
  "Politics": [215, 180, 180],
  "Psychology": [230, 210, 200],
  "Religion": [210, 200, 190],
  "Science": [180, 200, 220],
  "Sociology": [210, 220, 200],
  "Sports": [255, 230, 200],
  "Technology": [87, 109, 114],
  "Others": [0, 0, 0]
};
```

### Traductions français des catégories
```javascript
const TRAD_CAT = {
  "Arts": "Arts",
  "Cinema": "Cinéma",
  "Culture": "Culture",
  "Economy": "Économie",
  "Geography": "Géographie",
  "History": "Histoire",
  "Literature": "Littérature",
  "Music": "Musique",
  "Philosophy": "Philosophie",
  "Politics": "Politique",
  "Psychology": "Psychologie",
  "Religion": "Religion",
  "Science": "Science",
  "Sociology": "Sociologie",
  "Sports": "Sports",
  "Technology": "Technologie",
  "Others": "Autres"
};
```

### Logique de traduction
```javascript
// Si la langue est "english", garder le nom anglais
// Sinon, traduire en français
const categoryLabel = (langue === 'english') 
  ? category 
  : (TRAD_CAT[category] || category);
```

---

## Spécifications de mise en page (format A4, unités: pixels)

### Dimensions et repères
- **Format**: A4 portrait (595 × 842 px environ)
- **Marges standards**: gauche=90px, droite=50px, haut=50px
- **Marge gauche pour texte couverture**: 58px (spécifique à la page 1)

### 1. Bande verticale gauche (dégradé décoratif)
- **Position**: X=0, Y=0
- **Taille**: largeur=35px, hauteur=toute la page (842px)
- **Type**: Dégradé vertical (gradient linéaire de haut en bas)
- **Couleurs du dégradé**:
  - Haut (0%): Bleu foncé `rgb(63, 81, 181)` ou `#3F51B5`
  - Milieu (50%): Violet `rgb(142, 36, 170)` ou `#8E24AA`
  - Bas (100%): Rose/Magenta `rgb(236, 64, 122)` ou `#EC407A`
- **Alternative**: Si vous avez une image `degrade.jpg` (35px × 842px), utilisez-la directement
- **Fallback**: Si impossible de faire un dégradé, utiliser une couleur unie sombre `#3a3a3a`

**Code exemple pour le dégradé** (selon votre bibliothèque PDF):
```javascript
// Avec canvas/PDF supportant les gradients
const gradient = createLinearGradient(0, 0, 0, pageHeight);
gradient.addColorStop(0, 'rgb(63, 81, 181)');    // Bleu en haut
gradient.addColorStop(0.5, 'rgb(142, 36, 170)'); // Violet au milieu
gradient.addColorStop(1, 'rgb(236, 64, 122)');   // Rose en bas

drawRectangle({
  x: 0,
  y: 0,
  width: 35,
  height: pageHeight,
  fill: gradient
});
```

### 2. Image de couverture
- **URL de téléchargement**: `http://54.38.35.141:8080/download_image/{ID_EBOOK}`
  - Exemple: `http://54.38.35.141:8080/download_image/ID-0-626`
- **Zone réservée**: carré de 396×396 px
- **Position de la zone**: 
  - X = largeur_page - 40 - 396 = 595 - 436 = **159px** depuis la gauche (ou 40px depuis la droite)
  - Y = **40px** depuis le haut
- **Placement de l'image dans la zone**:
  - Centrer l'image dans le carré 396×396
  - Conserver les proportions (aspect ratio)
  - Si l'image est plus large que haute: ajuster la largeur à 396px
  - Si l'image est plus haute que large: ajuster la hauteur à 396px
- **Fallback**: si le téléchargement échoue, utiliser une image locale générique ou un rectangle gris

### 3. Titre
- **Police**: Quicksand Bold
- **Taille**: 26px
- **Couleur**: RGB(30, 30, 30) - gris très foncé
- **Alignement**: gauche
- **Largeur disponible**: 600 - 58 = **542px**
- **Position X**: 58px
- **Position Y**: calculée dynamiquement (voir calcul ci-dessous)

### 4. Sous-titre
- **Police**: Quicksand Regular (non gras)
- **Taille**: 19px
- **Couleur**: RGB(100, 100, 100) - gris moyen
- **Alignement**: gauche
- **Largeur disponible**: 542px
- **Position X**: 58px
- **Position Y**: calculée dynamiquement (voir calcul ci-dessous)

### 5. Calcul dynamique des positions Y (titre et sous-titre)

Le positionnement se fait **de bas en haut** pour éviter les chevauchements:

```javascript
const textWidth = 542; // largeur disponible
const lineY = 680; // ligne de référence basse

// 1. Mesurer la hauteur du sous-titre (multi-ligne si nécessaire)
const sousTitreHeight = measureTextHeight(sousTitre, {
  font: 'Quicksand',
  size: 19,
  width: textWidth
});

// 2. Positionner le sous-titre
const sousTitreY = lineY - sousTitreHeight - 20;

// 3. Mesurer la hauteur du titre (multi-ligne si nécessaire)
const titreHeight = measureTextHeight(titre, {
  font: 'Quicksand Bold',
  size: 26,
  width: textWidth
});

// 4. Positionner le titre AU-DESSUS du sous-titre
const titreY = lineY - sousTitreHeight - titreHeight - 130;
```

**Important**: Vous devez implémenter une fonction `measureTextHeight` qui calcule la hauteur d'un texte multi-ligne dans une largeur donnée.

### 6. Ligne horizontale colorée
- **Position**: Y = **745px**
- **Début**: X = 60px
- **Fin**: X = 572px
- **Épaisseur**: 3px
- **Couleur**: RGB de la catégorie (voir table `CAT_COLOR`)

### 7. Libellé de catégorie
- **Police**: Quicksand Regular
- **Taille**: 18px
- **Couleur**: RGB(30, 30, 30) - gris très foncé
- **Position**: X=56px, Y=765px
- **Texte**: `categoryLabel` (traduit selon la langue)

### 8. Logo Smartbook
- **Image**: logo "Smartbook" (à fournir ou remplacer par texte "SMARTBOOK")
- **Position**: X=473px, Y=745px
- **Taille**: largeur=100px, hauteur=62px

---

## Algorithme complet de génération

```javascript
// ÉTAPE 1: Extraire les données du JSON
const id = ebook._id || ebook.id_ebook;
const titre = ebook.titles?.title || '';
const formatted = ebook.formatted_text || '';
const langue = (ebook.initialize_data?.language || 'français').toLowerCase();
const category = ebook.categories?.principalCategory || 'Others';

// ÉTAPE 2: Parser le sous-titre depuis formatted_text
function extractBetween(text, startTag, endTag) {
  const startIdx = text.indexOf(startTag);
  if (startIdx === -1) return '';
  const endIdx = text.indexOf(endTag, startIdx + startTag.length);
  if (endIdx === -1) return '';
  return text.slice(startIdx + startTag.length, endIdx).trim();
}

const sousTitre = extractBetween(formatted, '[DEBUT_CHAMP_SOUS_TITRE]', '[FIN_CHAMP_SOUS_TITRE]') 
  || ebook.titles?.subtitle || '';

// ÉTAPE 3: Déterminer la couleur et le label de catégorie
const rgb = CAT_COLOR[category] || CAT_COLOR.Others;
const categoryLabel = (langue === 'english') ? category : (TRAD_CAT[category] || category);

// ÉTAPE 4: Créer la page PDF (A4, portrait)
createPage({ format: 'A4', orientation: 'portrait' });

// ÉTAPE 5: Dessiner la bande verticale gauche avec dégradé
const gradient = createLinearGradient(0, 0, 0, pageHeight);
gradient.addColorStop(0, 'rgb(63, 81, 181)');    // Bleu en haut
gradient.addColorStop(0.5, 'rgb(142, 36, 170)'); // Violet au milieu
gradient.addColorStop(1, 'rgb(236, 64, 122)');   // Rose en bas

drawRectangle({
  x: 0,
  y: 0,
  width: 35,
  height: pageHeight, // 842px
  fill: gradient
});
// Alternative: utiliser une image degrade.jpg de 35×842px

// ÉTAPE 6: Télécharger et placer l'image de couverture
const imageUrl = `http://54.38.35.141:8080/download_image/${id}`;
const coverImage = await downloadImage(imageUrl);
const zoneSize = 396;
const zoneX = pageWidth - 40 - zoneSize; // 159px
const zoneY = 40;

drawImageCentered({
  image: coverImage,
  zoneX: zoneX,
  zoneY: zoneY,
  zoneSize: zoneSize,
  maintainAspectRatio: true
});

// ÉTAPE 7: Calculer et dessiner le titre et sous-titre
const textWidth = 542;
const lineY = 680;

const sousTitreHeight = measureTextHeight(sousTitre, {
  font: 'Quicksand',
  size: 19,
  width: textWidth
});
const sousTitreY = lineY - sousTitreHeight - 20;

const titreHeight = measureTextHeight(titre, {
  font: 'Quicksand Bold',
  size: 26,
  width: textWidth
});
const titreY = lineY - sousTitreHeight - titreHeight - 130;

drawText({
  text: titre,
  x: 58,
  y: titreY,
  font: 'Quicksand Bold',
  size: 26,
  color: [30, 30, 30],
  width: textWidth,
  align: 'left'
});

drawText({
  text: sousTitre,
  x: 58,
  y: sousTitreY,
  font: 'Quicksand',
  size: 19,
  color: [100, 100, 100],
  width: textWidth,
  align: 'left'
});

// ÉTAPE 8: Dessiner la ligne horizontale colorée
drawLine({
  x1: 60,
  y1: 745,
  x2: 572,
  y2: 745,
  thickness: 3,
  color: rgb
});

// ÉTAPE 9: Dessiner le label de catégorie
drawText({
  text: categoryLabel,
  x: 56,
  y: 765,
  font: 'Quicksand',
  size: 18,
  color: [30, 30, 30]
});

// ÉTAPE 10: Dessiner le logo Smartbook
drawImage({
  image: smartbookLogo,
  x: 473,
  y: 745,
  width: 100,
  height: 62
});
```

---

## Points critiques à implémenter

### 1. Mesure de hauteur de texte multi-ligne
C'est LA fonction la plus importante. Elle doit:
- Découper le texte en mots
- Calculer combien de lignes sont nécessaires dans la largeur donnée
- Retourner la hauteur totale (nombre_lignes × hauteur_ligne)

Exemple conceptuel:
```javascript
function measureTextHeight(text, { font, size, width }) {
  // Utiliser la bibliothèque PDF pour mesurer
  // Retourner la hauteur en pixels
}
```

### 2. Téléchargement d'image
```javascript
async function downloadImage(url) {
  try {
    const response = await fetch(url);
    if (!response.ok) throw new Error('Download failed');
    return await response.arrayBuffer();
  } catch (err) {
    // Retourner une image par défaut
    return defaultCoverImage;
  }
}
```

### 3. Centrage d'image dans une zone carrée
```javascript
function drawImageCentered({ image, zoneX, zoneY, zoneSize, maintainAspectRatio }) {
  const imgWidth = image.width;
  const imgHeight = image.height;
  
  let targetW, targetH;
  
  if (imgWidth > imgHeight) {
    targetW = zoneSize;
    targetH = (imgHeight / imgWidth) * zoneSize;
  } else {
    targetH = zoneSize;
    targetW = (imgWidth / imgHeight) * zoneSize;
  }
  
  const posX = zoneX + (zoneSize - targetW) / 2;
  const posY = zoneY + (zoneSize - targetH) / 2;
  
  drawImage({ image, x: posX, y: posY, width: targetW, height: targetH });
}
```

---

## Exemple de prompt pour une IA

```
Tu es un générateur de PDF. Tu dois créer la première page (couverture) d'un ebook Smartbook.

Voici les spécifications complètes:
[COLLER TOUT CE DOCUMENT]

Maintenant, lis le fichier JSON fourni et génère la première page en suivant exactement ces instructions.
```

---

## Résumé des valeurs clés

| Élément | Police | Taille | Couleur | Position X | Position Y |
|---------|--------|--------|---------|------------|------------|
| Bande verticale | - | 35×842px | Dégradé bleu→violet→rose | 0 | 0 |
| Image couverture | - | 396×396px | - | 159 | 40 |
| Titre | Quicksand Bold | 26px | rgb(30,30,30) | 58 | calculé |
| Sous-titre | Quicksand | 19px | rgb(100,100,100) | 58 | calculé |
| Ligne colorée | - | 3px | CAT_COLOR | 60→572 | 745 |
| Label catégorie | Quicksand | 18px | rgb(30,30,30) | 56 | 765 |
| Logo | - | 100×62px | - | 473 | 745 |

**Détail du dégradé de la bande verticale**:
- 0% (haut): `rgb(63, 81, 181)` - Bleu Material Design
- 50% (milieu): `rgb(142, 36, 170)` - Violet/Pourpre
- 100% (bas): `rgb(236, 64, 122)` - Rose/Magenta


