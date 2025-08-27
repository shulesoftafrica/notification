<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    private TemplateRenderer $templateRenderer;

    public function __construct(TemplateRenderer $templateRenderer)
    {
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * List templates for project/tenant
     */
    public function index(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $query = Template::where('project_id', $project->project_id)
                        ->where('tenant_id', $tenantId);

        // Apply filters
        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->has('status')) {
            $query->where('enabled', $request->input('status') === 'enabled');
        }

        if ($request->has('locale')) {
            $query->where('locale', $request->input('locale'));
        }

        $templates = $query->orderBy('created_at', 'desc')
                          ->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'pagination' => [
                    'total' => $templates->total(),
                    'per_page' => $templates->perPage(),
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                ],
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Create a new template
     */
    public function store(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'channel' => 'required|in:email,sms,whatsapp',
            'locale' => 'required|string|max:10',
            'content' => 'required|array',
            'content.subject' => 'required_if:channel,email|string|max:255',
            'content.text' => 'required|string',
            'content.html' => 'sometimes|string',
            'variables' => 'sometimes|array',
            'description' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        // Validate template syntax
        $templateErrors = $this->templateRenderer->validateTemplate($request->input('content'));
        if (!empty($templateErrors)) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_SYNTAX_ERROR',
                    'message' => 'Template contains syntax errors.',
                    'details' => $templateErrors,
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        // Check for duplicate template name
        $existingTemplate = Template::where('project_id', $project->project_id)
                                  ->where('tenant_id', $tenantId)
                                  ->where('name', $request->input('name'))
                                  ->where('channel', $request->input('channel'))
                                  ->where('locale', $request->input('locale'))
                                  ->first();

        if ($existingTemplate) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_ALREADY_EXISTS',
                    'message' => 'A template with this name already exists for this channel and locale.',
                    'trace_id' => $requestId
                ]
            ], 409);
        }

        // Extract variables from template content
        $extractedVariables = $this->templateRenderer->extractVariables($request->input('content'));

        $template = Template::create([
            'template_id' => 'tmpl_' . Str::ulid(),
            'project_id' => $project->project_id,
            'tenant_id' => $tenantId,
            'name' => $request->input('name'),
            'channel' => $request->input('channel'),
            'locale' => $request->input('locale'),
            'content' => $request->input('content'),
            'variables' => $request->input('variables', []),
            'extracted_variables' => $extractedVariables,
            'description' => $request->input('description'),
            'enabled' => true
        ]);

        return response()->json([
            'data' => $template,
            'meta' => [
                'project_id' => $project->project_id,
                'tenant_id' => $tenantId,
                'extracted_variables' => $extractedVariables,
                'trace_id' => $requestId
            ]
        ], 201);
    }

    /**
     * Get a specific template
     */
    public function show(Request $request, string $templateId): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $template = Template::where('template_id', $templateId)
                          ->where('project_id', $project->project_id)
                          ->where('tenant_id', $tenantId)
                          ->first();

        if (!$template) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_NOT_FOUND',
                    'message' => 'Template not found or access denied',
                    'trace_id' => $requestId
                ]
            ], 404);
        }

        return response()->json([
            'data' => $template,
            'meta' => [
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Update a template
     */
    public function update(Request $request, string $templateId): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $template = Template::where('template_id', $templateId)
                          ->where('project_id', $project->project_id)
                          ->where('tenant_id', $tenantId)
                          ->first();

        if (!$template) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_NOT_FOUND',
                    'message' => 'Template not found or access denied',
                    'trace_id' => $requestId
                ]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'content' => 'sometimes|array',
            'content.subject' => 'required_if:channel,email|string|max:255',
            'content.text' => 'required_with:content|string',
            'content.html' => 'sometimes|string',
            'variables' => 'sometimes|array',
            'description' => 'sometimes|string|max:500',
            'enabled' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        // Validate template syntax if content is being updated
        if ($request->has('content')) {
            $templateErrors = $this->templateRenderer->validateTemplate($request->input('content'));
            if (!empty($templateErrors)) {
                return response()->json([
                    'error' => [
                        'code' => 'TEMPLATE_SYNTAX_ERROR',
                        'message' => 'Template contains syntax errors.',
                        'details' => $templateErrors,
                        'trace_id' => $requestId
                    ]
                ], 422);
            }
        }

        $updateData = $request->only(['name', 'content', 'variables', 'description', 'enabled']);

        // Extract variables if content is updated
        if ($request->has('content')) {
            $updateData['extracted_variables'] = $this->templateRenderer->extractVariables($request->input('content'));
        }

        $template->update($updateData);

        return response()->json([
            'data' => $template->fresh(),
            'meta' => [
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Delete a template
     */
    public function destroy(Request $request, string $templateId): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $template = Template::where('template_id', $templateId)
                          ->where('project_id', $project->project_id)
                          ->where('tenant_id', $tenantId)
                          ->first();

        if (!$template) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_NOT_FOUND',
                    'message' => 'Template not found or access denied',
                    'trace_id' => $requestId
                ]
            ], 404);
        }

        $template->delete();

        return response()->json([
            'data' => [
                'template_id' => $templateId,
                'deleted' => true
            ],
            'meta' => [
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Preview template with test variables
     */
    public function preview(Request $request, string $templateId): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $template = Template::where('template_id', $templateId)
                          ->where('project_id', $project->project_id)
                          ->where('tenant_id', $tenantId)
                          ->first();

        if (!$template) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_NOT_FOUND',
                    'message' => 'Template not found or access denied',
                    'trace_id' => $requestId
                ]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'variables' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Test variables are required for preview.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        try {
            $renderedContent = $this->templateRenderer->render(
                $templateId,
                $project->project_id,
                $tenantId,
                $request->input('variables', [])
            );

            return response()->json([
                'data' => [
                    'template_id' => $templateId,
                    'rendered_content' => $renderedContent,
                    'variables_used' => $request->input('variables'),
                    'extracted_variables' => $template->extracted_variables
                ],
                'meta' => [
                    'trace_id' => $requestId
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'TEMPLATE_RENDER_ERROR',
                    'message' => 'Failed to render template: ' . $e->getMessage(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }
    }
}
