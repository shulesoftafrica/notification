<?php

namespace App\Services;

use App\Models\Template;
use Illuminate\Support\Facades\Log;
use Exception;

class TemplateRenderer
{
    /**
     * Render template with variables
     */
    public function render(string $templateId, string $projectId, string $tenantId, array $variables = []): array
    {
        try {
            // Find template
            $template = Template::where('template_id', $templateId)
                ->where('project_id', $projectId)
                ->where('tenant_id', $tenantId)
                ->where('enabled', true)
                ->first();

            if (!$template) {
                throw new Exception("Template not found: {$templateId}");
            }

            // Get template content
            $content = $template->content;
            
            // Render subject
            $subject = $this->renderString($content['subject'] ?? '', $variables);
            
            // Render text content
            $textContent = $this->renderString($content['text'] ?? $content['content'] ?? '', $variables);
            
            // Render HTML content if available
            $htmlContent = null;
            if (isset($content['html'])) {
                $htmlContent = $this->renderString($content['html'], $variables);
            }

            Log::info('Template rendered successfully', [
                'template_id' => $templateId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId,
                'variables_count' => count($variables)
            ]);

            return [
                'subject' => $subject,
                'content' => $textContent,
                'html_content' => $htmlContent
            ];

        } catch (Exception $e) {
            Log::error('Template rendering failed', [
                'template_id' => $templateId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("Failed to render template: {$e->getMessage()}");
        }
    }

    /**
     * Render string with variable substitution
     */
    private function renderString(string $template, array $variables): string
    {
        // Simple variable substitution using mustache-like syntax
        // Supports {{variable_name}} format
        
        $rendered = $template;
        
        foreach ($variables as $key => $value) {
            // Convert value to string if it's not already
            $stringValue = is_array($value) || is_object($value) 
                ? json_encode($value) 
                : (string) $value;
                
            // Replace {{key}} with value
            $rendered = str_replace("{{$key}}", $stringValue, $rendered);
            $rendered = str_replace("{{ $key }}", $stringValue, $rendered);
        }

        // Clean up any remaining unmatched variables (optional)
        $rendered = preg_replace('/\{\{\s*\w+\s*\}\}/', '', $rendered);

        return $rendered;
    }

    /**
     * Validate template syntax
     */
    public function validateTemplate(array $content): array
    {
        $errors = [];
        
        // Check if required fields exist
        if (empty($content['subject']) && empty($content['text']) && empty($content['content'])) {
            $errors[] = 'Template must have at least a subject or content';
        }

        // Validate variable syntax in all content fields
        foreach (['subject', 'text', 'content', 'html'] as $field) {
            if (isset($content[$field])) {
                $fieldErrors = $this->validateVariableSyntax($content[$field], $field);
                $errors = array_merge($errors, $fieldErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate variable syntax in a string
     */
    private function validateVariableSyntax(string $content, string $fieldName): array
    {
        $errors = [];
        
        // Check for malformed variables
        if (preg_match_all('/\{[^}]*\}/', $content, $matches)) {
            foreach ($matches[0] as $match) {
                if (!preg_match('/^\{\{\s*\w+\s*\}\}$/', $match)) {
                    $errors[] = "Invalid variable syntax in {$fieldName}: {$match}";
                }
            }
        }

        return $errors;
    }

    /**
     * Extract variables from template content
     */
    public function extractVariables(array $content): array
    {
        $variables = [];
        
        foreach (['subject', 'text', 'content', 'html'] as $field) {
            if (isset($content[$field])) {
                $fieldVars = $this->extractVariablesFromString($content[$field]);
                $variables = array_merge($variables, $fieldVars);
            }
        }

        return array_unique($variables);
    }

    /**
     * Extract variables from a string
     */
    private function extractVariablesFromString(string $content): array
    {
        $variables = [];
        
        if (preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $content, $matches)) {
            $variables = $matches[1];
        }

        return $variables;
    }
}
