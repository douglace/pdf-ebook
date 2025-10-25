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
            $browsershot = Browsershot::html($html)
                ->format('A4') // Format A4 standard
                ->landscape(false) // Portrait
                ->margins(0, 0, 0, 0) // Pas de marges
                ->showBackground() // Imprimer les arrière-plans
                ->waitUntilNetworkIdle(); // Attendre que toutes les ressources soient chargées

            // Détecter et configurer les chemins Node.js
            $this->configureNodePaths($browsershot);
            
            // Essayer d'utiliser Chrome système si disponible
            $this->configureChromePath($browsershot);

            $browsershot->save($pdfPath);

            if (!file_exists($pdfPath)) {
                throw new \Exception("Le PDF n'a pas été généré correctement");
            }

            return $pdfPath;

        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la génération du PDF: " . $e->getMessage());
        }
    }

    /**
     * Configurer les chemins Node.js pour Browsershot
     * Détecte automatiquement l'emplacement de node et npm
     */
    private function configureNodePaths($browsershot): void
    {
        // Charger la config si elle existe
        $configFile = dirname(__DIR__, 2) . '/config.php';
        $config = file_exists($configFile) ? include $configFile : [];

        $nodePath = null;
        $npmPath = null;

        // Priorité 1 : Utiliser les chemins du fichier config.php si définis
        if (!empty($config['node_binary']) && file_exists($config['node_binary'])) {
            $nodePath = $config['node_binary'];
        }

        if (!empty($config['npm_binary']) && file_exists($config['npm_binary'])) {
            $npmPath = $config['npm_binary'];
        }

        // Priorité 2 : Détection automatique si pas défini dans config
        if (!$nodePath) {
            $possibleNodePaths = [];
            
            // Chemins Windows
            if (PHP_OS_FAMILY === 'Windows') {
                $possibleNodePaths = [
                    'C:\Program Files\nodejs\node.exe',
                    'C:\Program Files (x86)\nodejs\node.exe',
                    trim(shell_exec('where node 2>nul') ?: ''),
                    trim(shell_exec('(Get-Command node).Source 2>$null') ?: '')
                ];
            } else {
                // Chemins Unix/Linux/macOS
                $possibleNodePaths = [
                    '/usr/bin/node',
                    '/usr/local/bin/node',
                    '/opt/homebrew/bin/node',
                    trim(shell_exec('which node 2>/dev/null') ?: '')
                ];
            }

            foreach ($possibleNodePaths as $path) {
                if (!empty($path) && file_exists($path) && is_executable($path)) {
                    $nodePath = $path;
                    break;
                }
            }
        }

        if (!$npmPath) {
            $possibleNpmPaths = [];
            
            // Chemins Windows
            if (PHP_OS_FAMILY === 'Windows') {
                $possibleNpmPaths = [
                    'C:\Program Files\nodejs\npm.cmd',
                    'C:\Program Files\nodejs\npm.ps1',
                    'C:\Program Files (x86)\nodejs\npm.cmd',
                    'C:\Program Files (x86)\nodejs\npm.ps1',
                    trim(shell_exec('where npm 2>nul') ?: ''),
                    trim(shell_exec('(Get-Command npm).Source 2>$null') ?: '')
                ];
            } else {
                // Chemins Unix/Linux/macOS
                $possibleNpmPaths = [
                    '/usr/bin/npm',
                    '/usr/local/bin/npm',
                    '/opt/homebrew/bin/npm',
                    trim(shell_exec('which npm 2>/dev/null') ?: '')
                ];
            }

            foreach ($possibleNpmPaths as $path) {
                if (!empty($path) && file_exists($path) && is_executable($path)) {
                    $npmPath = $path;
                    break;
                }
            }
        }

        // Configurer Browsershot avec les chemins trouvés
        if ($nodePath) {
            $browsershot->setNodeBinary($nodePath);
        }

        if ($npmPath) {
            $browsershot->setNpmBinary($npmPath);
        }

        // Log pour debug
        error_log("PdfGenerator - Node.js: " . ($nodePath ?: 'non trouvé') .
                  ", npm: " . ($npmPath ?: 'non trouvé'));

        // Si aucun chemin n'a été trouvé, lever une erreur explicite
        if (!$nodePath) {
            throw new \Exception(
                "Node.js introuvable. Installez Node.js ou configurez le chemin dans config.php\n" .
                "Installation: curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash - && sudo apt-get install -y nodejs"
            );
        }

        if (!$npmPath) {
            throw new \Exception(
                "npm introuvable. Installez npm ou configurez le chemin dans config.php"
            );
        }
    }

    /**
     * Configurer le chemin de Chrome/Chromium pour Browsershot
     * Essaie d'utiliser Chrome système si Puppeteer n'est pas disponible
     */
    private function configureChromePath($browsershot): void
    {
        $possibleChromePaths = [];
        
        if (PHP_OS_FAMILY === 'Windows') {
            $possibleChromePaths = [
                'C:\Program Files\Google\Chrome\Application\chrome.exe',
                'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
                'C:\Program Files\Microsoft\Edge\Application\msedge.exe'
            ];
        } else {
            // Chemins Linux/Unix
            $possibleChromePaths = [
                '/usr/bin/google-chrome',
                '/usr/bin/google-chrome-stable',
                '/usr/bin/chromium-browser',
                '/usr/bin/chromium',
                '/snap/bin/chromium',
                '/opt/google/chrome/chrome',
                '/usr/local/bin/chrome',
                trim(shell_exec('which google-chrome 2>/dev/null') ?: ''),
                trim(shell_exec('which chromium-browser 2>/dev/null') ?: ''),
                trim(shell_exec('which chromium 2>/dev/null') ?: '')
            ];
        }

        foreach ($possibleChromePaths as $path) {
            if (!empty($path) && file_exists($path) && is_executable($path)) {
                $browsershot->setChromePath($path);
                error_log("PdfGenerator - Chrome trouvé: " . $path);
                break;
            }
        }
    }

    /**
     * Obtenir le chemin du dossier output
     */
    public function getOutputPath(): string
    {
        return $this->outputPath;
    }
}
