<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Always return JSON for API routes
        if ($request->is('api/*') || $request->wantsJson()) {
            return $this->renderJsonException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Render exception as JSON response
     */
    protected function renderJsonException(Request $request, Throwable $e): JsonResponse
    {
        $status = 500;
        $message = 'Internal Server Error';
        $details = null;

        if ($e instanceof ValidationException) {
            $status = 422;
            $message = 'Validation failed';
            $details = $e->errors();
        } elseif ($e instanceof NotFoundHttpException) {
            $status = 404;
            $message = 'Resource not found';
        } elseif ($e instanceof MethodNotAllowedHttpException) {
            $status = 405;
            $message = 'Method not allowed';
        } elseif (method_exists($e, 'getStatusCode')) {
            $status = $e->getStatusCode();
            $message = $e->getMessage();
        } elseif (!empty($e->getMessage())) {
            $message = $e->getMessage();
        }

        $response = [
            'success' => false,
            'error' => $message,
            'status_code' => $status,
        ];

        if ($details) {
            $response['details'] = $details;
        }

        // Add debug information in local environment
        if (config('app.debug') && app()->environment('local')) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        return response()->json($response, $status);
    }
}
