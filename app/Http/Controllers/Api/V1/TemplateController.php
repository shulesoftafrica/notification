<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Template;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    protected $templateRenderer;

    public function __construct(TemplateRenderer $templateRenderer)
    {
        $this->templateRenderer = $templateRenderer;
        $this->middleware('api.auth');
    }

    /**
     * List all templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Template::query();

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by status
            if ($request->has('active')) {
                $query->where('active', $request->boolean('active'));
            }

            // Search by name or description
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $templates = $query->orderBy('name')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $templates->items(),
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                    'last_page' => $templates->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve templates',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific template
     */
    public function show($id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $template
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create a new template
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:templates',
                'type' => 'required|in:sms,email,whatsapp',
                'subject' => 'required_if:type,email|string|max:255',
                'content' => 'required|string',
                'description' => 'nullable|string|max:500',
                'variables' => 'nullable|array',
                'variables.*' => 'string|max:100',
                'active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate template syntax
            $validationResult = $this->templateRenderer->validateTemplate(
                $request->content,
                $request->variables ?? []
            );

            if (!$validationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Template validation failed',
                    'message' => $validationResult['error']
                ], 422);
            }

            $template = Template::create([
                'name' => $request->name,
                'type' => $request->type,
                'subject' => $request->subject,
                'content' => $request->content,
                'description' => $request->description,
                'variables' => $request->variables ?? [],
                'active' => $request->boolean('active', true),
                'created_by' => $request->attributes->get('api_key'),
            ]);

            Log::info('Template created', [
                'template_id' => $template->id,
                'name' => $template->name,
                'type' => $template->type
            ]);

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => 'Template created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create template',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing template
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|unique:templates,name,' . $id,
                'type' => 'sometimes|in:sms,email,whatsapp',
                'subject' => 'required_if:type,email|string|max:255',
                'content' => 'sometimes|string',
                'description' => 'nullable|string|max:500',
                'variables' => 'nullable|array',
                'variables.*' => 'string|max:100',
                'active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate template syntax if content is being updated
            if ($request->has('content')) {
                $validationResult = $this->templateRenderer->validateTemplate(
                    $request->content,
                    $request->variables ?? $template->variables ?? []
                );

                if (!$validationResult['valid']) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Template validation failed',
                        'message' => $validationResult['error']
                    ], 422);
                }
            }

            $template->update($request->only([
                'name', 'type', 'subject', 'content', 'description', 'variables', 'active'
            ]));

            Log::info('Template updated', [
                'template_id' => $template->id,
                'name' => $template->name
            ]);

            return response()->json([
                'success' => true,
                'data' => $template->fresh(),
                'message' => 'Template updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update template',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template
     */
    public function destroy($id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);
            $templateName = $template->name;
            
            $template->delete();

            Log::info('Template deleted', [
                'template_id' => $id,
                'name' => $templateName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete template',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview template with sample data
     */
    public function preview(Request $request, $id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'data' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $rendered = $this->templateRenderer->render($template, $request->data);

            return response()->json([
                'success' => true,
                'data' => [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'type' => $template->type,
                    'rendered_subject' => $rendered['subject'] ?? null,
                    'rendered_content' => $rendered['content'],
                    'variables_used' => $rendered['variables_used'] ?? [],
                    'missing_variables' => $rendered['missing_variables'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to preview template',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test template rendering
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'content' => 'required|string',
                'subject' => 'nullable|string',
                'type' => 'required|in:sms,email,whatsapp',
                'data' => 'required|array',
                'variables' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create temporary template object
            $tempTemplate = new Template([
                'type' => $request->type,
                'subject' => $request->subject,
                'content' => $request->content,
                'variables' => $request->variables ?? []
            ]);

            $rendered = $this->templateRenderer->render($tempTemplate, $request->data);

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => $request->type,
                    'rendered_subject' => $rendered['subject'] ?? null,
                    'rendered_content' => $rendered['content'],
                    'variables_used' => $rendered['variables_used'] ?? [],
                    'missing_variables' => $rendered['missing_variables'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to test template',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get template variables
     */
    public function variables($id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);
            
            $variables = $this->templateRenderer->extractVariables($template->content);
            if ($template->subject) {
                $subjectVariables = $this->templateRenderer->extractVariables($template->subject);
                $variables = array_unique(array_merge($variables, $subjectVariables));
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'variables' => $variables,
                    'predefined_variables' => $template->variables ?? [],
                    'required_variables' => array_diff($variables, $template->variables ?? [])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to extract template variables',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
