<?php

namespace App\Tests\Form;

use App\Form\KPIType;
use App\Form\KPIValueType;
use App\Form\MailSettingsType;
use App\Form\UserType;
use App\Form\KPIAdminType;
use PHPUnit\Framework\TestCase;

class SimpleFormTypeTest extends TestCase
{
    public function testKPITypeCanBeInstantiated(): void
    {
        $formType = new KPIType();
        $this->assertInstanceOf(KPIType::class, $formType);
        $this->assertTrue(method_exists($formType, 'buildForm'));
        $this->assertTrue(method_exists($formType, 'configureOptions'));
    }

    public function testKPIValueTypeCanBeInstantiated(): void
    {
        $formType = new KPIValueType();
        $this->assertInstanceOf(KPIValueType::class, $formType);
        $this->assertTrue(method_exists($formType, 'buildForm'));
        $this->assertTrue(method_exists($formType, 'configureOptions'));
    }

    public function testUserTypeCanBeInstantiated(): void
    {
        $formType = new UserType();
        $this->assertInstanceOf(UserType::class, $formType);
        $this->assertTrue(method_exists($formType, 'buildForm'));
        $this->assertTrue(method_exists($formType, 'configureOptions'));
    }

    public function testMailSettingsTypeCanBeInstantiated(): void
    {
        $formType = new MailSettingsType();
        $this->assertInstanceOf(MailSettingsType::class, $formType);
        $this->assertTrue(method_exists($formType, 'buildForm'));
        $this->assertTrue(method_exists($formType, 'configureOptions'));
    }

    public function testKPIAdminTypeCanBeInstantiated(): void
    {
        $formType = new KPIAdminType();
        $this->assertInstanceOf(KPIAdminType::class, $formType);
        $this->assertTrue(method_exists($formType, 'buildForm'));
        $this->assertTrue(method_exists($formType, 'configureOptions'));
    }

    public function testAllFormTypesExtendAbstractType(): void
    {
        $forms = [
            new KPIType(),
            new KPIValueType(),
            new UserType(),
            new MailSettingsType(),
            new KPIAdminType(),
        ];

        foreach ($forms as $form) {
            $this->assertInstanceOf(\Symfony\Component\Form\AbstractType::class, $form);
        }
    }

    public function testFormNamespaces(): void
    {
        $this->assertEquals('App\\Form\\KPIType', KPIType::class);
        $this->assertEquals('App\\Form\\KPIValueType', KPIValueType::class);
        $this->assertEquals('App\\Form\\UserType', UserType::class);
        $this->assertEquals('App\\Form\\MailSettingsType', MailSettingsType::class);
        $this->assertEquals('App\\Form\\KPIAdminType', KPIAdminType::class);
    }
}