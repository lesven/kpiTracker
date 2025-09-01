<?php

namespace App\Form;

use App\Domain\ValueObject\KpiInterval;
use App\Entity\KPI;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formular-Typ für die Erstellung und Bearbeitung von KPIs durch Benutzer.
 *
 * User Story 3: Benutzer kann eigene KPIs anlegen und bearbeiten.
 */
class KPIType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'KPI-Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. Umsatz, Kundenzufriedenheit, ...',
                ],
                'help' => 'Eindeutiger Name für Ihre KPI',
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
                    'Wöchentlich' => KpiInterval::WEEKLY,
                    'Monatlich' => KpiInterval::MONTHLY,
                    'Quartalsweise' => KpiInterval::QUARTERLY,
                ],
                'choice_value' => fn (?KpiInterval $choice) => $choice?->value,
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
