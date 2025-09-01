<?php

namespace App\Form;

use App\Entity\KPI;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formular-Typ für die KPI-Erstellung durch Administratoren.
 *
 * Ermöglicht die Auswahl eines Benutzers und die Anlage einer KPI für diesen.
 * User Story 4: Administrator kann KPIs für Benutzer anlegen.
 */
class KPIAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getEmail().' ('.$user->getFirstName().' '.$user->getLastName().')';
                },
                'label' => 'Benutzer',
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Für welchen Benutzer soll diese KPI angelegt werden?',
                'placeholder' => 'Benutzer auswählen...',
            ])
            ->add('name', TextType::class, [
                'label' => 'KPI-Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. Umsatz, Kundenzufriedenheit, ...',
                ],
                'help' => 'Eindeutiger Name für die KPI',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Optionale Beschreibung der KPI...',
                ],
                'help' => 'Zusätzliche Informationen zur KPI (optional)',
            ])
            ->add('interval', ChoiceType::class, [
                'label' => 'Intervall',
                'choices' => [
                    'Wöchentlich' => 'weekly',
                    'Monatlich' => 'monthly',
                    'Quartalsweise' => 'quarterly',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Wie oft soll diese KPI erfasst werden?',
            ])
            ->add('unit', TextType::class, [
                'label' => 'Einheit',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. EUR, %, Anzahl, ...',
                ],
                'help' => 'Maßeinheit für die Werte (optional)',
            ])
            ->add('target', TextType::class, [
                'label' => 'Zielwert',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. 100000, 95, ...',
                ],
                'help' => 'Angestrebter Zielwert (optional)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => KPI::class,
        ]);
    }
}
