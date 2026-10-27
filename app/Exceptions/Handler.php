<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as BaseHandler;
use Throwable;

class Handler extends BaseHandler
{
    // The framework adds exception class, file, line and trace to JSON bodies whenever APP_DEBUG is true,
    // and echoes the model class in "No query results for model [...]". JSON bodies must never carry either.
    protected function convertExceptionToArray(Throwable $e)
    {
        if (! $this->isHttpException($e)) {
            return ['message' => 'Server error.'];
        }

        $status = $e->getStatusCode();

        return ['message' => match (true) {
            $status === 404 => 'Not found.',
            $status >= 400 && $status < 500 => $e->getMessage(),
            default => 'Server error.',
        }];
    }
}
