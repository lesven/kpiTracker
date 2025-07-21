<?php

namespace App\Form;

use App\Entity\KPIValue;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\All;

/**
 * Formular für KPI-Wert-Erfassung
 * User Story 5: Benutzer kann KPI-Werte erfassen
 */
class KPIValueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('value', TextType::class, [
                'label' => 'Wert',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. 125000, 87.5, ...',
                    'pattern' => '[0-9]+([,\.][0-9]+)?',
                    'title' => 'Bitte geben Sie eine gültige Zahl ein'
                ],
                'help' => 'Der zu erfassende KPI-Wert (Zahlen mit Komma oder Punkt)'
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Kommentar',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Optionaler Kommentar zum Wert...'
                ],
                'help' => 'Zusätzliche Informationen oder Erklärungen (optional)'
            ])
            ->add('uploadedFiles', FileType::class, [
                'label' => 'Dateien anhängen',
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt'
                ],
                'help' => 'Optional: Dateien als Beleg oder zusätzliche Information anhängen',
                'constraints' => [
                    new All([
                        'allowMissingFields' => true,
                        'constraints' => [
                            new File([
                                'maxSize' => '5M',
                                'mimeTypes' => [
                                    'application/pdf',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'image/jpeg',
                                    'image/jpg',
                                    'image/png',
                                    'image/gif',
                                    'text/plain'
                                ],
                                'mimeTypesMessage' => 'Bitte laden Sie nur unterstützte Dateiformate hoch (PDF, Word, Excel, Bilder, Text).',
                                'maxSizeMessage' => 'Die Datei ist zu groß. Maximal 5MB sind erlaubt.'
                            ])
                        ]
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => KPIValue::class,
        ]);
    }
}
