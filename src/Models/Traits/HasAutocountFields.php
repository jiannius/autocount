<?php

namespace Jiannius\Autocount\Models\Traits;

use App\Models\AutocountField;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Jiannius\Autocount\Enums\AutocountFieldType;
use Jiannius\Autocount\Models\Observers\HasAutocountFieldsObserver;

trait HasAutocountFields
{
    /**
     * Boot the trait
     */
    protected static function bootHasAutocountFields()
    {
        static::observe(HasAutocountFieldsObserver::class);
    }

    /**
     * Get the autocount records
     */
    public function autocountFields() : MorphMany
    {
        return $this->morphMany(AutocountField::class, 'parent');
    }

    /**
     * Get the autocount field
     */
    public function getAutocountField($type)
    {
        return $this->autocountFields()->where('type', $type)->first();
    }

    /**
     * Set the autocount field
     */
    public function setAutocountField($type, $value)
    {
        if (!$value) $this->autocountFields()->where('type', $type)->delete();
        else $this->autocountFields()->updateOrCreate(['type' => $type], ['data' => $value]);
    }

    /**
     * Get the debtor code
     */
    public function getAutocountDebtorCode()
    {
        $field = $this->getAutocountField(AutocountFieldType::DEBTOR_CODE);

        return $field?->data;
    }

    /**
     * Set the debtor code
     */
    public function setAutocountDebtorCode($value)
    {
        $this->setAutocountField(AutocountFieldType::DEBTOR_CODE, $value);
    }

    /**
     * Get the creditor code
     */
    public function getAutocountCreditorCode()
    {
        $field = $this->getAutocountField(AutocountFieldType::CREDITOR_CODE);

        return $field?->data;
    }

    /**
     * Set the creditor code
     */
    public function setAutocountCreditorCode($value)
    {
        $this->setAutocountField(AutocountFieldType::CREDITOR_CODE, $value);
    }
}