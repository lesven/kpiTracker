<?php

namespace App\Form;

use App\Entity\MailSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
                'label' => 'Benutzername',
                'attr' => [
                    'class' => 'form-control',
                ],
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MailSettings::class,
        ]);
    }
}
