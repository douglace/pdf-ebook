# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP-based PDF/ebook generation system for creating SmartBook ebooks. The system processes JSON data and generates professional PDF ebooks with covers, license pages, and formatted content.

## Key Commands

### Development Commands
- `php index.php` - Run the main ebook generation script
- `composer install` - Install PHP dependencies
- `npm install` - Install Node.js dependencies (for Puppeteer)

### Testing
- No formal test framework is configured
- Test by running `php index.php` with sample JSON data in `/exports` folder

## Architecture

### Core Components

**Main Entry Point**
- `index.php` - Main script that orchestrates the ebook generation process

**Data Models**
- `src/Models/EbookData.php` - Handles JSON data parsing and cover data extraction

**Services**
- `src/Services/EbookGenerator.php` - Generates complete HTML for the ebook (cover + license pages)
- `src/Services/PdfGenerator.php` - Uses Spatie\Browsershot to convert HTML to PDF
- `src/Services/LicenseGenerator.php` - Handles license text generation
- `src/Services/CoverGenerator.php` - Manages cover generation logic

**Templates**
- `src/Templates/ebook.html` - HTML template for the complete ebook
- `src/Templates/cover.html` - HTML template for cover generation
- `src/Templates/license.html` - HTML template for license page
- `src/Templates/styles.css` - CSS styles for all templates

### Key Dependencies

**PHP Dependencies (composer.json)**
- `spatie/browsershot` - HTML to PDF conversion using Puppeteer

**Node.js Dependencies (package.json)**
- `puppeteer` - Headless Chrome for PDF generation

### Data Flow

1. **Input**: JSON files in `/exports` folder (e.g., `ebook-623.json`)
2. **Processing**: 
   - `EbookData` parses JSON and extracts cover information
   - `EbookGenerator` creates complete HTML with embedded assets
   - `PdfGenerator` converts HTML to PDF using Browsershot/Puppeteer
3. **Output**: PDF files in `/output` folder

### Asset Management

**Fonts**
- Quicksand font family in `/assets/fonts/quicksand/`
- Fonts are converted to base64 and embedded in HTML

**Images**
- Cover images, logos, and decorative elements in `/assets/images/`
- Images are converted to base64 for HTML embedding

### File Processing

**JSON Structure Expected**
- Cover data with title, subtitle, category information
- Language settings (français/english)
- Category color mappings for visual styling

**Template System**
- Uses placeholder replacement (e.g., `{{TITLE}}`, `{{SUBTITLE}}`)
- CSS variables for dynamic styling based on category colors
- Base64 asset embedding for self-contained HTML

## Important Implementation Details

### PDF Generation Process
1. Load JSON data from exports folder
2. Extract cover data including title, subtitle, category
3. Generate HTML with embedded fonts and images
4. Convert to PDF using Puppeteer via Browsershot
5. Save to output folder with timestamp-based naming

### Language Support
- Supports French and English license text
- Dynamic month name translation for creation dates
- Language-specific formatting and content

### Error Handling
- File existence checks for templates and assets
- Exception handling throughout the generation pipeline
- Fallback mechanisms for missing assets

## Development Notes

- The system uses PSR-4 autoloading with namespace `Vex6\Phppdf`
- All assets are embedded as base64 to create self-contained HTML
- The PDF generation relies on a headless Chrome instance via Puppeteer
- Output files use descriptive naming based on source data IDs