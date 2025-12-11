<?php

namespace Jiannius\Autocount\Enums;

enum AutocountFieldType: string
{
    case DEBTOR_CODE = 'debtor-code';
    case CREDITOR_CODE = 'creditor-code';
    case PURCHASE_ACCOUNTING_CODE = 'purchase-accounting-code';
    case SALES_ACCOUNTING_CODE = 'sales-accounting-code';
    case PAYMENT_ACCOUNTING_CODE = 'payment-accounting-code';
}