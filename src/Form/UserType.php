<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formular für Benutzerverwaltung durch Administratoren
 * User Story 2: Administrator kann Benutzer anlegen.
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-Mail-Adresse',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'user@example.com',
                ],
                'help' => 'Wird als Benutzername für die Anmeldung verwendet',
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Vorname',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Max',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nachname',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Mustermann',
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Benutzerrolle',
                'choices' => [
                    'Benutzer' => User::ROLE_USER,
                    'Administrator' => User::ROLE_ADMIN,
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'Administratoren können andere Benutzer verwalten',
            ]);

        // Passwort-Feld nur bei Neuanlage als Pflichtfeld, bei Bearbeitung optional
        if (!$isEdit) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Passwort',
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                    'help' => 'Mindestens 8 Zeichen',
                ],
                'second_options' => [
                    'label' => 'Passwort wiederholen',
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'Die Passwörter müssen übereinstimmen.',
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank([
                        'message' => 'Bitte geben Sie ein Passwort ein.',
                    ]),
                    new \Symfony\Component\Validator\Constraints\Length([
                        'min' => 8,
                        'minMessage' => 'Das Passwort muss mindestens {{ limit }} Zeichen lang sein.',
                        'max' => 4096,
                    ]),
                ],
            ]);
        } else {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'Neues Passwort',
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Leer lassen um Passwort nicht zu ändern',
                    ],
                    'help' => 'Leer lassen um das aktuelle Passwort zu behalten',
                ],
                'second_options' => [
                    'label' => 'Passwort wiederholen',
                    'attr' => [
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'Die Passwörter müssen übereinstimmen.',
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Length([
                        'min' => 8,
                        'minMessage' => 'Das Passwort muss mindestens {{ limit }} Zeichen lang sein.',
                        'max' => 4096,
                    ]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
