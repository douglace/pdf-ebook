<?php

namespace Vex6\Phppdf\Services;

/**
 * Service pour générer le texte de licence de l'ebook
 */
class LicenseGenerator
{
    /**
     * Obtenir le texte de licence selon la langue
     */
    public function getLicenseText(string $language): string
    {
        return $this->getFrenchLicenseText() . ' ' . $this->getEnglishLicenseText();
    }

    /**
     * Formater la date de création
     */
    public function formatCreationDate(?int $timestamp, string $language): string
    {
        if (!$timestamp) {
            $timestamp = time();
        }

        $date = date('j F Y', $timestamp);

        // Traduire le mois si français
        if ($language === 'français') {
            $monthsFr = [
                'January' => 'janvier', 'February' => 'février', 'March' => 'mars',
                'April' => 'avril', 'May' => 'mai', 'June' => 'juin',
                'July' => 'juillet', 'August' => 'août', 'September' => 'septembre',
                'October' => 'octobre', 'November' => 'novembre', 'December' => 'décembre'
            ];
            $date = str_replace(array_keys($monthsFr), array_values($monthsFr), $date);
            return 'Smartbook Créé : ' . $date;
        }

        return 'Generated : ' . $date;
    }

    /**
     * Texte de licence en français
     */
    private function getFrenchLicenseText(): string
    {
        $paragraphs = [
            '© 2025 Encyclo-AI. Tous droits réservés.',

            'Cet ebook est protégé par le droit d\'auteur et d\'autres lois sur la propriété intellectuelle. Toute reproduction, distribution, ou utilisation non autorisée de cet Ebook est strictement interdite, sauf disposition contraire de la licence d\'utilisation.',

            'En téléchargeant ou en utilisant cet Ebook, vous acceptez d\'être lié par les termes du contrat de licence de Encyclo-AI. Pour consulter le contrat de licence complet, veuillez visiter : https://www.encyclo-ai.com/licence-smartbook-encyclo-ai-francais/ .',

            'Toute violation des termes de cette licence peut entraîner la résiliation immédiate de vos droits d\'utilisation et peut donner lieu à des poursuites judiciaires.'
        ];

        return $this->formatLicenseParagraphs($paragraphs);
    }

    /**
     * Texte de licence en anglais
     */
    private function getEnglishLicenseText(): string
    {
        $paragraphs = [
            '© 2025 Encyclo-AI. All rights reserved.',

            'This Ebook is protected by copyright and other intellectual property laws. Any unauthorized '
            . 'reproduction, distribution, or use of this Ebook is strictly prohibited, except as expressly '
            . 'permitted under the terms of the license agreement.',

            'By downloading or using this Ebook, you agree to be bound by the terms of the Encyclo-AI '
            . 'license agreement. To review the full license agreement, please visit: '
            . 'https://www.encyclo-ai.com/licence-smartbook-encyclo-ai-anglais/',

            'Any violation of the terms of this license may result in the immediate termination of your '
            . 'rights to use this Ebook and may lead to legal action.'
        ];

        return $this->formatLicenseParagraphs($paragraphs);
    }

    /**
     * Formater les paragraphes en HTML
     */
    private function formatLicenseParagraphs(array $paragraphs): string
    {
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $html .= '<div class="license-text">' . htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        return $html;
    }
}