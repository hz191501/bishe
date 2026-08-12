<?php

/* 回答表单：定义用户回复帖子时填写的文字内容。 */

namespace App\Form;

use App\Entity\TaskResponse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


final class TaskResponseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // content 对应 TaskResponse::$content；实体中的 Assert 负责最终长度验证。
        $builder->add('content', TextareaType::class, [
            'label' => 'La tua risposta',
            'help' => 'Scrivi almeno 10 caratteri, in modo concreto, rispettoso e facile da seguire.',
            'attr' => [
                'rows' => 9,
                'maxlength' => 5000,
                'placeholder' => 'Condividi la tua esperienza o spiega i passaggi che possono aiutare...',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // 绑定 TaskResponse 后，handleRequest() 会把提交内容自动写入回答实体。
        $resolver->setDefaults(['data_class' => TaskResponse::class]);
    }
}
