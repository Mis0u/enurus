<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Password\PasswordRuleEnum;
use App\Enum\tailwind_class\form\field\FieldClassEnum;
use Symfony\Component\Form\AbstractType;

/**
 * @template TData of object|null
 * @extends AbstractType<TData>
 */

abstract class AbstractFormSecurityType extends AbstractType
{
    protected const array ATTR_FIELD_CLASS = [
        'class' => FieldClassEnum::ATTRIBUTE_FIELD_CLASS->value,
        'placeholder' => '••••••••',
    ];

    protected const array DATA_PASSWORD = [
        'data-password-validator-target' => 'inputPassword',
        'data-action' => 'input->password-validator#validate',
        'data-min-length' => PasswordRuleEnum::MIN_LENGTH->value,
    ];
}
