<?php

require_once __DIR__ . '/vendor/autoload.php';

use Vex6\Phppdf\Models\EbookData;
use Vex6\Phppdf\Services\EbookGenerator;
use Vex6\Phppdf\Services\PdfGenerator;
use Vex6\Phppdf\Services\HtmlCleaner;

// Configuration
define('PROJECT_ROOT', __DIR__);
define('EBOOK_JSON_PATH', PROJECT_ROOT . '/exports/ebook-0-32.json');

echo "====================================\n";
echo "  GÉNÉRATEUR DE PDF SMARTBOOK\n";
echo "====================================\n\n";

try {
    // 1. Charger les données de l'ebook
    echo "[1/3] Chargement des données de l'ebook...\n";
    $ebookData = new EbookData(EBOOK_JSON_PATH);
    $coverData = $ebookData->getCoverData();

    echo "      - ID: {$coverData['id']}\n";
    echo "      - Titre: {$coverData['title']}\n";
    echo "      - Sous-titre: {$coverData['subtitle']}\n";
    echo "      - Catégorie: {$coverData['categoryLabel']}\n";
    echo "      - Langue: {$coverData['language']}\n";
    echo "      ✓ Données chargées avec succès\n\n";

    // 2. Générer le HTML complet de l'ebook (toutes les pages)
    echo "[2/3] Génération du HTML de l'ebook (Pages: Couverture + Licence + TOC + Introduction)...\n";
    $ebookGenerator = new EbookGenerator(PROJECT_ROOT);
    $html = $ebookGenerator->generate($ebookData, time());
    
    // Nettoyer le HTML en supprimant les pages vides
    echo "      - Nettoyage des pages vides...\n";
    $html = HtmlCleaner::removeEmptyPages($html);
    echo "      ✓ Pages vides supprimées\n";

    // Mettre à jour la numérotation de la TOC avec les vrais numéros de page
    echo "      - Mise à jour de la numérotation de la TOC...\n";
    $html = HtmlCleaner::updateTOCPageNumbers($html);
    echo "      ✓ TOC mise à jour avec les vrais numéros de page\n";

    // Ajouter la numérotation des pages
    echo "      - Ajout de la numérotation des pages...\n";
    $html = HtmlCleaner::addPageNumbers($html);
    echo "      ✓ Numérotation ajoutée\n";

    // Optionnel: sauvegarder le HTML pour debug
    if (!is_dir(PROJECT_ROOT . '/output')) {
        mkdir(PROJECT_ROOT . '/output', 0755, true);
    }
    file_put_contents(PROJECT_ROOT . '/output/ebook_debug.html', $html);
    echo "      - HTML sauvegardé: output/ebook_debug.html\n";
    echo "      ✓ HTML complet généré avec succès\n\n";

    // 3. Générer le PDF avec toutes les pages
    echo "[3/3] Génération du PDF avec toutes les pages...\n";
    echo "      (Cela peut prendre quelques secondes...)\n";
    $pdfGenerator = new PdfGenerator(PROJECT_ROOT);
    // Fonction pour nettoyer le nom de fichier (supprimer accents et caractères spéciaux)
    $cleanTitle = function($text) {
        // Supprimer les accents
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        // Convertir en majuscules
        $text = strtoupper($text);
        // Remplacer les espaces et caractères spéciaux par des tirets
        $text = preg_replace('/[^A-Z0-9]+/', '-', $text);
        // Supprimer les tirets en début/fin
        return trim($text, '-');
    };
    
    $pdfPath = $pdfGenerator->generateFromHtml($html, $coverData['id']."-".$cleanTitle($coverData['title']));
    echo "      ✓ PDF généré avec succès\n\n";

    // Résultat
    echo "====================================\n";
    echo "  Résultat:\n";
    echo "====================================\n";
    echo "  ✓ PDF généré: " . basename($pdfPath) . "\n";
    echo "  ✓ Nombre de pages: 5\n";
    echo "    - Page 1: Couverture\n";
    echo "    - Page 2: Mentions légales\n";
    echo "    - Page 3-4: Table des matières\n";
    echo "    - Page 5: Introduction\n";
    echo "  ✓ Taille: " . round(filesize($pdfPath) / 1024, 2) . " Ko\n";
    echo "  ✓ Emplacement: {$pdfPath}\n\n";

    echo "====================================\n";
    echo "  ✓ GÉNÉRATION TERMINÉE AVEC SUCCÈS\n";
    echo "====================================\n";

} catch (\Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
