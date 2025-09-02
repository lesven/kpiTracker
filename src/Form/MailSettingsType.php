<?php

namespace App\Form;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\MailSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formular-Typ für die Konfiguration von SMTP-Mailservern.
 *
 * Ermöglicht die Eingabe und Bearbeitung von Mailserver-Einstellungen für Reminder und Systemmails.
 */
class MailSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('host', TextType::class, [
                'label' => 'SMTP Host',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'smtp.example.com',
                ],
            ])
            ->add('port', IntegerType::class, [
                'label' => 'Port',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '587',
                ],
            ])
            ->add('username', TextType::class, [
                'required' => false,
                'label' => 'Benutzername (E-Mail)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'user@smtp-provider.com',
                ],
                'help' => 'Meist eine E-Mail-Adresse für SMTP-Authentifizierung',
            ])
            ->add('password', PasswordType::class, [
                'required' => false,
                'label' => 'Passwort',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('ignoreCertificate', CheckboxType::class, [
                'required' => false,
                'label' => 'SSL-Zertifikat ignorieren',
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('isDefault', CheckboxType::class, [
                'required' => false,
                'label' => 'Standard-Konfiguration',
                'help' => 'Diese Konfiguration als Standard verwenden',
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ]);

        // Data Transformer für EmailAddress username
        $builder->get('username')
            ->addModelTransformer(new CallbackTransformer(
                fn (?EmailAddress $email) => $email?->getValue() ?? '',
                fn (?string $value) => null !== $value && '' !== trim($value) ? new EmailAddress($value) : null
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MailSettings::class,
        ]);
    }
}
