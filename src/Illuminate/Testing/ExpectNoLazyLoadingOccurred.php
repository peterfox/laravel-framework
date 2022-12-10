<?php

namespace Illuminate\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\AssertionFailedError;

class ExpectNoLazyLoadingOccurred
{
    /**
     * Assert that no lazy loading occurred.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @return void
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \RuntimeException
     */
    public function __invoke($model, $key)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        $caller = $this->resolveTrace($trace, $model);
        if ($caller === null || !isset($caller['file'], $caller['line'])) {
            throw new \RuntimeException('Unable to determine the caller of the lazy loading.');
        }

        ['file' => $file, 'line' => $line] = $caller;
        $modelName = $model::class;

        throw new AssertionFailedError("{$modelName}::{$key} was lazy loaded in {$file}:{$line}");
    }

    /**
     * @param  array $trace
     * @param  Model $model
     * @return array|null
     */
    public function resolveTrace($trace, $model)
    {
        return collect($trace)->first(function ($trace) use ($model) {
            return isset($trace['object']) &&
                $trace['object'] instanceof Model &&
                $model->is($trace['object']) && $trace['function'] === '__get';
        });
    }
}
