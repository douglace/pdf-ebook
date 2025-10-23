<?php

require_once __DIR__ . '/vendor/autoload.php';

use Vex6\Phppdf\Models\EbookData;
use Vex6\Phppdf\Services\CoverGenerator;
use Vex6\Phppdf\Services\PdfGenerator;

// Configuration
define('PROJECT_ROOT', __DIR__);
define('EBOOK_JSON_PATH', PROJECT_ROOT . '/exports/ebook-623.json');

echo "====================================\n";
echo "  GÉNÉRATEUR DE PDF SMARTBOOK\n";
echo "====================================\n\n";

try {
    // 1. Charger les données de l'ebook
    echo "[1/4] Chargement des données de l'ebook...\n";
    $ebookData = new EbookData(EBOOK_JSON_PATH);
    $coverData = $ebookData->getCoverData();

    echo "      - ID: {$coverData['id']}\n";
    echo "      - Titre: {$coverData['title']}\n";
    echo "      - Sous-titre: {$coverData['subtitle']}\n";
    echo "      - Catégorie: {$coverData['categoryLabel']}\n";
    echo "      ✓ Données chargées avec succès\n\n";

    // 2. Générer le HTML de la couverture
    echo "[2/4] Génération du HTML de la couverture...\n";
    $coverGenerator = new CoverGenerator(PROJECT_ROOT);
    $html = $coverGenerator->generate($ebookData);

    // Optionnel: sauvegarder le HTML pour debug
    $htmlPath = PROJECT_ROOT . '/output/cover_debug.html';
    if (!is_dir(PROJECT_ROOT . '/output')) {
        mkdir(PROJECT_ROOT . '/output', 0755, true);
    }
    file_put_contents($htmlPath, $html);
    echo "      - HTML sauvegardé: output/cover_debug.html\n";
    echo "      ✓ HTML généré avec succès\n\n";

    // 3. Générer le PDF
    echo "[3/4] Génération du PDF avec Browsershot...\n";
    echo "      (Cela peut prendre quelques secondes...)\n";
    $pdfGenerator = new PdfGenerator(PROJECT_ROOT);
    $pdfPath = $pdfGenerator->generateCover($html, $coverData['id']);
    echo "      ✓ PDF généré avec succès\n\n";

    // 4. Résultat
    echo "[4/4] Résultat:\n";
    echo "      ✓ PDF généré: " . basename($pdfPath) . "\n";
    echo "      ✓ Emplacement: {$pdfPath}\n";
    echo "      ✓ Taille: " . round(filesize($pdfPath) / 1024, 2) . " Ko\n\n";

    echo "====================================\n";
    echo "  ✓ GÉNÉRATION TERMINÉE AVEC SUCCÈS\n";
    echo "====================================\n";

} catch (\Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
