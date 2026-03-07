<?php

namespace App\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    protected function successResponse(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = [],
    ): JsonResponse {
        $response = [];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->additional(array_filter([
                'message' => $message,
                'meta' => $meta ?: null,
            ]))->response()->setStatusCode($status);
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a success response for resource creation (HTTP 201).
     *
     * When a $location URL is provided, the response includes a Location header
     * pointing to the newly created resource (per RFC 7231 §6.3.2).
     */
    protected function createdResponse(mixed $data = null, string $message = 'Created successfully', ?string $location = null): JsonResponse
    {
        $response = $this->successResponse($data, $message, Response::HTTP_CREATED);

        if ($location !== null) {
            $response->header('Location', $location);
        }

        return $response;
    }

    /**
     * Return a success response with no content (HTTP 204).
     */
    protected function noContentResponse(): Response
    {
        return response()->noContent();
    }

    /**
     * Return an accepted response (HTTP 202) for async operations.
     */
    protected function acceptedResponse(string $message = 'Request accepted'): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_ACCEPTED);
    }

    /**
     * Return an error response.
     */
    protected function errorResponse(
        string $message = 'An error occurred',
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        array $errors = [],
    ): JsonResponse {
        $response = ['message' => $message];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a not found error (HTTP 404).
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a forbidden error (HTTP 403).
     */
    protected function forbiddenResponse(string $message = 'You do not have permission to perform this action'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return an unauthorized error (HTTP 401).
     */
    protected function unauthorizedResponse(string $message = 'Unauthenticated'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a validation error (HTTP 422).
     */
    protected function validationErrorResponse(string $message = 'Validation failed', array $errors = []): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }
}
