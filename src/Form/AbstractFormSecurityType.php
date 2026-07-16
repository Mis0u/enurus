<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Password\PasswordRuleEnum;
use App\Enum\TailwindClass\Form\Field\FieldClassEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        'data-special-chars' => PasswordRuleEnum::SPECIAL_CHARS->value,
    ];

    protected const array LABEL_ATTR = [
        'class' => FieldClassEnum::LABEL_ATTRIBUTE->value,
    ];

    /**
     * @return array{0: Length, 1: Regex}
     */
    protected function passwordConstraints(TranslatorInterface $translator): array
    {
        return [
            new Length(min: (int) PasswordRuleEnum::MIN_LENGTH->value, max: 4096),
            new Regex(
                pattern: PasswordRuleEnum::REGEX->value,
                message: $translator->trans('sentence.password.security.regex', [], 'common')
            ),
        ];
    }
}
