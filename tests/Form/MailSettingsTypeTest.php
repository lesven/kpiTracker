<?php

namespace App\Tests\Form;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\MailSettings;
use App\Form\MailSettingsType;
use Symfony\Component\Form\Test\TypeTestCase;

class MailSettingsTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $formData = [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'user@example.com',
            'password' => 'secret123',
            'ignoreCertificate' => true,
            'isDefault' => false,
        ];

        $mailSettings = new MailSettings();
        $form = $this->factory->create(MailSettingsType::class, $mailSettings);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        
        $this->assertEquals('smtp.example.com', $mailSettings->getHost());
        $this->assertEquals(587, $mailSettings->getPort());
        $this->assertInstanceOf(EmailAddress::class, $mailSettings->getUsername());
        $this->assertEquals('user@example.com', $mailSettings->getUsername()->getValue());
        $this->assertEquals('secret123', $mailSettings->getPassword());
        $this->assertTrue($mailSettings->isIgnoreCertificate());
        $this->assertFalse($mailSettings->isDefault());
    }

    public function testSubmitValidDataWithOptionalFields(): void
    {
        $formData = [
            'host' => 'smtp.test.com',
            'port' => 25,
        ];

        $mailSettings = new MailSettings();
        $form = $this->factory->create(MailSettingsType::class, $mailSettings);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        
        $this->assertEquals('smtp.test.com', $mailSettings->getHost());
        $this->assertEquals(25, $mailSettings->getPort());
        $this->assertNull($mailSettings->getUsername());
        $this->assertNull($mailSettings->getPassword());
        $this->assertFalse($mailSettings->isIgnoreCertificate());
        $this->assertFalse($mailSettings->isDefault());
    }

    public function testFormFields(): void
    {
        $form = $this->factory->create(MailSettingsType::class);
        
        $this->assertTrue($form->has('host'));
        $this->assertTrue($form->has('port'));
        $this->assertTrue($form->has('username'));
        $this->assertTrue($form->has('password'));
        $this->assertTrue($form->has('ignoreCertificate'));
        $this->assertTrue($form->has('isDefault'));
    }

    public function testConfigureOptions(): void
    {
        $form = $this->factory->create(MailSettingsType::class);
        $config = $form->getConfig();
        
        $this->assertEquals(MailSettings::class, $config->getDataClass());
    }

    public function testUsernameTransformer(): void
    {
        $mailSettings = new MailSettings();
        $mailSettings->setUsername(new EmailAddress('smtp@test.com'));
        
        $form = $this->factory->create(MailSettingsType::class, $mailSettings);
        
        // Test that EmailAddress is transformed to string for display
        $this->assertEquals('smtp@test.com', $form->get('username')->getViewData());
    }

    public function testOptionalFieldsAreNotRequired(): void
    {
        $form = $this->factory->create(MailSettingsType::class);
        
        $this->assertTrue($form->get('host')->getConfig()->getRequired());
        $this->assertTrue($form->get('port')->getConfig()->getRequired());
        $this->assertFalse($form->get('username')->getConfig()->getRequired());
        $this->assertFalse($form->get('password')->getConfig()->getRequired());
        $this->assertFalse($form->get('ignoreCertificate')->getConfig()->getRequired());
        $this->assertFalse($form->get('isDefault')->getConfig()->getRequired());
    }

    public function testEmptyUsernameHandling(): void
    {
        $formData = [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => '',
        ];

        $mailSettings = new MailSettings();
        $form = $this->factory->create(MailSettingsType::class, $mailSettings);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertNull($mailSettings->getUsername());
    }

    public function testWhitespaceUsernameHandling(): void
    {
        $formData = [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => '   ',
        ];

        $mailSettings = new MailSettings();
        $form = $this->factory->create(MailSettingsType::class, $mailSettings);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertNull($mailSettings->getUsername());
    }

    public function testCheckboxFieldsHandleBoolean(): void
    {
        $formData = [
            'host' => 'smtp.example.com',
            'port' => 587,
            'ignoreCertificate' => '1',
            'isDefault' => false,
        ];

        $mailSettings = new MailSettings();
        $form = $this->factory->create(MailSettingsType::class, $mailSettings);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertTrue($mailSettings->isIgnoreCertificate());
        $this->assertFalse($mailSettings->isDefault());
    }
}