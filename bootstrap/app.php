<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // I use this custom exceptions on other projects to standardize the API error responses.
        // As this is a POC and timebox project, I didn't implement it here, but it's a good improvement to be made.

        //  $apiException = function (string $exceptionClass, int $statusCode, callable $payloadBuilder) use ($exceptions) {
        //     $exceptions->renderable(function (Throwable $e, Request $request) use ($exceptionClass, $statusCode, $payloadBuilder) {
        //         if (! $request->expectsJson() || ! $e instanceof $exceptionClass) {
        //             return;
        //         }
        //         $data = $payloadBuilder($e);
        //         $data['message'] = $data['message'] ?? $e->getMessage() ?: 'Error';
        //         $data['code'] = $data['code'] ?? $e->getCode() ?: $statusCode;
        //         $data['error'] = true;
        //         $data['error_id'] = (string) Str::uuid();
        //         $data['exception'] = class_basename($e);
        //         Log::error('Errors:', [$data]);

        //         return response()->json($data, $statusCode);
        //     });
        // };

        // // Exceções específicas com mensagens traduzidas
        // $apiException(JWTException::class, 400, fn () => [
        //     'message' => trans('auth.token_parse_error'),
        // ]);
        // $apiException(TokenBlacklistedException::class, 401, fn () => [
        //     'message' => trans('auth.token_invalid'),
        // ]);

        // $apiException(TokenExpiredException::class, 401, fn () => [
        //     'message' => trans('auth.token_invalid'),
        // ]);

        // $apiException(AuthenticationException::class, 401, fn () => [
        //     'message' => trans('auth.token_invalid'),
        // ]);

        // $apiException(AccessDeniedHttpException::class, 403, function (AccessDeniedHttpException $e) {
        //     return [
        //         'message' => trans('auth.validation_error'),
        //         'errors' => $e->getMessage(),
        //     ];
        // });

        // $apiException(NotFoundHttpException::class, 404, function (NotFoundHttpException $exception) {
        //     $previous = $exception->getPrevious();

        //     if ($previous instanceof ModelNotFoundException) {
        //         $model = $previous->getModel();
        //         $ids = $previous->getIds();

        //         return [
        //             'message' => trans('validation.model_not_found', [
        //                 'model' => $model !== null ? "[{$model}]" : 'Resource',
        //                 'id' => $ids !== [] ? implode(', ', array_map(static fn ($value): string => (string) $value, $ids)) : '-',
        //             ]),
        //         ];
        //     }

        //     return [
        //         'message' => trans('validation.resource_not_found'),
        //     ];
        // });

        // $apiException(ValidationException::class, 422, function (ValidationException $e) {
        //     return [
        //         'message' => trans('auth.validation_error'),
        //         'errors' => $e->errors(),
        //     ];
        // });

        // // Handler de exceções genérico
        // $apiException(Throwable::class, 500, function ($e) {
        //     $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        //     $message = $e->getMessage() ?: trans('auth.general_error');

        //     $data = [
        //         'message' => $message,
        //         'code' => $status,
        //         'exception' => class_basename($e),
        //     ];

        //     Log::error('Errors:', [$data]);

        //     return $data;
        // });
    })->create();
