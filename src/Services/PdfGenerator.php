<?php

namespace Vex6\Phppdf\Services;

use Spatie\Browsershot\Browsershot;

/**
 * Service pour générer des PDF avec Browsershot
 */
class PdfGenerator
{
    private string $outputPath;

    public function __construct(string $projectRoot)
    {
        $this->outputPath = $projectRoot . '/output';

        // Créer le dossier output s'il n'existe pas
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Générer un PDF depuis du HTML
     *
     * @param string $html Le contenu HTML
     * @param string $filename Le nom du fichier PDF (sans extension)
     * @return string Le chemin complet du PDF généré
     */
    public function generateFromHtml(string $html, string $filename): string
    {
        $pdfPath = $this->outputPath . '/' . $filename . '.pdf';

        try {
            Browsershot::html($html)
                ->format('A4') // Format A4 standard
                ->landscape(false) // Portrait
                ->margins(0, 0, 0, 0) // Pas de marges
                ->showBackground() // Imprimer les arrière-plans
                ->waitUntilNetworkIdle() // Attendre que toutes les ressources soient chargées
                ->save($pdfPath);

            if (!file_exists($pdfPath)) {
                throw new \Exception("Le PDF n'a pas été généré correctement");
            }

            return $pdfPath;

        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la génération du PDF: " . $e->getMessage());
        }
    }

    /**
     * Générer le PDF de la couverture
     *
     * @param string $html Le HTML de la couverture
     * @param string $ebookId L'ID de l'ebook
     * @return string Le chemin du PDF généré
     */
    public function generateCover(string $html, string $ebookId): string
    {
        $filename = 'cover_' . $ebookId;
        return $this->generateFromHtml($html, $filename);
    }

    /**
     * Obtenir le chemin du dossier output
     */
    public function getOutputPath(): string
    {
        return $this->outputPath;
    }
}
