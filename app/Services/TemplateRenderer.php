<?php

namespace App\Services;

use App\Models\Template;
use Illuminate\Support\Facades\Log;

class TemplateRenderer
{
    /**
     * Render template with provided data
     */
    public function render(Template $template, array $data = []): string
    {
        try {
            $content = $template->content;
            
            // Replace variables in the template
            foreach ($data as $key => $value) {
                $placeholder = "{{" . $key . "}}";
                $content = str_replace($placeholder, $this->formatValue($value), $content);
            }
            
            // Check for any unreplaced variables and log warning
            if (preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches)) {
                Log::warning('Template has unreplaced variables', [
                    'template_id' => $template->id,
                    'variables' => $matches[1]
                ]);
            }
            
            return $content;
            
        } catch (\Exception $e) {
            Log::error('Template rendering failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception("Failed to render template: " . $e->getMessage());
        }
    }

    /**
     * Render template content with data (static method)
     */
    public static function renderContent(string $content, array $data = []): string
    {
        foreach ($data as $key => $value) {
            $placeholder = "{{" . $key . "}}";
            $content = str_replace($placeholder, self::formatValue($value), $content);
        }
        
        return $content;
    }

    /**
     * Extract variables from template content
     */
    public function extractVariables(string $content): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Validate template syntax
     */
    public function validateSyntax(string $content): array
    {
        $errors = [];
        
        // Check for balanced braces
        $openBraces = substr_count($content, '{{');
        $closeBraces = substr_count($content, '}}');
        
        if ($openBraces !== $closeBraces) {
            $errors[] = 'Unbalanced template braces';
        }
        
        // Check for malformed variables
        if (preg_match('/\{[^{]|\}[^}]/', $content)) {
            $errors[] = 'Malformed template variables (use {{ }} for variables)';
        }
        
        // Check for empty variables
        if (preg_match('/\{\{\s*\}\}/', $content)) {
            $errors[] = 'Empty template variables found';
        }
        
        // Check for invalid variable names
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        foreach ($matches[1] as $variable) {
            $trimmed = trim($variable);
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $trimmed)) {
                $errors[] = "Invalid variable name: {$trimmed}";
            }
        }
        
        return $errors;
    }

    /**
     * Generate sample data for template preview
     */
    public function generateSampleData(Template $template): array
    {
        $variables = $this->extractVariables($template->content);
        $sampleData = [];
        
        foreach ($variables as $variable) {
            $sampleData[$variable] = $this->getSampleValue($variable);
        }
        
        return $sampleData;
    }

    /**
     * Get sample value based on variable name
     */
    protected function getSampleValue(string $variableName): string
    {
        $variableName = strtolower($variableName);
        
        $sampleValues = [
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'company' => 'Acme Corp',
            'title' => 'Software Engineer',
            'date' => now()->format('Y-m-d'),
            'time' => now()->format('H:i:s'),
            'datetime' => now()->format('Y-m-d H:i:s'),
            'amount' => '$100.00',
            'price' => '$99.99',
            'quantity' => '5',
            'total' => '$499.95',
            'order_id' => 'ORD-12345',
            'invoice_id' => 'INV-67890',
            'product' => 'Premium Package',
            'service' => 'Cloud Hosting',
            'url' => 'https://example.com',
            'link' => 'https://example.com/verify',
            'code' => '123456',
            'token' => 'abc123xyz',
            'password' => 'TempPass123',
            'username' => 'johndoe',
            'status' => 'Active',
            'balance' => '$1,250.00',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'address' => '123 Main St, City, State 12345',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001',
            'country' => 'United States',
        ];
        
        // Check for exact matches first
        if (isset($sampleValues[$variableName])) {
            return $sampleValues[$variableName];
        }
        
        // Check for partial matches
        foreach ($sampleValues as $key => $value) {
            if (strpos($variableName, $key) !== false) {
                return $value;
            }
        }
        
        // Default sample value
        return ucwords(str_replace('_', ' ', $variableName));
    }

    /**
     * Format value for template rendering
     */
    protected function formatValue($value): string
    {
        if (is_null($value)) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }

    /**
     * Static version of formatValue for external use
     */
    public static function formatValueStatic($value): string
    {
        return (new self())->formatValue($value);
    }

    /**
     * Check if template has specific variable
     */
    public function hasVariable(string $content, string $variable): bool
    {
        return strpos($content, "{{" . $variable . "}}") !== false;
    }

    /**
     * Get template statistics
     */
    public function getTemplateStats(Template $template): array
    {
        $content = $template->content;
        $variables = $this->extractVariables($content);
        
        return [
            'character_count' => strlen($content),
            'word_count' => str_word_count(strip_tags($content)),
            'variable_count' => count($variables),
            'variables' => $variables,
            'has_html' => $content !== strip_tags($content),
            'syntax_errors' => $this->validateSyntax($content),
        ];
    }

    /**
     * Convert template to different formats
     */
    public function convertFormat(string $content, string $fromFormat, string $toFormat): string
    {
        if ($fromFormat === $toFormat) {
            return $content;
        }
        
        switch ($toFormat) {
            case 'plain':
                return strip_tags($content);
                
            case 'html':
                if ($fromFormat === 'plain') {
                    return nl2br(htmlspecialchars($content));
                }
                return $content;
                
            case 'markdown':
                if ($fromFormat === 'html') {
                    // Basic HTML to Markdown conversion
                    $content = preg_replace('/<br\s*\/?>/', "\n", $content);
                    $content = preg_replace('/<p>(.*?)<\/p>/', "$1\n\n", $content);
                    $content = preg_replace('/<strong>(.*?)<\/strong>/', "**$1**", $content);
                    $content = preg_replace('/<em>(.*?)<\/em>/', "*$1*", $content);
                    $content = strip_tags($content);
                }
                return $content;
                
            default:
                return $content;
        }
    }

    /**
     * Minify template content (remove extra whitespace)
     */
    public function minify(string $content): string
    {
        // Remove extra whitespace but preserve template variables
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\s*\{\{\s*/', '{{', $content);
        $content = preg_replace('/\s*\}\}\s*/', '}}', $content);
        
        return trim($content);
    }
}
