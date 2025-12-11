<?php

namespace Jiannius\Autocount\Models\Observers;

class HasAutocountFieldsObserver
{
    /**
     * Handle deleting event
     */
    public function deleting($model): void
    {
        $model->autocountFields()->withoutGlobalScopes()->delete();
    }
}
