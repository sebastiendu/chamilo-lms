<?php

declare(strict_types=1);

namespace Doctrine\Tests\Common\DataFixtures\TestTypes;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\Common\DataFixtures\TestValueObjects\Uuid;

class UuidType extends Type
{
    public const NAME = 'uuid';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $field_declaration['length'] = 36;
        $field_declaration['fixed']  = true;

        return $platform->getVarcharTypeDeclarationSQL($field_declaration);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value === null ? null : new Uuid($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return $value === null ? null : (string) $value;
    }

    public function getName()
    {
        return self::NAME;
    }
}
