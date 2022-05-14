<?php

namespace Fregata\FregataBundle\Form\StartMigration;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The form used to run a new migration from the web interface.
 * @internal
 */
class StartMigrationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var array<string, mixed> $migrations */
        $migrations = $options['migrations'];
        $migrationChoices = array_combine(array_keys($migrations), array_keys($migrations));

        $builder
            ->add('migration', ChoiceType::class, [
                'choices' => $migrationChoices,
                'constraints' => [
                    new NotBlank(['message' => 'Please choose a migration to run.']),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'migrations' => [],
        ]);
    }
}
